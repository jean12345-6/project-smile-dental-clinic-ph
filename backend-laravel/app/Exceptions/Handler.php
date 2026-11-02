<?php

namespace App\Exceptions;

use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as BaseHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns every exception of the API into the same JSON error format.
 */
class Handler extends BaseHandler
{
    use ApiResponses;

    /**
     * Exception class => method that builds the error list.
     */
    protected array $handlers = [
        ValidationException::class => 'handleValidation',
        ModelNotFoundException::class => 'handleModelNotFound',
        NotFoundHttpException::class => 'handleNotFound',
        AuthenticationException::class => 'handleAuthentication',
    ];

    /**
     * Render the exception as JSON for API requests.
     */
    public function render($request, Throwable $e)
    {
        // Pages outside the API keep the normal Laravel behaviour
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return parent::render($request, $e);
        }

        foreach ($this->handlers as $class => $method) {
            if ($e instanceof $class) {
                return $this->$method($e);
            }
        }

        return $this->handleOther($e);
    }

    // 422: one error object per problem
    protected function handleValidation(ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => 422,
                    'message' => $message,
                    'source' => $field,
                ];
            }
        }

        return $this->error($errors, 422);
    }

    // 404: a model (appointment, patient, user...) does not exist
    protected function handleModelNotFound(ModelNotFoundException $e): JsonResponse
    {
        return $this->error([
            [
                'status' => 404,
                'message' => 'The resource cannot be found.',
                'source' => $e->getModel(),
            ],
        ], 404);
    }

    // 404: the URL does not match any route
    protected function handleNotFound(NotFoundHttpException $e): JsonResponse
    {
        return $this->error([
            [
                'status' => 404,
                'message' => 'The route cannot be found.',
                'source' => 'Route',
            ],
        ], 404);
    }

    // 401: no token, or an invalid or expired token
    protected function handleAuthentication(AuthenticationException $e): JsonResponse
    {
        return $this->error([
            [
                'status' => 401,
                'message' => 'Unauthenticated',
                'source' => '',
            ],
        ], 401);
    }

    // Everything else: the status comes from the exception (for example 405) or is 500
    protected function handleOther(Throwable $e): JsonResponse
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $debug = config('app.debug');

        // Never leak internal messages of server errors in production
        $message = ($status >= 500 && ! $debug)
            ? 'Server error.'
            : ($e->getMessage() ?: 'An error occurred.');

        return $this->error([
            [
                'status' => $status,
                'message' => $message,
                // The file and line are shown only when APP_DEBUG is true
                'source' => $debug ? $e->getFile() . ' : ' . $e->getLine() : '',
            ],
        ], $status);
    }
}
