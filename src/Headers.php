<?php

namespace Inilim\Request;

use Inilim\Request\Request;

final class Headers
{
    const HOST       = 'HOST',
        CONTENT_TYPE = 'CONTENT-TYPE',
        USER_AGENT   = 'USER-AGENT';

    protected Request $request;

    function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    function get(string $name, $default = null)
    {
        return $this->request->getHeader($name, $default);
    }

    /**
     * @return string
     */
    function getHost()
    {
        return $this->request->getHeader(self::HOST, '');
    }

    /**
     * @return string
     */
    function getUserAgent()
    {
        return $this->request->getHeader(self::USER_AGENT, '');
    }

    /**
     * @return string
     */
    function getContentType()
    {
        return $this->request->getHeader(self::CONTENT_TYPE, '');
    }

    /**
     * @return Request
     */
    function getRequest()
    {
        return $this->request;
    }
}
