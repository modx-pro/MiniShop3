<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Services\ImageService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class ImageServiceWatermarkTest extends TestCase
{
    private string $baseDir;

    private string $sourcePng;

    private string $watermarkPng;

    /** @var list<array{0: int|string, 1: string}> */
    private array $logs = [];

    protected function setUp(): void
    {
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $this->markTestSkipped('GD or Imagick required for ImageService tests');
        }
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }

        $this->baseDir = sys_get_temp_dir() . '/ms3_wm_' . bin2hex(random_bytes(4));
        mkdir($this->baseDir . '/assets', 0777, true);

        $this->sourcePng = $this->baseDir . '/source.png';
        $this->watermarkPng = $this->baseDir . '/assets/watermark.png';
        $this->writeSolidPng($this->sourcePng, 80, 80, [20, 40, 200]);
        $this->writeSolidPng($this->watermarkPng, 20, 20, [255, 0, 0]);
        $this->logs = [];
    }

    protected function tearDown(): void
    {
        if (!isset($this->baseDir)) {
            return;
        }

        foreach ([$this->sourcePng, $this->watermarkPng] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        if (is_dir($this->baseDir . '/assets')) {
            @rmdir($this->baseDir . '/assets');
        }
        if (is_dir($this->baseDir)) {
            @rmdir($this->baseDir);
        }
    }

    public function testWatermarkChangesThumbnailBytes(): void
    {
        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $base = [
            'width' => 80,
            'height' => 80,
            'mode' => 'cover',
            'format' => 'png',
            'quality' => 90,
        ];
        $plain = $this->thumbnail($service, $base);
        $marked = $this->thumbnail($service, $base + [
            'watermark' => [
                'enabled' => true,
                'path' => 'assets/watermark.png',
                'position' => 'center',
                'offset_x' => 0,
                'offset_y' => 0,
                'opacity' => 100,
            ],
        ]);

        self::assertNotNull($plain);
        self::assertNotNull($marked);
        self::assertNotSame($plain, $marked);
        self::assertSame([], $this->errorMessages());
    }

    public function testTiledWatermarkPositionFillsCanvas(): void
    {
        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $base = [
            'width' => 80,
            'height' => 80,
            'mode' => 'cover',
            'format' => 'png',
            'quality' => 90,
        ];
        $plain = $this->thumbnail($service, $base);
        $center = $this->thumbnail($service, $base + [
            'watermark' => [
                'enabled' => true,
                'path' => 'assets/watermark.png',
                'position' => 'center',
                'opacity' => 25,
            ],
        ]);
        $tiled = $this->thumbnail($service, $base + [
            'watermark' => [
                'enabled' => true,
                'path' => 'assets/watermark.png',
                'position' => 'tile',
                'offset_x' => 0,
                'offset_y' => 0,
                'opacity' => 25,
            ],
        ]);

        self::assertNotNull($plain);
        self::assertNotNull($center);
        self::assertNotNull($tiled);
        self::assertNotSame($plain, $tiled);
        self::assertNotSame($center, $tiled);
        self::assertSame([], $this->errorMessages());

        // Reused mark with baked opacity — far tiles must still differ from plain.
        $plainCorner = $this->pngPixelRgb($plain, 50, 50);
        $tiledCorner = $this->pngPixelRgb($tiled, 50, 50);
        self::assertNotSame($plainCorner, $tiledCorner);
    }

    public function testSemiTransparentWatermarkKeepsClearPixelsOnTransparentSource(): void
    {
        $this->writeTransparentSourceWithBlueCenter($this->sourcePng, 80, 80);
        $this->writeWatermarkWithClearHalf($this->watermarkPng, 40, 20);

        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $out = $this->thumbnail($service, [
            'width' => 80,
            'height' => 80,
            'mode' => 'stretch',
            'format' => 'png',
            'quality' => 90,
            'watermark' => [
                'enabled' => true,
                'path' => 'assets/watermark.png',
                'position' => 'bottom-right',
                'opacity' => 50,
            ],
        ]);

        self::assertNotNull($out);
        self::assertSame([], $this->errorMessages());

        // Transparent half of the logo sits over transparent canvas — must stay fully transparent
        // (not the opaque gray plate from GD imagecopymerge).
        $clear = $this->pngPixelRgba($out, 70, 70);
        self::assertSame(127, $clear[3], 'clear watermark area must remain transparent');

        // Opaque red half of the logo should be semi-transparent red.
        $red = $this->pngPixelRgba($out, 50, 70);
        self::assertLessThan(100, $red[3], 'red watermark half must be semi-transparent');
        self::assertGreaterThan(200, $red[0]);
        self::assertLessThan(40, $red[1]);
        self::assertLessThan(40, $red[2]);
    }

    public function testPhpThumbLegacyPositionsAreRejected(): void
    {
        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $base = [
            'width' => 80,
            'height' => 80,
            'mode' => 'cover',
            'format' => 'png',
            'quality' => 90,
        ];
        $plain = $this->thumbnail($service, $base);

        foreach (['BR', 'C', '*'] as $legacy) {
            $this->logs = [];
            $marked = $this->thumbnail($service, $base + [
                'watermark' => [
                    'enabled' => true,
                    'path' => 'assets/watermark.png',
                    'position' => $legacy,
                    'opacity' => 100,
                ],
            ]);

            self::assertNotNull($marked);
            self::assertSame($plain, $marked, "legacy position {$legacy} must not place overlay");
            $errors = $this->errorMessages();
            self::assertNotEmpty($errors);
            self::assertStringContainsString('Unknown watermark position', $errors[0]);
        }
    }

    public function testUnknownWatermarkPositionIsLoggedAndSkipped(): void
    {
        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $base = [
            'width' => 80,
            'height' => 80,
            'mode' => 'cover',
            'format' => 'png',
            'quality' => 90,
        ];
        $plain = $this->thumbnail($service, $base);
        $this->logs = [];
        $marked = $this->thumbnail($service, $base + [
            'watermark' => [
                'enabled' => true,
                'path' => 'assets/watermark.png',
                'position' => 'nope',
                'opacity' => 50,
            ],
        ]);

        self::assertNotNull($plain);
        self::assertNotNull($marked);
        self::assertSame($plain, $marked);
        $errors = $this->errorMessages();
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Unknown watermark position', $errors[0]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function pngPixelRgb(string $pngBytes, int $x, int $y): array
    {
        $rgba = $this->pngPixelRgba($pngBytes, $x, $y);

        return [$rgba[0], $rgba[1], $rgba[2]];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} RGB + GD alpha (0 opaque … 127 transparent)
     */
    private function pngPixelRgba(string $pngBytes, int $x, int $y): array
    {
        $im = imagecreatefromstring($pngBytes);
        self::assertNotFalse($im);
        $color = imagecolorat($im, $x, $y);
        self::assertNotFalse($color);
        imagedestroy($im);

        return [
            ($color >> 16) & 0xFF,
            ($color >> 8) & 0xFF,
            $color & 0xFF,
            ($color & 0x7F000000) >> 24,
        ];
    }

    public function testLeadingSlashPathResolvesUnderSiteBase(): void
    {
        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $base = [
            'width' => 80,
            'height' => 80,
            'mode' => 'cover',
            'format' => 'png',
            'quality' => 90,
        ];
        $plain = $this->thumbnail($service, $base);
        $marked = $this->thumbnail($service, $base + [
            'watermark' => [
                'enabled' => true,
                'path' => '/assets/watermark.png',
                'position' => 'center',
                'opacity' => '',
            ],
        ]);

        self::assertNotNull($plain);
        self::assertNotNull($marked);
        self::assertNotSame($plain, $marked);
        self::assertSame([], $this->errorMessages());
    }

    public function testMissingWatermarkPathIsLoggedAndThumbnailStillGenerated(): void
    {
        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $out = $this->thumbnail($service, [
            'width' => 40,
            'height' => 40,
            'mode' => 'cover',
            'format' => 'png',
            'watermark' => [
                'enabled' => true,
                'path' => 'assets/missing-watermark.png',
                'position' => 'bottom-right',
                'opacity' => 50,
            ],
        ]);

        self::assertNotNull($out);
        $errors = $this->errorMessages();
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Watermark file not found', $errors[0]);
    }

    public function testDisabledWatermarkIsIgnoredWithoutError(): void
    {
        $service = new ImageService($this->loggingModx(), $this->baseDir);
        $out = $this->thumbnail($service, [
            'width' => 40,
            'height' => 40,
            'format' => 'png',
            'watermark' => [
                'enabled' => false,
                'path' => 'assets/missing-watermark.png',
            ],
        ]);

        self::assertNotNull($out);
        self::assertSame([], $this->errorMessages());
    }

    public function testPathTraversalOutsideBaseIsRejected(): void
    {
        $outside = sys_get_temp_dir() . '/ms3_wm_outside_' . bin2hex(random_bytes(3)) . '.png';
        $this->writeSolidPng($outside, 10, 10, [0, 255, 0]);
        try {
            $service = new ImageService($this->loggingModx(), $this->baseDir);
            $out = $this->thumbnail($service, [
                'width' => 40,
                'height' => 40,
                'format' => 'png',
                'watermark' => [
                    'enabled' => true,
                    'path' => $outside,
                ],
            ]);
            self::assertNotNull($out);
            $errors = $this->errorMessages();
            self::assertNotEmpty($errors);
            self::assertStringContainsString('Watermark file not found or outside site base path', $errors[0]);
        } finally {
            @unlink($outside);
        }
    }

    private function loggingModx(): modX
    {
        $logs = &$this->logs;

        return new class ($logs) extends modX {
            /** @param list<array{0: int|string, 1: string}> $logs */
            public function __construct(private array &$logs)
            {
                parent::__construct();
            }

            public function log($level, $message): void
            {
                $this->logs[] = [$level, (string) $message];
            }
        };
    }

    /**
     * @return list<string>
     */
    private function errorMessages(): array
    {
        $messages = [];
        foreach ($this->logs as [$level, $message]) {
            if ((int) $level === modX::LOG_LEVEL_ERROR) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function thumbnail(ImageService $service, array $options): ?string
    {
        return $service->makeThumbnail(
            ['content' => (string) file_get_contents($this->sourcePng)],
            $options
        );
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function writeSolidPng(string $path, int $width, int $height, array $rgb): void
    {
        $im = imagecreatetruecolor($width, $height);
        self::assertNotFalse($im);
        $color = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        self::assertNotFalse($color);
        imagefilledrectangle($im, 0, 0, $width, $height, $color);
        self::assertTrue(imagepng($im, $path));
        imagedestroy($im);
    }

    private function writeTransparentSourceWithBlueCenter(string $path, int $width, int $height): void
    {
        $im = imagecreatetruecolor($width, $height);
        self::assertNotFalse($im);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
        self::assertNotFalse($clear);
        imagefilledrectangle($im, 0, 0, $width, $height, $clear);
        imagealphablending($im, true);
        $blue = imagecolorallocate($im, 0, 0, 255);
        self::assertNotFalse($blue);
        $inset = (int) ($width / 4);
        imagefilledrectangle($im, $inset, $inset, $width - $inset, $height - $inset, $blue);
        self::assertTrue(imagepng($im, $path));
        imagedestroy($im);
    }

    private function writeWatermarkWithClearHalf(string $path, int $width, int $height): void
    {
        $im = imagecreatetruecolor($width, $height);
        self::assertNotFalse($im);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
        self::assertNotFalse($clear);
        imagefilledrectangle($im, 0, 0, $width, $height, $clear);
        $red = imagecolorallocatealpha($im, 255, 0, 0, 0);
        self::assertNotFalse($red);
        imagefilledrectangle($im, 0, 0, (int) ($width / 2), $height, $red);
        self::assertTrue(imagepng($im, $path));
        imagedestroy($im);
    }
}
