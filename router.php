<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

if (preg_match('/^\/books\//', $uri)) {
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimes = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','mp4'=>'video/mp4','mov'=>'video/quicktime','avi'=>'video/x-msvideo','mkv'=>'video/x-matroska','zip'=>'application/zip','epub'=>'application/epub+zip','txt'=>'text/plain','csv'=>'text/csv','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }
}

if (preg_match('/^\/admin(\/|$)/', $uri) && file_exists(__DIR__ . '/admin/index.php')) {
    require __DIR__ . '/admin/index.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
