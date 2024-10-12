<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Request\Request;

Dump::init();

$a = new Request;

// $headers = $a->getHeaders();

de($a);
