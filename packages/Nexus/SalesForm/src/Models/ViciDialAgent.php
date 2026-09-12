<?php

namespace Nexus\SalesForm\Models;

use Illuminate\Database\Eloquent\Model;

class ViciDialAgent extends Model
{
    protected $table = 'vicidial_agents';

    protected $guarded = ['id'];

    /**
     * Reduce any user-entered phone number to the 10-digit NANP form used as the
     * lookup key: digits only, country code dropped.
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name])));
    }
}
