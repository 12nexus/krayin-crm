<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When Google stopped accepting the connected account's refresh token, so the
 * CRM can stop calling Google, say so on the settings page and ask for the
 * account to be reconnected (nexus:google-calendar-check).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_google_calendar_accounts', function (Blueprint $table) {
            $table->timestamp('expired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('nexus_google_calendar_accounts', function (Blueprint $table) {
            $table->dropColumn('expired_at');
        });
    }
};
