<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
            return $this->error('MCP endpoint is disabled.', Response::HTTP_NOT_FOUND);
        }

        $expected = (string) config('ai.mcp.token', '');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return $this->error('Invalid or missing MCP bearer token.', Response::HTTP_UNAUTHORIZED, true);
        }

        $userId = config('ai.mcp.acting_as_user_id');
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user instanceof User || (! $user->isAdmin() && ! $user->isGlobalAdmin())) {
            return $this->error('MCP acting user is not authorized.', Response::HTTP_FORBIDDEN);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function error(string $message, int $status, bool $challenge = false): JsonResponse
    {
        $response = response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $status === Response::HTTP_UNAUTHORIZED ? -32001 : -32000,
                'message' => $message,
            ],
        ], $status);

        if ($challenge) {
            $response->headers->set('WWW-Authenticate', 'Bearer');
        }

        return $response;
    }
}
