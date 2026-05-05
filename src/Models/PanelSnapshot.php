<?php

namespace EdrisaTuray\FilamentStarterMinimal\Models;

use Illuminate\Database\Eloquent\Model;

class PanelSnapshot extends Model
{
    protected $table = 'starter_minimal_panel_snapshots';

    protected $fillable = [
        'panel_id',
        'meta',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }
}
