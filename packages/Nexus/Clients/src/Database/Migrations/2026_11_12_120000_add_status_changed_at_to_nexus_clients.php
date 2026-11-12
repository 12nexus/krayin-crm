<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a client last changed status, so the dashboard can date lost revenue
 * (paused or ended clients) by when the service stopped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_clients', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });

        DB::table('nexus_clients')
            ->whereIn('status', ['paused', 'ended'])
            ->update(['status_changed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('nexus_clients', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });
    }
};
