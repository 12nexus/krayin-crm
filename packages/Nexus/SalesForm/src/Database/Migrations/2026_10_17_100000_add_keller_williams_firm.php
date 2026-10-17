<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keller Williams joins eXp Realty and RE/MAX as a firm with its own ViciDial
 * agent list (`nexus:import-vicidial <file> --firm=kw`).
 *
 *  - Adds Keller Williams to the lead's Firm select, after the other firms and
 *    before Other.
 *  - Leads filed under Other with a Keller Williams brokerage typed in are moved
 *    onto the new firm. Their typed brokerage ("Keller Williams Realty
 *    Preferred") is kept, as it names the office.
 */
return new class extends Migration
{
    public function up(): void
    {
        $attributes = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->whereIn('code', ['firm', 'brokerage'])
            ->pluck('id', 'code');

        if (! isset($attributes['firm'])) {
            return;
        }

        $firmId = $attributes['firm'];

        foreach (array_values(config('sales_form.firms')) as $index => $firm) {
            $option = DB::table('attribute_options')
                ->where('attribute_id', $firmId)
                ->where('name', $firm['name'])
                ->first();

            if (! $option) {
                DB::table('attribute_options')->insert([
                    'attribute_id' => $firmId,
                    'name'         => $firm['name'],
                    'sort_order'   => $index + 1,
                ]);
            }
        }

        $otherId = DB::table('attribute_options')
            ->where('attribute_id', $firmId)
            ->where('name', config('sales_form.other_firm.name'))
            ->value('id');

        if (! $otherId) {
            return;
        }

        DB::table('attribute_options')
            ->where('id', $otherId)
            ->update(['sort_order' => count(config('sales_form.firms')) + 1]);

        if (! isset($attributes['brokerage'])) {
            return;
        }

        $kwId = DB::table('attribute_options')
            ->where('attribute_id', $firmId)
            ->where('name', config('sales_form.firms.kw.name'))
            ->value('id');

        $kwLeadIds = DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('attribute_id', $attributes['brokerage'])
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(text_value) LIKE ?', ['%keller%williams%'])
                ->orWhereRaw('LOWER(TRIM(text_value)) = ?', ['kw'])
                ->orWhereRaw('LOWER(TRIM(text_value)) LIKE ?', ['kw %']))
            ->pluck('entity_id');

        if ($kwLeadIds->isNotEmpty()) {
            DB::table('attribute_values')
                ->where('entity_type', 'leads')
                ->where('attribute_id', $firmId)
                ->where('integer_value', $otherId)
                ->whereIn('entity_id', $kwLeadIds)
                ->update(['integer_value' => $kwId]);
        }
    }

    /**
     * Not reversed: Keller Williams leads would lose which firm they are with.
     */
    public function down(): void {}
};
