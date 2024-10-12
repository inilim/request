<?php

namespace Inilim\Request;

use Inilim\Request\Request;

final class Header
{
    const HOST       = 'HOST';
    const USER_AGENT = 'USER-AGENT';

    function __construct(
        public readonly Request $request,
    ) {}

    function getHost(): string
    {
        return $this->request->getHeader(static::HOST, '');
    }

    function getUserAgent(): string
    {
        return $this->request->getHeader(static::USER_AGENT, '');
    }
}
