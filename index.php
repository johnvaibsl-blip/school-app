<?php
// Root router
require_once __DIR__ . '/config/database.php';

// Everyone (logged in or not) gets index.html — it's the SPA
// The SPA handles role-based screen visibility client-side
$landing = __DIR__ . '/index.html';
if (file_exists($landing)) {
    // If logged in, embed role info so SPA knows which screens to show
    if (isLoggedIn()) {
        $html = file_get_contents($landing);
        // Inject role data before closing </head>
        $roleData = '<script>window.__USER_ROLE="' . htmlspecialchars($_SESSION['role']) . '";window.__USER_NAME="' . htmlspecialchars($_SESSION['user_name'] ?? '') . '";window.__USER_CLASS="' . htmlspecialchars($_SESSION['user_class'] ?? 'Class 8') . '";</script>';
        $html = str_replace('</head>', $roleData . '</head>', $html);
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }
    // Not logged in — serve raw landing page
    header('Content-Type: text/html; charset=UTF-8');
    readfile($landing);
    exit;
}
header('Location: /login.php');
exit;
