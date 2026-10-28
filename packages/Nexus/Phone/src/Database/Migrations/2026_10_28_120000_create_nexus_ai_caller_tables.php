<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The web phone's AI follow-up caller phones quiet New Leads:
 * - nexus_ai_calls: one row per call it made, with the outcome (disposition),
 *   the full transcript and the CRM's copy of the recording, so attempts can be
 *   counted and spaced out and nothing said on a call is lost;
 * - nexus_ai_dnc: numbers that asked not to be called again, never dialled by
 *   the AI caller whatever lead they turn up on;
 * - the "AI Caller" user (inactive: it cannot sign in) that its notes, call
 *   backs and Lost decisions are recorded under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_ai_calls', function (Blueprint $table) {
            $table->id();
            $table->string('call_id', 64)->unique();
            $table->unsignedInteger('lead_id')->index();
            $table->string('number', 32);
            $table->string('disposition', 32);
            $table->boolean('contacted')->default(false);
            $table->unsignedInteger('duration')->default(0);
            $table->text('summary')->nullable();
            $table->longText('transcript')->nullable();
            $table->string('recording_path')->nullable();
            $table->unsignedInteger('transcript_activity_id')->nullable();
            $table->string('action', 32)->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamps();
        });

        Schema::create('nexus_ai_dnc', function (Blueprint $table) {
            $table->id();
            $table->string('digits', 20)->unique();
            $table->unsignedInteger('lead_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        $email = config('phone.ai.user_email', 'ai-caller@12nexusbpo.com');

        if (! DB::table('users')->where('email', $email)->exists()) {
            $roleId = DB::table('roles')->where('permission_type', 'all')->orderBy('id')->value('id')
                ?? DB::table('roles')->orderBy('id')->value('id');

            DB::table('users')->insert([
                'name'       => 'AI Caller',
                'email'      => $email,
                'password'   => Hash::make(Str::random(48)),
                'status'     => 0,
                'role_id'    => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_ai_calls');
        Schema::dropIfExists('nexus_ai_dnc');
    }
};
