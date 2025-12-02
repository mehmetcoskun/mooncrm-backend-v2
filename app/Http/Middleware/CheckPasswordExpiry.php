<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }

        $passwordChangeRoutes = [
            'api/auth/change-password',
            'api/auth/me'
        ];

        $webhookRoutes = [
            'api/vapi/webhook',
            'api/customer/webhook',
            'api/web-form/iframe'
        ];

        $currentRoute = $request->path();
        
        foreach ($passwordChangeRoutes as $route) {
            if (str_contains($currentRoute, $route)) {
                return $next($request);
            }
        }

        foreach ($webhookRoutes as $route) {
            if (str_contains($currentRoute, $route)) {
                return $next($request);
            }
        }

        if ($user->needsPasswordChange()) {
            return response()->json([
                'message' => 'Şifrenizin süresi dolmuş. Lütfen şifrenizi değiştirin.',
                'needs_password_change' => true
            ], 403);
        }

        return $next($request);
    }
}
