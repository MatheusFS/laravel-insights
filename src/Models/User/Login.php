<?php

namespace MatheusFS\Laravel\Insights\Models\User;

use Illuminate\Database\Eloquent\Model;

/**
 * Login Model - Tracking de logins (success/failed)
 * 
 * Compatível com v1.0 (guard, ip_address, browser) e v1.1+ (email, success, device_type)
 */
class Login extends Model
{
    const UPDATED_AT = null;

    protected $touches = ['user'];

    protected $table = 'user_logins';
    
    /**
     * Fillable attributes - Suporta v1.0 e v1.1+
     */
    protected $fillable = [
        // v1.0 fields
        'guard',
        'user_id',
        'ip_address',
        'browser',
        // v2.0 fields (após upgrade migration)
        'email',
        'success',
        'failure_reason',
        'device_type',
    ];

    /**
     * Casts - v1.1+
     */
    protected $casts = [
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship: Belongs to User
     */
    public function user()
    {
        return $this->belongsTo(config('insights.user_model'));
    }

    /**
     * Scopes
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeBetweenDates($query, string $start, string $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Accessors para compatibilidade v1.0 ↔ v2.0
     */
    public function getIpAttribute()
    {
        return $this->ip_address; // Fallback para v1.0
    }

    public function getUserAgentAttribute()
    {
        return $this->browser; // Fallback para v1.0
    }
}
