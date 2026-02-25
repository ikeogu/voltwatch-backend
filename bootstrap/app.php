<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('v1')
                ->group(base_path('routes/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('v1/*') || $request->expectsJson();
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            $wantsJson = $request->is('v1/*') || $request->expectsJson();

            if (!$wantsJson) {
                return null;
            }


            return match (true) {
                $e instanceof AuthenticationException => response()->json([
                    'status' => 'failed',
                    'message' => 'Unauthenticated. Please login to proceed'
                ], Response::HTTP_UNAUTHORIZED),

                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => response()->json([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ], Response::HTTP_FORBIDDEN),

                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => response()->json([
                    'status' => 'failed',
                    'message' => 'Requested resource is not found'
                ], Response::HTTP_NOT_FOUND),

                $e instanceof ValidationException => response()->json([
                    'status' => 'failed',
                    'errors' => $e->validator->errors()->all()
                ], Response::HTTP_UNPROCESSABLE_ENTITY),

                $e instanceof ThrottleRequestsException => response()->json([
                    'status' => 'failed',
                    'message' => $e->getMessage()
                ], Response::HTTP_TOO_MANY_REQUESTS),

                $e instanceof TokenMismatchException => response()->json([
                    'status' => 'failed',
                    'message' => $e->getMessage()
                ], Response::HTTP_FORBIDDEN),

                default => (function () use ($e) {

                    Log::error('Unhandled exception: ' . $e->getMessage(), [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Something went wrong, please try again later'
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                })()
            };
        });
    })->create();
