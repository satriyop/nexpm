<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReadOnly
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        foreach ($roles as $role) {
            if ($user?->role === Role::from($role)) {
                abort_unless(
                    $request->isMethod('GET') || $request->isMethod('HEAD'),
                    403,
                    'This action is not allowed for your role.'
                );
            }
        }

        return $next($request);
    }
}
