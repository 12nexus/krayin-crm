<?php

namespace Nexus\Funnel\Listeners;

use Illuminate\Support\Facades\DB;
use Nexus\Funnel\Services\Funnel;

/**
 * What the kanban card shows in place of the title and the price: the lead's
 * most recent meeting in the agent's own timezone, part-time or full-time, and
 * whether the lead has been checked as valid.
 *
 * Reads the attribute values Krayin already eager-loads for the board, so the
 * only extra query per card is the meeting.
 */
class CardData
{
    /**
     * attribute_id => code, and option_id => label, loaded once per request.
     */
    protected ?array $codes = null;

    protected ?array $options = null;

    public function __construct(protected Funnel $funnel) {}

    public function handle($lead): array
    {
        $fields = $this->fields($lead);

        $meeting = null;

        if ($current = $this->funnel->currentMeeting($lead, $fields['meeting_timezone'] ?? null)) {
            $meeting = [
                'label'    => $current['label'],
                'done'     => $current['done'],
                'upcoming' => $current['upcoming'],
            ];
        }

        return [
            'meeting'    => $meeting,
            'engagement' => $fields['engagement_type'] ?? null,
            'validity'   => $fields['lead_validity'] ?? null,
        ];
    }

    protected function fields($lead): array
    {
        $this->codes ??= DB::table('attributes')
            ->where('entity_type', 'leads')
            ->whereIn('code', ['meeting_timezone', 'engagement_type', 'lead_validity'])
            ->pluck('code', 'id')
            ->all();

        $this->options ??= DB::table('attribute_options')
            ->whereIn('attribute_id', array_keys($this->codes))
            ->pluck('name', 'id')
            ->all();

        $fields = [];

        foreach ($lead->attribute_values ?? [] as $value) {
            if ($code = $this->codes[$value->attribute_id] ?? null) {
                $fields[$code] = $this->options[$value->integer_value] ?? null;
            }
        }

        return $fields;
    }
}
