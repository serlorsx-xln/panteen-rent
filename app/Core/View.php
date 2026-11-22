<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP templates. A view sets sections with $this-less helpers:
 * the rendered view body becomes $content inside the layout.
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function make(string $view, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view");
        }
        ob_start();
        try {
            // The template runs in a scope of its own, so a view may name a variable anything, $data and $view included.
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                include $__file;
            })($file, $data);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layouts/shop'): void
    {
        $content = self::make($view, $data);
        echo self::make($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $view, array $data = []): void
    {
        echo self::make('partials/' . $view, $data);
    }
}
