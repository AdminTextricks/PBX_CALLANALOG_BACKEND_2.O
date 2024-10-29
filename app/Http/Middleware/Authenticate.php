<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Crypt;
use Laravel\Sanctum\PersonalAccessToken;
use Closure;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }

    public function handle($request, Closure $next, ...$guards)
    {
        
        $encryptedToken = $request->bearerToken();

        try {
            
            if (!$encryptedToken) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decryptedToken = Crypt::decryptString($encryptedToken);
            
            $token = PersonalAccessToken::findToken($decryptedToken);

            if (!$token || !$token->tokenable) {
                return response()->json(['error' => 'Invalid or expired token'], 401);
            }

            $user = $token->tokenable;
            $currentIp = $request->ip();

            $userAgent = $request->header('User-Agent');
            
            if ($token->ip_address !== $currentIp || $token->user_agent !== $userAgent) {
                return response()->json(['message' => 'Unauthorized: IP address or User-Agent mismatch'], 401);
            }
    
            auth()->login($user);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Token decryption failed'], 401);
        }
        
        return $next($request);
    }
}
