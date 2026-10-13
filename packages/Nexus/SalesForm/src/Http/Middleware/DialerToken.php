<?php

namespace Nexus\SalesForm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Webkul\User\Models\User;

/**
 * Signs in the 12Nexus Dialer extension's requests by their bearer token.
 *
 * The token's user is put on Krayin's own `user` guard, so bouncer(), the form
 * requests and LeadBuilder see the same rep they would on the web forms and
 * apply the same permissions.
 */
class DialerToken
{
    /**
     * The only ability a dialer token carries; a token minted for anything
     * else is not accepted here.
     */
    public const ABILITY = 'dialer';

    public function handle(Request $request, Closure $next)
    {
        $token = PersonalAccessToken::findToken((string) $request->bearerToken());

        $user = $token?->tokenable;

        if (
            ! $token
            || ! $token->can(self::ABILITY)
            || ($token->expires_at && $token->expires_at->isPast())
            || ! $user instanceof User
            || ! $user->status
            || ! $user->role
        ) {
            return response()->json([
                'message' => trans('sales_form::app.dialer.signed-out'),
            ], 401);
        }

        auth()->guard('user')->setUser($user);

        $request->setUserResolver(fn () => $user);

        $request->attributes->set('dialer_token', $token);

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
