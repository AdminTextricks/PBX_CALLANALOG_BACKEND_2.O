<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        if ($accessToken) {
            // Retrieve the token from the database
            $token = PersonalAccessToken::findToken($accessToken);

            if ($token) {
                // Check if the token has been inactive for more than 2 hours
                $lastUsed = $token->last_used_at ? Carbon::parse($token->last_used_at) : $token->created_at;
                $now = Carbon::now();
                if ($lastUsed->diffInHours($now) >= 2) {
                    // Token is expired due to inactivity (more than 2 hours)
                    // Optionally, you can revoke or delete the token here
                    $token->delete();

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
