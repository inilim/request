<?php

namespace Inilim\Request;

class Header
{
    protected ?array $headers = null;

    /**
     * @return array<string,string>
     */
    public function get(): array
    {
        if ($this->headers !== null) return $this->headers;
        return $this->headers = $this->define();
    }

    // ------------------------------------------------------------------
    // ___
    // ------------------------------------------------------------------

    /**
     * @return array<string,string>
     */
    protected function define(): array
    {
        $headers = [];

        if (\function_exists('getallheaders')) {
            $headers = \getallheaders();
            if ($headers !== false) return $headers;
        }

        foreach ($_SERVER as $name => $value) {
            if (
                ($http = \str_starts_with($name, 'HTTP_'))
                ||
                $name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH'
            ) {
                if ($http) $name = \substr($name, 5);
                $name = \strtolower(\str_replace('_', '-', $name));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
