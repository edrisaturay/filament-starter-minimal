<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('starter_minimal_audit_logs', function (Blueprint $blueprint): void {
            $blueprint->uuid('correlation_id')->nullable()->after('actor_user_id');
            $blueprint->index('correlation_id', 'audit_logs_correlation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('starter_minimal_audit_logs', function (Blueprint $blueprint): void {
            try {
                $blueprint->dropIndex('audit_logs_correlation_idx');
            } catch (\Throwable $e) {
            }

            try {
                $blueprint->dropColumn('correlation_id');
            } catch (\Throwable $e) {
            }
        });
    }
};
