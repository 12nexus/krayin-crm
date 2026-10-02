<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sales executives work a lead by logging notes and activities (calls,
 * meetings, files) against it. They no longer change the lead itself: its
 * details, its stage, or its contact person and organisation.
 *
 * The role keeps leads.create (the sales form and Create Lead) and every
 * activities permission. Administrators can grant the edit rights back from
 * Settings → Roles.
 */
return new class extends Migration
{
    const ROLE = 'Sales Executive';

    const REVOKED = [
        'leads.edit',
        'contacts.persons.edit',
        'contacts.organizations.edit',
    ];

    public function up(): void
    {
        $this->change(fn (array $permissions) => array_values(array_diff($permissions, self::REVOKED)));
    }

    public function down(): void
    {
        $this->change(fn (array $permissions) => array_values(array_unique(array_merge($permissions, self::REVOKED))));
    }

    protected function change(callable $map): void
    {
        $role = DB::table('roles')->where('name', self::ROLE)->where('permission_type', 'custom')->first();

        if (! $role) {
            return;
        }

        $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode($map($permissions)),
            'updated_at'  => now(),
        ]);
    }
};
