<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ForceJsonApiMiddleware
 *
 * Ensures every request to the API is treated as a JSON request by:
 *
 *  1. Forcing `Accept: application/json` so Laravel always returns JSON
 *     errors (no HTML redirect/exception pages on API routes).
 *
 *  2. When the request body appears to be JSON (detected by JSON_DETECT_OBJECT
 *     on the raw content) but the client did not send `Content-Type:
 *     application/json` (e.g. APIdog with Body → "text/plain", curl without
 *     --header flag, or a redirect that strips headers), we upgrade the header
 *     on the fly. This allows Laravel/Symfony's input bag to decode the body
 *     and populate `$request->all()` correctly without any `prepareForValidation`
 *     fallback in individual FormRequests.
 *
 *  3. Adds hardened security headers to every API response.
 */
class ForceJsonApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Always request JSON responses.
        $request->headers->set('Accept', 'application/json');

        // 2. If the body looks like JSON but Content-Type is wrong/missing,
        //    upgrade it so the Symfony input bag parses it correctly.
        if ($this->bodyIsJsonButContentTypeWrong($request)) {
            $request->headers->set('Content-Type', 'application/json');
        }

        // 3. Inspect and sanitize JSON payload (allowing JS comments from tools like Apidog/curl)
        $content = $request->getContent();
        if ($request->isJson() && ! empty($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cleanContent = $this->stripJsonComments($content);
                $cleanDecoded = json_decode($cleanContent, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($cleanDecoded)) {
                    $request->json()->replace($cleanDecoded);
                    $request->merge($cleanDecoded);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Malformed JSON in request body: '.json_last_error_msg().'. Please ensure your payload contains valid JSON syntax.',
                        'error' => 'MALFORMED_JSON',
                        'error_code' => 'MALFORMED_JSON',
                    ], 400);
                }
            }
        }

        $response = $next($request);

        // 3. Harden API response headers.
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'deny');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Content-Security-Policy', "default-src 'self'");
        $response->headers->remove('X-Powered-By');

        return $response;
    }

    /**
     * Determine whether the request body contains JSON but the Content-Type
     * header does not reflect that (missing, text/plain, form, etc.).
     */
    private function bodyIsJsonButContentTypeWrong(Request $request): bool
    {
        if ($request->isJson()) {
            // Already correctly typed — nothing to do.
            return false;
        }

        $content = $request->getContent();

        if (empty($content)) {
            return false;
        }

        // A JSON object or array starts with { or [ (after optional whitespace).
        $firstChar = ltrim($content)[0] ?? '';

        return $firstChar === '{' || $firstChar === '[';
    }

    /**
     * Safely strip single-line (// ...) and multi-line (/ * ... * /) comments
     * from JSON text without corrupting string literals or URLs (e.g. "https://...").
     */
    private function stripJsonComments(string $json): string
    {
        $result = '';
        $inString = false;
        $escaped = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $char = $json[$i];

            if ($inString) {
                $result .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                $result .= $char;

                continue;
            }

            // Single line comment: //
            if ($char === '/' && isset($json[$i + 1]) && $json[$i + 1] === '/') {
                while ($i < $len && $json[$i] !== "\n" && $json[$i] !== "\r") {
                    $i++;
                }
                $result .= "\n";

                continue;
            }

            // Multi line comment: /* ... */
            if ($char === '/' && isset($json[$i + 1]) && $json[$i + 1] === '*') {
                $i += 2;
                while ($i < $len - 1 && ! ($json[$i] === '*' && $json[$i + 1] === '/')) {
                    $i++;
                }
                $i++;

                continue;
            }

            $result .= $char;
        }

        return $result;
    }
}
