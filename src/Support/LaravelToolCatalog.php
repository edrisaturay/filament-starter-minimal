<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use InvalidArgumentException;

class LaravelToolCatalog
{
    public static function all(): array
    {
        return [
            'horizon' => ['label' => 'Horizon: Redis queues', 'package' => 'laravel/horizon', 'dev' => false],
            'telescope' => ['label' => 'Telescope: request and query debugging', 'package' => 'laravel/telescope', 'dev' => false],
            'pulse' => ['label' => 'Pulse: performance dashboard', 'package' => 'laravel/pulse', 'dev' => false],
            'reverb' => ['label' => 'Reverb: WebSocket broadcasting', 'package' => 'laravel/reverb', 'dev' => false],
            'scout' => ['label' => 'Scout: database search', 'package' => 'laravel/scout', 'dev' => false],
            'pennant' => ['label' => 'Pennant: feature flags', 'package' => 'laravel/pennant', 'dev' => false],
            'sanctum' => ['label' => 'Sanctum: API tokens', 'package' => 'laravel/sanctum', 'dev' => false],
            'socialite' => ['label' => 'Socialite: OAuth provider support', 'package' => 'laravel/socialite', 'dev' => false],
            'cashier' => ['label' => 'Cashier: Stripe billing', 'package' => 'laravel/cashier', 'dev' => false],
            'octane' => ['label' => 'Octane: FrankenPHP application server', 'package' => 'laravel/octane', 'dev' => false],
            'sail' => ['label' => 'Sail: Docker development environment', 'package' => 'laravel/sail', 'dev' => true],
            'dusk' => ['label' => 'Dusk: browser testing', 'package' => 'laravel/dusk', 'dev' => true],
            'pint' => ['label' => 'Pint: code formatting', 'package' => 'laravel/pint', 'dev' => true],
            'pail' => ['label' => 'Pail: live log viewer', 'package' => 'laravel/pail', 'dev' => true],
        ];
    }

    public static function validate(array $keys): array
    {
        $unknown = array_diff($keys, array_keys(self::all()));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown Laravel tools: '.implode(', ', $unknown));
        }

        return array_values(array_unique($keys));
    }
}
