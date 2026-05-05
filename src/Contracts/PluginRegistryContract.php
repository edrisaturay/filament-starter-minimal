<?php

namespace EdrisaTuray\FilamentStarterMinimal\Contracts;

use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;

interface PluginRegistryContract
{
    /**
     * Register a plugin definition. Last write wins, so consumers may override
     * package-shipped definitions by registering the same key again.
     */
    public function register(PluginDefinition $definition): void;

    public function has(string $key): bool;

    public function get(string $key): ?PluginDefinition;

    /**
     * @return array<string, PluginDefinition>
     */
    public function all(): array;

    /**
     * Only definitions whose underlying class is autoloadable. Useful for the
     * sync manager and resolver — we should not try to install a plugin whose
     * package the consumer never required.
     *
     * @return array<string, PluginDefinition>
     */
    public function available(): array;

    /**
     * Stable signature of the current registry — changes whenever a definition
     * is added, removed, or its default-state-affecting fields change. Used by
     * `PluginStateResolver` to namespace its cache so a `withPlugin()` call at
     * boot time naturally invalidates stale resolver caches without explicit
     * `Cache::forget()` calls.
     */
    public function signature(): string;
}
