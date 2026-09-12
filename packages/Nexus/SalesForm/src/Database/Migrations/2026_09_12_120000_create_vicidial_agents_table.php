<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of the confidential ViciDial eXp agent list, so the sales form can
 * resolve a phone number to a verified agent record without touching the CSV on
 * every keystroke. Populated by `php artisan nexus:import-vicidial <file>`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vicidial_agents', function (Blueprint $table) {
            $table->id();

            // 10-digit NANP number, punctuation and country code stripped.
            $table->string('phone', 20)->index();
            $table->string('alt_phone', 20)->nullable();

            $table->string('vendor_lead_code')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('middle_initial', 10)->nullable();
            $table->string('last_name')->nullable();
            $table->string('title')->nullable();

            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country_code', 10)->nullable();

            $table->string('gender', 20)->nullable();
            $table->string('date_of_birth', 30)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('source_id')->nullable();
            $table->text('comments')->nullable();
            $table->string('rank', 30)->nullable();
            $table->string('owner')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vicidial_agents');
    }
};
