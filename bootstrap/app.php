<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \Nwidart\Modules\LaravelModulesServiceProvider::class,
    ])
     ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super.admin'        => \App\Http\Middleware\SuperAdmin::class,
            'maintenance'        => \App\Http\Middleware\MaintenanceMode::class,
            'verify.hub.token'   => \App\Http\Middleware\VerifyHubToken::class,
            'subscription'       => \App\Http\Middleware\CheckSubscription::class,
        ]);

        // Contrôle de l'abonnement sur toute la partie web authentifiée
        $middleware->web(append: [
            \App\Http\Middleware\CheckSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Intercepter toutes les exceptions pour afficher la page premium, même en débug
        $exceptions->render(function (Throwable $e, Request $request) {
            // Si c'est une requête API/JSON, on garde le format JSON
            if ($request->expectsJson()) {
                $statusCode = 500;
                if (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $statusCode = $e->getStatusCode();
                }

                return response()->json([
                    'message' => $e->getMessage(),
                    'status' => 'error',
                    'code' => $statusCode,
                ], $statusCode);
            }

            // Pour les requêtes Web classiques (HTML)
            // On force l'affichage de notre vue premium pour les erreurs fatales uniquement
            // On NE DOIT PAS intercepter les exceptions d'auth ou de validation qui gèrent leurs propres redirections
            if (
                !($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) &&
                !($e instanceof \Illuminate\Auth\AuthenticationException) &&
                !($e instanceof \Illuminate\Validation\ValidationException)
            ) {
                // On prépare les données pour la vue
                return response()->view('errors.500', [
                    'exception' => $e
                ], 500);
            }
        });

        // Rediriger explicitement les erreurs 404 vers notre page premium
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found',
                    'status' => 'error',
                    'code' => 404
                ], 404);
            }
            return response()->view('errors.404', [], 404);
        });

        // Logger les erreurs critiques
        $exceptions->report(function (Throwable $e) {
            if ($e instanceof \Exception && $e->getCode() >= 500) {
                Log::critical('Critical error occurred', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        });
    })->create();
