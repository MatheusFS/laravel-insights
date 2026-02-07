<?php

namespace MatheusFS\Laravel\Insights\Helpers;

use Illuminate\Support\Facades\Storage;

/**
 * Icon Generator Helper
 * 
 * Generates PNG icon files for PDF rendering (Dompdf compatibility).
 * Icons are cached in storage/app/pdf-icons/ to avoid regeneration.
 */
class IconGenerator
{
    /**
     * Cores disponíveis (RGB)
     */
    private static array $colors = [
        'blue'     => [25, 118, 210],
        'red'      => [211, 47, 47],
        'orange'   => [245, 124, 0],
        'yellow'   => [251, 192, 45],
        'green'    => [56, 142, 60],
        'gray'     => [97, 97, 97],
        'purple'   => [156, 39, 176],
        'cyan'     => [0, 172, 193],
        'pink'     => [233, 30, 99],
        'teal'     => [0, 128, 128],
    ];

    /**
     * Get absolute path to icon file
     * 
     * Generates the PNG if it doesn't exist, then returns the file path.
     * 
     * @param string $colorName Color name (e.g., 'blue')
     * @param string $type Icon type: 'dot', 'square', 'triangle', 'check', 'x', 'warning', 'info'
     * @return string Absolute file path to the icon PNG
     */
    public static function getPath(string $colorName, string $type = 'dot'): string
    {
        $disk = Storage::disk('local');
        $filename = "pdf-icons/{$colorName}_{$type}.png";
        
        // Generate if doesn't exist
        if (!$disk->exists($filename)) {
            static::generateIcon($colorName, $type);
        }
        
        // Return absolute path
        return storage_path("app/{$filename}");
    }

    /**
     * Get public URL to icon file
     * 
     * Useful for web display or if icons need to be accessible via HTTP.
     * 
     * @param string $colorName Color name
     * @param string $type Icon type
     * @return string Public URL or Storage URL
     */
    public static function getUrl(string $colorName, string $type = 'dot'): string
    {
        $path = static::getPath($colorName, $type);
        
        // Try to get Storage URL if available
        if (method_exists(Storage::class, 'url')) {
            $relPath = "pdf-icons/{$colorName}_{$type}.png";
            return Storage::url($relPath);
        }
        
        return $path;
    }

    /**
     * Generate a single icon PNG
     */
    private static function generateIcon(string $colorName, string $type): void
    {
        if (!isset(static::$colors[$colorName])) {
            throw new \InvalidArgumentException("Unknown color: {$colorName}");
        }

        $rgb = static::$colors[$colorName];
        $disk = Storage::disk('local');

        // Create directory if it doesn't exist
        if (!$disk->exists('pdf-icons')) {
            $disk->makeDirectory('pdf-icons');
        }

        $im = imagecreatetruecolor(12, 12);
        imagesavealpha($im, true);
        $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans);
        $color = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);

        match ($type) {
            'dot' => static::drawDot($im, $color),
            'square' => static::drawSquare($im, $color),
            'triangle' => static::drawTriangle($im, $color),
            'check' => static::drawCheck($im, $color),
            'x' => static::drawX($im, $color),
            'warning' => static::drawWarning($im, $color),
            'info' => static::drawInfo($im, $color),
            default => throw new \InvalidArgumentException("Unknown icon type: {$type}"),
        };

        // Save to Storage
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        $filename = "pdf-icons/{$colorName}_{$type}.png";
        $disk->put($filename, $png);
    }

    private static function drawDot($im, $color): void
    {
        imagefilledellipse($im, 6, 6, 10, 10, $color);
    }

    private static function drawSquare($im, $color): void
    {
        imagefilledrectangle($im, 2, 2, 10, 10, $color);
    }

    private static function drawTriangle($im, $color): void
    {
        $points = [6, 1, 10, 10, 2, 10];
        imagefilledpolygon($im, $points, 3, $color);
    }

    private static function drawCheck($im, $color): void
    {
        imagesetthickness($im, 2);
        imageline($im, 3, 6, 5, 9, $color);
        imageline($im, 5, 9, 9, 2, $color);
    }

    private static function drawX($im, $color): void
    {
        imagesetthickness($im, 2);
        imageline($im, 2, 2, 10, 10, $color);
        imageline($im, 10, 2, 2, 10, $color);
    }

    private static function drawWarning($im, $color): void
    {
        // Barra vertical + ponto
        imagefilledrectangle($im, 5, 2, 7, 8, $color);
        imagefilledellipse($im, 6, 10, 2, 2, $color);
    }

    private static function drawInfo($im, $color): void
    {
        // Ponto superior + barra inferior
        imagefilledellipse($im, 6, 3, 2, 2, $color);
        imagefilledrectangle($im, 5, 5, 7, 10, $color);
    }

    /**
     * Get icon array for use in Blade templates
     * 
     * Generates all icons and returns array with file paths for easy use.
     * 
     * @return array Array with keys like 'blue_dot', 'red_warning', etc.
     */
    public static function getIconArray(): array
    {
        $icons = [];
        $types = ['dot', 'square', 'triangle', 'check', 'x', 'warning', 'info'];

        foreach (static::$colors as $colorName => $rgb) {
            foreach ($types as $type) {
                $path = static::getPath($colorName, $type);
                $icons["{$colorName}_{$type}"] = $path;
            }
        }

        return $icons;
    }

    /**
     * Clean up all generated icons
     * Useful for development or when regenerating is needed
     */
    public static function cleanup(): void
    {
        Storage::disk('local')->deleteDirectory('pdf-icons');
    }
}
