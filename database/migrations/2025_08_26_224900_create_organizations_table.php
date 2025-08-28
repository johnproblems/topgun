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
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('hierarchy_type', ['top_branch', 'master_branch', 'sub_user', 'end_user']);
            $table->integer('hierarchy_level')->default(0);
            $table->uuid('parent_organization_id')->nullable();
            $table->json('branding_config')->nullable();
            $table->json('feature_flags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint will be added after table creation
            $table->index(['hierarchy_type', 'hierarchy_level']);
            $table->index('parent_organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
