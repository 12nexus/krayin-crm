<?php

namespace Nexus\Phone\Services;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use Webkul\Activity\Repositories\ActivityRepository;

/**
 * Gemini's notes on a recorded web phone call: downloads the recording from the
 * phone (signed with the shared secret), has Gemini transcribe it and write a
 * summary, key points and next steps, and adds them to the call's lead as a note.
 * The transcript is kept on the note's "additional" for the activity list
 * (phone::activities.recording) and on the nexus_phone_calls row.
 *
 * Run for each new recording by the TranscribeCall job, and by nexus:call-notes
 * for older calls or calls whose job gave up.
 */
class CallNotes
{
    const API = 'https://generativelanguage.googleapis.com';

    public function __construct(protected ActivityRepository $activityRepository) {}

    public function enabled(): bool
    {
        return config('phone.call_notes.enabled') && config('phone.call_notes.api_key') && config('phone.url');
    }

    /**
     * Why a call gets no notes, or null when it should get them.
     */
    public function skipReason(object $row): ?string
    {
        return match (true) {
            ! $row->recording_url                                              => 'no recording',
            $row->result !== 'answered'                                        => 'not answered',
            $row->duration < (int) config('phone.call_notes.min_duration')     => 'shorter than '.config('phone.call_notes.min_duration').' s',
            ! $row->lead_id || ! DB::table('leads')->where('id', $row->lead_id)->exists() => 'no lead',
            default                                                            => null,
        };
    }

    /**
     * Transcribe one call and put its notes on the lead. With $force, calls that
     * already have notes are transcribed again and their note is replaced.
     *
     * @return array{status: string, activity_id: ?int, reason: ?string}
     */
    public function process(string $callId, bool $force = false): array
    {
        $row = DB::table('nexus_phone_calls')->where('call_id', $callId)->first();

        if (! $row) {
            return ['status' => 'skipped', 'activity_id' => null, 'reason' => 'unknown call'];
        }

        if ($row->notes_activity_id && ! $force) {
            return ['status' => 'done', 'activity_id' => (int) $row->notes_activity_id, 'reason' => 'already has notes'];
        }

        if (! $this->enabled()) {
            return ['status' => 'skipped', 'activity_id' => null, 'reason' => 'call notes are off (NEXUS_CALL_NOTES / GEMINI_API_KEY)'];
        }

        if ($reason = $this->skipReason($row)) {
            return ['status' => 'skipped', 'activity_id' => null, 'reason' => $reason];
        }

        DB::table('nexus_phone_calls')->where('id', $row->id)->increment('transcribe_attempts');

        $file = null;

        try {
            $file = $this->download($row->recording_url);
            $notes = $this->transcribe($file, $row);
        } catch (Throwable $e) {
            DB::table('nexus_phone_calls')->where('id', $row->id)->update(['transcribe_error' => mb_substr($e->getMessage(), 0, 500)]);

            throw $e;
        } finally {
            if ($file) {
                @unlink($file);
            }
        }

        $activityId = DB::transaction(function () use ($row, $notes, $force) {
            // A second run (retry, overlapping job) that finished first wins.
            $row = DB::table('nexus_phone_calls')->where('id', $row->id)->lockForUpdate()->first();

            if ($row->notes_activity_id && ! $force) {
                return (int) $row->notes_activity_id;
            }

            $activityId = $this->saveNote($row, $notes);

            DB::table('nexus_phone_calls')->where('id', $row->id)->update([
                'notes_activity_id' => $activityId,
                'transcript'        => $this->transcriptText($notes['transcript']),
                'summary'           => $notes['summary'],
                'transcribed_at'    => now(),
                'transcribe_error'  => null,
            ]);

            return $activityId;
        });

        return ['status' => 'added', 'activity_id' => $activityId, 'reason' => null];
    }

    /**
     * The recording, from the phone, into a temporary file.
     */
    protected function download(string $url): string
    {
        $base = (string) config('phone.url');
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($base === '' || ! str_starts_with($url, $base.'/') || ! preg_match('#^recordings/[\w-]+\.wav$#', $path)) {
            throw new RuntimeException('The recording is not on the web phone: '.$url);
        }

        $timestamp = (string) time();
        $file = tempnam(sys_get_temp_dir(), 'call');

        try {
            Http::withHeaders([
                'X-Nexus-Timestamp' => $timestamp,
                'X-Nexus-Signature' => hash_hmac('sha256', $timestamp.'.'.$path.'.', (string) config('phone.secret')),
            ])->timeout(120)->sink($file)->get($base.'/'.$path)->throw();
        } catch (Throwable $e) {
            @unlink($file);

            throw $e;
        }

        return $file;
    }

    /**
     * Gemini's transcript and notes for the recording.
     *
     * @return array{summary: string, key_points: string[], next_steps: string[], transcript: array<array{speaker: string, text: string}>}
     */
    protected function transcribe(string $file, object $row): array
    {
        $key = (string) config('phone.call_notes.api_key');
        $uploaded = $this->upload($file, 'call-'.$row->call_id, $key);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(300)
                ->post(self::API.'/v1beta/models/'.config('phone.call_notes.model').':generateContent', [
                    'contents' => [[
                        'role'  => 'user',
                        'parts' => [
                            ['file_data' => ['mime_type' => 'audio/wav', 'file_uri' => $uploaded['uri']]],
                            ['text' => $this->prompt($row)],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature'      => 0.2,
                        'maxOutputTokens'  => 65536,
                        'responseMimeType' => 'application/json',
                        'responseSchema'   => $this->schema(),
                    ],
                ])
                ->throw()
                ->json();
        } finally {
            Http::withHeaders(['x-goog-api-key' => $key])->timeout(30)->delete(self::API.'/v1beta/'.$uploaded['name']);
        }

        $candidate = $response['candidates'][0] ?? [];
        $text = collect($candidate['content']['parts'] ?? [])->reject(fn ($part) => $part['thought'] ?? false)->pluck('text')->implode('');
        $notes = json_decode($text, true);

        if (! is_array($notes) || ! isset($notes['summary'])) {
            throw new RuntimeException('Gemini returned no notes (finish reason: '.($candidate['finishReason'] ?? $response['promptFeedback']['blockReason'] ?? 'unknown').')');
        }

        return [
            'summary'    => trim((string) $notes['summary']),
            'key_points' => array_values(array_filter(array_map('trim', (array) ($notes['key_points'] ?? [])))),
            'next_steps' => array_values(array_filter(array_map('trim', (array) ($notes['next_steps'] ?? [])))),
            'transcript' => array_values(array_map(fn ($turn) => [
                'speaker' => trim((string) ($turn['speaker'] ?? '')),
                'text'    => trim((string) ($turn['text'] ?? '')),
            ], (array) ($notes['transcript'] ?? []))),
        ];
    }

    /**
     * Upload through the Gemini Files API (recordings outgrow inline requests
     * after about 20 minutes) and wait until it can be used.
     *
     * @return array{name: string, uri: string}
     */
    protected function upload(string $file, string $displayName, string $key): array
    {
        $start = Http::withHeaders([
            'x-goog-api-key'                      => $key,
            'X-Goog-Upload-Protocol'              => 'resumable',
            'X-Goog-Upload-Command'               => 'start',
            'X-Goog-Upload-Header-Content-Length' => (string) filesize($file),
            'X-Goog-Upload-Header-Content-Type'   => 'audio/wav',
        ])->timeout(30)->post(self::API.'/upload/v1beta/files', ['file' => ['display_name' => $displayName]])->throw();

        $uploadUrl = $start->header('X-Goog-Upload-URL') ?: throw new RuntimeException('Gemini gave no upload URL');

        $uploaded = Http::withHeaders([
            'X-Goog-Upload-Offset'  => '0',
            'X-Goog-Upload-Command' => 'upload, finalize',
        ])->timeout(300)->withBody(Utils::streamFor(fopen($file, 'rb')), 'audio/wav')->post($uploadUrl)->throw()->json('file');

        for ($i = 0; ($uploaded['state'] ?? 'ACTIVE') === 'PROCESSING' && $i < 60; $i++) {
            sleep(2);
            $uploaded = Http::withHeaders(['x-goog-api-key' => $key])->timeout(30)->get(self::API.'/v1beta/'.$uploaded['name'])->throw()->json();
        }

        if (($uploaded['state'] ?? 'ACTIVE') !== 'ACTIVE') {
            throw new RuntimeException('Gemini could not process the recording (state '.($uploaded['state'] ?? '?').')');
        }

        return ['name' => $uploaded['name'], 'uri' => $uploaded['uri']];
    }

    protected function prompt(object $row): string
    {
        $lead = DB::table('leads')
            ->leftJoin('persons', 'persons.id', '=', 'leads.person_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'persons.organization_id')
            ->where('leads.id', $row->lead_id)
            ->first(['leads.title', 'persons.name as person', 'organizations.name as organization']);

        $agent = $row->user_id ? DB::table('users')->where('id', $row->user_id)->value('name') : null;

        $context = implode("\n", array_filter([
            '- Direction: '.($row->direction === 'in' ? 'the client called the agent' : 'the agent called the client'),
            $agent ? '- Agent: '.$agent : null,
            $lead?->person ? '- Client: '.$lead->person.($lead->organization ? ' ('.$lead->organization.')' : '') : null,
            $lead?->title ? '- Lead in the CRM: '.$lead->title : null,
            '- Date: '.Carbon::parse($row->started_at, 'UTC')->setTimezone(config('app.timezone', 'UTC'))->format('D j M Y, g:i A T'),
        ]));

        return <<<PROMPT
This is the recording of a phone call between a sales agent of 12Nexus BPO (an outsourcing company offering virtual assistants, call center and back-office staff) and a client. Transcribe it and write notes for the client's lead in the CRM.

Call details:
{$context}

- transcript: every turn of the conversation in order, word for word in the language spoken. Use "Agent" or "Client" as the speaker, followed by their name in parentheses when known (e.g. "Agent (Sara)"). Write [inaudible] for words you cannot make out. Leave out the automated "this call may be recorded" announcement.
- summary: 2 to 4 sentences in English: why the call happened, what was discussed and how it ended.
- key_points: short facts in English the sales team should remember: the client's business, needs, services of interest, volumes, budget, timeline, decision makers, objections, competitors.
- next_steps: what was agreed, who does it and by when, in English. Empty if nothing was agreed.

Only write what was said; do not guess. If there was no conversation (silence, ringing, a voicemail greeting), say so in the summary, leave key_points and next_steps empty, and transcribe any message that was left.
PROMPT;
    }

    protected function schema(): array
    {
        $strings = ['type' => 'ARRAY', 'items' => ['type' => 'STRING']];

        return [
            'type'       => 'OBJECT',
            'properties' => [
                'summary'    => ['type' => 'STRING'],
                'key_points' => $strings,
                'next_steps' => $strings,
                'transcript' => [
                    'type'  => 'ARRAY',
                    'items' => [
                        'type'       => 'OBJECT',
                        'properties' => ['speaker' => ['type' => 'STRING'], 'text' => ['type' => 'STRING']],
                        'required'   => ['speaker', 'text'],
                    ],
                ],
            ],
            'required'         => ['summary', 'key_points', 'next_steps', 'transcript'],
            'propertyOrdering' => ['transcript', 'summary', 'key_points', 'next_steps'],
        ];
    }

    /**
     * The note on the lead (and its person), listed just after the call. Replaced
     * in place when the call is transcribed again.
     */
    protected function saveNote(object $row, array $notes): int
    {
        $lead = DB::table('leads')->where('id', $row->lead_id)->first(['id', 'user_id', 'person_id']);

        $values = [
            'title'      => 'Call notes · '.($row->direction === 'in' ? 'incoming' : 'outgoing').' call with '.$row->number,
            'comment'    => $this->comment($notes),
            'additional' => json_encode([
                'phone_call_id' => $row->call_id,
                'call_notes'    => [
                    'recording_url' => $row->recording_url,
                    'transcript'    => $notes['transcript'],
                    'model'         => config('phone.call_notes.model'),
                ],
            ]),
        ];

        if ($row->notes_activity_id && DB::table('activities')->where('id', $row->notes_activity_id)->exists()) {
            DB::table('activities')->where('id', $row->notes_activity_id)->update($values);

            return (int) $row->notes_activity_id;
        }

        $activity = $this->activityRepository->create($values + [
            'type'    => 'note',
            'user_id' => $row->user_id ?: ($lead->user_id ?? (int) DB::table('users')->orderBy('id')->value('id')),
            'is_done' => 1,
        ]);

        DB::table('lead_activities')->insert(['lead_id' => $lead->id, 'activity_id' => $activity->id]);

        if ($lead->person_id) {
            DB::table('person_activities')->insert(['person_id' => $lead->person_id, 'activity_id' => $activity->id]);
        }

        // Dated when the call ended, so it sits next to the call in the list.
        if ($row->started_at) {
            $at = Carbon::parse($row->started_at, 'UTC')->addSeconds($row->duration + 1)->format('Y-m-d H:i:s');
            DB::table('activities')->where('id', $activity->id)->update(['created_at' => $at, 'updated_at' => $at]);
        }

        return (int) $activity->id;
    }

    /**
     * Plain text on one line: the activity list runs line breaks together.
     */
    protected function comment(array $notes): string
    {
        return implode(' — ', array_filter([
            $notes['summary'],
            $notes['key_points'] ? 'Key points: '.implode('; ', $notes['key_points']) : null,
            $notes['next_steps'] ? 'Next steps: '.implode('; ', $notes['next_steps']) : null,
        ]));
    }

    protected function transcriptText(array $transcript): string
    {
        return implode("\n", array_map(fn ($turn) => $turn['speaker'].': '.$turn['text'], $transcript));
    }
}
