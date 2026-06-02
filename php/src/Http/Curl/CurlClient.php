<?php

declare(strict_types=1);

namespace Spart\Sdk\Http\Curl;

use Spart\Sdk\Exceptions\SpartTimeoutException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpRequest;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\Internal\HttpResponseClassifier;
use Spart\Sdk\Internal\UserAgentValidator;

final class CurlClient implements HttpClient
{
    private readonly string $userAgent;

    public function __construct(?string $userAgent = null)
    {
        // Defense-in-depth. SpartClientConfig already validates UAs,
        // but CurlClient is a public class that can be constructed
        // directly (e.g. by custom HttpClientFactory implementations
        // or in tests), so the same rules MUST apply here.
        $this->userAgent = UserAgentValidator::validate($userAgent, 'CurlClient::userAgent');
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new SpartTransportException('curl_init failed.');
        }

        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => strtoupper($request->method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => $request->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => $this->userAgent,
        ]);

        if ($request->body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->body);
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($_ch, $line) use (&$responseHeaders): int {
            $len = strlen($line);
            $sep = strpos($line, ':');
            if ($sep !== false) {
                $name = strtolower(trim(substr($line, 0, $sep)));
                $value = trim(substr($line, $sep + 1));
                $responseHeaders[$name] = $value;
            }
            return $len;
        });

        try {
            $body = curl_exec($ch);
            if ($body === false) {
                $errno = curl_errno($ch);
                $err = curl_error($ch);
                if ($errno === CURLE_OPERATION_TIMEDOUT) {
                    throw new SpartTimeoutException("HTTP transport timeout: $err");
                }
                throw new SpartTransportException("HTTP transport error: $err");
            }
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $response = new HttpResponse((int) $status, $responseHeaders, (string) $body);
            // Classify non-2xx BEFORE returning so the response crosses
            // the RetryingHttpClient boundary as a typed exception:
            // 5xx → SpartServerException, 429 → SpartRateLimitException,
            // both of which the retry decorator targets. 4xx still flows
            // up as SpartApiException / SpartAuthException / etc. but is
            // intentionally NOT retried because resending the same request
            // will reproduce the same client-side error.
            if ($response->statusCode < 200 || $response->statusCode > 299) {
                HttpResponseClassifier::throwForFailureStatus($response);
            }
            return $response;
        } finally {
            curl_close($ch);
        }
    }
}
