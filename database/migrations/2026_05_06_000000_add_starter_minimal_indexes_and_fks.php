<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('starter_minimal_audit_logs', function (Blueprint $blueprint): void {
            $blueprint->index(['actor_user_id', 'created_at'], 'audit_logs_actor_created_idx');
            $blueprint->index(['panel_id', 'plugin_key', 'created_at'], 'audit_logs_subject_created_idx');
            $blueprint->index('action', 'audit_logs_action_idx');
            $blueprint->index('created_at', 'audit_logs_created_at_idx');
        });

        Schema::table('starter_minimal_panel_snapshots', function (Blueprint $blueprint): void {
            $blueprint->index('last_seen_at', 'panel_snapshots_last_seen_idx');
        });

        // The composite unique on (panel_id, plugin_key) already covers
        // panel_id as its leftmost prefix — drop the redundant single-column
        // index. SQLite/MySQL drop is best-effort; ignore failure on
        // non-conforming drivers.
        Schema::table('starter_minimal_panel_plugin_overrides', function (Blueprint $blueprint): void {
            try {
                $blueprint->dropIndex(['panel_id']);
            } catch (\Throwable $e) {
                // Already dropped or driver doesn't support it — non-fatal.
            }
        });

        // Foreign keys are added as a separate ALTER so we can rely on
        // doctrine/dbal-style nullable FK introspection. Skipped if the
        // app has no `users` table (e.g. running against a non-Laravel-default
        // schema).
        if (Schema::hasTable('users')) {
            Schema::table('starter_minimal_audit_logs', function (Blueprint $blueprint): void {
                $blueprint->foreign('actor_user_id', 'audit_logs_actor_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            Schema::table('starter_minimal_panel_plugin_overrides', function (Blueprint $blueprint): void {
                $blueprint->foreign('updated_by_user_id', 'overrides_updated_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('starter_minimal_panel_plugin_overrides', function (Blueprint $blueprint): void {
                try {
                    $blueprint->dropForeign('overrides_updated_by_fk');
                } catch (\Throwable $e) {
                }
            });

            Schema::table('starter_minimal_audit_logs', function (Blueprint $blueprint): void {
                try {
                    $blueprint->dropForeign('audit_logs_actor_fk');
                } catch (\Throwable $e) {
                }
            });
        }

        Schema::table('starter_minimal_panel_plugin_overrides', function (Blueprint $blueprint): void {
            try {
                $blueprint->index('panel_id');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('starter_minimal_panel_snapshots', function (Blueprint $blueprint): void {
            try {
                $blueprint->dropIndex('panel_snapshots_last_seen_idx');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('starter_minimal_audit_logs', function (Blueprint $blueprint): void {
            foreach ([
                'audit_logs_actor_created_idx',
                'audit_logs_subject_created_idx',
                'audit_logs_action_idx',
                'audit_logs_created_at_idx',
            ] as $name) {
                try {
                    $blueprint->dropIndex($name);
                } catch (\Throwable $e) {
                }
            }
        });
    }
};
