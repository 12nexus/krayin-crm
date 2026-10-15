<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per call made or taken on the web phone, keyed by the phone's own call
 * id so a retried report never logs a call twice. The lead's "call" activity is
 * created from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_phone_calls', function (Blueprint $table) {
            $table->id();
            $table->string('call_id', 64)->unique();
            $table->unsignedInteger('activity_id')->nullable()->index();
            $table->unsignedInteger('lead_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('direction', 8);
            $table->string('number', 32);
            $table->string('result', 40);
            $table->unsignedInteger('duration')->default(0);
            $table->string('recording_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_phone_calls');
    }
};
