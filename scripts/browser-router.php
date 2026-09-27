<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

// Isolated browser-test server only; never configure this as a deployment entry point.
if (getenv('RHU_BROWSER_TESTING') !== '1' || getenv('APP_ENV') !== 'testing') {
    http_response_code(403);
    exit;
}
$public = realpath(__DIR__.'/../public');
$requested = realpath($public.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($requested && str_starts_with($requested, $public.DIRECTORY_SEPARATOR) && is_file($requested)) {
    return false;
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->booted(function () {
    Vite::useHotFile(storage_path('framework/testing/unused.hot'));
});
$app->handleRequest(Request::capture());
