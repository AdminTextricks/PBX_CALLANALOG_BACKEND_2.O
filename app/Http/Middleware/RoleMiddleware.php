<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role, $permission = null): Response
    {
        if ($request->user()->hasRole('super-admin')) {
            return $next($request);
        }
        
        if(!$request->user()->hasRole($role)){
            //abort(404);
            $response = ["status" => false,"message" => 'You do not have to access this role!'];
            return response()->json($response, 403);
        }

        if($permission != null && !$request->user()->can($permission)){
            //abort(404);
            $response = ["status" => false,"message" => 'You do not have permission to access!'];
            return response()->json($response, 403);
        }
        return $next($request);
    }
}

