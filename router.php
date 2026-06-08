<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

if (preg_match('/^\/admin(\/|$)/', $uri) && file_exists(__DIR__ . '/admin/index.php')) {
    require __DIR__ . '/admin/index.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
