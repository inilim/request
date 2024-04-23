<?php

namespace Inilim\Request;

use Inilim\QueryBuild\QueryBuild;

class Request
{
    protected ?array $headers = null;
    protected ?QueryBuild $query_build = null;
    protected ?string $method = null;
    protected ?string $path_query = null;
    protected ?string $path = null;
    protected ?array $path_array = null;
    protected array $server;
    protected array $parameters;
    protected array $cookie;

    public function __construct(bool $clear_global_vars = false)
    {
        // $_FILES - что с этим делать?

        $this->cookie     = $_COOKIE ?? [];
        $this->server     = &$_SERVER;
        $this->parameters = \array_merge($_GET ?? [], $_POST ?? [], $this->getInput());
        if ($clear_global_vars) {
            $_COOKIE = [];
            $_GET    = [];
            $_POST   = [];
        }
    }

    // ------------------------------------------------------------------
    // Cookie
    // ------------------------------------------------------------------

    /**
     * @param mixed $default
     * @return mixed
     */
    public function getCookie(int|string $key, $default = null)
    {
        return $this->cookie[$key] ?? $default;
    }

    public function hasCookie(int|string $key): bool
    {
        return \array_key_exists($key, $this->cookie);
    }

    public function getCookies(): array
    {
        return $this->cookie;
    }

    // ------------------------------------------------------------------
    // Parameters
    // ------------------------------------------------------------------

    /**
     * @param mixed $default
     * @return mixed
     */
    public function getParam(int|string $key, $default = null)
    {
        return $this->parameters[$key] ?? $default;
    }

    public function getParams(): array
    {
        return $this->parameters;
    }

    public function hasParam(int|string $key): bool
    {
        return \array_key_exists($key, $this->parameters);
    }

    // ------------------------------------------------------------------
    // Headers
    // ------------------------------------------------------------------

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        if ($this->headers !== null) return $this->headers;
        return $this->headers = $this->defineHeaders();
    }

    // ------------------------------------------------------------------
    // Method
    // ------------------------------------------------------------------

    public function getMethod(): string
    {
        if ($this->method !== null) return $this->method;
        return $this->method = $this->defineMethod();
    }

    // ------------------------------------------------------------------
    // Path
    // ------------------------------------------------------------------

    /**
     * /any/any...
     */
    public function getPath(): string
    {
        if ($this->path !== null) return $this->path;
        return $this->path = $this->definePath();
    }

    /**
     * @return string[]|array{}
     */
    public function getPathAsArray(): array
    {
        if ($this->path_array !== null) return $this->path_array;

        $a = \trim($this->getPath(), '/');
        if ($a === '') return $this->path_array = [];
        $a = \explode('/', $a);
        return $this->path_array = $a;
    }

    /**
     * @param string|string[] $value
     */
    public function containsValueInPath(string|array $value): bool
    {
        if (!\is_array($value)) $value = [$value];
        $path = $this->getPath() . '/';
        foreach ($value as $v) {
            if (!\str_contains($path, '/' . \trim(\strval($v), '/') . '/')) return false;
        }
        return true;
    }

    public function pathValueAtIndex(string $value, int $idx): bool
    {
        return ($this->getPathAsArray()[$idx] ?? null) === $value;
    }

    public function getValueByIndexFromPath(int $idx): ?string
    {
        return $this->getPathAsArray()[$idx] ?? null;
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    public function getQueryBuilder(): QueryBuild
    {
        if ($this->query_build !== null) return $this->query_build;
        return $this->query_build = new QueryBuild($this->getPathAndQuery());
    }

    public function getQuery(): string
    {
        return $this->getQueryBuilder()->getQuery(true);
    }

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

    /**
     * @return array<string,string>
     */
    protected function defineHeaders(): array
    {
        $headers = [];

        if (\function_exists('getallheaders')) {
            $headers = \getallheaders();
            if ($headers !== false) {
                return \array_change_key_case($headers, \CASE_LOWER);
            }
        }

        foreach ($this->server as $name => $value) {
            /** @var string $name */
            if (
                ($http = \str_starts_with($name, 'HTTP_'))
                ||
                $name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH'
            ) {
                if ($http) $name = \substr($name, 5);
                $name = \str_replace('_', '-', $name);
                $headers[$name] = $value;
            }
        }

        return \array_change_key_case($headers, \CASE_LOWER);
    }

    protected function defineMethod(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? '';

        if ($method == 'POST') {
            $headers = $this->getHeaders();
            if (isset($headers['x-http-method-override']) && \in_array($headers['x-http-method-override'], ['PUT', 'DELETE', 'PATCH'])) {
                $method = $headers['x-http-method-override'];
            }
        }

        return $method;
    }

    /**
     * /any/any...
     */
    protected function definePath(): string
    {
        $p = $this->getPathAndQuery();

        if (false !== $pos = \strpos($p, '?')) {
            $p = \substr($p, 0, $pos);
        }

        // делаем еще раз trim так как до символа "?" может быть "/", например "/admin/page/?param=value"
        return '/' . \trim($p, '/');
    }

    /**
     * "/.../...(/?)?..."
     */
    protected function getPathAndQuery(): string
    {
        if ($this->path_query !== null) return $this->path_query;
        return $this->path_query = '/' . \trim(\rawurldecode($this->server['REQUEST_URI'] ?? ''), '/');
    }

    /**
     * ждем https://wiki.php.net/rfc/rfc1867-non-post
     */
    protected function getInput(): array
    {
        $value = $this->getRawInput();
        if ($value === '') return [];
        if (\_json()->isJSON($value)) {
            $value = \json_decode($value, true);
            if (!\is_array($value)) $value = [$value];
            return $value;
        }
        $inputs = [];
        \parse_str($value, $inputs);
        if (!$inputs) return [];
        $post_keys = \array_keys($_POST ?? []);
        if ($post_keys) {
            $inputs = \_arr()->except($inputs, $post_keys);
        }
        return $inputs;
    }

    protected function getRawInput(): string
    {
        $value = @\file_get_contents('php://input');
        if ($value === false) return '';
        return $value;
    }
}
