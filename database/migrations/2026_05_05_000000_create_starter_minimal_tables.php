<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('starter_minimal_panel_plugin_overrides', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('panel_id');
            $blueprint->string('plugin_key');
            $blueprint->boolean('enabled')->nullable();
            $blueprint->boolean('is_dangerous')->default(false);
            $blueprint->json('options')->nullable();
            $blueprint->integer('options_version')->default(1);
            $blueprint->string('tenant_id')->nullable();
            $blueprint->unsignedBigInteger('updated_by_user_id')->nullable();
            $blueprint->timestamps();

            $blueprint->index('panel_id');
            $blueprint->index('plugin_key');
            $blueprint->index('tenant_id');
            $blueprint->unique(['panel_id', 'plugin_key', 'tenant_id'], 'starter_minimal_overrides_unique');
        });

        Schema::create('starter_minimal_panel_snapshots', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('panel_id')->unique();
            $blueprint->json('meta');
            $blueprint->timestamp('last_seen_at')->nullable();
            $blueprint->timestamps();
        });

        Schema::create('starter_minimal_audit_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('actor_user_id')->nullable();
            $blueprint->string('action');
            $blueprint->string('panel_id')->nullable();
            $blueprint->string('plugin_key')->nullable();
            $blueprint->json('before')->nullable();
            $blueprint->json('after')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('starter_minimal_panel_plugin_overrides');
        Schema::dropIfExists('starter_minimal_panel_snapshots');
        Schema::dropIfExists('starter_minimal_audit_logs');
    }
};
