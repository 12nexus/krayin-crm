<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Coldwell Banker joins eXp Realty, RE/MAX and Keller Williams as a firm with
 * its own ViciDial agent list (`nexus:import-vicidial <file> --firm=cb`). Its
 * list is three coldwellbanker.com scrapes: all 50 states, Luxury and
 * Commercial, each imported onto the same firm.
 *
 *  - Adds Coldwell Banker to the lead's Firm select, after the other firms and
 *    before Other.
 *  - Leads filed under Other with a Coldwell Banker brokerage typed in are
 *    moved onto the new firm. Their typed brokerage ("Coldwell Banker Realty")
 *    is kept, as it names the office.
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

        $cbId = DB::table('attribute_options')
            ->where('attribute_id', $firmId)
            ->where('name', config('sales_form.firms.cb.name'))
            ->value('id');

        $cbLeadIds = DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('attribute_id', $attributes['brokerage'])
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(text_value) LIKE ?', ['%coldwell%'])
                ->orWhereRaw('LOWER(TRIM(text_value)) = ?', ['cb'])
                ->orWhereRaw('LOWER(TRIM(text_value)) = ?', ['cbc']))
            ->pluck('entity_id');

        if ($cbLeadIds->isNotEmpty()) {
            DB::table('attribute_values')
                ->where('entity_type', 'leads')
                ->where('attribute_id', $firmId)
                ->where('integer_value', $otherId)
                ->whereIn('entity_id', $cbLeadIds)
                ->update(['integer_value' => $cbId]);
        }
    }

    /**
     * Not reversed: Coldwell Banker leads would lose which firm they are with.
     */
    public function down(): void {}
};
