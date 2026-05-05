<?php

namespace EdrisaTuray\FilamentStarterMinimal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Context;
use RuntimeException;

class AuditLog extends Model
{
    protected $table = 'starter_minimal_audit_logs';

    /**
     * actor_user_id and correlation_id are intentionally NOT fillable.
     * Both are server-set in the creating hook (actor from auth, correlation
     * from the request-scoped Context), so callers cannot forge them.
     */
    protected $fillable = [
        'action',
        'panel_id',
        'plugin_key',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = config('auth.providers.users.model', User::class);

        return $this->belongsTo($model, 'actor_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            if ($log->actor_user_id === null) {
                $log->actor_user_id = auth()->id();
            }

            if ($log->correlation_id === null) {
                $log->correlation_id = Context::get('filament_starter_minimal.correlation_id');
            }
        });

        static::updating(function (): void {
            throw new RuntimeException('Audit logs are immutable; updates are not permitted.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit logs are immutable; deletes are not permitted.');
        });
    }
}
