<?php

use EdrisaTuray\FilamentStarterMinimal\Models\AuditLog;

it('auto-sets actor_user_id from auth on creation', function (): void {
    $this->actingAs(new class extends \Illuminate\Foundation\Auth\User
    {
        public $id = 42;
    });

    $log = AuditLog::create([
        'action' => 'test',
        'panel_id' => 'admin',
        'plugin_key' => 'filament-shield',
    ]);

    expect($log->actor_user_id)->toBe(42);
});

it('ignores caller-supplied actor_user_id via mass assignment', function (): void {
    $this->actingAs(new class extends \Illuminate\Foundation\Auth\User
    {
        public $id = 7;
    });

    $log = AuditLog::create([
        'actor_user_id' => 999,
        'action' => 'test',
        'panel_id' => 'admin',
        'plugin_key' => 'filament-shield',
    ]);

    expect($log->actor_user_id)->toBe(7);
});

it('throws when an audit log is updated', function (): void {
    $log = AuditLog::create([
        'action' => 'test',
        'panel_id' => 'admin',
        'plugin_key' => 'filament-shield',
    ]);

    expect(fn () => $log->update(['action' => 'tampered']))
        ->toThrow(RuntimeException::class, 'immutable');
});

it('throws when an audit log is deleted', function (): void {
    $log = AuditLog::create([
        'action' => 'test',
        'panel_id' => 'admin',
        'plugin_key' => 'filament-shield',
    ]);

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class, 'immutable');
});
