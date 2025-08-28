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
        Schema::create('cloud_provider_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('provider_name'); // aws, gcp, azure, digitalocean, hetzner
            $table->string('provider_region')->nullable();
            $table->json('credentials'); // encrypted API keys, secrets
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'provider_name']);
            $table->index(['provider_name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloud_provider_credentials');
    }
};
