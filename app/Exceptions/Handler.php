<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
    
    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Handle wrong HTTP method errors
        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid HTTP method. Please check your request type (GET, POST, etc.).',
            ], 405);
        }

        // Handle unauthenticated user errors
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authenticated. Please login to continue.',
            ], 401);
        }

        // Default fallback to parent handler
        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Please login first.',
        ], 401);
    }
}
