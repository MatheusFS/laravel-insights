<?php

namespace MatheusFS\Laravel\Insights\Helpers;

use Illuminate\Support\Facades\File;

/**
 * Logo Path Helper
 * 
 * Centraliza o uso dos logos da Continuo Tecnologia em todo o projeto.
 * Garante uso consistente de caminhos absolutos e compatibilidade com DOMPDF.
 * 
 * Logos disponíveis:
 * - icone_regular.png (ícone apenas)
 * - logo_fundo_claro.png (logo completo para fundos claros)
 * - logo_fundo_escuro.png (logo completo para fundos escuros)
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
     * @param string $variant Logo variant: 'icon', 'light', 'dark'
     * @return string file:// URI to logo (absolute path with 3 slashes: file:///path)
     */
    public static function getUri(string $variant = 'icon'): string
    {
        $path = self::getPath($variant);
        // Ensure absolute path and use 3 slashes for file:// (file:///path/to/file)
        return 'file://' . (strpos($path, '/') === 0 ? '' : '/') . $path;
    }

    /**
     * Get absolute filesystem path
     * 
     * @param string $variant Logo variant: 'icon', 'light', 'dark'
     * @return string Absolute path to logo
     */
    public static function getPath(string $variant = 'icon'): string
    {
        $filename = self::getFilename($variant);
        
        // Try package assets first
        $packagePath = realpath(__DIR__ . '/../../resources/assets/' . $filename);
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
        return __DIR__ . '/../../resources/assets/' . $filename;
    }

    /**
     * Get filename for logo variant
     * 
     * @param string $variant 'icon', 'light', 'dark'
     * @return string Filename
     */
    protected static function getFilename(string $variant): string
    {
        return match($variant) {
            'light' => 'logo_fundo_claro.png',
            'dark' => 'logo_fundo_escuro.png',
            'icon' => 'icone_regular.png',
            default => 'icone_regular.png',
        };
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
     * @param string $variant Logo variant: 'icon', 'light', 'dark'
     * @return string data:image/png;base64,...
     */
    public static function getBase64(string $variant = 'icon'): string
    {
        $path = self::getPath($variant);
        
        if (!file_exists($path)) {
            return '';
        }

        $imageData = file_get_contents($path);
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Get URI optimized for PDF generation (base64 data URI)
     * 
     * DOMPDF has issues with file:// protocol across symlinks,
     * so we return base64 data URI by default for PDFs.
     * 
     * @param string $variant Logo variant: 'icon', 'light', 'dark'
     * @return string data:image/png;base64,...
     */
    public static function getPdfUri(string $variant = 'icon'): string
    {
        return self::getBase64($variant);
    }

    /**
     * Get logo for light background (shorthand)
     * 
     * @return string data:image/png;base64,...
     */
    public static function getPdfUriLight(): string
    {
        return self::getPdfUri('light');
    }

    /**
     * Get logo for dark background (shorthand)
     * 
     * @return string data:image/png;base64,...
     */
    public static function getPdfUriDark(): string
    {
        return self::getPdfUri('dark');
    }

    /**
     * Get logo dimensions
     * 
     * @param string $variant Logo variant: 'icon', 'light', 'dark'
     * @return array|null ['width' => int, 'height' => int] or null if not found
     */
    public static function dimensions(string $variant = 'icon'): ?array
    {
        $path = self::getPath($variant);
        
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
