<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Asset\GameAssetBuilder;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class GameAssetBuilderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function testPngAndJpegAssetsAreConvertedToMatchingWebpPaths(): void
    {
        $cwebp = $this->findCwebp();
        if ($cwebp === null) {
            self::markTestSkipped('cwebp is not installed.');
        }

        $source = $this->temporaryDirectory('asset-source-');
        $output = $this->temporaryDirectory('asset-output-');
        mkdir($source . '/events');
        file_put_contents($source . '/banner.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
        file_put_contents($source . '/events/card.jpg', base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gOTAK/9sAQwADAgIDAgIDAwMDBAMDBAUIBQUEBAUKBwcGCAwKDAwLCgsLDQ4SEA0OEQ4LCxAWEBETFBUVFQwPFxgWFBgSFBUU/9sAQwEDBAQFBAUJBQUJFA0LDRQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQU/8AAEQgAAgACAwERAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A8vr4I/uM/9k=',
            true,
        ));

        $count = (new GameAssetBuilder($cwebp))->build($source, $output);

        self::assertSame(2, $count);
        self::assertFileExists($output . '/banner.webp');
        self::assertFileExists($output . '/events/card.webp');
        self::assertSame(0644, fileperms($output . '/banner.webp') & 0777);
        self::assertSame(0644, fileperms($output . '/events/card.webp') & 0777);
        $banner = file_get_contents($output . '/banner.webp');
        $card = file_get_contents($output . '/events/card.webp');
        self::assertIsString($banner);
        self::assertIsString($card);
        self::assertStringStartsWith('RIFF', $banner);
        self::assertSame('WEBP', substr($banner, 8, 4));
        self::assertStringStartsWith('RIFF', $card);
        self::assertSame('WEBP', substr($card, 8, 4));
    }

    public function testDuplicateOutputPathsAreRejectedBeforeConversion(): void
    {
        $source = $this->temporaryDirectory('asset-source-');
        $output = $this->temporaryDirectory('asset-output-');
        file_put_contents($source . '/banner.png', 'png');
        file_put_contents($source . '/banner.jpg', 'jpg');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('same WebP path');
        (new GameAssetBuilder(PHP_BINARY))->build($source, $output);
    }

    public function testUnsupportedFilesAreRejected(): void
    {
        $source = $this->temporaryDirectory('asset-source-');
        $output = $this->temporaryDirectory('asset-output-');
        file_put_contents($source . '/notes.txt', 'not an image');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported game asset');
        (new GameAssetBuilder(PHP_BINARY))->build($source, $output);
    }

    private function findCwebp(): ?string
    {
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cwebp';
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function temporaryDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($path, 0700));
        $this->temporaryDirectories[] = $path;
        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
