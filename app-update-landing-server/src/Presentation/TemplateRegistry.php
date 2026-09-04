<?php

declare(strict_types=1);

namespace App\Presentation;

use RuntimeException;

final class TemplateRegistry
{
    /** @var array<string, string> */
    private array $templates;

    public function __construct(string $templateDirectory)
    {
        $this->templates = [
            'event-update' => $templateDirectory . '/event-update.php',
        ];
    }

    public function pathFor(string $template): string
    {
        $path = $this->templates[$template] ?? null;
        if ($path === null || !is_file($path)) {
            throw new RuntimeException('Template is unavailable.');
        }
        return $path;
    }
}
