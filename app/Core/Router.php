<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router: '/costume/{id}' style patterns, GET/POST, middleware per route.
 */
final class Router
{
    /** @var list<array{method: string, path: string, regex: string, handler: callable|array{class-string, string}, middleware: list<string>}> */
    private array $routes = [];

    /**
     * @param callable|array{class-string, string} $handler
     * @param list<string> $middleware
     */
    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param callable|array{class-string, string} $handler
     * @param list<string> $middleware
     */
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param callable|array{class-string, string} $handler
     * @param list<string> $middleware
     */
    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/') . '$#u';
        $this->routes[] = compact('method', 'path', 'regex', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        // HEAD is a GET without the body (link checkers and uptime monitors send it); PHP drops the body itself
        $method = $method === 'HEAD' ? 'GET' : $method;
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['middleware'] as $mw) {
                Middleware::handle($mw);
            }
            if ($method === 'POST') {
                Csrf::verifyRequest();
            }
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action(...array_values($params));
            } else {
                $handler(...array_values($params));
            }
            return;
        }
        if ($allowed) {
            http_response_code(405);
            View::render('errors/error', ['code' => 405, 'title' => 'ไม่รองรับวิธีเรียกนี้', 'message' => 'หน้านี้ไม่รองรับคำขอแบบนี้ ลองกลับไปหน้าก่อนแล้วทำใหม่อีกครั้ง'], 'layouts/shop');
            return;
        }
        abort(404);
    }
}
