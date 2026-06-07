<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$path = $_GET['action'] ?? '';
$db = getDB();

switch ($path) {
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $user = $db->find('users', 'email', $email);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            jsonResponse(['success'=>true,'user'=>['id'=>$user['id'],'name'=>$user['name'],'role'=>$user['role']]]);
        }
        jsonResponseError('Invalid credentials', 401);
        break;

    case 'register':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $db->insert('users', [
            'name' => sanitize($input['name'] ?? ''),
            'email' => sanitize($input['email'] ?? ''),
            'password' => password_hash($input['password'] ?? '', PASSWORD_DEFAULT),
            'role' => sanitize($input['role'] ?? 'student'),
            'class' => sanitize($input['class'] ?? ''),
            'is_premium' => 0
        ]);
        jsonResponse(['success'=>true,'id'=>$id]);
        break;

    case 'subjects': jsonResponse($db->query('subjects')); break;
    case 'homework': jsonResponse($db->queryAll('SELECT * FROM homework ORDER BY id DESC')); break;
    case 'exams': jsonResponse($db->queryAll('SELECT * FROM exams ORDER BY id DESC')); break;
    case 'teachers': jsonResponse($db->queryAll('SELECT t.*, u.name, u.email FROM teachers t JOIN users u ON t.user_id = u.id')); break;
    case 'live_classes': jsonResponse($db->query('live_classes')); break;
    case 'announcements': jsonResponse($db->queryAll('SELECT * FROM announcements ORDER BY id DESC')); break;
    case 'packages': jsonResponse($db->query('packages')); break;
    case 'notifications':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $role = $_SESSION['role'] ?? '';
        $all = $db->query('notifications');
        $filtered = array_filter($all, function($n) use ($role) {
            return ($n['target_role'] ?? '') === $role || ($n['target_role'] ?? '') === 'all';
        });
        jsonResponse(array_values($filtered));
        break;
    case 'student_progress':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $sid = intval($_GET['student_id'] ?? $_SESSION['user_id'] ?? 0);
        $prog = $db->find('student_progress', 'student_id', $sid);
        jsonResponse($prog ?: []);
        break;
    case 'homework_submissions':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        jsonResponse($db->queryAll('SELECT * FROM homework_submissions ORDER BY id DESC'));
        break;
    case 'exam_results':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $eid = intval($_GET['student_id'] ?? $_SESSION['user_id'] ?? 0);
        $results = $db->findAll('exam_results', 'student_id', $eid);
        jsonResponse($results);
        break;
    case 'library':
        $class = $_GET['class'] ?? '';
        $subjectId = $_GET['subject_id'] ?? '';
        $all = $db->query('library');
        $filtered = array_filter($all, function($item) use ($class, $subjectId) {
            if ($item['is_active'] != 1) return false;
            if ($class && ($item['class'] ?? '') !== $class) return false;
            if ($subjectId && (string)$item['subject_id'] !== (string)$subjectId) return false;
            return true;
        });
        jsonResponse(array_values($filtered));
        break;
    case 'library_add':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $input = json_decode(file_get_contents('php://input'), true);
        $role = $_SESSION['role'] ?? '';
        if ($role !== 'admin' && $role !== 'teacher') jsonResponseError('Unauthorized', 403);
        $id = $db->insert('library', [
            'title' => sanitize($input['title'] ?? ''),
            'subject_id' => intval($input['subject_id'] ?? 0),
            'class' => sanitize($input['class'] ?? ''),
            'type' => sanitize($input['type'] ?? 'textbook'),
            'description' => sanitize($input['description'] ?? ''),
            'file_url' => sanitize($input['file_url'] ?? ''),
            'cover_url' => sanitize($input['cover_url'] ?? ''),
            'uploader_id' => intval($_SESSION['user_id'] ?? 0),
            'uploader_type' => $role,
            'downloads' => 0,
            'is_active' => 1
        ]);
        jsonResponse(['success' => true, 'id' => $id]);
        break;
    case 'library_delete':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $role = $_SESSION['role'] ?? '';
        if ($role !== 'admin' && $role !== 'teacher') jsonResponseError('Unauthorized', 403);
        $delId = intval($_GET['id'] ?? 0);
        if ($delId > 0) { $db->delete('library', $delId); jsonResponse(['success' => true]); }
        else jsonResponseError('Invalid ID');
        break;

    case 'stats':
        jsonResponse([
            'students' => count($db->findAll('users','role','student')),
            'teachers' => count($db->findAll('users','role','teacher')),
            'homework' => count($db->query('homework')),
            'exams' => count($db->query('exams')),
            'classes' => count($db->query('live_classes')),
        ]);
        break;

    case 'profile':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        jsonResponse(currentUser());
        break;

    default: jsonResponseError('Unknown action');
}