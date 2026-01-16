<?php

namespace App\Traits;
 
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{ 
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
 
        static::creating(function ($model) {
            if (! $model->tenant_id && app()->bound('current_tenant_id')) {
                $model->tenant_id = app('current_tenant_id');
            }
        });
    } 
  
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
