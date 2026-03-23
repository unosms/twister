<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use App\Http\Middleware\ApplySystemTimezone;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAuthenticated;
use App\Http\Middleware\RecordAuditActivity;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ApplySystemTimezone::class);

        $middleware->alias([
            'admin.auth' => EnsureAdminAuthenticated::class,
            'auth.session' => EnsureAuthenticated::class,
            'audit.log' => RecordAuditActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Session expired. Please refresh and try again.',
                ], 419);
            }

            $referer = (string) $request->headers->get('referer', '');
            $fallback = $request->is('portal/*')
                ? route('portal.index')
                : route('auth.login');
            $target = $referer !== '' ? $referer : $fallback;

            return redirect()->to($target)->withErrors([
                'session' => 'Session expired. Please refresh the page and try again.',
            ]);
        });
    })->create();
