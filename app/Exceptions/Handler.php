<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(static function (ValidationException $e) {
        })->stop();
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'message' => $e->getMessage() ?: data_get(Response::$statusTexts, $e->getStatusCode(), 'error'),
            ], $e->getStatusCode());
        }

        return response()->json([
            'message' => 'Server Error',
        ], 500);
    }


}
