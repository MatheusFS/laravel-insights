<?php

namespace MatheusFS\Laravel\Insights\Helpers;

/**
 * Emoji Path Helper
 * 
 * Gerencia referências a emojis PNG do projeto.
 * Suporta Twemoji (MIT) ou Noto Color Emoji (Apache 2.0).
 * 
 * Emojis armazenados em: resources/emojis/ ou public/emojis/
 */
class EmojiPath
{
    /**
     * Emoji source (twemoji ou noto)
     */
    private static string $source = 'twemoji';

    /**
     * Base directory for emojis
     */
    private static string $baseDir = 'emojis';

    /**
     * Set emoji source
     * 
     * @param string $source 'twemoji' ou 'noto'
     */
    public static function setSource(string $source): void
    {
        if (!in_array($source, ['twemoji', 'noto'])) {
            throw new \InvalidArgumentException("Invalid emoji source: {$source}. Use 'twemoji' or 'noto'.");
        }
        self::$source = $source;
    }

    /**
     * Get emoji path by unicode codepoint
     * 
     * @param string $codepoint Emoji unicode (e.g., '1f600' for 😀)
     * @return string Absolute file path to emoji PNG
     */
    public static function get(string $codepoint): string
    {
        // Try public folder first
        $publicPath = public_path(self::$baseDir . '/' . self::$source . '/' . $codepoint . '.png');
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // Try resources folder
        $resourcePath = resource_path(self::$baseDir . '/' . self::$source . '/' . $codepoint . '.png');
        if (file_exists($resourcePath)) {
            return $resourcePath;
        }

        // Fallback: return the expected path (may not exist)
        return public_path(self::$baseDir . '/' . self::$source . '/' . $codepoint . '.png');
    }

    /**
     * Get emoji as file:// URI (for DOMPDF)
     * 
     * @param string $codepoint Emoji unicode
     * @return string file:// URI
     */
    public static function getUri(string $codepoint): string
    {
        return 'file://' . self::get($codepoint);
    }

    /**
     * Get emoji as base64 data URI
     * 
     * @param string $codepoint Emoji unicode
     * @return string data:image/png;base64,...
     */
    public static function getBase64(string $codepoint): string
    {
        $path = self::get($codepoint);
        
        if (!file_exists($path)) {
            return '';
        }

        $imageData = file_get_contents($path);
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Check if emoji exists
     * 
     * @param string $codepoint Emoji unicode
     * @return bool
     */
    public static function exists(string $codepoint): bool
    {
        return file_exists(self::get($codepoint));
    }

    /**
     * Get common emoji codepoints
     * Inclui emojis padrão + ícones coloridos para PDFs
     * 
     * @return array [emoji_name => unicode_codepoint]
     */
    public static function common(): array
    {
        return config('emojis.byName', [
            // Emojis padrão
            'check' => '2714',           // ✔️
            'fail' => '274c',            // ❌
            'warning' => '26a0',         // ⚠️
            'info' => '2139',            // ℹ️
            'fire' => '1f525',           // 🔥
            'clock' => '1f550',          // 🕐
            'dot' => '2b55',             // 🔵
            'star' => '2b50',            // ⭐
            'ok' => '1f44c',             // 👌
            'no' => '1f44e',             // 👎
            'yes' => '1f44d',            // 👍
            'alert' => '1f6a8',          // 🚨
            'speed' => '1f4a8',          // 💨
            'perfect' => '1f4af',        // 💯
            'check2' => '2705',          // ✅
            
            // Ícones coloridos (substituem IconGenerator)
            'blue_dot' => '1f535',       // 🔵 Blue dot
            'red_dot' => '1f534',        // 🔴 Red dot
            'orange_warning' => '26a0',  // ⚠️ Orange warning
            'green_check' => '2705',     // ✅ Green check
            'blue_info' => '2139',       // ℹ️ Blue info
        ]);
    }

    /**
     * Get emoji path by name (from common() list)
     * 
     * @param string $name Emoji name
     * @return string|null File path or null if not found
     */
    public static function byName(string $name): ?string
    {
        $common = self::common();
        
        if (!isset($common[$name])) {
            return null;
        }

        return self::get($common[$name]);
    }

    /**
     * Get icon array for PDF (simplificado - usa codepoints diretos)
     * Retorna array com codepoint como chave, file:// URI como valor
     * 
     * @return array [codepoint => file:// URI]  ex: ['2139' => 'file://...', '26a0' => 'file://...']
     */
    public static function getIconArray(): array
    {
        // Ícones principais do PDF - uso direto de codepoints
        $codepoints = [
            '2139',     // ℹ️ Info (blue_info)
            '26a0',     // ⚠️ Warning (orange_warning)
            '1f534',    // 🔴 Red dot
            '1f535',    // 🔵 Blue dot
            '2705',     // ✅ Check (green_check)
        ];

        $icons = [];
        foreach ($codepoints as $codepoint) {
            $path = self::get($codepoint);
            // Retorna file:// URI compatível com DOMPDF
            $icons[$codepoint] = self::exists($codepoint) 
                ? 'file://' . $path 
                : '';
        }

        return $icons;
    }

    /**
     * Set base directory for emoji storage
     * 
     * @param string $dir Directory name (e.g., 'emojis', 'icons')
     */
    public static function setBaseDir(string $dir): void
    {
        self::$baseDir = $dir;
    }
}
