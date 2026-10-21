<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Actions;

use Filament\Facades\Filament;

class Impersonate extends \STS\FilamentImpersonate\Actions\Impersonate
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guard(fn (): string => Filament::getCurrentOrDefaultPanel()->getAuthGuard());
        $this->redirectTo(fn (): string => Filament::getCurrentOrDefaultPanel()->getUrl());
        $this->backTo(fn (): string => Filament::getCurrentOrDefaultPanel()->getUrl());
        $this->requiresConfirmation();
    }

    protected function canImpersonate($target): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && method_exists($user, 'hasRole')
            && $user->hasRole(config('filament-starter-minimal.superadmin.role', 'super_admin'))
            && parent::canImpersonate($target);
    }
}
