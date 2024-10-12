<?php

namespace Inilim\Request;

final class Request
{
    protected ?array $headers    = null;
    protected ?string $method    = null;
    protected ?string $query     = null;
    protected ?string $pathQuery = null;
    protected ?string $path      = null;
    protected ?array $pathArray  = null;

    protected array $server;
    protected array $cookie;
    protected array $files;
    protected array $parameters;

    function __construct(bool $clearGlobalVars = false)
    {
        $this->cookie     = &$_COOKIE ?? [];
        $this->server     = &$_SERVER ?? [];
        $this->files      = &$_FILES ?? [];
        $this->parameters = \array_merge($_GET ?? [], $_POST ?? [], $this->getInput());

        if ($clearGlobalVars) {
            $_GET    = [];
            $_POST   = [];
        }
    }

    // ------------------------------------------------------------------
    // Cookie
    // ------------------------------------------------------------------

    /**
     * @param mixed $default
     * @param int|string $key
     * @return mixed
     */
    function getCookie($key, $default = null)
    {
        return $this->cookie[$key] ?? $default;
    }

    /**
     * @param int|string $key
     */
    function hasCookie($key): bool
    {
        return \array_key_exists($key, $this->cookie);
    }

    function getCookies(): array
    {
        return $this->cookie;
    }

    // ------------------------------------------------------------------
    // Parameters
    // ------------------------------------------------------------------

    /**
     * @param mixed $default
     * @param int|string $key
     * @return mixed
     */
    function getParam($key, $default = null)
    {
        return $this->parameters[$key] ?? $default;
    }

    function getParams(): array
    {
        return $this->parameters;
    }

    /**
     * @param int|string $key
     */
    function hasParam($key): bool
    {
        return \array_key_exists($key, $this->parameters);
    }

    // ------------------------------------------------------------------
    // Headers
    // ------------------------------------------------------------------

    /**
     * @return array<string,string>
     */
    function getHeaders(): array
    {
        return $this->headers ??= $this->defineHeaders();
        // return $this->headers ??= $this->defineHeadersSymfony();
    }

    function getHeader(string $name, $default = null)
    {
        return $this->getHeaders()[\strtoupper($name)] ?? $default;
    }

    function hasHeader(string $name): bool
    {
        return \array_key_exists(\strtoupper($name), $this->getHeaders());
    }

    // ------------------------------------------------------------------
    // Method
    // ------------------------------------------------------------------

    function getMethod(): string
    {
        return $this->method ??= $this->defineMethod();
    }

    // ------------------------------------------------------------------
    // Path
    // ------------------------------------------------------------------

    /**
     * /any/any...
     */
    function getPath(): string
    {
        return $this->path ??= $this->definePath();
    }

    /**
     * @return string[]|array{}
     */
    function getPathAsArray(): array
    {
        if ($this->pathArray !== null) return $this->pathArray;

        $a = \trim($this->getPath(), '/');
        if ($a === '') return $this->pathArray = [];
        $a = \explode('/', $a);
        return $this->pathArray = $a;
    }

    /**
     * @param string|string[] $value
     */
    function containsValueInPath($value): bool
    {
        if (!\is_array($value)) $value = [$value];
        $path = $this->getPath() . '/';
        foreach ($value as $v) {
            $needle = '/' . \trim(\strval($v), '/') . '/';
            if (\mb_strpos($path, $needle, 0, 'UTF-8') === false) {
                return false;
            }
        }
        return true;
    }

    function pathValueAtIndex(string $value, int $idx): bool
    {
        return ($this->getPathAsArray()[$idx] ?? null) === $value;
    }

    function getValueByIndexFromPath(int $idx, $default = null)
    {
        return $this->getPathAsArray()[$idx] ?? $default;
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    /**
     * key=value&...
     */
    function getQuery(): string
    {
        return $this->query ??= \explode(
            '?',
            $this->getPathAndQuery(),
            2
        )[1] ?? '';
    }

    // ------------------------------------------------------------------
    // Server
    // ------------------------------------------------------------------

    function getAllServer(): array
    {
        return $this->server;
    }

    function getServer(string $name, $default = null)
    {
        return $this->server[$name] ?? $default;
    }

    function hasServer(string $name): bool
    {
        return \array_key_exists($name, $this->server);
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
                return \array_change_key_case($headers, \CASE_UPPER);
            }
        }

        foreach ($this->server as $name => $value) {
            /** @var string $name */
            if (
                ($http = (\strpos($name, 'HTTP_') === 0))
                ||
                $name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH'
            ) {
                if ($http) $name = \substr($name, 5);
                $name = \str_replace('_', '-', $name);
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    protected function defineHeadersSymfony(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (\strpos($key, 'HTTP_') === 0) {
                $headers[\substr($key, 5)] = $value;
            } elseif (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true) && '' !== $value) {
                $headers[$key] = $value;
            }
        }

        if (isset($this->server['PHP_AUTH_USER'])) {
            $headers['PHP_AUTH_USER'] = $this->server['PHP_AUTH_USER'];
            $headers['PHP_AUTH_PW'] = $this->server['PHP_AUTH_PW'] ?? '';
        } else {
            /*
             * php-cgi under Apache does not pass HTTP Basic user/pass to PHP by default
             * For this workaround to work, add these lines to your .htaccess file:
             * RewriteCond %{HTTP:Authorization} .+
             * RewriteRule ^ - [E=HTTP_AUTHORIZATION:%0]
             *
             * A sample .htaccess file:
             * RewriteEngine On
             * RewriteCond %{HTTP:Authorization} .+
             * RewriteRule ^ - [E=HTTP_AUTHORIZATION:%0]
             * RewriteCond %{REQUEST_FILENAME} !-f
             * RewriteRule ^(.*)$ index.php [QSA,L]
             */

            $authorizationHeader = null;
            if (isset($this->server['HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $this->server['HTTP_AUTHORIZATION'];
            } elseif (isset($this->server['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $this->server['REDIRECT_HTTP_AUTHORIZATION'];
            }

            if (null !== $authorizationHeader) {
                if (0 === \stripos($authorizationHeader, 'basic ')) {
                    // Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
                    $exploded = \explode(':', \base64_decode(\substr($authorizationHeader, 6)), 2);
                    if (2 == \count($exploded)) {
                        [$headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']] = $exploded;
                    }
                } elseif (empty($this->server['PHP_AUTH_DIGEST']) && (0 === \stripos($authorizationHeader, 'digest '))) {
                    // In some circumstances PHP_AUTH_DIGEST needs to be set
                    $headers['PHP_AUTH_DIGEST'] = $authorizationHeader;
                    $this->server['PHP_AUTH_DIGEST'] = $authorizationHeader;
                } elseif (0 === \stripos($authorizationHeader, 'bearer ')) {
                    /*
                     * XXX: Since there is no PHP_AUTH_BEARER in PHP predefined variables,
                     *      I'll just set $headers['AUTHORIZATION'] here.
                     *      https://php.net/reserved.variables.server
                     */
                    $headers['AUTHORIZATION'] = $authorizationHeader;
                }
            }
        }

        if (isset($headers['AUTHORIZATION'])) {
            return $headers;
        }

        // PHP_AUTH_USER/PHP_AUTH_PW
        if (isset($headers['PHP_AUTH_USER'])) {
            $headers['AUTHORIZATION'] = 'Basic ' . \base64_encode($headers['PHP_AUTH_USER'] . ':' . ($headers['PHP_AUTH_PW'] ?? ''));
        } elseif (isset($headers['PHP_AUTH_DIGEST'])) {
            $headers['AUTHORIZATION'] = $headers['PHP_AUTH_DIGEST'];
        }

        return $headers;
    }

    protected function defineMethod(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? '';

        if ($method == 'POST') {
            $headers = $this->getHeaders();
            if (isset($headers['X-HTTP-METHOD-OVERRIDE']) && \in_array($headers['X-HTTP-METHOD-OVERRIDE'], ['PUT', 'DELETE', 'PATCH'])) {
                $method = $headers['X-HTTP-METHOD-OVERRIDE'];
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
        return $this->pathQuery ??= '/' . \trim(\rawurldecode($this->server['REQUEST_URI'] ?? ''), '/');
    }

    /**
     * ждем request_parse_body
     * @see https://wiki.php.net/rfc/rfc1867-non-post
     */
    protected function getInput(): array
    {
        $value = $this->getPhpInput();
        if ($value === '') return [];
        if (\_json()->isJSON($value)) {
            $value = \json_decode($value, true);
            if (!\is_array($value)) $value = [$value];
            return $value;
        }
        $inputs = [];
        \parse_str($value, $inputs);
        if (!$inputs) return [];
        $postKeys = \array_keys($_POST ?? []);
        if ($postKeys) {
            // \_arr()->except($inputs, $postKeys);
            $inputs = \array_diff_key($inputs, \array_flip($postKeys));
        }
        return $inputs;
    }

    // protected function validQuery(string $value): bool
    // {
    //     return \filter_var('http://site.ru?' . $value, \FILTER_VALIDATE_URL, \FILTER_FLAG_QUERY_REQUIRED) !== false;
    // }

    protected function getPhpInput(): string
    {
        $value = @\file_get_contents('php://input');
        if ($value === false) return '';
        return $value;
    }
}
