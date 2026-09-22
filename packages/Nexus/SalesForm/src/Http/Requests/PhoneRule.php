<?php

namespace Nexus\SalesForm\Http\Requests;

/**
 * A 10-digit NANP number is what the ViciDial mirror is keyed on; anything
 * shorter cannot identify an agent.
 */
class PhoneRule
{
    public static function isValid(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10;
    }
}
