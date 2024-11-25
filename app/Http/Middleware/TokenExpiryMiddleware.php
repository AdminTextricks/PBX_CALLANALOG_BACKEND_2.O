<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class TokenExpiryMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get the bearer token from the request
        $accessToken = $request->bearerToken();
        $token = '';
        if ($accessToken) {
            // Retrieve the token from the database
            $token = PersonalAccessToken::findToken($accessToken);
            
            if ($token) {

                $currentIp = $request->ip();
                $userAgent = $request->header('User-Agent');            
                //if ($token->ip_address !== $currentIp || $token->user_agent !== $userAgent) {
                if ($token->user_agent !== $userAgent) {
                    Log::error('Unauthorized: IP address or User-Agent mismatch. IP:'.$currentIp.' User-Agent: '. $userAgent);
                    return response()->json(['message' => 'Unauthorized: IP address or User-Agent mismatch'], 401);
                    
                }

                // Check if the token has been inactive for more than 2 hours
                $lastUsed = $token->last_used_at ? Carbon::parse($token->last_used_at) : $token->created_at;
                $now = Carbon::now();
                if ($lastUsed->diffInHours($now) >= 2) {
                    // Token is expired due to inactivity (more than 2 hours)
                    // Optionally, you can revoke or delete the token here
                    $token->delete();
                    Log::error('Token expired due to inactivity');
                    return response()->json(['message' => 'Token expired due to inactivity'], 401);
                }
            }
            
        }

        //return $next($request);
        // Proceed with the request
        $response = $next($request);

        // Manually update the last_used_at field after checking expiration
        if ($token) {
            $token->forceFill([
                'last_used_at' => now(),
            ])->save();
        }
        return $response;
    }
}
