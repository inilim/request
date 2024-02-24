<?php

namespace Inilim\Request;

use Inilim\Request\Header;
use Inilim\QueryBuild\QueryBuild;

class Request
{
    protected ?Header $header = null;
    protected ?QueryBuild $query_build = null;
    protected ?string $method = null;
    protected ?string $path_query = null;
    protected ?string $uri = null;

    public function getHandleHeader(): Header
    {
        if ($this->header !== null) return $this->header;
        return $this->header = new Header;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->getHandleHeader()->get();
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

    protected function defineMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

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
        return $this->path_query = \rawurldecode($_SERVER['REQUEST_URI'] ?? '');
    }
}
