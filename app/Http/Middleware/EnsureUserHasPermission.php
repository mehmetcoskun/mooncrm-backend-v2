<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->id === 1 || $user->roles->contains('id', 1)) {
                Gate::after(function () {
                    return true;
                });
            } else {
                $permissions = Permission::whereHas('roles', function ($query) use ($user) {
                    $query->whereIn('roles.id', $user->roles->pluck('id'));
                })->get();

                foreach ($permissions as $permission) {
                    Gate::define($permission->slug, function () {
                        return true;
                    });
                }
            }
        }

        return $next($request);
    }
}
