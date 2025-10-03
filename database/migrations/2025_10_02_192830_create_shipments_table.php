<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('courier_code', 50)->index();
            $table->string('waybill_number', 100)->unique()->nullable();
            $table->string('courier_reference', 100)->nullable();
            $table->string('reference', 100)->index();
            $table->string('status', 50)->index();
            $table->string('courier_raw_status', 100)->nullable();

            // Address and package data stored as JSON
            $table->jsonb('shipper');
            $table->jsonb('receiver');
            $table->jsonb('package');

            // Additional metadata
            $table->jsonb('metadata')->nullable();
            $table->jsonb('courier_metadata')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('created_at');
            $table->index(['courier_code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
