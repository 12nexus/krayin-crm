<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shared as an engagement type (sales_form.engagement_types). The lead's
 * engagement_type select stores an option id, so the label needs an option
 * or the lead's choice is silently not saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        $attributeId = $this->attributeId();

        if (! $attributeId) {
            return;
        }

        $exists = DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('name', 'Shared')
            ->exists();

        if (! $exists) {
            DB::table('attribute_options')->insert([
                'attribute_id' => $attributeId,
                'name'         => 'Shared',
                'sort_order'   => (int) DB::table('attribute_options')->where('attribute_id', $attributeId)->max('sort_order') + 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('attribute_options')
            ->where('attribute_id', $this->attributeId())
            ->where('name', 'Shared')
            ->delete();
    }

    protected function attributeId(): ?int
    {
        return DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'engagement_type')
            ->value('id');
    }
};
