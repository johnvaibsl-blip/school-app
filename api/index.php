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
    case 'settings':
        $settings = $db->query('settings');
        $map = [];
        foreach ($settings as $s) $map[$s['key']] = $s['value'];
        jsonResponse($map);
        break;
    case 'homework': jsonResponse($db->queryAll('SELECT * FROM homework ORDER BY id DESC')); break;
    case 'exams': jsonResponse($db->queryAll('SELECT * FROM exams ORDER BY id DESC')); break;
    case 'teachers':
        $teachers = $db->query('teachers');
        $users = $db->query('users');
        $userMap = [];
        foreach ($users as $u) $userMap[$u['id']] = $u;
        $result = [];
        foreach ($teachers as $t) {
            $t['name'] = $userMap[$t['user_id']]['name'] ?? '';
            $t['email'] = $userMap[$t['user_id']]['email'] ?? '';
            $result[] = $t;
        }
        jsonResponse($result);
        break;
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

    case 'submit_homework':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        if (($_SESSION['role'] ?? '') !== 'student') jsonResponseError('Students only', 403);
        $input = json_decode(file_get_contents('php://input'), true);
        $hwId = intval($input['homework_id'] ?? 0);
        $answer = sanitize($input['answer'] ?? '');
        if (!$hwId || !$answer) jsonResponseError('homework_id and answer required');
        $id = $db->insert('homework_submissions', [
            'homework_id' => $hwId,
            'student_id' => intval($_SESSION['user_id']),
            'answer' => $answer,
            'marks_obtained' => 0,
            'status' => 'submitted',
            'comments' => '',
            'submitted_at' => date('Y-m-d H:i:s'),
            'graded_at' => ''
        ]);
        jsonResponse(['success' => true, 'id' => $id]);
        break;

    case 'send_message':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $input = json_decode(file_get_contents('php://input'), true);
        $toId = intval($input['to_id'] ?? 0);
        $msg = sanitize($input['message'] ?? '');
        if (!$toId || !$msg) jsonResponseError('to_id and message required');
        $id = $db->insert('messages', [
            'sender_id' => intval($_SESSION['user_id']),
            'receiver_id' => $toId,
            'message' => $msg,
            'is_read' => 0
        ]);
        jsonResponse(['success' => true, 'id' => $id]);
        break;

    case 'chat_messages':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id']);
        $otherId = intval($_GET['user_id'] ?? 0);
        if (!$otherId) jsonResponseError('user_id required');
        $allMsgs = $db->query('messages');
        $chat = [];
        foreach ($allMsgs as $m) {
            $sid = intval($m['sender_id'] ?? $m['from_id'] ?? 0);
            $rid = intval($m['receiver_id'] ?? $m['to_id'] ?? 0);
            if (($sid === $uid && $rid === $otherId) || ($sid === $otherId && $rid === $uid)) {
                $chat[] = [
                    'id' => $m['id'],
                    'from_id' => $sid,
                    'message' => $m['message'] ?? '',
                    'is_read' => $m['is_read'] ?? 0,
                    'created_at' => $m['created_at'] ?? ''
                ];
            }
        }
        usort($chat, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
        jsonResponse($chat);
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

    case 'edit_profile':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id']);
        $input = json_decode(file_get_contents('php://input'), true);
        $updates = [];
        if (!empty($input['name'])) $updates['name'] = sanitize($input['name']);
        if (!empty($input['phone'])) $updates['phone'] = sanitize($input['phone']);
        if (!empty($input['school'])) $updates['school'] = sanitize($input['school']);
        if (isset($input['avatar'])) $updates['avatar'] = sanitize($input['avatar']);
        if (!empty($input['password'])) $updates['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        if (!empty($updates)) $db->update('users', $uid, $updates);
        // Teacher-specific fields
        $user = $db->find('users', 'id', $uid);
        if ($user && ($user['role'] ?? '') === 'teacher') {
            $teacher = $db->find('teachers', 'user_id', $uid);
            if ($teacher) {
                $tUpdates = [];
                if (isset($input['subject'])) $tUpdates['subject'] = sanitize($input['subject']);
                if (isset($input['experience'])) $tUpdates['experience'] = intval($input['experience']);
                if (isset($input['hourly_rate'])) $tUpdates['hourly_rate'] = intval($input['hourly_rate']);
                if (isset($input['bio'])) $tUpdates['bio'] = sanitize($input['bio']);
                if (!empty($tUpdates)) $db->update('teachers', $teacher['id'], $tUpdates);
            }
        }
        $user = $db->find('users', 'id', $uid);
        jsonResponse(['success' => true, 'user' => $user]);
        break;

    case 'student_profile':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        $u = $db->find('users', 'id', $uid);
        $prog = $db->find('student_progress', 'student_id', $uid);
        $hwSubs = $db->findAll('homework_submissions', 'student_id', $uid);
        $examRes = $db->findAll('exam_results', 'student_id', $uid);
        $badges = $db->findAll('badges', 'student_id', $uid);
        jsonResponse([
            'user' => $u ?: [],
            'progress' => $prog ?: [],
            'homework_count' => count($hwSubs),
            'exam_count' => count($examRes),
            'badges' => $badges
        ]);
        break;

    case 'teacher_profile':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        $u = $db->find('users', 'id', $uid);
        $t = $db->find('teachers', 'user_id', $uid);
        $students = $db->findAll('users', 'role', 'student');
        $liveClasses = $db->findAll('live_classes', 'teacher_id', $uid);
        $hw = $db->query('homework');
        $myHw = array_filter($hw, function($h) use ($uid) { return intval($h['teacher_id'] ?? 0) === $uid; });
        $reviews = $db->findAll('reviews', 'teacher_id', $t['id'] ?? 0);
        $avgRating = 0;
        if (count($reviews) > 0) {
            $total = array_sum(array_column($reviews, 'rating'));
            $avgRating = round($total / count($reviews), 1);
        }
        jsonResponse([
            'user' => $u ?: [],
            'teacher' => $t ?: [],
            'student_count' => count($students),
            'class_count' => count($liveClasses),
            'hw_count' => count($myHw),
            'review_count' => count($reviews),
            'avg_rating' => $avgRating
        ]);
        break;

    case 'my_students':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $students = $db->findAll('users', 'role', 'student');
        $result = [];
        foreach ($students as $s) {
            $prog = $db->find('student_progress', 'student_id', $s['id']);
            $examRes = $db->findAll('exam_results', 'student_id', $s['id']);
            $avg = 0;
            if (count($examRes) > 0) {
                $avg = round(array_sum(array_column($examRes, 'score')) / count($examRes));
            }
            $result[] = [
                'id' => $s['id'],
                'name' => $s['name'],
                'email' => $s['email'],
                'class' => $s['class'] ?? '',
                'avg_score' => $avg,
                'streak' => $prog['streak'] ?? 0
            ];
        }
        jsonResponse($result);
        break;

    case 'student_detail':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $sid = intval($_GET['student_id'] ?? 0);
        $u = $db->find('users', 'id', $sid);
        $prog = $db->find('student_progress', 'student_id', $sid);
        $examRes = $db->findAll('exam_results', 'student_id', $sid);
        $hwSubs = $db->findAll('homework_submissions', 'student_id', $sid);
        $hwPend = 0;
        foreach ($hwSubs as $h) { if (($h['status'] ?? '') !== 'graded') $hwPend++; }
        jsonResponse([
            'user' => $u ?: [],
            'progress' => $prog ?: [],
            'exam_results' => $examRes,
            'hw_done' => count($hwSubs),
            'hw_pending' => $hwPend,
            'avg_score' => count($examRes) > 0 ? round(array_sum(array_column($examRes, 'score')) / count($examRes)) : 0
        ]);
        break;

    case 'live_schedule':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        jsonResponse($db->query('live_classes'));
        break;

    case 'conversations':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        $msgs = $db->query('messages');
        $convos = [];
        $seen = [];
        foreach ($msgs as $m) {
            $senderId = intval($m['sender_id'] ?? $m['from_id'] ?? 0);
            $receiverId = intval($m['receiver_id'] ?? $m['to_id'] ?? 0);
            if ($senderId === $uid || $receiverId === $uid) {
                $otherId = $senderId === $uid ? $receiverId : $senderId;
                if (!isset($seen[$otherId])) {
                    $seen[$otherId] = true;
                    $other = $db->find('users', 'id', $otherId);
                    $convos[] = [
                        'user_id' => $otherId,
                        'name' => $other['name'] ?? 'Unknown',
                        'last_message' => $m['message'] ?? '',
                        'time' => $m['created_at'] ?? '',
                        'unread' => ($m['is_read'] ?? 0) == 0 && $receiverId === $uid ? 1 : 0
                    ];
                }
            }
        }
        jsonResponse($convos);
        break;

    case 'earnings':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        $liveClasses = $db->query('live_classes');
        $myClasses = array_filter($liveClasses, function($c) use ($uid) { return intval($c['teacher_id'] ?? 0) === $uid; });
        $classCount = count($myClasses);
        $monthly = $classCount * 500;
        jsonResponse([
            'monthly' => $monthly,
            'total' => $monthly * 9,
            'class_count' => $classCount,
            'students' => count($db->findAll('users', 'role', 'student'))
        ]);
        break;

    case 'badges':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        jsonResponse($db->findAll('badges', 'student_id', $uid));
        break;

    case 'activity_log':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        jsonResponse($db->findAll('activity_log', 'user_id', $uid));
        break;

    case 'log_activity':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $input = json_decode(file_get_contents('php://input'), true);
        $db->insert('activity_log', [
            'user_id' => intval($_SESSION['user_id'] ?? 0),
            'action' => sanitize($input['action'] ?? ''),
            'details' => sanitize($input['details'] ?? ''),
        ]);
        jsonResponse(['success' => true]);
        break;

    case 'activity_feed':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        jsonResponse($db->findAll('activity_feed', 'user_id', $uid));
        break;

    case 'chapters':
        $sid = intval($_GET['subject_id'] ?? 0);
        jsonResponse($db->findAll('chapters', 'subject_id', $sid));
        break;

    case 'book_content':
        $cid = intval($_GET['chapter_id'] ?? 0);
        $all = $db->query('book_content');
        $found = null;
        foreach ($all as $b) { if (intval($b['chapter_id'] ?? 0) === $cid) { $found = $b; break; } }
        jsonResponse($found ?: []);
        break;

    case 'class_schedule':
        jsonResponse($db->query('class_schedule'));
        break;

    case 'calendar_events':
        jsonResponse($db->query('calendar_events'));
        break;

    case 'reports':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? '';
        if ($role === 'student') {
            jsonResponse($db->findAll('reports', 'student_id', $uid));
        } else {
            jsonResponse($db->query('reports'));
        }
        break;

    case 'admin_stats':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $users = $db->query('users');
        $students = count(array_filter($users, function($u) { return ($u['role'] ?? '') === 'student'; }));
        $teachers = count(array_filter($users, function($u) { return ($u['role'] ?? '') === 'teacher'; }));
        $liveClasses = count($db->query('live_classes'));
        $hw = count($db->query('homework'));
        $exams = count($db->query('exams'));
        jsonResponse(['students' => $students, 'teachers' => $teachers, 'live_classes' => $liveClasses, 'homework' => $hw, 'exams' => $exams]);
        break;

    case 'exam_analytics':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $exams = $db->query('exams');
        $results = $db->query('exam_results');
        $totalExams = count($exams);
        $totalResults = count($results);
        $avgScore = $totalResults > 0 ? round(array_sum(array_column($results, 'score')) / $totalResults) : 0;
        $passCount = 0;
        foreach ($results as $r) { if (($r['score'] ?? 0) >= 50) $passCount++; }
        $passRate = $totalResults > 0 ? round($passCount / $totalResults * 100) : 0;
        jsonResponse(['avg_score' => $avgScore, 'pass_rate' => $passRate, 'total_exams' => $totalExams, 'total_results' => $totalResults, 'results' => $results, 'exams' => $exams]);
        break;

    case 'hw_analytics':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $hw = $db->query('homework');
        $subs = $db->query('homework_submissions');
        $totalAssigned = count($hw);
        $totalSubmitted = count($subs);
        $pending = $totalAssigned - $totalSubmitted;
        $rate = $totalAssigned > 0 ? round($totalSubmitted / $totalAssigned * 100) : 0;
        jsonResponse(['total_assigned' => $totalAssigned, 'submitted' => $totalSubmitted, 'pending' => $pending, 'submission_rate' => $rate, 'submissions' => $subs, 'homework' => $hw]);
        break;

    case 'question_bank':
        jsonResponse($db->query('question_bank'));
        break;

    case 'student_evaluations':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        jsonResponse($db->query('student_evaluations'));
        break;

    default: jsonResponseError('Unknown action');
}