<?php

declare(strict_types=1);

namespace App\Asset;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class GameAssetBuilder
{
    private const SUPPORTED_EXTENSIONS = ['jpeg', 'jpg', 'png'];

    public function __construct(
        private readonly string $cwebpBinary,
        private readonly int $quality = 82,
    ) {
        if (!is_file($this->cwebpBinary) || !is_executable($this->cwebpBinary)) {
            throw new RuntimeException('cwebp is unavailable. Install the WebP command-line tools first.');
        }

        if ($this->quality < 0 || $this->quality > 100) {
            throw new RuntimeException('WebP quality must be between 0 and 100.');
        }
    }

    public function build(string $sourceDirectory, string $outputDirectory): int
    {
        if (!is_dir($sourceDirectory)) {
            return 0;
        }

        $sourceRoot = realpath($sourceDirectory);
        if ($sourceRoot === false) {
            throw new RuntimeException('Game asset source directory is unavailable.');
        }

        /** @var array<string, array{source: string, relative: string}> $assets */
        $assets = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Game asset source directory must not contain symbolic links.');
            }

            if (!$file->isFile()) {
                continue;
            }

            if (str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                throw new RuntimeException(sprintf('Unsupported game asset: %s', $file->getFilename()));
            }

            $sourcePath = $file->getPathname();
            $relativeSource = substr($sourcePath, strlen($sourceRoot) + 1);
            $relativeOutput = substr($relativeSource, 0, -strlen($extension)) . 'webp';
            $collisionKey = strtolower(str_replace('\\', '/', $relativeOutput));
            if (isset($assets[$collisionKey])) {
                throw new RuntimeException(sprintf('Multiple source images produce the same WebP path: %s', $relativeOutput));
            }

            $assets[$collisionKey] = [
                'source' => $sourcePath,
                'relative' => $relativeOutput,
            ];
        }

        ksort($assets);
        foreach ($assets as $asset) {
            $targetPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $asset['relative']);
            $targetDirectory = dirname($targetPath);
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException('Unable to create the generated asset directory.');
            }

            $temporaryPath = tempnam($targetDirectory, '.webp-');
            if ($temporaryPath === false) {
                throw new RuntimeException('Unable to prepare a generated WebP file.');
            }

            try {
                $this->convert($asset['source'], $temporaryPath, $asset['relative']);
                if (!chmod($temporaryPath, 0644)) {
                    throw new RuntimeException(sprintf('Unable to set generated asset permissions: %s', $asset['relative']));
                }
                if (!rename($temporaryPath, $targetPath)) {
                    throw new RuntimeException(sprintf('Unable to publish generated asset: %s', $asset['relative']));
                }
            } finally {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }

        return count($assets);
    }

    private function convert(string $sourcePath, string $targetPath, string $relativeOutput): void
    {
        $process = proc_open(
            [
                $this->cwebpBinary,
                '-quiet',
                '-q',
                (string) $this->quality,
                '-metadata',
                'none',
                $sourcePath,
                '-o',
                $targetPath,
            ],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Unable to start WebP conversion: %s', $relativeOutput));
        }

        stream_get_contents($pipes[1]);
        $errorOutput = trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !is_file($targetPath) || filesize($targetPath) === 0) {
            $detail = $errorOutput === '' ? '' : ' ' . $errorOutput;
            throw new RuntimeException(sprintf('WebP conversion failed for %s.%s', $relativeOutput, $detail));
        }
    }
}
