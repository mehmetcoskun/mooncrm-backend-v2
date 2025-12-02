<?php

use App\Console\Commands\CheckCustomerReminders;
use App\Console\Commands\RefreshFacebookTokens;
use App\Console\Commands\SendDailyReport;
use App\Http\Middleware\CheckPasswordExpiry;
use App\Http\Middleware\EnsureUserHasPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CheckCustomerReminders::class,
        RefreshFacebookTokens::class,
        SendDailyReport::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            CheckPasswordExpiry::class,
            EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
