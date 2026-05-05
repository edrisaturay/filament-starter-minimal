<?php

namespace EdrisaTuray\FilamentStarterMinimal\Registry;

use Closure;
use Filament\Panel;

/**
 * @phpstan-type InstallerCallable Closure(Panel $panel, array<string, mixed> $options): Panel
 */
class PluginDefinition
{
    /**
     * @param  Closure(Panel, array<string, mixed>): Panel  $installer
     * @param  array<string, mixed>  $defaultOptions
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly Closure $installer,
        public readonly bool $defaultEnabled = false,
        public readonly bool $dangerousToDisable = false,
        public readonly array $defaultOptions = [],
        public readonly ?string $class = null,
        public readonly ?string $package = null,
    ) {}

    /**
     * Whether the underlying composer package is installed (class is autoloadable).
     */
    public function isAvailable(): bool
    {
        return $this->class === null || class_exists($this->class);
    }
}
