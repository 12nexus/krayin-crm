<?php

namespace Nexus\Phone\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Signs the current CRM user in to the web phone: a two-minute link the phone
 * verifies with the shared secret. ?embed=1 opens the compact view for the dock.
 */
class SsoController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $user = auth()->guard('user')->user();

        $payload = rtrim(strtr(base64_encode(json_encode([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'admin' => $user->role?->permission_type === 'all',
            'exp'   => time() + 120,
        ])), '+/', '-_'), '=');

        $token = $payload.'.'.hash_hmac('sha256', $payload, (string) config('phone.secret'));

        return redirect()->away(config('phone.url').'/sso?'.http_build_query([
            't'     => $token,
            'embed' => $request->boolean('embed') ? 1 : null,
        ]));
    }
}
