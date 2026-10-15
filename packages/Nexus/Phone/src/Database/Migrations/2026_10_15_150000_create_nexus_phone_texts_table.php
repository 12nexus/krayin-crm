<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per text sent or received on the web phone's number, keyed by the
 * phone's message id so a retried report is logged once, with the lead activity
 * it became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_phone_texts', function (Blueprint $table) {
            $table->id();
            $table->string('sms_id', 64)->unique();
            $table->unsignedInteger('activity_id')->nullable()->index();
            $table->unsignedInteger('lead_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('direction', 8);
            $table->string('number', 32);
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_phone_texts');
    }
};
