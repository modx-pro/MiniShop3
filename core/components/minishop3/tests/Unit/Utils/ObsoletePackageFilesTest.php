<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\ObsoletePackageFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ObsoletePackageFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ms3-obsolete-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($dir, 0777, true));
        $resolved = realpath($dir);
        self::assertNotFalse($resolved);
        $this->root = $resolved;
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    #[DataProvider('rejectedRelativePaths')]
    public function testResolveUnderRootRejectsTraversal(string $relative): void
    {
        self::assertNull(ObsoletePackageFiles::resolveUnderRoot($this->root, $relative));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function rejectedRelativePaths(): iterable
    {
        yield 'parent segment' => ['../Autocomplete.php'];
        yield 'nested parent' => ['src/Processors/../../etc/passwd'];
        yield 'absolute unix' => ['/etc/passwd'];
        yield 'absolute windows' => ['C:/Windows/system.ini'];
        yield 'empty' => [''];
        yield 'dot only' => ['.'];
        yield 'null byte' => ["src/\0Processors/Autocomplete.php"];
    }

    public function testResolveUnderRootKeepsNestedFile(): void
    {
        $resolved = ObsoletePackageFiles::resolveUnderRoot(
            $this->root,
            'src/Processors/Product/Autocomplete.php',
        );

        self::assertSame(
            $this->root . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Processors'
            . DIRECTORY_SEPARATOR . 'Product'
            . DIRECTORY_SEPARATOR . 'Autocomplete.php',
            $resolved,
        );
    }

    public function testPurgeDeletesExistingFileAndSkipsMissing(): void
    {
        $dir = $this->root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Processors';
        self::assertTrue(mkdir($dir, 0777, true));
        $file = $dir . DIRECTORY_SEPARATOR . 'Autocomplete.php';
        self::assertTrue(file_put_contents($file, '<?php') !== false);

        $result = ObsoletePackageFiles::purge($this->root, [
            'src/Processors/Autocomplete.php',
            'src/Processors/Missing.php',
        ]);

        self::assertFileDoesNotExist($file);
        self::assertSame(['src/Processors/Autocomplete.php'], $result['removed']);
        self::assertSame(['src/Processors/Missing.php'], $result['skipped']);
        self::assertSame([], $result['rejected']);
        self::assertSame([], $result['kept']);
    }

    public function testPurgeKeepsPathsStillListedInFrontendAssets(): void
    {
        $dir = $this->root . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'web'
            . DIRECTORY_SEPARATOR . 'modules';
        self::assertTrue(mkdir($dir, 0777, true));
        $form = $dir . DIRECTORY_SEPARATOR . 'form.js';
        $cart = $dir . DIRECTORY_SEPARATOR . 'cart.js';
        self::assertTrue(file_put_contents($form, 'form') !== false);
        self::assertTrue(file_put_contents($cart, 'cart') !== false);

        $result = ObsoletePackageFiles::purge(
            $this->root,
            ['js/web/modules/form.js', 'js/web/modules/cart.js'],
            ['js/web/modules/form.js'],
        );

        self::assertFileExists($form);
        self::assertFileDoesNotExist($cart);
        self::assertSame(['js/web/modules/cart.js'], $result['removed']);
        self::assertSame(['js/web/modules/form.js'], $result['kept']);
    }

    public function testIsReferencedByFrontendAssetsMatchesPlaceholdersAndEscapedSlashes(): void
    {
        $setting = '["[[+jsUrl]]web\\/modules\\/form.js","[[+jsUrl]]web/ms3.js"]';
        $placeholders = [
            'jsUrl' => '/assets/components/minishop3/js/',
            'cssUrl' => '/assets/components/minishop3/css/',
            'assetsUrl' => '/assets/components/minishop3/',
        ];

        self::assertTrue(ObsoletePackageFiles::isReferencedByFrontendAssets(
            'js/web/modules/form.js',
            $setting,
            $placeholders,
        ));
        self::assertFalse(ObsoletePackageFiles::isReferencedByFrontendAssets(
            'js/web/modules/cart.js',
            $setting,
            $placeholders,
        ));
    }

    public function testPurgeRejectsSymlinkOutsideRoot(): void
    {
        $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ms3-obsolete-outside-' . bin2hex(random_bytes(4));
        self::assertTrue(file_put_contents($outside, 'secret') !== false);
        $link = $this->root . DIRECTORY_SEPARATOR . 'escape.php';
        if (!@symlink($outside, $link)) {
            @unlink($outside);
            self::markTestSkipped('symlink() is not available');
        }

        $result = ObsoletePackageFiles::purge($this->root, ['escape.php']);

        self::assertFileExists($outside);
        self::assertSame(['escape.php'], $result['rejected']);
        self::assertSame([], $result['removed']);

        @unlink($link);
        @unlink($outside);
    }

    public function testLoadReadsCoreAndAssetsLists(): void
    {
        $config = $this->root . DIRECTORY_SEPARATOR . 'list.php';
        self::assertTrue(file_put_contents(
            $config,
            "<?php\nreturn ['core' => ['src/Processors/Product/Autocomplete.php'], 'assets' => ['js/gone.js']];\n",
        ) !== false);

        self::assertSame(
            [
                'core' => ['src/Processors/Product/Autocomplete.php'],
                'assets' => ['js/gone.js'],
            ],
            ObsoletePackageFiles::load($config),
        );
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full) && !is_link($full)) {
                $this->removeTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
