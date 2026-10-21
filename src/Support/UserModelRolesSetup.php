<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use Illuminate\Filesystem\Filesystem;
use PhpToken;
use ReflectionClass;
use RuntimeException;

class UserModelRolesSetup
{
    public function ensure(string $model): bool
    {
        if (! class_exists($model)) {
            throw new RuntimeException("Configured user model [{$model}] does not exist.");
        }

        if (method_exists($model, 'assignRole') && method_exists($model, 'hasRole')) {
            return false;
        }

        $reflection = new ReflectionClass($model);
        $path = $reflection->getFileName();

        if ($path === false || ! is_writable($path)) {
            throw new RuntimeException("Cannot add HasRoles: the source file for [{$model}] is not writable.");
        }

        $files = new Filesystem;
        $source = $files->get($path);
        $tokens = PhpToken::tokenize($source, TOKEN_PARSE);
        $foundClass = false;

        foreach ($tokens as $index => $token) {
            if ($token->id === T_CLASS && $token->line === $reflection->getStartLine()) {
                $next = $index + 1;
                while (isset($tokens[$next]) && $tokens[$next]->isIgnorable()) {
                    $next++;
                }
                $foundClass = isset($tokens[$next]) && $tokens[$next]->text === $reflection->getShortName();
            }

            if (! $foundClass || $token->text !== '{') {
                continue;
            }

            $newline = str_contains($source, "\r\n") ? "\r\n" : "\n";
            $insertion = $newline.'    use \\Spatie\\Permission\\Traits\\HasRoles;'.$newline;
            $offset = $token->pos + 1;

            // A loaded class can be stale when ensure() runs twice in the same PHP process.
            if (substr($source, $offset, strlen($insertion)) !== $insertion) {
                $updated = substr_replace($source, $insertion, $offset, 0);
                PhpToken::tokenize($updated, TOKEN_PARSE);
                $files->replace($path, $updated);
            }

            return true;
        }

        throw new RuntimeException("Cannot locate the class declaration for [{$model}].");
    }
}
