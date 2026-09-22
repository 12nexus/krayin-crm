<?php

namespace Nexus\SalesForm\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads and writes a lead's custom attribute values directly.
 *
 * Krayin's repository only writes the attributes it is handed on create, and
 * select-type attributes store an `attribute_options.id` rather than the label.
 * Every write here takes the human label and resolves it, so callers never deal
 * with option ids.
 */
class LeadFields
{
    /**
     * Custom attribute type => the attribute_values column holding it.
     */
    const TYPE_COLUMN = [
        'text'        => 'text_value',
        'textarea'    => 'text_value',
        'price'       => 'float_value',
        'boolean'     => 'boolean_value',
        'select'      => 'integer_value',
        'multiselect' => 'text_value',
        'checkbox'    => 'text_value',
        'lookup'      => 'integer_value',
        'datetime'    => 'datetime_value',
        'date'        => 'date_value',
        'file'        => 'text_value',
        'image'       => 'text_value',
    ];

    /**
     * code => attribute row, memoised per request.
     */
    protected array $attributes = [];

    /**
     * Write several values at once. Nulls and empty strings are skipped, so a
     * blank field on a form never wipes something already on the record.
     */
    public function set(int $leadId, array $values): void
    {
        foreach ($values as $code => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $this->write($leadId, $code, $value);
        }
    }

    /**
     * code => display value, with select options resolved to their label.
     */
    public function get(int $leadId): array
    {
        $rows = DB::table('attribute_values as av')
            ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
            ->leftJoin('attribute_options as o', 'o.id', '=', 'av.integer_value')
            ->where('av.entity_type', 'leads')
            ->where('av.entity_id', $leadId)
            ->where('a.is_user_defined', 1)
            ->get([
                'a.code', 'a.type', 'o.name as option_name',
                'av.text_value', 'av.integer_value', 'av.float_value',
                'av.boolean_value', 'av.datetime_value', 'av.date_value',
            ]);

        $fields = [];

        foreach ($rows as $row) {
            if ($row->type === 'select') {
                $fields[$row->code] = $row->option_name;

                continue;
            }

            $column = self::TYPE_COLUMN[$row->type] ?? 'text_value';

            $fields[$row->code] = $row->{$column} ?? null;
        }

        return $fields;
    }

    protected function write(int $leadId, string $code, mixed $value): void
    {
        $attribute = $this->attribute($code);

        if (! $attribute) {
            return;
        }

        $column = self::TYPE_COLUMN[$attribute->type] ?? 'text_value';

        if ($attribute->type === 'select') {
            $value = DB::table('attribute_options')
                ->where('attribute_id', $attribute->id)
                ->where('name', $value)
                ->value('id');

            if (! $value) {
                return;
            }
        }

        $existing = DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('entity_id', $leadId)
            ->where('attribute_id', $attribute->id)
            ->value('id');

        if ($existing) {
            DB::table('attribute_values')->where('id', $existing)->update([$column => $value]);

            return;
        }

        DB::table('attribute_values')->insert([
            'entity_type'  => 'leads',
            'entity_id'    => $leadId,
            'attribute_id' => $attribute->id,
            $column        => $value,
            'unique_id'    => $leadId.'|'.$attribute->id,
        ]);
    }

    protected function attribute(string $code): ?object
    {
        if (! array_key_exists($code, $this->attributes)) {
            $this->attributes[$code] = DB::table('attributes')
                ->where('entity_type', 'leads')
                ->where('code', $code)
                ->first();
        }

        return $this->attributes[$code];
    }
}
