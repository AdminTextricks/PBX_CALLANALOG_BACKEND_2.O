<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LogRequestResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

     protected $logPath;

    public function __construct()
    {
        // Define the log file path
        $this->logPath = base_path('logs/' . date('Y-m-d') . '/request.log');

        // Ensure the log directory exists
        $this->ensureDirectoryExists(dirname($this->logPath));
    }

    public function handle(Request $request, Closure $next)
    {
        // Log request data
        $this->logRequest($request);

        // Proceed with the request
        $response = $next($request);

        // Log response data
        $this->logResponse($response);

        return $response;
    }

    protected function logRequest(Request $request)
    {
        // Log request data
        Log::build([
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'info',
        ])->info('Request Data:', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);
    }

    protected function logResponse($response)
    {
        // Log response data
        Log::build([
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'info',
        ])->info('Response Data:', [
            'status' => $response->status(),
            'content' => $response->getContent(),
        ]);
    }

    /**
     * Ensure the directory exists.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensureDirectoryExists($path)
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }
    /*public function handle(Request $request, Closure $next): Response
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
    }*/
}
