<?php

namespace MatheusFS\Laravel\Insights\Helpers;

use Illuminate\Support\Facades\File;

/**
 * Logo Path Helper
 * 
 * Centraliza o uso do logo da Continuo Tecnologia em todo o projeto.
 * Garante uso consistente de caminhos absolutos e compatibilidade com DOMPDF.
 * 
 * Logo oficial: assets/icone_regular.png
 */
class LogoPath
{
    /**
     * Get absolute path to logo PNG
     * 
     * @return string Absolute file path to logo (file:// protocol for DOMPDF)
     */
    public static function get(): string
    {
        return self::getPath();
    }

    /**
     * Get path with file:// protocol (ideal for DOMPDF)
     * 
     * @return string file:// URI to logo (absolute path with 3 slashes: file:///path)
     */
    public static function getUri(): string
    {
        $path = self::getPath();
        // Ensure absolute path and use 3 slashes for file:// (file:///path/to/file)
        return 'file://' . (strpos($path, '/') === 0 ? '' : '/') . $path;
    }

    /**
     * Get absolute filesystem path
     * 
     * @return string Absolute path to logo
     */
    public static function getPath(): string
    {
        // Try package assets first
        $packagePath = realpath(__DIR__ . '/../../resources/assets/icone_regular.png');
        if ($packagePath && file_exists($packagePath)) {
            return $packagePath;
        }

        // Fallback: try public folder (if used in consuming app)
        try {
            $publicPath = public_path('images/logo.png');
            if (file_exists($publicPath)) {
                return realpath($publicPath) ?: $publicPath;
            }
        } catch (\Exception $e) {
            // public_path() may not be available in all contexts
        }

        // Last resort: return the original path (may not exist)
        return __DIR__ . '/../../resources/assets/icone_regular.png';
    }

    /**
     * Check if logo exists
     * 
     * @return bool
     */
    public static function exists(): bool
    {
        return file_exists(self::getPath());
    }

    /**
     * Get logo as data URI (base64)
     * 
     * Útil para casos onde file:// não é suportado.
     * 
     * @return string data:image/png;base64,...
     */
    public static function getBase64(): string
    {
        $path = self::getPath();
        
        if (!file_exists($path)) {
            return '';
        }

        $imageData = file_get_contents($path);
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Get logo dimensions
     * 
     * @return array|null ['width' => int, 'height' => int] or null if not found
     */
    public static function dimensions(): ?array
    {
        $path = self::getPath();
        
        if (!file_exists($path)) {
            return null;
        }

        $size = getimagesize($path);
        
        if ($size === false) {
            return null;
        }

        return [
            'width' => $size[0],
            'height' => $size[1],
        ];
    }
}
