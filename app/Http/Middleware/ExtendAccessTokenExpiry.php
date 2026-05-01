<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtendAccessTokenExpiry
{
    public const TOKEN_LIFETIME_DAYS = 30;

    private const RENEW_THRESHOLD_HOURS = 6;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();

            if ($token && $token->name === 'crm-login') {
                $newExpiry = Carbon::now()->addDays(self::TOKEN_LIFETIME_DAYS);

                if (
                    !$token->expires_at
                    || $token->expires_at->diffInHours($newExpiry, false) >= self::RENEW_THRESHOLD_HOURS
                ) {
                    $token->forceFill(['expires_at' => $newExpiry])->save();
                }
            }
        }

        return $next($request);
    }
}
