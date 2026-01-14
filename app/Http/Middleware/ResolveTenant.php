<?php

namespace App\Http\Middleware; 
 
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');
        
        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'X-Tenant-ID header is required',
            ], 400);
            
        }
        $tenant = Tenant::where('id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive tenant',
            ], 403);
        }
        
        app()->instance('current_tenant_id', $tenant->id);
        app()->instance('current_tenant', $tenant);
        return $next($request);
        
    }
}
