<?php

namespace MatheusFS\Laravel\Insights\Listeners;

use Illuminate\Auth\Events\Login;
use MatheusFS\Laravel\Insights\Models\User\Login as LoginModel;

/**
 * LogUserLogin Listener
 *
 * Registra tentativas de login bem-sucedidas
 */
class LogUserLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! config('insights.features.logins', true)) {
            return;
        }

        try {
            LoginModel::create([
                // v1.0 fields (compatibilidade)
                'guard' => $event->guard ?? 'web',
                'user_id' => $event->user->id,
                'ip_address' => request()->ip(),
                'browser' => request()->userAgent(),
                // v1.1+ fields (se migration v1.1 rodou)
                'email' => $event->user->email,
                'success' => true,
                'device_type' => $this->detectDeviceType(request()->userAgent()),
                // created_at é preenchido automaticamente pelo Laravel
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to log user login', [
                'error' => $e->getMessage(),
                'user_id' => $event->user->id,
            ]);
        }
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
