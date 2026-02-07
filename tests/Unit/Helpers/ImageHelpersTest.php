<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit\Helpers;

use MatheusFS\Laravel\Insights\Helpers\LogoPath;
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;
use PHPUnit\Framework\TestCase;

/**
 * Testes para Image Helpers (LogoPath, EmojiPath)
 * 
 * Validam que os helpers retornam paths/URIs válidos para DOMPDF 3.1+
 * EmojiPath consolidou IconGenerator para usar apenas emojis PNG
 */
class ImageHelpersTest extends TestCase
{
    /**
     * @test
     * @group image-helpers
     */
    public function test_logo_path_exists(): void
    {
        $this->assertTrue(LogoPath::exists());
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_logo_path_returns_file_uri(): void
    {
        $uri = LogoPath::getUri();
        
        $this->assertStringStartsWith('file://', $uri);
        $this->assertStringEndsWith('.png', $uri);
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_logo_path_returns_absolute_path(): void
    {
        $path = LogoPath::getPath();
        
        $this->assertStringStartsWith('/', $path);
        $this->assertStringEndsWith('icone_regular.png', $path);
        $this->assertTrue(file_exists($path));
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_logo_dimensions_valid(): void
    {
        [$width, $height] = LogoPath::dimensions();
        
        $this->assertGreaterThan(0, $width);
        $this->assertGreaterThan(0, $height);
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_emoji_common_returns_array(): void
    {
        $common = EmojiPath::common();
        
        $this->assertIsArray($common);
        $this->assertNotEmpty($common);
        $this->assertArrayHasKey('check', $common);
        $this->assertArrayHasKey('fail', $common);
        $this->assertArrayHasKey('warning', $common);
        // Ícones coloridos do PDF
        $this->assertArrayHasKey('blue_info', $common);
        $this->assertArrayHasKey('red_dot', $common);
        $this->assertArrayHasKey('green_check', $common);
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_emoji_by_name_check(): void
    {
        $checkUri = EmojiPath::byName('check');
        
        if ($checkUri !== null) {
            // Emoji foi baixado
            $this->assertNotEmpty($checkUri);
            $this->assertTrue(
                file_exists($checkUri) || strpos($checkUri, 'data:') === 0,
                "Emoji path must be file or base64: {$checkUri}"
            );
        }
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_emoji_get_uri_valid_format(): void
    {
        $uri = EmojiPath::getUri('2714');
        
        $this->assertTrue(
            strpos($uri, 'file://') === 0 || strpos($uri, 'data:') === 0,
            "Emoji URI must be file:// or data: format, got: {$uri}"
        );
    }

    /**
     * @test
     * @group image-helpers
     * Testa o novo método getIconArray() que consolidou IconGenerator
     */
    public function test_emoji_get_icon_array_pdf_icons(): void
    {
        $icons = EmojiPath::getIconArray();
        
        $this->assertIsArray($icons);
        // Deve ter os 5 ícones do PDF
        $this->assertArrayHasKey('blue_info', $icons);
        $this->assertArrayHasKey('orange_warning', $icons);
        $this->assertArrayHasKey('red_dot', $icons);
        $this->assertArrayHasKey('green_check', $icons);
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_emoji_icon_array_values_are_uris(): void
    {
        $icons = EmojiPath::getIconArray();
        
        foreach ($icons as $name => $uri) {
            // URI deve ser file:// ou vazio (se emoji não existe)
            $this->assertTrue(
                strpos($uri, 'file://') === 0 || $uri === '',
                "Icon {$name} must be file:// URI or empty, got: {$uri}"
            );
        }
    }
    public function test_logo_base64_format(): void
    {
        $base64 = LogoPath::getBase64();
        
        $this->assertStringStartsWith('data:image/', $base64);
        $this->assertStringContains(';base64,', $base64);
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_emoji_set_source_twemoji(): void
    {
        EmojiPath::setSource('twemoji');
        
        // Should not throw exception
        $this->assertTrue(true);
    }

    /**
     * @test
     * @group image-helpers
     */
    public function test_emoji_invalid_source_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        EmojiPath::setSource('invalid_source');
    }
}
