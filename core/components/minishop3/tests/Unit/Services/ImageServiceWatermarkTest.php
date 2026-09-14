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
}
