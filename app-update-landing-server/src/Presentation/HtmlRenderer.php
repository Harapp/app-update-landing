<?php

declare(strict_types=1);

namespace App\Presentation;

final class HtmlRenderer
{
    public function __construct(private readonly TemplateRegistry $templates)
    {
    }

    public function render(UpdatePageViewModel $viewModel): string
    {
        $template = $this->templates->pathFor($viewModel->template);
        ob_start();
        require $template;
        return (string) ob_get_clean();
    }
}
