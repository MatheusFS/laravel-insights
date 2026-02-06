<?php

namespace MatheusFS\Laravel\Insights\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MatheusFS\Laravel\Insights\Models\User\AuthenticatedRequest as AuthenticatedRequestModel;

/**
 * TrackAuthenticatedRequest Middleware
 *
 * Registra todas as requisições autenticadas para análise de incidentes
 */
class TrackAuthenticatedRequest
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
        // Se tracking desabilitado, pula
        if (! config('insights.features.authenticated_requests', true)) {
            return $next($request);
        }

        // Só rastreia se usuário autenticado
        if (! Auth::check()) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        // Calcular tempo de resposta
        $responseTime = (microtime(true) - $startTime) * 1000; // em ms

        // Registrar de forma assíncrona se configurado
        if (config('insights.performance.queue_writes', false)) {
            // TODO: dispatch job
        } else {
            $this->recordRequest($request, $response, $responseTime);
        }

        return $response;
    }

    /**
     * Registra a requisição no banco
     */
    private function recordRequest(Request $request, $response, float $responseTime): void
    {
        try {
            AuthenticatedRequestModel::create([
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => $responseTime,
                'user_agent' => $request->userAgent(),
                'device_type' => $this->detectDeviceType($request),
                // created_at é preenchido automaticamente pelo Laravel
            ]);
        } catch (\Exception $e) {
            // Log erro mas não falha request
            \Log::warning('Failed to track authenticated request', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
        }
    }

    /**
     * Detecta tipo de device (mobile, tablet, desktop)
     */
    private function detectDeviceType(Request $request): string
    {
        if (! config('insights.device_detection.enabled', true)) {
            return 'unknown';
        }

        $userAgent = $request->userAgent();

        if (str_contains(strtolower($userAgent), 'mobile')) {
            return 'mobile';
        }

        if (str_contains(strtolower($userAgent), 'tablet')) {
            return 'tablet';
        }

        return 'desktop';
    }
}
