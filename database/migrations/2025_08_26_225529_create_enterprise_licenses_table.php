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
        Schema::create('enterprise_licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('license_key')->unique();
            $table->string('license_type'); // perpetual, subscription, trial
            $table->string('license_tier'); // basic, professional, enterprise
            $table->json('features')->default('{}');
            $table->json('limits')->default('{}'); // user limits, domain limits, resource limits
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->json('authorized_domains')->default('[]');
            $table->enum('status', ['active', 'expired', 'suspended', 'revoked'])->default('active');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['status', 'expires_at']);
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enterprise_licenses');
    }
};
