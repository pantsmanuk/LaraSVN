<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Browser harness transport: execute the real HTTP kernel without opening a TCP port.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$request = Request::create($argv[1]);
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'headers' => $response->headers->all(), 'bodyBase64' => base64_encode($response->getContent())], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
