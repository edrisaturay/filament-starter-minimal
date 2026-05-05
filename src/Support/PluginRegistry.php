<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use RuntimeException;

/**
 * Container-bound plugin registry. Definitions are seeded from the package's
 * default catalog in the service provider; consumers can register additional
 * (or override existing) definitions via FilamentStarterMinimalPlugin::withPlugin()
 * or by resolving the contract directly.
 */
class PluginRegistry implements PluginRegistryContract
{
    /**
     * @var array<string, PluginDefinition>
     */
    private array $definitions = [];

    /**
     * Keys for which the package shipped a `dangerousToDisable=true` default.
     * register() refuses to silently demote these unless the caller passes
     * `allowDangerousOverride: true`. Closes the chained-takeover path where
     * a third-party `withPlugin()` call could redefine `filament-shield` with
     * `dangerousToDisable=false` and then disable RBAC.
     *
     * @var array<string, true>
     */
    private array $protectedKeys = [];

    public function register(PluginDefinition $definition, bool $allowDangerousOverride = false): void
    {
        if (
            isset($this->protectedKeys[$definition->key])
            && ! $definition->dangerousToDisable
            && ! $allowDangerousOverride
        ) {
            throw new RuntimeException(sprintf(
                'Refusing to register plugin "%s" with dangerousToDisable=false: '.
                'the package-shipped default marks it as dangerous. Pass '.
                'allowDangerousOverride: true to override explicitly.',
                $definition->key,
            ));
        }

        $this->definitions[$definition->key] = $definition;

        if ($definition->dangerousToDisable) {
            $this->protectedKeys[$definition->key] = true;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ?PluginDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /**
     * @return array<string, PluginDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, PluginDefinition>
     */
    public function available(): array
    {
        return array_filter($this->definitions, fn (PluginDefinition $d): bool => $d->isAvailable());
    }

    public function signature(): string
    {
        $parts = [];
        foreach ($this->definitions as $key => $def) {
            $parts[] = $key.':'.($def->defaultEnabled ? '1' : '0').':'.($def->dangerousToDisable ? '1' : '0');
        }
        sort($parts);

        return hash('crc32b', implode('|', $parts));
    }

    /**
     * Authoritative source: the registry definition itself. The DB column
     * `is_dangerous` is a denormalized cache maintained by the model's
     * `saving` hook; reading the registry directly avoids drift.
     */
    public static function isDangerous(?string $key, ?string $panelId = null): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        $definition = app(PluginRegistryContract::class)->get($key);

        return $definition !== null && $definition->dangerousToDisable;
    }
}
