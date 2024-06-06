<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LogRequestResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
		// Log the request
        Log::info('Request:', [
            'method' => $request->getMethod(),
            'url' => $request->getUri(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        // Handle the request and get the response
        $response = $next($request);

        // Log the response
        Log::info('Response:', [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $response->getContent(),
        ]);

        return $response;
        //return $next($request);
    }
}
