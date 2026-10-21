<?php

namespace EdrisaTuray\FilamentStarterMinimal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthorizeLaravelTool
{
    public static function allows(mixed $user): bool
    {
        return $user !== null && method_exists($user, 'hasRole')
            && $user->hasRole(config('filament-starter-minimal.superadmin.role', 'super_admin'));
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $guard = config('starter-minimal-tools.guard') ?: config('auth.defaults.guard', 'web');
        abort_unless(static::allows($request->user($guard)), 403);

        return $next($request);
    }
}
