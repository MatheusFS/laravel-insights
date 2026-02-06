<?php

namespace MatheusFS\Laravel\Insights\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuthenticatedRequest Model - Rastreamento de requisições autenticadas
 *
 * @property int $id
 * @property int $user_id
 * @property string $ip
 * @property string $method
 * @property string $path
 * @property int $status_code
 * @property float|null $response_time_ms
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property \Illuminate\Support\Carbon $created_at
 */
class AuthenticatedRequest extends Model
{
    const UPDATED_AT = null;

    protected $table = 'user_requests';

    protected $fillable = [
        'user_id',
        'ip',
        'method',
        'path',
        'status_code',
        'response_time_ms',
        'user_agent',
        'device_type',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'float',
        'created_at' => 'datetime',
    ];

    /**
     * Relação com User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('insights.user_model', 'App\Models\User'));
    }

    /**
     * Scope: apenas erros (4xx e 5xx)
     */
    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope: apenas sucesso (2xx e 3xx)
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status_code', '<', 400);
    }

    /**
     * Scope: por método HTTP
     */
    public function scopeMethod($query, string $method)
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * Scope: por período
     */
    public function scopeBetweenDates($query, string $start, string $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope: apenas requisições lentas (> threshold ms)
     */
    public function scopeSlowRequests($query, float $thresholdMs = 1000.0)
    {
        return $query->where('response_time_ms', '>', $thresholdMs);
    }
}
