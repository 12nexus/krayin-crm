<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Calendar write access for moving rescheduled meetings:
 *
 *  - `nexus_google_calendar_accounts`: the one Google account connected from
 *    Settings, with its refresh token (encrypted).
 *  - `nexus_meeting_calendar_events`: which event in the sales calendar a
 *    meeting activity is, once it has been found, so later moves go straight
 *    to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_google_calendar_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->unsignedInteger('connected_by')->nullable();
            $table->timestamps();
        });

        Schema::create('nexus_meeting_calendar_events', function (Blueprint $table) {
            $table->unsignedInteger('activity_id')->primary();
            $table->string('google_event_id');
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_meeting_calendar_events');

        Schema::dropIfExists('nexus_google_calendar_accounts');
    }
};
