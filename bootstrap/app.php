<?php

use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API clients are never redirected to a login page; they get a 401 instead.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API routes always answer in JSON, even without an "Accept: application/json" header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // 4xx errors (404, 403, 405, 409, 429...): uniform body, no internal details.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = match (true) {
                $status === Response::HTTP_NOT_FOUND => 'Resource not found.',
                $e->getMessage() !== '' => $e->getMessage(),
                default => Response::$statusTexts[$status] ?? 'Error.',
            };

            return response()->json(['message' => $message], $status, $e->getHeaders());
        });

        // Anything unexpected: generic 500, never the exception message or stack trace.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')
                || $e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException) {
                return null;
            }

            return response()->json(['message' => Controller::SERVER_ERROR_MESSAGE], 500);
        });
    })->create();
