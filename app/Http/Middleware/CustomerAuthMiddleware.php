<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * For demo purposes, we use the X-Customer-Id header to identify the customer.
     * In production, this would be replaced with proper authentication.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customerId = $request->header('X-Customer-Id');

        if (!$customerId) {
            return response()->json([
                'message' => 'X-Customer-Id header is required.',
            ], 401);
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        $request->merge(['customer' => $customer]);

        return $next($request);
    }
}
