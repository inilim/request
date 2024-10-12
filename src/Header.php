<?php

namespace Inilim\Request;

use Inilim\Request\Request;

final class Header
{
    const HOST       = 'HOST';
    const USER_AGENT = 'USER-AGENT';

    protected Request $request;

    function __construct(
        Request $request
    ) {
        $this->request = $request;
    }

    function getHost(): string
    {
        return $this->request->getHeader(static::HOST, '');
    }

    function getUserAgent(): string
    {
        return $this->request->getHeader(static::USER_AGENT, '');
    }

    function getRequest(): Request
    {
        return $this->request;
    }
}
