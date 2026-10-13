<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notes `nexus:meeting-notes` has put on leads: one per calendar event and
 * lead, holding the Gemini notes, recordings and transcripts Google Meet
 * attached to that event. A later run adds files that arrived since to the same
 * note instead of writing a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_meeting_notes', function (Blueprint $table) {
            $table->id();
            $table->string('google_event_id');
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('meeting_activity_id')->nullable();
            $table->unsignedInteger('note_activity_id');
            $table->json('file_ids');
            $table->timestamps();

            $table->unique(['google_event_id', 'lead_id']);

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('note_activity_id')->references('id')->on('activities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_meeting_notes');
    }
};
