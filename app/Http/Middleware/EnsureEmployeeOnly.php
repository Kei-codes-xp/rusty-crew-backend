<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user is a regular employee (not Manager/Admin).
 * Used on employee-only routes so managers can't accidentally hit them.
 *
 * For employee routes, the backend already filters data by auth()->id()
 * so even if a manager hits these endpoints, they'd only see their own data.
 * This middleware adds an explicit role check as a defense-in-depth measure.
 */
class EnsureEmployeeOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Managers and Admins have their own dashboard routes
        if (in_array($user->role, ['Manager', 'Admin'])) {
            return response()->json([
                'message' => 'This endpoint is for employees only. Use the manager dashboard.',
            ], 403);
        }

        if ($user->status !== 'Active') {
            return response()->json(['message' => 'Your account is not active'], 403);
        }

        return $next($request);
    }
}