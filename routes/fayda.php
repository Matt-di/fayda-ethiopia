<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Mattdi\Fayda\Laravel\Http\Controllers\FaydaController;

$routeConfig = (array) config('fayda.routes', []);
$middleware = $routeConfig['middleware'] ?? ['web'];
$middleware = is_array($middleware) ? $middleware : [$middleware];
$prefix = trim((string) ($routeConfig['prefix'] ?? 'auth/fayda'), '/');
$redirectName = (string) ($routeConfig['redirect_name'] ?? 'fayda.redirect');
$callbackName = (string) ($routeConfig['callback_name'] ?? 'fayda.callback');

Route::middleware($middleware)
    ->prefix($prefix)
    ->group(static function () use ($redirectName, $callbackName): void {
        Route::get('redirect', [FaydaController::class, 'redirect'])->name($redirectName);

        Route::match(['get', 'post'], 'callback', [FaydaController::class, 'callback'])
            ->name($callbackName)
            ->withoutMiddleware(VerifyCsrfToken::class);
    });
