<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A lead's brokerage is eXp Realty, RE/MAX, or Other with the brokerage's name
 * typed in. Adds Other to the lead's Firm select, and moves the leads already
 * on file whose brokerage is neither firm onto it, so their name is kept.
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

        $name = config('sales_form.other_firm.name');

        $otherId = DB::table('attribute_options')
            ->where('attribute_id', $attributes['firm'])
            ->where('name', $name)
            ->value('id');

        if (! $otherId) {
            $otherId = DB::table('attribute_options')->insertGetId([
                'attribute_id' => $attributes['firm'],
                'name'         => $name,
                'sort_order'   => count(config('sales_form.firms')) + 1,
            ]);
        }

        if (! isset($attributes['brokerage'])) {
            return;
        }

        $firmNames = array_column(config('sales_form.firms'), 'name');

        $leadIds = DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('attribute_id', $attributes['brokerage'])
            ->whereNotNull('text_value')
            ->where('text_value', '!=', '')
            ->whereNotIn('text_value', $firmNames)
            ->pluck('entity_id');

        if ($leadIds->isNotEmpty()) {
            DB::table('attribute_values')
                ->where('entity_type', 'leads')
                ->where('attribute_id', $attributes['firm'])
                ->whereIn('entity_id', $leadIds)
                ->update(['integer_value' => $otherId]);
        }
    }

    /**
     * Not reversed: which firm those leads were filed under before was a guess.
     */
    public function down(): void {}
};
