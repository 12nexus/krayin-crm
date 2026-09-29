<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leads now come from more than one firm: eXp Realty, and RE/MAX alongside it.
 *
 *  - `vicidial_agents.firm` says which firm's ViciDial list a row came from.
 *    Every row so far is from the eXp list.
 *  - A `firm` select on leads records which firm the client is with. Every
 *    lead so far is an eXp lead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vicidial_agents', 'firm')) {
            Schema::table('vicidial_agents', function (Blueprint $table) {
                $table->string('firm', 20)->default('exp')->after('id')->index();
            });
        }

        $now = now();

        $attributeId = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'firm')
            ->value('id');

        if (! $attributeId) {
            $sortOrder = (int) DB::table('attributes')
                ->where('entity_type', 'leads')
                ->where('code', 'brokerage')
                ->value('sort_order');

            $attributeId = DB::table('attributes')->insertGetId([
                'code'            => 'firm',
                'name'            => 'Firm',
                'type'            => 'select',
                'entity_type'     => 'leads',
                'sort_order'      => $sortOrder,
                'is_required'     => 0,
                'is_unique'       => 0,
                'quick_add'       => 0,
                'is_user_defined' => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        foreach (array_values(config('sales_form.firms')) as $index => $firm) {
            $exists = DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->where('name', $firm['name'])
                ->exists();

            if (! $exists) {
                DB::table('attribute_options')->insert([
                    'attribute_id' => $attributeId,
                    'name'         => $firm['name'],
                    'sort_order'   => $index + 1,
                ]);
            }
        }

        $expOption = DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('name', config('sales_form.firms.exp.name'))
            ->value('id');

        $leadIds = DB::table('leads')
            ->whereNotIn('id', DB::table('attribute_values')
                ->where('attribute_id', $attributeId)
                ->where('entity_type', 'leads')
                ->select('entity_id'))
            ->pluck('id');

        foreach ($leadIds->chunk(500) as $chunk) {
            DB::table('attribute_values')->insert($chunk->map(fn ($id) => [
                'entity_type'   => 'leads',
                'entity_id'     => $id,
                'attribute_id'  => $attributeId,
                'integer_value' => $expOption,
                'unique_id'     => $id.'|'.$attributeId,
            ])->values()->all());
        }
    }

    /**
     * Not reversed: RE/MAX leads and agents would lose which firm they are with.
     */
    public function down(): void {}
};
