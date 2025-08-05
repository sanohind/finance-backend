<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $isRole): Response
    {
        try {
            // Check if user is authenticated first
            if (!Auth::check()) {
                Log::warning('Unauthenticated access attempt', [
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Explode the roles into an array if passed as a comma-separated string
            $roles = explode(',', $isRole);

            // Check if user role is in the allowed roles
            if (!in_array($user->role, $roles)) {
                Log::warning('Unauthorized access attempt', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'user_role' => $user->role,
                    'required_roles' => $roles,
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Log successful authorization
            Log::debug('User authorized', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'url' => $request->fullUrl()
            ]);

            return $next($request);
            
        } catch (\Exception $e) {
            Log::error('UserRole middleware error: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'url' => $request->fullUrl()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
