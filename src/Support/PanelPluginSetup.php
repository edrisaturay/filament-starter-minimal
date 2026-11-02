<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Filament\FilamentStarterMinimalPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Filesystem\Filesystem;
use PhpToken;
use ReflectionMethod;
use RuntimeException;

class PanelPluginSetup
{
    public function ensure(): bool
    {
        $ids = config('filament-starter-minimal.managed_panels', ['admin']);
        if ($ids === []) {
            throw new RuntimeException('Configure at least one managed panel before installing.');
        }

        $panels = Filament::getPanels();
        $missing = [];
        foreach ($ids as $id) {
            if (! isset($panels[$id])) {
                throw new RuntimeException("Managed panel [{$id}] is not registered. Create and register its Filament PanelProvider, or correct managed_panels.");
            }
            if (! $panels[$id]->hasPlugin('filament-starter-minimal')) {
                $missing[] = $id;
            }
        }
        if ($missing === []) {
            return false;
        }

        $providers = [];
        foreach (app()->getProviders(PanelProvider::class) as $provider) {
            $id = $provider->panel(Panel::make())->getId();
            if (in_array($id, $missing, true)) {
                $providers[$id] = $provider;
            }
        }
        foreach ($missing as $id) {
            if (! isset($providers[$id])) {
                throw new RuntimeException("Cannot locate the PanelProvider for [{$id}]. Register FilamentStarterMinimalPlugin in that panel's definition.");
            }
        }
        foreach ($providers as $provider) {
            $method = new ReflectionMethod($provider, 'panel');
            $path = $method->getFileName();
            if ($path === false || ! str_starts_with($path, app_path().DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Automatic panel registration requires a writable application PanelProvider. Register FilamentStarterMinimalPlugin in your panel definition.');
            }
            $this->registerInProvider($provider::class);
        }

        return true;
    }

    public function registerInProvider(string $provider): void
    {
        $method = new ReflectionMethod($provider, 'panel');
        $path = $method->getFileName();
        if ($path === false || ! is_writable($path)) {
            throw new RuntimeException("Cannot update panel provider [{$provider}]: its source is not writable.");
        }

        $files = new Filesystem;
        $source = $files->get($path);
        $tokens = PhpToken::tokenize($source, TOKEN_PARSE);
        $depth = 0;
        $returns = [];
        $nestedReturn = false;
        $start = null;
        foreach ($tokens as $token) {
            if ($token->line < $method->getStartLine() || $token->line > $method->getEndLine()) {
                continue;
            }
            if ($token->text === '{' || $token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($token->text === '}') {
                $depth--;
            } elseif ($token->id === T_RETURN) {
                if ($depth !== 1) {
                    $nestedReturn = true;
                } else {
                    $start = $token->pos + strlen($token->text);
                }
            } elseif ($token->text === ';' && $depth === 1 && $start !== null) {
                $returns[] = [$start, $token->pos];
                $start = null;
            }
        }
        if ($nestedReturn || count($returns) !== 1) {
            throw new RuntimeException("Cannot safely update [{$provider}::panel]: expected one top-level return. Register FilamentStarterMinimalPlugin manually in this custom provider.");
        }

        [$start, $end] = $returns[0];
        $expression = trim(substr($source, $start, $end - $start));
        $plugin = '\\'.FilamentStarterMinimalPlugin::class.'::make()';
        if (str_contains($expression, $plugin)) {
            throw new RuntimeException("[{$provider}] already contains the starter registration but the running panel does not. Restart Artisan and check the panel definition.");
        }
        $updated = substr_replace($source, ' ('.$expression.')->plugin('.$plugin.')', $start, $end - $start);
        PhpToken::tokenize($updated, TOKEN_PARSE);
        $files->replace($path, $updated);
    }
}
