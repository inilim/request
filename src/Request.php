<?php

namespace Inilim\Request;

use Inilim\Request\Header;
use Inilim\QueryBuild\QueryBuild;

class Request
{
    protected ?array $headers = null;
    protected ?QueryBuild $query_build = null;
    protected ?string $method = null;
    protected ?string $path_query = null;
    protected ?string $uri = null;
    protected array $server;
    protected array $parameters;

    public function __construct()
    {
        $this->server = &$_SERVER;
        $this->parameters = \array_merge($_GET ?? [], $_POST ?? []);
        $_GET  = [];
        $_POST = [];
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        if ($this->headers !== null) return $this->headers;
        return $this->headers = $this->defineHeaders();
    }

    public function getMethod(): string
    {
        if ($this->method !== null) return $this->method;
        return $this->method = $this->defineMethod();
    }

    /**
     * /any/any...
     */
    public function getURI(): string
    {
        if ($this->uri !== null) return $this->uri;
        return $this->uri = $this->defineURI();
    }

    public function getQueryBuilder(): QueryBuild
    {
        if ($this->query_build !== null) return $this->query_build;
        return $this->query_build = new QueryBuild($this->getPathAndQuery());
    }

    // ------------------------------------------------------------------
    // ___
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
                return \array_change_key_case($headers, CASE_LOWER);
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

        return \array_change_key_case($headers, CASE_LOWER);
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
    protected function defineURI(): string
    {
        $uri = $this->getPathAndQuery();
        $pos = \strpos($uri, '?');
        if (\is_int($pos)) $uri = \substr($uri, 0, $pos);
        return '/' . \trim($uri, '/');
    }

    protected function getPathAndQuery(): string
    {
        if ($this->path_query !== null) return $this->path_query;
        return $this->path_query = \rawurldecode($this->server['REQUEST_URI'] ?? '');
    }
}
