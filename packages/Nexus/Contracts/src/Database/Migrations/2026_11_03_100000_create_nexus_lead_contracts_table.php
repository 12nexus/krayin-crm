<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One set of contract fields per lead, shared by the NDA and the Agreement to
 * Hire Virtual Assistant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_lead_contracts', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('lead_id')->unsigned()->unique();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();

            $table->json('fields');

            $table->integer('created_by')->unsigned()->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->integer('updated_by')->unsigned()->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_lead_contracts');
    }
};
