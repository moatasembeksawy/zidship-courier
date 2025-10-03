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
        Schema::create('shipment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('status', 50);
            $table->string('courier_status', 100)->nullable();
            $table->text('description');
            $table->string('location', 200)->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            // Indexes
            $table->index(['shipment_id', 'occurred_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
