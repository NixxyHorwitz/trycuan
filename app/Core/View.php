<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'public', int $status = 200): void
    {
        http_response_code($status);
        $data += ['title' => 'TryCuan', 'user' => Auth::user(), 'flash' => Session::takeFlash(), 'errors' => [], 'old' => [], 'seo' => null, 'active' => ''];
        $content = self::capture($template, $data);
        echo self::capture('layouts/' . $layout, $data + ['content' => $content]);
    }

    private static function capture(string $__template, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require ROOT . '/app/Views/' . $__template . '.php';
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
