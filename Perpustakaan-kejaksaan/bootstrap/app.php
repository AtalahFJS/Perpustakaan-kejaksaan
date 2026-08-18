<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'peran' => App\Http\Middleware\PastikanPeran::class,
            'aktif' => App\Http\Middleware\PastikanAkunAktif::class,
        ]);

        // F-1.4: setiap permintaan terautentikasi diperiksa keaktifan akunnya.
        $middleware->web(append: [
            App\Http\Middleware\PastikanAkunAktif::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('masuk'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
