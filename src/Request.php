<?php

namespace Inilim\Request;

use Inilim\Tool\Arr;
use Inilim\Tool\Str;
use Inilim\Tool\Json;
use Inilim\Tool\Other;
use Inilim\Request\Headers;
use Inilim\Request\DecodeFormData;

final class Request
{
    protected ?array $headers    = null;
    protected ?string $method    = null;
    protected ?string $query     = null;
    protected ?string $pathQuery = null;
    protected ?string $path      = null;
    protected ?array $pathArray  = null;
    /**
     * @var mixed
     */
    protected $input = null;
    /**
     * @var ?string
     */
    protected $rawInput = null;
    /**
     * @var bool
     */
    protected $rawInputHandler = false;

    /**
     * @var string[]
     */
    protected $keysFromGet  = [];
    /**
     * @var string[]
     */
    protected $keysFromPost = [];

    /**
     * @var array
     */
    protected $server;
    /**
     * @var array
     */
    protected $cookie;
    /**
     * @var array
     */
    protected $files;
    /**
     * @var array
     */
    protected $parameters;

    function __construct(array $get = [], array $post = [], array $cookies = [], array $files = [], array $server = [])
    {
        $this->cookie       = $cookies;
        $this->server       = $server;
        $this->keysFromGet  = \array_keys($get);
        $this->keysFromPost = \array_keys($post);
        $this->parameters   = \array_merge($get, $post);
        $this->files        = $files;

        $rest = \in_array($this->getMethod(), ['PUT', 'PATCH', 'DELETE'], true);

        // ---------------------------------------------
        // 
        // ---------------------------------------------

        if ($rest) {

            $php84  = \PHP_VERSION_ID >= 80400;

            // ---------------------------------------------
            // 
            // ---------------------------------------------

            if ($php84) {
                [$post, $files] = Other::tryCallWithErrHandler(static function () {
                    return \request_parse_body();
                }, null);

                if (\is_array($files) && $files) {
                    $this->files = $files;
                }
                if (\is_array($post) && $post) {
                    $this->parameters = \array_merge($this->parameters, $post);
                    $this->keysFromPost   = \array_keys($post);
                }
            }
            // less 8.4
            else {
                $this->rawInput = Other::phpInput();
                $contentType    = $this->getHeader('CONTENT-TYPE', '');

                if (Str::_startsWith($contentType, 'application/x-www-form-urlencoded')) {
                    \parse_str($this->rawInput, $data);
                    if ($data) {
                        $this->parameters = \array_merge($this->parameters, $data);
                    }
                    $this->rawInputHandler = true;
                } elseif (Str::_startsWith($contentType, 'application/json')) {
                    $data = Json::tryDecode($this->rawInput, true);
                    if (\is_array($data) && $data) {
                        $this->parameters = \array_merge($this->parameters, $data);
                    } else {
                        $this->input = $data;
                    }
                    $this->rawInputHandler = true;
                } elseif (Str::_startsWith($contentType, 'multipart/form-data')) {
                    [$post, $files] = Other::tryCallWithErrHandler(function () {
                        return (new DecodeFormData)->__invoke($this->rawInput);
                    }, null);
                    if (\is_array($files) && $files) {
                        $this->files = $files;
                        // очищаем rawInput чтобы в памяти не висели контент файлов
                        $this->rawInput = '';
                    }
                    if (\is_array($post) && $post) {
                        $this->parameters = \array_merge($this->parameters, $post);
                        $this->keysFromPost   = \array_keys($post);
                    }
                    $this->rawInputHandler = true;
                } else {
                    $this->input = $this->rawInput;
                    $this->rawInput = '';
                }
            }
        }
    }

    /**
     * @return self
     */
    static function createFromGlobals()
    {
        return new self($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
    }

    // ------------------------------------------------------------------
    // Input
    // ------------------------------------------------------------------

    /**
     * @return mixed
     */
    function getInput()
    {
        return $this->input;
    }

    // ------------------------------------------------------------------
    // Cookie
    // ------------------------------------------------------------------

    /**
     * @param mixed $default
     * @return mixed
     */
    function getCookie(string $key, $default = null)
    {
        return $this->cookie[$key] ?? $default;
    }

    /**
     * @return bool
     */
    function hasCookie(string $key)
    {
        return \array_key_exists($key, $this->cookie);
    }

    /**
     * @return array<string,mixed>
     */
    function getCookies()
    {
        return $this->cookie;
    }

    // ------------------------------------------------------------------
    // Parameters
    // ------------------------------------------------------------------

    /**
     * @template T of mixed
     * @param mixed $default
     * @return mixed|T
     */
    function getParam(string $key, $default = null)
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    function getParams()
    {
        return $this->parameters;
    }

    /**
     * @return bool
     */
    function hasParam(string $key)
    {
        return \array_key_exists($key, $this->parameters);
    }

    /**
     * @return array<string,mixed>
     */
    function paramsFromGET()
    {
        if (!$this->keysFromGet) {
            return [];
        }
        return Arr::only($this->parameters, $this->keysFromGet);
    }

    /**
     * @return array<string,mixed>
     */
    function paramsFromPOST()
    {
        if (!$this->keysFromPost) {
            return [];
        }
        return Arr::only($this->parameters, $this->keysFromPost);
    }

    // ------------------------------------------------------------------
    // Headers
    // ------------------------------------------------------------------

    /**
     * @return Headers
     */
    function getHeadersAsObj()
    {
        return new Headers($this);
    }

    /**
     * @return array<string,string>
     */
    function getHeaders()
    {
        return $this->headers ??= Other::requestHeaders($this->server);
    }

    /**
     * @template T of mixed
     * @param mixed $default
     * @return mixed|T
     */
    function getHeader(string $name, $default = null)
    {
        return $this->getHeaders()[$this->normalizeNameHeader($name)] ?? $default;
    }

    /**
     * @return bool
     */
    function hasHeader(string $name)
    {
        return \array_key_exists($this->normalizeNameHeader($name), $this->getHeaders());
    }

    /**
     * @param string $name
     * @return string
     */
    protected function normalizeNameHeader($name)
    {
        return \strtoupper(\strtr($name, '_', '-'));
    }

    // ------------------------------------------------------------------
    // Method
    // ------------------------------------------------------------------

    /**
     * @return string
     */
    function getMethod()
    {
        return $this->method ??= $this->defineMethod();
    }

    // ------------------------------------------------------------------
    // Path
    // ------------------------------------------------------------------

    /**
     * /any/any...
     * @return string
     */
    function getPath()
    {
        return $this->path ??= $this->definePath();
    }

    /**
     * @return string[]
     */
    function getPathAsArray()
    {
        if ($this->pathArray !== null) {
            return $this->pathArray;
        }

        $a = \trim($this->getPath(), '/');
        if ($a === '') return $this->pathArray = [];
        $a = \explode('/', $a);
        return $this->pathArray = $a;
    }

    /**
     * @param string|string[] $value
     * @return bool
     */
    function containsValueInPath($value)
    {
        if (!\is_array($value)) $value = [\strval($value)];

        $path = $this->getPath() . '/';
        foreach ($value as $v) {
            $needle = '/' . \trim(\strval($v), '/') . '/';
            // if (\mb_strpos($path, $needle, 0, 'UTF-8') === false) {
            if (!Str::_contains($path, $needle)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    function pathValueAtIndex(string $value, int $idx)
    {
        return ($this->getPathAsArray()[$idx] ?? null) === $value;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    function getValueByIndexFromPath(int $idx, $default = null)
    {
        return $this->getPathAsArray()[$idx] ?? $default;
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    /**
     * key=value&...
     * @return string
     */
    function getQuery()
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

    /**
     * @return array<string,mixed>
     */
    function getAllServer()
    {
        return $this->server;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    function getServer(string $name, $default = null)
    {
        return $this->server[$this->normalizeNameServer($name)] ?? $default;
    }

    /**
     * @return bool
     */
    function hasServer(string $name)
    {
        return \array_key_exists($this->normalizeNameServer($name), $this->server);
    }

    /**
     * @param string $name
     * @return string
     */
    protected function normalizeNameServer($name)
    {
        return \strtoupper(\strtr($name, '-', '_'));
    }

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

    /**
     * /any/any...
     * @return string
     */
    protected function defineMethod()
    {
        $method = $this->server['REQUEST_METHOD'] ?? '';

        if ($method === 'POST') {
            $headers = $this->getHeaders();
            if (isset($headers['X-HTTP-METHOD-OVERRIDE']) && \in_array($headers['X-HTTP-METHOD-OVERRIDE'], ['PUT', 'DELETE', 'PATCH'])) {
                $method = $headers['X-HTTP-METHOD-OVERRIDE'];
            }
        }

        return $method;
    }

    /**
     * /any/any...
     * @return string
     */
    protected function definePath()
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
     * @return string
     */
    protected function getPathAndQuery()
    {
        return $this->pathQuery ??= '/' . \trim(\rawurldecode($this->server['REQUEST_URI'] ?? ''), '/');
    }
}
