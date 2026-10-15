<?php

namespace Nexus\Phone\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Requests from the web phone carry X-Nexus-Timestamp and X-Nexus-Signature,
 * an HMAC-SHA256 of "<timestamp>.<path after /nexus-phone/>[?query].<body>"
 * with the shared secret.
 */
class VerifyPhoneSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('phone.secret');
        $timestamp = (string) $request->header('X-Nexus-Timestamp');
        $signature = (string) $request->header('X-Nexus-Signature');

        if ($secret === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > config('phone.max_skew')) {
            abort(401);
        }

        $path = substr($request->path(), strlen('nexus-phone/'));
        // Laravel re-sorts the query string; sign against the raw one.
        $rawQuery = parse_url($request->getRequestUri(), PHP_URL_QUERY);
        $payload = $timestamp.'.'.$path.($rawQuery ? '?'.$rawQuery : '').'.'.$request->getContent();

        if (! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            abort(401);
        }

        return $next($request);
    }
}
