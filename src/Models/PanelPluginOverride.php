<?php

namespace EdrisaTuray\FilamentStarterMinimal\Models;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PanelPluginOverride extends Model
{
    protected $table = 'starter_minimal_panel_plugin_overrides';

    /**
     * is_dangerous is intentionally NOT fillable. It is re-derived server-side
     * from the registry on every save, so it cannot be forged via mass
     * assignment or the admin form.
     */
    protected $fillable = [
        'panel_id',
        'plugin_key',
        'enabled',
        'options',
        'options_version',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_dangerous' => 'boolean',
            'options' => 'array',
            'options_version' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PanelPluginOverride $model): void {
            $definition = app(PluginRegistryContract::class)->get($model->plugin_key);
            $model->is_dangerous = $definition !== null && $definition->dangerousToDisable;

            if ($model->is_dangerous) {
                $model->enabled = true;
            }
        });

        static::saved(function (PanelPluginOverride $model): void {
            $payload = [
                'action' => 'update_plugin_state',
                'panel_id' => $model->panel_id,
                'plugin_key' => $model->plugin_key,
                'before' => $model->wasRecentlyCreated ? null : $model->getOriginal(),
                'after' => $model->getAttributes(),
            ];
            $panelId = $model->panel_id;

            DB::afterCommit(function () use ($payload, $panelId): void {
                PluginStateResolver::invalidate($panelId);

                try {
                    AuditLog::create($payload);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });

        static::deleted(function (PanelPluginOverride $model): void {
            $payload = [
                'action' => 'delete_plugin_override',
                'panel_id' => $model->panel_id,
                'plugin_key' => $model->plugin_key,
                'before' => $model->getAttributes(),
            ];
            $panelId = $model->panel_id;

            DB::afterCommit(function () use ($payload, $panelId): void {
                PluginStateResolver::invalidate($panelId);

                try {
                    AuditLog::create($payload);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
    }
}
