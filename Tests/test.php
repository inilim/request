<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Request\Request;

Dump::init();

$a = Request::createFromGlobals();

$a->getHeaders();

// de($a->getHeader('host'));

// de($a->getParam('2'));
// de($a->getQuery());

de($a);
