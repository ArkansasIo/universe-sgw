<?php

declare(strict_types=1);

namespace StellarDominion\Core;

/**
 * Minimal router registry.
 *
 * Supports:
 *   $router->get('/api/player', [PlayerController::class, 'show']);
 *   $router->group('/api', function ($r) { ... });
 *   $router->middleware(callable)   // runs before every matched route
 *   $router->handle(Request $request): Response
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|string, middleware:array}> */
    private array $routes = [];
    private array $globalMiddleware = [];
    private string $prefix = '';

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->prefix . $pattern,
            'handler' => $handler,
            'middleware' => [],
        ];
    }

    public function get(string $pattern, callable|array $handler): void { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, callable|array $handler): void { $this->add('POST', $pattern, $handler); }
    public function put(string $pattern, callable|array $handler): void { $this->add('PUT', $pattern, $handler); }
    public function patch(string $pattern, callable|array $handler): void { $this->add('PATCH', $pattern, $handler); }
    public function delete(string $pattern, callable|array $handler): void { $this->add('DELETE', $pattern, $handler); }

    public function group(string $prefix, callable $register): void
    {
        $previous = $this->prefix;
        $this->prefix = $previous . $prefix;
        $register($this);
        $this->prefix = $previous;
    }

    public function middleware(callable $mw): void
    {
        $this->globalMiddleware[] = $mw;
    }

    public function handle(Request $request): Response
    {
        $path = rtrim($request->path(), '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $regex = $this->compilePattern($route['pattern']);
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                foreach ($this->globalMiddleware as $mw) {
                    $result = $mw($request);
                    if ($result instanceof Response) {
                        return $result;
                    }
                }

                return $this->resolveHandler($route['handler'], $request, $params);
            }
        }

        return Response::make(404)->json(['status' => 'error', 'message' => 'Route not found']);
    }

    private function compilePattern(string $pattern): string
    {
        $pattern = rtrim($pattern, '/');
        if ($pattern === '') {
            $pattern = '/';
        }
        // Convert {param} and :param to named capture groups
        $pattern = preg_replace_callback('/\{(\w+)\}/', fn ($m) => "(?P<{$m[1]}>[^/]+)", $pattern);
        $pattern = preg_replace_callback('/:(\w+)/', fn ($m) => "(?P<{$m[1]}>[^/]+)", $pattern);
        return '#^' . $pattern . '$#';
    }

    private function resolveHandler(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = is_object($class) ? $class : new $class();
            $result = $controller->$method($request, ...array_values($params));
        } else {
            $result = $handler($request, ...array_values($params));
        }

        if ($result instanceof Response) {
            return $result;
        }

        // Plain values are wrapped as a success JSON response
        return Response::make()->success($result);
    }
}
