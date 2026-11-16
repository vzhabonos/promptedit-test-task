<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            // Also sent to Pixelforge as the Idempotency-Key and metadata.generation_id.
            $table->ulid('id')->primary();
            // Billing records outlive deleted accounts, so reports keep matching Pixelforge.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->string('status')->index();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->jsonb('reference_images');

            // Money columns are integer nanodollars (1 USD = 1,000,000,000).
            $table->bigInteger('estimated_cost_min');
            $table->bigInteger('estimated_cost_max');
            $table->bigInteger('provider_cost')->nullable();
            $table->bigInteger('charged_cost')->nullable();
            $table->jsonb('rates')->nullable();
            $table->jsonb('usage')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            // Pixelforge's completion time: GET /v1/usage bills on this UTC day.
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
