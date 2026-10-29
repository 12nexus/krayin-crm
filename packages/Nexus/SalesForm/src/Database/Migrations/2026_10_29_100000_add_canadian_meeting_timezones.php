<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Canadian provinces as meeting timezones (sales_form.timezones). The lead's
 * Meeting Timezone select stores an option id, so each new label needs an
 * option or the lead's zone is silently not saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        $attributeId = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'meeting_timezone')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        $sortOrder = (int) DB::table('attribute_options')->where('attribute_id', $attributeId)->max('sort_order');

        foreach (array_keys(config('sales_form.timezones')) as $label) {
            $exists = DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->where('name', $label)
                ->exists();

            if (! $exists) {
                DB::table('attribute_options')->insert([
                    'attribute_id' => $attributeId,
                    'name'         => $label,
                    'sort_order'   => ++$sortOrder,
                ]);
            }
        }
    }

    public function down(): void
    {
        $attributeId = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'meeting_timezone')
            ->value('id');

        DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('name', 'like', 'Canada%')
            ->delete();
    }
};
