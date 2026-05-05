<?php

use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;

it('re-derives is_dangerous from the registry on save', function (): void {
    // filament-shield is registered with dangerousToDisable=true.
    $override = PanelPluginOverride::create([
        'panel_id' => 'admin',
        'plugin_key' => 'filament-shield',
        'enabled' => true,
        'is_dangerous' => false, // attempt to forge — should be ignored (not fillable)
    ]);

    expect($override->is_dangerous)->toBeTrue();
});

it('forces enabled=true when is_dangerous is true', function (): void {
    $override = PanelPluginOverride::create([
        'panel_id' => 'admin',
        'plugin_key' => 'filament-shield',
        'enabled' => false,
    ]);

    expect($override->enabled)->toBeTrue()
        ->and($override->is_dangerous)->toBeTrue();
});

it('marks unknown plugin_key as not dangerous', function (): void {
    $override = PanelPluginOverride::create([
        'panel_id' => 'admin',
        'plugin_key' => 'nonexistent-plugin',
        'enabled' => false,
    ]);

    expect($override->is_dangerous)->toBeFalse()
        ->and($override->enabled)->toBeFalse();
});

it('does not enter a recursive update loop on dangerous flag change', function (): void {
    $override = PanelPluginOverride::create([
        'panel_id' => 'admin',
        'plugin_key' => 'filament-shield',
        'enabled' => true,
    ]);

    $beforeAuditCount = \EdrisaTuray\FilamentStarterMinimal\Models\AuditLog::query()->count();

    $override->touch();

    $afterAuditCount = \EdrisaTuray\FilamentStarterMinimal\Models\AuditLog::query()->count();

    // Exactly one new audit row — not two (which would indicate the recursive
    // updated-hook bug fixed in P1).
    expect($afterAuditCount - $beforeAuditCount)->toBe(1);
});
