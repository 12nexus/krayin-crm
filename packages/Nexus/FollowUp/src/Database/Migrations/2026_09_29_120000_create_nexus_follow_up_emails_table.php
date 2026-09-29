<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per automated email sent to a lead's client, so each step of the
 * follow-up sequence goes out at most once and later steps can tell what the
 * client has already had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_follow_up_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id');
            $table->string('step', 50);
            $table->string('recipient');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'step']);
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_follow_up_emails');
    }
};
