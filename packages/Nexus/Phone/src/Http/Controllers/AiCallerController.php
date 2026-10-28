<?php

namespace Nexus\Phone\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Nexus\Phone\Services\AiCaller;

/**
 * Endpoints for the web phone's AI follow-up caller, signed with the phone's
 * shared secret (VerifyPhoneSignature).
 */
class AiCallerController extends Controller
{
    public function __construct(protected AiCaller $ai) {}

    /**
     * Leads due a follow-up call, or one lead by id (for a test call).
     */
    public function queue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit'   => 'nullable|integer|min:1|max:100',
            'lead_id' => 'nullable|integer',
        ]);

        return new JsonResponse([
            'leads'  => $this->ai->queue((int) ($data['limit'] ?? 20), isset($data['lead_id']) ? (int) $data['lead_id'] : null),
            'config' => config('phone.ai'),
            'timezones' => array_keys(config('sales_form.timezones')),
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['required', Rule::in(array_keys(config('sales_form.timezones')))],
            'date'     => 'nullable|date_format:Y-m-d',
            'max'      => 'nullable|integer|min:1|max:40',
        ]);

        return new JsonResponse(['slots' => $this->ai->freeSlots($data['timezone'], (int) ($data['max'] ?? 12), $data['date'] ?? null)]);
    }

    public function book(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_id'  => 'required|integer',
            'date'     => 'required|date_format:Y-m-d',
            'time'     => 'required|date_format:H:i',
            'timezone' => ['required', Rule::in(array_keys(config('sales_form.timezones')))],
            'email'    => 'nullable|string|max:191',
            'notes'    => 'nullable|string|max:2000',
        ]);

        return new JsonResponse($this->ai->book($data['lead_id'], $data['date'], $data['time'], $data['timezone'], $data['email'] ?? null, $data['notes'] ?? null));
    }

    public function result(Request $request): JsonResponse
    {
        $data = $request->validate([
            'call_id'       => 'required|string|max:64',
            'lead_id'       => 'required|integer|exists:leads,id',
            'number'        => 'required|string|max:32',
            'disposition'   => ['required', Rule::in(array_keys(AiCaller::DISPOSITIONS))],
            'started_at'    => 'required|date',
            'duration'      => 'required|integer|min:0',
            'summary'       => 'nullable|string|max:2000',
            'transcript'    => 'nullable|string|max:20000',
            'recording_url' => 'nullable|url|max:255',
            'callback_at'   => 'nullable|string|max:40',
            'timezone'      => 'nullable|string|max:60',
        ]);

        return new JsonResponse($this->ai->result($data));
    }

    /**
     * The CRM's copy of an AI call's recording ("Play recording" on the lead).
     * Signed-in users only (web + user middleware); call ids are random UUIDs.
     */
    public function recording(string $callId)
    {
        $path = DB::table('nexus_ai_calls')->where('call_id', $callId)->value('recording_path');

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'ai-call-'.$callId.'.wav', ['Content-Type' => 'audio/wav']);
    }
}
