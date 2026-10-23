<?php

namespace App\Http;

use DomainException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns a domain rule that a request broke into a response: code 403 is a
 * refusal, 404 a record outside the person's reach (shown as not found), and
 * anything else a message on the form it came from under `$errorKey`.
 */
final class DomainRuleResponse
{
    public static function for(DomainException $e, Request $request, string $errorKey): Response
    {
        if ($e->getCode() === 403) {
            return response($e->getMessage(), 403);
        }

        if ($e->getCode() === 404) {
            return app(ExceptionHandler::class)->render($request, new NotFoundHttpException($e->getMessage()));
        }

        return $request->expectsJson()
            ? response()->json(['message' => $e->getMessage(), 'errors' => [$errorKey => [$e->getMessage()]]], 422)
            : back()->withErrors([$errorKey => $e->getMessage()]);
    }
}
