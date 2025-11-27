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
        Schema::create('white_label_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('platform_name')->default('Coolify');
            $table->text('logo_url')->nullable();
            $table->json('theme_config')->default('{}');
            $table->json('custom_domains')->default('[]');
            $table->boolean('hide_coolify_branding')->default(false);
            $table->json('custom_email_templates')->default('{}');
            $table->text('custom_css')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('white_label_configs');
    }
};
