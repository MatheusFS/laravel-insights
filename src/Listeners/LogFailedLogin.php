<?php

namespace MatheusFS\Laravel\Insights\Listeners;

use Illuminate\Auth\Events\Failed;
use MatheusFS\Laravel\Insights\Models\User\Login as LoginModel;

/**
 * LogFailedLogin Listener
 *
 * Registra tentativas de login falhadas para detecção de anomalias
 */
class LogFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        if (! config('insights.features.logins', true)) {
            return;
        }

        try {
            LoginModel::create([
                // v1.0 fields (compatibilidade)
                'guard' => $event->guard ?? 'web',
                'user_id' => null, // Login falhou
                'ip_address' => request()->ip(),
                'browser' => request()->userAgent(),
                // v1.1+ fields (se migration v1.1 rodou)
                'email' => $event->credentials['email'] ?? 'unknown',
                'success' => false,
                'failure_reason' => $this->determineFailureReason($event),
                'device_type' => $this->detectDeviceType(request()->userAgent()),
                // created_at é preenchido automaticamente pelo Laravel
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to log failed login', [
                'error' => $e->getMessage(),
                'email' => $event->credentials['email'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Determina razão da falha
     */
    private function determineFailureReason(Failed $event): string
    {
        // Por padrão, Laravel não expõe razão exata (security by obscurity)
        // Mas podemos inferir alguns casos
        if (! isset($event->credentials['email'])) {
            return 'missing_email';
        }

        if (! isset($event->credentials['password'])) {
            return 'missing_password';
        }

        // Razão genérica (user não existe ou senha errada)
        return 'invalid_credentials';
    }

    /**
     * Detecta tipo de device
     */
    private function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent || ! config('insights.device_detection.enabled', true)) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'mobile')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'tablet')) {
            return 'tablet';
        }

        return 'desktop';
    }
}
