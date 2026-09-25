<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct Google Calendar sync for sales meetings.
 *
 *  - nexus_google_accounts: the one Google account the CRM writes events as
 *    (Shehzer's), connected once through OAuth. Tokens are stored encrypted
 *    with the app key.
 *  - nexus_calendar_events: which calendar event belongs to which CRM meeting,
 *    so a reschedule moves the same event and the email can link to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_google_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('scopes', 1000)->nullable();
            $table->string('status')->default('connected');
            $table->text('last_error')->nullable();

            $table->integer('connected_by')->unsigned()->nullable();
            $table->foreign('connected_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('nexus_calendar_events', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('lead_id')->unsigned();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();

            $table->integer('activity_id')->unsigned()->nullable();
            $table->foreign('activity_id')->references('id')->on('activities')->nullOnDelete();

            $table->string('calendar_id');
            $table->string('google_event_id')->nullable();
            $table->string('html_link', 1000)->nullable();

            // active: the lead's current meeting; held: it took place;
            // cancelled: removed from the calendar; failed: never created.
            $table->string('status')->default('active');
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_calendar_events');
        Schema::dropIfExists('nexus_google_accounts');
    }
};
