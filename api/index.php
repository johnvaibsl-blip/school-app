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
            'is_premium' => 0,
            'premium_expires_at' => null
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
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
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
        $senderId = intval($_SESSION['user_id']);
        $senderRole = $_SESSION['role'] ?? '';
        if ($senderRole === 'student') {
            $allSubs = $db->query('subscriptions');
            $hasSub = false;
            foreach ($allSubs as $s) {
                if (($s['type'] ?? 'teacher') !== 'teacher') continue;
                if (intval($s['student_id']) === $senderId && intval($s['teacher_id']) === $toId && $s['status'] === 'approved') {
                    $hasSub = true;
                    break;
                }
            }
            if (!$hasSub) jsonResponseError('You need an active subscription to message this teacher');
        }
        $id = $db->insert('messages', [
            'sender_id' => $senderId,
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
        $role = $_SESSION['role'] ?? '';
        if ($role === 'student') {
            $allSubs = $db->query('subscriptions');
            $hasSub = false;
            foreach ($allSubs as $s) {
                if (($s['type'] ?? 'teacher') !== 'teacher') continue;
                if (intval($s['student_id']) === $uid && intval($s['teacher_id']) === $otherId && $s['status'] === 'approved') {
                    $hasSub = true; break;
                }
            }
            if (!$hasSub) jsonResponseError('Subscription required to view messages');
        }
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
        $uid = intval($_SESSION['user_id']);
        $role = $_SESSION['role'] ?? '';
        $students = $db->findAll('users', 'role', 'student');
        $allSubs = $db->query('subscriptions');
        $result = [];
        foreach ($students as $s) {
            $prog = $db->find('student_progress', 'student_id', $s['id']);
            $examRes = $db->findAll('exam_results', 'student_id', $s['id']);
            $avg = 0;
            if (count($examRes) > 0) {
                $avg = round(array_sum(array_column($examRes, 'score')) / count($examRes));
            }
            $subStatus = null;
            $subId = null;
            if ($role === 'teacher') {
                foreach ($allSubs as $sub) {
                    if (($sub['type'] ?? 'teacher') !== 'teacher') continue;
                    if (intval($sub['student_id']) === intval($s['id']) && intval($sub['teacher_id']) === $uid) {
                        $subStatus = $sub['status'];
                        $subId = $sub['id'];
                        break;
                    }
                }
            }
            $result[] = [
                'id' => $s['id'],
                'name' => $s['name'],
                'email' => $s['email'],
                'class' => $s['class'] ?? '',
                'avg_score' => $avg,
                'streak' => $prog['streak'] ?? 0,
                'sub_status' => $subStatus,
                'sub_id' => $subId
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
        $classes = $db->query('live_classes');
        $teachers = $db->query('teachers');
        $users = $db->query('users');
        $subjects = $db->query('subjects');
        $teacherMap = [];
        foreach ($teachers as $t) { $teacherMap[$t['user_id']] = $t; }
        $userMap = [];
        foreach ($users as $u) { $userMap[$u['id']] = $u; }
        $subjectMap = [];
        foreach ($subjects as $s) { $subjectMap[$s['id']] = $s['name'] ?? ''; }
        $result = [];
        foreach ($classes as $c) {
            $tid = intval($c['teacher_id'] ?? 0);
            $sid = intval($c['subject_id'] ?? 0);
            $tUser = $userMap[$tid] ?? [];
            $c['teacher_name'] = $tUser['name'] ?? 'Teacher';
            $c['subject'] = $subjectMap[$sid] ?? ($c['title'] ?? '');
            $c['time'] = $c['start_time'] ?? $c['time'] ?? '';
            $c['class_name'] = $c['class_name'] ?? '';
            $result[] = $c;
        }
        jsonResponse($result);
        break;

    case 'conversations':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? '';
        $msgs = $db->query('messages');
        $convos = [];
        $seen = [];
        $allSubs = $db->query('subscriptions');
        function _isSubscribed($uid, $otherId, $allSubs, $db) {
            foreach ($allSubs as $s) {
                if (($s['type'] ?? 'teacher') !== 'teacher') continue;
                if (intval($s['student_id']) === $uid && intval($s['teacher_id']) === $otherId && $s['status'] === 'approved') return true;
            }
            return false;
        }
        foreach ($msgs as $m) {
            $senderId = intval($m['sender_id'] ?? $m['from_id'] ?? 0);
            $receiverId = intval($m['receiver_id'] ?? $m['to_id'] ?? 0);
            if ($senderId === $uid || $receiverId === $uid) {
                $otherId = $senderId === $uid ? $receiverId : $senderId;
                if (!isset($seen[$otherId])) {
                    $seen[$otherId] = true;
                    if ($role === 'student' && !_isSubscribed($uid, $otherId, $allSubs, $db)) continue;
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
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $sid = intval($_GET['subject_id'] ?? 0);
        jsonResponse($db->findAll('chapters', 'subject_id', $sid));
        break;

    case 'book_content':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
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

    case 'ai_analyze_image':
        $input = json_decode(file_get_contents('php://input'), true);
        $imageData = $input['image'] ?? '';
        $imageName = $input['name'] ?? 'image';
        $systemPrompt = $input['system_prompt'] ?? 'You are a helpful school tutor. Analyze this image and explain what you see in a clear, educational way. If it contains a math problem, solve it. If it contains text, read and summarize it. If it is a diagram, explain it.';
        $settings = $db->query('settings');
        $sMap = [];
        foreach ($settings as $s) $sMap[$s['key']] = $s['value'];
        $provider = $input['provider_override'] ?? ($sMap['ai_provider'] ?? 'openai');
        $apiKey = '';
        $model = 'gpt-4o';
        if ($provider === 'openai') { $apiKey = $sMap['openai_api_key'] ?? ''; $model = 'gpt-4o'; }
        elseif ($provider === 'gemini') { $apiKey = $sMap['gemini_api_key'] ?? ''; $model = 'gemini-1.5-flash'; }
        elseif ($provider === 'claude') { $apiKey = $sMap['claude_api_key'] ?? ''; $model = 'claude-3-haiku-20240307'; }
        elseif ($provider === 'openrouter') { $apiKey = $sMap['openrouter_api_key'] ?? ''; $model = 'openai/gpt-4o'; }
        elseif ($provider === 'moondream') { $apiKey = $sMap['ai_moondream_api_key'] ?? ''; $model = 'moondream'; }
        $temp = floatval($sMap['ai_temperature'] ?? 0.7);
        $maxTokens = intval($sMap['ai_max_tokens'] ?? 2048);
        if (empty($apiKey)) {
            jsonResponse(['analysis' => '', 'provider' => $provider, 'configured' => false, 'needs_fallback' => true]);
            break;
        }
        $headers = [];
        $body = '';
        $url = '';
        if ($provider === 'openai' || $provider === 'openrouter') {
            $url = ($provider === 'openai' ? 'https://api.openai.com/v1/chat/completions' : 'https://openrouter.ai/api/v1/chat/completions');
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
            $body = json_encode([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temp,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => 'Analyze this image: ' . $imageName],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageData, 'detail' => 'auto']]
                    ]]
                ]
            ]);
        } elseif ($provider === 'gemini') {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;
            $headers = ['Content-Type: application/json'];
            $body = json_encode([
                'contents' => [['parts' => [
                    ['text' => $systemPrompt . "\n\nAnalyze this image: " . $imageName],
                    ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => str_replace(['data:image/', 'base64,'], '', $imageData)]]
                ]]],
                'generationConfig' => ['temperature' => $temp, 'maxOutputTokens' => $maxTokens]
            ]);
        } elseif ($provider === 'claude') {
            $url = 'https://api.anthropic.com/v1/messages';
            $headers = ['Content-Type: application/json', 'x-api-key' => $apiKey, 'anthropic-version: 2023-06-01'];
            $body = json_encode([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => str_replace(['data:image/', 'base64,'], '', $imageData)]],
                    ['type' => 'text', 'text' => 'Analyze this image: ' . $imageName]
                ]]]
            ]);
        } elseif ($provider === 'moondream') {
            $url = 'https://api.moondream.ai/v1/query';
            $headers = ['Content-Type: application/json', 'X-Moondream-Auth: ' . $apiKey];
            $body = json_encode([
                'image_url' => $imageData,
                'question' => $systemPrompt . "\n\nAnalyze this image: " . $imageName
            ]);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error || $httpCode !== 200) {
            jsonResponse(['analysis' => 'Image received. Analysis temporarily unavailable (API error). Please try again.', 'provider' => $provider, 'configured' => true, 'error' => $error ?: 'HTTP ' . $httpCode]);
            break;
        }
        $json = json_decode($result, true);
        $analysis = '';
        if ($provider === 'openai' || $provider === 'openrouter') {
            $analysis = $json['choices'][0]['message']['content'] ?? 'Could not analyze image.';
        } elseif ($provider === 'gemini') {
            $analysis = $json['candidates'][0]['content']['parts'][0]['text'] ?? 'Could not analyze image.';
        } elseif ($provider === 'claude') {
            $analysis = $json['content'][0]['text'] ?? 'Could not analyze image.';
        } elseif ($provider === 'moondream') {
            $analysis = $json['answer'] ?? $json['result'] ?? 'Could not analyze image.';
        }
        jsonResponse(['analysis' => $analysis, 'provider' => $provider, 'configured' => true]);
        break;

    case 'subscribe_teacher':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        if ($_SESSION['role'] !== 'student') jsonResponseError('Only students can subscribe', 403);
        $input = json_decode(file_get_contents('php://input'), true);
        $teacherId = intval($input['teacher_id'] ?? 0);
        $packageId = intval($input['package_id'] ?? 0);
        $amount = floatval($input['amount'] ?? 0);
        $txId = sanitize($input['transaction_id'] ?? '');
        if (!$teacherId || !$packageId || !$amount || !$txId) jsonResponseError('teacher_id, package_id, amount, and transaction_id required');
        $existing = $db->query('subscriptions');
        foreach ($existing as $s) {
            if (($s['type'] ?? 'teacher') !== 'teacher') continue;
            if (intval($s['student_id']) === intval($_SESSION['user_id']) && intval($s['teacher_id']) === $teacherId) {
                if ($s['status'] === 'approved') jsonResponseError('Already subscribed to this teacher');
                if ($s['status'] === 'pending') jsonResponseError('You already have a pending subscription for this teacher');
            }
        }
        $id = $db->insert('subscriptions', [
            'student_id' => intval($_SESSION['user_id']),
            'teacher_id' => $teacherId,
            'package_id' => $packageId,
            'amount' => $amount,
            'transaction_id' => $txId,
            'status' => 'pending',
            'type' => 'teacher',
            'created_at' => date('Y-m-d H:i:s'),
            'approved_at' => null
        ]);
        jsonResponse(['success' => true, 'id' => $id]);
        break;

    case 'my_subscriptions':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id']);
        $allSubs = $db->query('subscriptions');
        $result = [];
        foreach ($allSubs as $s) {
            if (($s['type'] ?? 'teacher') !== 'teacher') continue;
            if (intval($s['student_id']) === $uid) {
                $teacher = null;
                $teachers = $db->query('teachers');
                foreach ($teachers as $t) {
                    if (intval($t['user_id']) === intval($s['teacher_id']) || intval($t['id']) === intval($s['teacher_id'])) {
                        $teacher = $t;
                        break;
                    }
                }
                $teacherUser = $teacher ? $db->find('users', 'id', $teacher['user_id']) : null;
                $pkg = $db->find('packages', 'id', $s['package_id']);
                $result[] = [
                    'id' => $s['id'],
                    'teacher_id' => $s['teacher_id'],
                    'teacher_name' => $teacherUser ? $teacherUser['name'] : 'Unknown',
                    'teacher_subject' => $teacher ? $teacher['subject'] : '',
                    'package_name' => $pkg ? $pkg['name'] : '',
                    'amount' => $s['amount'],
                    'transaction_id' => $s['transaction_id'],
                    'status' => $s['status'],
                    'created_at' => $s['created_at'],
                    'approved_at' => $s['approved_at']
                ];
            }
        }
        jsonResponse($result);
        break;

    case 'is_subscribed':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id']);
        $teacherId = intval($_GET['teacher_id'] ?? 0);
        if (!$teacherId) jsonResponseError('teacher_id required');
        $allSubs = $db->query('subscriptions');
        $found = null;
        foreach ($allSubs as $s) {
            if (($s['type'] ?? 'teacher') !== 'teacher') continue;
            if (intval($s['student_id']) === $uid && intval($s['teacher_id']) === $teacherId) {
                $found = $s;
                break;
            }
            if (intval($s['student_id']) === $uid) {
                $teacherRec = $db->find('teachers', 'user_id', $teacherId);
                if ($teacherRec && intval($s['teacher_id']) === intval($teacherRec['id'])) {
                    $found = $s;
                    break;
                }
            }
        }
        jsonResponse([
            'subscribed' => $found !== null && $found['status'] === 'approved',
            'status' => $found ? $found['status'] : null,
            'subscription_id' => $found ? $found['id'] : null
        ]);
        break;

    case 'subscription_requests':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        if ($_SESSION['role'] !== 'admin') jsonResponseError('Admin only', 403);
        $type = $_GET['type'] ?? 'teacher';
        $allSubs = $db->query('subscriptions');
        $result = [];
        foreach ($allSubs as $s) {
            $subType = $s['type'] ?? 'teacher';
            if ($subType !== $type) continue;
            $student = $db->find('users', 'id', $s['student_id']);
            $teacher = null;
            $teachers = $db->query('teachers');
            foreach ($teachers as $t) {
                if (intval($t['user_id']) === intval($s['teacher_id']) || intval($t['id']) === intval($s['teacher_id'])) {
                    $teacher = $t;
                    break;
                }
            }
            $teacherUser = $teacher ? $db->find('users', 'id', $teacher['user_id']) : null;
            $pkg = $db->find('packages', 'id', $s['package_id']);
            $result[] = [
                'id' => $s['id'],
                'student_id' => $s['student_id'],
                'student_name' => $student ? $student['name'] : 'Unknown',
                'student_email' => $student ? $student['email'] : '',
                'teacher_id' => $s['teacher_id'],
                'teacher_name' => $type === 'teacher' ? ($teacherUser ? $teacherUser['name'] : 'Unknown') : '-',
                'teacher_subject' => $type === 'teacher' ? ($teacher ? $teacher['subject'] : '') : '',
                'package_name' => $pkg ? $pkg['name'] : '',
                'amount' => $s['amount'],
                'transaction_id' => $s['transaction_id'],
                'status' => $s['status'],
                'type' => $subType,
                'created_at' => $s['created_at'],
                'approved_at' => $s['approved_at']
            ];
        }
        jsonResponse($result);
        break;

    case 'approve_subscription':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        if ($_SESSION['role'] !== 'admin') jsonResponseError('Admin only', 403);
        $input = json_decode(file_get_contents('php://input'), true);
        $subId = intval($input['id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$subId || !in_array($action, ['approve', 'reject'])) jsonResponseError('id and action (approve/reject) required');
        $sub = $db->find('subscriptions', 'id', $subId);
        if (!$sub) jsonResponseError('Subscription not found');
        if (($sub['status'] ?? '') !== 'pending' && ($sub['status'] ?? '') !== 'teacher_approved') jsonResponseError('Subscription is not pending approval');
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $updateData = ['status' => $newStatus];
        if ($action === 'approve') $updateData['approved_at'] = date('Y-m-d H:i:s');
        $db->update('subscriptions', $subId, $updateData);
        if ($action === 'approve' && ($sub['type'] ?? 'teacher') === 'platform') {
            $pkg = $db->find('packages', 'id', $sub['package_id']);
            $duration = $pkg ? intval($pkg['duration']) : 30;
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $duration . ' days'));
            $db->update('users', $sub['student_id'], ['is_premium' => 1, 'premium_expires_at' => $expiresAt]);
        }
        jsonResponse(['success' => true, 'status' => $newStatus]);
        break;

    case 'subscribe_platform':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        if ($_SESSION['role'] !== 'student') jsonResponseError('Only students can subscribe', 403);
        $input = json_decode(file_get_contents('php://input'), true);
        $packageId = intval($input['package_id'] ?? 0);
        $amount = floatval($input['amount'] ?? 0);
        $txId = sanitize($input['transaction_id'] ?? '');
        $payMethod = sanitize($input['payment_method'] ?? 'bkash');
        if (!$packageId || !$amount || !$txId) jsonResponseError('package_id, amount, and transaction_id required');
        $uid = intval($_SESSION['user_id']);
        $existing = $db->query('subscriptions');
        foreach ($existing as $s) {
            if (($s['type'] ?? '') !== 'platform') continue;
            if (intval($s['student_id']) === $uid && ($s['status'] === 'approved' || $s['status'] === 'pending')) {
                jsonResponseError('You already have an active or pending platform subscription');
            }
        }
        $id = $db->insert('subscriptions', [
            'student_id' => $uid,
            'teacher_id' => null,
            'package_id' => $packageId,
            'amount' => $amount,
            'transaction_id' => $txId,
            'status' => 'pending',
            'type' => 'platform',
            'created_at' => date('Y-m-d H:i:s'),
            'approved_at' => null
        ]);
        jsonResponse(['success' => true, 'id' => $id]);
        break;

    case 'my_platform_subscription':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id']);
        $user = $db->find('users', 'id', $uid);
        $allSubs = $db->query('subscriptions');
        $found = null;
        foreach ($allSubs as $s) {
            if (($s['type'] ?? '') !== 'platform') continue;
            if (intval($s['student_id']) === $uid && ($s['status'] === 'approved' || $s['status'] === 'pending')) {
                $found = $s;
                if ($s['status'] === 'approved') break;
            }
        }
        $pkg = $found ? $db->find('packages', 'id', $found['package_id']) : null;
        jsonResponse([
            'is_premium' => intval($user['is_premium'] ?? 0) === 1,
            'expires_at' => $user['premium_expires_at'] ?? null,
            'subscription' => $found ? [
                'id' => $found['id'],
                'package_name' => $pkg ? $pkg['name'] : '',
                'amount' => $found['amount'],
                'status' => $found['status'],
                'created_at' => $found['created_at'],
                'approved_at' => $found['approved_at']
            ] : null
        ]);
        break;

    case 'is_premium':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        $uid = intval($_SESSION['user_id']);
        $user = $db->find('users', 'id', $uid);
        jsonResponse([
            'is_premium' => intval($user['is_premium'] ?? 0) === 1,
            'expires_at' => $user['premium_expires_at'] ?? null
        ]);
        break;

    case 'teacher_subscription_requests':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        if ($_SESSION['role'] !== 'teacher') jsonResponseError('Teachers only', 403);
        $uid = intval($_SESSION['user_id']);
        $allSubs = $db->query('subscriptions');
        $result = [];
        foreach ($allSubs as $s) {
            if (($s['type'] ?? 'teacher') !== 'teacher') continue;
            if (intval($s['teacher_id']) !== $uid) continue;
            $student = $db->find('users', 'id', $s['student_id']);
            $pkg = $db->find('packages', 'id', $s['package_id']);
            $result[] = [
                'id' => $s['id'],
                'student_id' => $s['student_id'],
                'student_name' => $student ? $student['name'] : 'Unknown',
                'student_class' => $student ? ($student['class'] ?? '') : '',
                'package_name' => $pkg ? $pkg['name'] : '',
                'amount' => $s['amount'],
                'status' => $s['status'],
                'created_at' => $s['created_at']
            ];
        }
        jsonResponse($result);
        break;

    case 'approve_teacher_subscription':
        if (!isLoggedIn()) jsonResponseError('Not logged in', 401);
        if ($_SESSION['role'] !== 'teacher') jsonResponseError('Teachers only', 403);
        $uid = intval($_SESSION['user_id']);
        $input = json_decode(file_get_contents('php://input'), true);
        $subId = intval($input['id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$subId || !in_array($action, ['approve', 'reject'])) jsonResponseError('id and action (approve/reject) required');
        $sub = $db->find('subscriptions', 'id', $subId);
        if (!$sub) jsonResponseError('Subscription not found');
        if (($sub['type'] ?? '') !== 'teacher') jsonResponseError('Not a teacher subscription');
        if (intval($sub['teacher_id']) !== $uid) jsonResponseError('Not your subscription');
        if ($sub['status'] !== 'pending') jsonResponseError('Subscription is not pending');
        $newStatus = $action === 'approve' ? 'teacher_approved' : 'rejected';
        $db->update('subscriptions', $subId, ['status' => $newStatus]);
        jsonResponse(['success' => true, 'status' => $newStatus]);
        break;

    default: jsonResponseError('Unknown action');
}