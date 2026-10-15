<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gemini's notes on a recorded call (Services/CallNotes): the transcript, the
 * note activity they became on the lead, and the last failure if any.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_phone_calls', function (Blueprint $table) {
            $table->unsignedInteger('notes_activity_id')->nullable()->after('activity_id');
            $table->longText('transcript')->nullable()->after('notes');
            $table->text('summary')->nullable()->after('transcript');
            $table->timestamp('transcribed_at')->nullable()->after('summary');
            $table->unsignedTinyInteger('transcribe_attempts')->default(0)->after('transcribed_at');
            $table->string('transcribe_error', 500)->nullable()->after('transcribe_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('nexus_phone_calls', function (Blueprint $table) {
            $table->dropColumn(['notes_activity_id', 'transcript', 'summary', 'transcribed_at', 'transcribe_attempts', 'transcribe_error']);
        });
    }
};
