<?php
define('BOOKS_DIR', realpath(__DIR__ . '/../books') ?: __DIR__ . '/../books');
define('UPLOAD_MAX_SIZE', 100 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf','epub','mobi','zip','mp4','mov','avi','mkv','jpg','jpeg','png','gif','webp','doc','docx','xls','xlsx','ppt','pptx','txt','md','csv']);

function getBooksDir() { return BOOKS_DIR; }

function ensureDir($path) {
    if (!is_dir($path)) mkdir($path, 0777, true);
}

function sanitizePath($p) {
    return preg_replace('/[<>:"|?*]/', '', trim($p));
}

function getExtIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = ['pdf'=>'file-text','jpg'=>'image','jpeg'=>'image','png'=>'image','gif'=>'image','webp'=>'image','mp4'=>'video','mov'=>'video','avi'=>'video','mkv'=>'video','zip'=>'archive','epub'=>'book','mobi'=>'book','doc'=>'file-text','docx'=>'file-text','xls'=>'file-text','xlsx'=>'file-text','ppt'=>'file-text','pptx'=>'file-text','txt'=>'file-text','csv'=>'file-text'];
    return $map[$ext] ?? 'file';
}

function scanFolder($path) {
    $items = [];
    if (!is_dir($path)) return $items;
    $entries = scandir($path);
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') continue;
        $full = $path . '/' . $e;
        $items[] = [
            'name' => $e,
            'is_dir' => is_dir($full),
            'size' => is_file($full) ? filesize($full) : 0,
            'modified' => is_file($full) ? date('Y-m-d H:i', filemtime($full)) : '',
            'icon' => is_dir($full) ? 'folder' : getExtIcon($e),
            'type' => is_dir($full) ? 'folder' : strtolower(pathinfo($e, PATHINFO_EXTENSION))
        ];
    }
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    return $items;
}

function formatSize($bytes) {
    if ($bytes == 0) return '0 B';
    $u = ['B','KB','MB','GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $u[$i];
}
