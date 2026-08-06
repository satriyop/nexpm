<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate MCP web clients via a shared bearer token mapped to an admin user.
 */
class AuthenticateAiMcp
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(config('ai.mcp.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }

        $expected = (string) config('ai.mcp.token', '');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid or missing MCP bearer token.');
        }

        $userId = config('ai.mcp.acting_as_user_id');
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403, 'MCP acting user must be a valid admin (set AI_MCP_ACTING_AS_USER_ID).');
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
