<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarded clients: the people a won lead becomes, with their documents (the
 * 12 Nexus contract among them) and the monthly invoices raised against them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_clients', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('engagement_type')->nullable();
            $table->decimal('monthly_retainer', 12, 2)->nullable();
            $table->date('onboarded_on')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();

            $table->integer('lead_id')->unsigned()->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();

            $table->integer('person_id')->unsigned()->nullable();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();

            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('nexus_client_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('client_id')->unsigned();
            $table->foreign('client_id')->references('id')->on('nexus_clients')->cascadeOnDelete();
            $table->string('category')->default('other');
            $table->string('title');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->integer('uploaded_by')->unsigned()->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('nexus_client_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('client_id')->unsigned();
            $table->foreign('client_id')->references('id')->on('nexus_clients')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('billing_month');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('issued_on');
            $table->date('due_date');
            $table->string('status')->default('unpaid');
            $table->date('paid_on')->nullable();
            $table->string('description')->nullable();
            $table->text('notes')->nullable();

            $table->integer('created_by')->unsigned()->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['client_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_client_invoices');
        Schema::dropIfExists('nexus_client_documents');
        Schema::dropIfExists('nexus_clients');
    }
};
