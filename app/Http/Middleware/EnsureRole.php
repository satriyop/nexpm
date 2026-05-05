<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        foreach ($roles as $role) {
            if ($user->role === Role::from($role)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized.');
    }
}
