<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
requireDesktop();
$db = getDB();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$page = $_GET['page'] ?? 'dashboard';
$edit = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$view = isset($_GET['view']) ? intval($_GET['view']) : 0;
$msg = $_GET['msg'] ?? '';

if ($edit > 0 && isset($_GET['del_table'])) {
    $allowed = ['users','subjects','homework','exams','library','book_content','question_bank','chapters','announcements','live_classes','live_schedule','notifications','student_progress','homework_submissions','exam_results','badges','calendar_events','subscriptions','packages','settings','teachers'];
    if (in_array($_GET['del_table'], $allowed)) { $db->delete($_GET['del_table'], $edit); header("Location: ?page=$page&msg=deleted"); exit; }
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) { die('Invalid CSRF token'); }
    $a = $_POST['action'];
    if ($a === 'add_user') { $uid=$db->insert('users', ['name'=>sanitize($_POST['name']),'email'=>sanitize($_POST['email']),'password'=>password_hash($_POST['password'],PASSWORD_DEFAULT),'role'=>sanitize($_POST['role']),'class'=>sanitize($_POST['class']??''),'phone'=>sanitize($_POST['phone']??''),'school'=>sanitize($_POST['school']??''),'is_premium'=>0]); if(sanitize($_POST['role'])==='teacher'&&isset($_POST['t_subject'])&&$_POST['t_subject']!==''){$db->insert('teachers',['user_id'=>$uid,'subject'=>sanitize($_POST['t_subject']),'class_name'=>sanitize($_POST['t_class_name']??'All'),'experience'=>intval($_POST['t_experience']??1),'bio'=>sanitize($_POST['t_bio']??''),'featured_video'=>'','rating'=>0,'total_students'=>0,'total_classes'=>0,'is_featured'=>0,'is_top_rated'=>0,'is_popular'=>0,'is_new'=>0,'is_active'=>1]);} header('Location: ?page=users&msg=added'); exit; }
    if ($a === 'edit_user') { $d = ['name'=>sanitize($_POST['name']),'email'=>sanitize($_POST['email']),'role'=>sanitize($_POST['role']),'class'=>sanitize($_POST['class']??''),'phone'=>sanitize($_POST['phone']??''),'school'=>sanitize($_POST['school']??''),'is_premium'=>intval($_POST['is_premium']??0)]; if(!empty($_POST['password'])) $d['password']=password_hash($_POST['password'],PASSWORD_DEFAULT); $db->update('users',intval($_POST['id']),$d); if(sanitize($_POST['role'])==='teacher'&&isset($_POST['t_subject'])&&$_POST['t_subject']!==''){$etp=$db->queryOne('SELECT * FROM teachers WHERE user_id='.intval($_POST['id']));$td=['user_id'=>intval($_POST['id']),'subject'=>sanitize($_POST['t_subject']),'class_name'=>sanitize($_POST['t_class_name']??'All'),'experience'=>intval($_POST['t_experience']??1),'bio'=>sanitize($_POST['t_bio']??''),'featured_video'=>'','rating'=>0,'total_students'=>0,'total_classes'=>0,'is_featured'=>0,'is_top_rated'=>0,'is_popular'=>0,'is_new'=>0,'is_active'=>1];if($etp){$db->update('teachers',$etp['id'],$td);}else{$db->insert('teachers',$td);}} header('Location: ?page=users&msg=updated'); exit; }
    if ($a === 'bulk_upload_users') {
        $validClasses = ['Class 7','Class 8','Class 9 Science','Class 9 Commerce','Class 9 Arts','Class 10 Science','Class 10 Commerce','Class 10 Arts'];
        $added=0; $skipped=0; $errors=[];
        if(isset($_FILES['csv_file'])&&$_FILES['csv_file']['error']===0){
            $file=fopen($_FILES['csv_file']['tmp_name'],'r');
            if($file){$header=fgetcsv($file);while(($row=fgetcsv($file))!==false){
                if(count($row)<4){$skipped++;continue;}
                $name=trim($row[0]??''); $email=trim($row[1]??''); $password=trim($row[2]??''); $class=trim($row[3]??'');
                $phone=trim($row[4]??''); $school=trim($row[5]??''); $role=trim($row[6]??'student');
                $subject=trim($row[7]??''); $className=trim($row[8]??'All'); $experience=intval($row[9]??1); $bio=trim($row[10]??'');
                if(empty($name)||empty($email)||empty($password)){ $errors[]="Skipped: missing name/email/password for '$email'"; $skipped++; continue; }
                if(strlen($password)<6){ $errors[]="Skipped: password too short for '$email'"; $skipped++; continue; }
                if($db->find('users','email',$email)){ $errors[]="Skipped: duplicate email '$email'"; $skipped++; continue; }
                if($role!=='teacher'){$role='student';}
                if($role==='student'&&$class&&!in_array($class,$validClasses)){ $errors[]="Skipped: invalid class '$class' for '$email'"; $skipped++; continue; }
                $uid=$db->insert('users',['name'=>sanitize($name),'email'=>sanitize($email),'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'class'=>sanitize($class),'phone'=>sanitize($phone),'school'=>sanitize($school),'is_premium'=>0]);
                if($role==='teacher'&&$subject!==''){
                    $db->insert('teachers',['user_id'=>$uid,'subject'=>sanitize($subject),'class_name'=>sanitize($className?:'All'),'experience'=>$experience,'bio'=>sanitize($bio),'featured_video'=>'','rating'=>0,'total_students'=>0,'total_classes'=>0,'is_featured'=>0,'is_top_rated'=>0,'is_popular'=>0,'is_new'=>0,'is_active'=>1]);
                }
                $added++;
            } fclose($file);}
        }
        $msgParam=$added>0?'bulk_upload&count='.$added.'&skipped='.$skipped:'bulk_upload_fail';
        header('Location: ?page=users&msg='.$msgParam); exit;
    }
    if ($a === 'add_subject') { $db->insert('subjects', ['name'=>sanitize($_POST['name']),'icon'=>sanitize($_POST['icon']),'color'=>sanitize($_POST['color'])]); header('Location: ?page=subjects&msg=added'); exit; }
    if ($a === 'edit_subject') { $db->update('subjects',intval($_POST['id']),['name'=>sanitize($_POST['name']),'icon'=>sanitize($_POST['icon']),'color'=>sanitize($_POST['color'])]); header('Location: ?page=subjects&msg=updated'); exit; }
    if ($a === 'add_homework') { $db->insert('homework', ['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'teacher_id'=>intval($_POST['teacher_id']??1),'description'=>sanitize($_POST['description']??''),'due_date'=>sanitize($_POST['due_date']),'total_marks'=>intval($_POST['total_marks']),'status'=>sanitize($_POST['status']??'pending')]); header('Location: ?page=homework&msg=added'); exit; }
    if ($a === 'edit_homework') { $db->update('homework',intval($_POST['id']),['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'description'=>sanitize($_POST['description']??''),'due_date'=>sanitize($_POST['due_date']),'total_marks'=>intval($_POST['total_marks']),'status'=>sanitize($_POST['status'])]); header('Location: ?page=homework&msg=updated'); exit; }
    if ($a === 'add_exam') { $db->insert('exams', ['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'teacher_id'=>intval($_POST['teacher_id']??1),'exam_date'=>sanitize($_POST['exam_date']),'total_marks'=>intval($_POST['total_marks']),'duration'=>intval($_POST['duration']),'type'=>sanitize($_POST['type']),'status'=>sanitize($_POST['status']??'upcoming')]); header('Location: ?page=exams&msg=added'); exit; }
    if ($a === 'edit_exam') { $db->update('exams',intval($_POST['id']),['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'exam_date'=>sanitize($_POST['exam_date']),'total_marks'=>intval($_POST['total_marks']),'duration'=>intval($_POST['duration']),'type'=>sanitize($_POST['type']),'status'=>sanitize($_POST['status'])]); header('Location: ?page=exams&msg=updated'); exit; }
    if ($a === 'add_announcement') { $db->insert('announcements', ['title'=>sanitize($_POST['title']),'message'=>sanitize($_POST['message']),'category'=>sanitize($_POST['category']),'target_class'=>sanitize($_POST['target_class']),'is_pinned'=>intval($_POST['is_pinned']??0),'teacher_id'=>intval($_POST['teacher_id']??1)]); header('Location: ?page=announcements&msg=added'); exit; }
    if ($a === 'edit_announcement') { $db->update('announcements',intval($_POST['id']),['title'=>sanitize($_POST['title']),'message'=>sanitize($_POST['message']),'category'=>sanitize($_POST['category']),'target_class'=>sanitize($_POST['target_class']),'is_pinned'=>intval($_POST['is_pinned']??0)]); header('Location: ?page=announcements&msg=updated'); exit; }
    if ($a === 'add_package') { $db->insert('packages', ['name'=>sanitize($_POST['name']),'price'=>floatval($_POST['price']),'duration'=>intval($_POST['duration']),'features'=>sanitize($_POST['features']),'is_active'=>1]); header('Location: ?page=packages&msg=added'); exit; }
    if ($a === 'edit_package') { $db->update('packages',intval($_POST['id']),['name'=>sanitize($_POST['name']),'price'=>floatval($_POST['price']),'duration'=>intval($_POST['duration']),'features'=>sanitize($_POST['features']),'is_active'=>intval($_POST['is_active']??1)]); header('Location: ?page=packages&msg=updated'); exit; }
    if ($a === 'add_chapter') { $db->insert('chapters', ['subject_id'=>intval($_POST['subject_id']),'title'=>sanitize($_POST['title']),'status'=>sanitize($_POST['status']??'not_started'),'pages'=>intval($_POST['pages']??10),'order'=>intval($_POST['order']??1)]); header('Location: ?page=chapters&msg=added'); exit; }
    if ($a === 'edit_chapter') { $db->update('chapters',intval($_POST['id']),['subject_id'=>intval($_POST['subject_id']),'title'=>sanitize($_POST['title']),'status'=>sanitize($_POST['status']??'not_started'),'pages'=>intval($_POST['pages']??10),'order'=>intval($_POST['order']??1)]); header('Location: ?page=chapters&msg=updated'); exit; }
    if ($a === 'toggle_chapter') { $ch = $db->find('chapters','id',intval($_POST['id'])); $newStatus = ($ch['status'] ?? 'not_started') === 'completed' ? 'not_started' : 'completed'; $db->update('chapters',intval($_POST['id']),['status'=>$newStatus]); header('Location: ?page=chapters&msg=toggled'); exit; }
    if ($a === 'add_question') { $opts=[]; for($i=1;$i<=6;$i++){if(!empty($_POST['opt_'.$i]))$opts[]=sanitize($_POST['opt_'.$i]);} $db->insert('question_bank', ['subject_id'=>intval($_POST['subject_id']),'chapter'=>sanitize($_POST['chapter']),'type'=>sanitize($_POST['type']),'question'=>sanitize($_POST['question']),'options'=>$opts,'correct'=>intval($_POST['correct_answer']??0),'marks'=>intval($_POST['marks']??1),'difficulty'=>sanitize($_POST['difficulty']??'easy')]); header('Location: ?page=questions&msg=added'); exit; }
    if ($a === 'edit_question') { $opts=[]; for($i=1;$i<=6;$i++){if(!empty($_POST['opt_'.$i]))$opts[]=sanitize($_POST['opt_'.$i]);} $db->update('question_bank',intval($_POST['id']),['subject_id'=>intval($_POST['subject_id']),'chapter'=>sanitize($_POST['chapter']),'type'=>sanitize($_POST['type']),'question'=>sanitize($_POST['question']),'options'=>$opts,'correct'=>intval($_POST['correct_answer']??0),'marks'=>intval($_POST['marks']??1),'difficulty'=>sanitize($_POST['difficulty']??'easy')]); header('Location: ?page=questions&msg=updated'); exit; }
    if ($a === 'add_bank_question') { $db->insert('question_bank', ['subject_id'=>intval($_POST['subject_id']),'type'=>sanitize($_POST['type']),'question'=>sanitize($_POST['question']),'correct'=>sanitize($_POST['correct_answer']??''),'marks'=>intval($_POST['marks']??1),'difficulty'=>sanitize($_POST['difficulty']??'easy')]); header('Location: ?page=bank-questions&msg=added'); exit; }
    if ($a === 'edit_bank_question') { $db->update('question_bank',intval($_POST['id']),['subject_id'=>intval($_POST['subject_id']),'type'=>sanitize($_POST['type']),'question'=>sanitize($_POST['question']),'correct'=>sanitize($_POST['correct_answer']??''),'marks'=>intval($_POST['marks']??1),'difficulty'=>sanitize($_POST['difficulty']??'easy')]); header('Location: ?page=bank-questions&msg=updated'); exit; }
    if ($a === 'add_library') { $db->insert('library', ['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'class'=>sanitize($_POST['class']??'Class 8'),'type'=>sanitize($_POST['type']),'description'=>sanitize($_POST['description']??''),'file_url'=>sanitize($_POST['file_url']??''),'cover_url'=>sanitize($_POST['cover_url']??''),'uploader_id'=>0,'uploader_type'=>'admin','downloads'=>0,'is_active'=>1]); header('Location: ?page=library&msg=added'); exit; }
    if ($a === 'edit_library') { $db->update('library',intval($_POST['id']),['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'class'=>sanitize($_POST['class']??'Class 8'),'type'=>sanitize($_POST['type']),'description'=>sanitize($_POST['description']??''),'file_url'=>sanitize($_POST['file_url']??''),'cover_url'=>sanitize($_POST['cover_url']??''),'is_active'=>intval($_POST['is_active']??1)]); header('Location: ?page=library&msg=updated'); exit; }
    if ($a === 'add_live_class') { $db->insert('live_classes', ['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'teacher_id'=>intval($_POST['teacher_id']??1),'class_date'=>sanitize($_POST['class_date']),'start_time'=>sanitize($_POST['start_time']),'end_time'=>sanitize($_POST['end_time']),'status'=>'scheduled','meeting_link'=>sanitize($_POST['meeting_link']??'#')]); header('Location: ?page=live_classes&msg=added'); exit; }
    if ($a === 'edit_live_class') { $db->update('live_classes',intval($_POST['id']),['title'=>sanitize($_POST['title']),'subject_id'=>intval($_POST['subject_id']),'teacher_id'=>intval($_POST['teacher_id']??1),'class_date'=>sanitize($_POST['class_date']),'start_time'=>sanitize($_POST['start_time']),'end_time'=>sanitize($_POST['end_time']),'status'=>sanitize($_POST['status']),'meeting_link'=>sanitize($_POST['meeting_link']??'#')]); header('Location: ?page=live_classes&msg=updated'); exit; }
    if ($a === 'add_teacher_profile') { $db->insert('teachers', ['user_id'=>intval($_POST['user_id']),'subject'=>sanitize($_POST['subject']),'class_name'=>sanitize($_POST['class_name']??'All'),'experience'=>intval($_POST['experience']),'bio'=>sanitize($_POST['bio']??''),'featured_video'=>sanitize($_POST['featured_video']??''),'rating'=>0,'total_students'=>0,'total_classes'=>0,'is_featured'=>intval($_POST['is_featured']??0),'is_top_rated'=>intval($_POST['is_top_rated']??0),'is_popular'=>intval($_POST['is_popular']??0),'is_new'=>intval($_POST['is_new']??0),'is_active'=>1]); header('Location: ?page=teachers&msg=added'); exit; }
    if ($a === 'edit_teacher') { $db->update('teachers',intval($_POST['id']),['subject'=>sanitize($_POST['subject']),'class_name'=>sanitize($_POST['class_name']??'All'),'experience'=>intval($_POST['experience']),'bio'=>sanitize($_POST['bio']??''),'featured_video'=>sanitize($_POST['featured_video']??''),'rating'=>floatval($_POST['rating']??0),'is_featured'=>intval($_POST['is_featured']??0),'is_top_rated'=>intval($_POST['is_top_rated']??0),'is_popular'=>intval($_POST['is_popular']??0),'is_new'=>intval($_POST['is_new']??0),'is_active'=>intval($_POST['is_active']??1)]); header('Location: ?page=teachers&msg=updated'); exit; }
    if ($a === 'update_settings') { foreach($_POST as $k=>$v){if($k==='action')continue;$e=$db->find('settings','key',$k);if($e){$db->update('settings',$e['id'],['value'=>sanitize($v)]);}else{$db->insert('settings',['key'=>$k,'value'=>sanitize($v)]);}} header('Location: ?page=settings&msg=updated'); exit; }
    if ($a === 'grade_submission') { $db->update('homework_submissions',intval($_POST['id']),['marks_obtained'=>intval($_POST['marks_obtained']),'comments'=>sanitize($_POST['comments']??''),'status'=>sanitize($_POST['status']??'graded'),'graded_at'=>date('Y-m-d H:i:s')]); header('Location: ?page=submissions&msg=updated'); exit; }
    if ($a === 'add_notification') { $db->insert('notifications', ['title'=>sanitize($_POST['title']),'message'=>sanitize($_POST['message']),'type'=>sanitize($_POST['type']),'target_role'=>sanitize($_POST['target_role']),'is_read'=>0]); header('Location: ?page=notifications&msg=added'); exit; }
    if ($a === 'send_broadcast') { foreach($db->findAll('users','role',$_POST['target_role']??'student') as $u){$db->insert('notifications',['title'=>sanitize($_POST['title']),'message'=>sanitize($_POST['message']),'type'=>'broadcast','target_role'=>$u['role'],'is_read'=>0]);} header('Location: ?page=notifications&msg=broadcast'); exit; }
    if ($a === 'add_calendar_event') { $db->insert('calendar_events', ['title'=>sanitize($_POST['title']),'date'=>sanitize($_POST['date']),'type'=>sanitize($_POST['type']),'color'=>sanitize($_POST['color']??'#4F46E5')]); header('Location: ?page=calendar_events&msg=added'); exit; }
    if ($a === 'edit_calendar_event') { $db->update('calendar_events',intval($_POST['id']),['title'=>sanitize($_POST['title']),'date'=>sanitize($_POST['date']),'type'=>sanitize($_POST['type']),'color'=>sanitize($_POST['color']??'#4F46E5')]); header('Location: ?page=calendar_events&msg=updated'); exit; }
    if ($a === 'add_class_schedule') { $time=sanitize($_POST['start_time']??'').' - '.sanitize($_POST['end_time']??''); $db->insert('class_schedule', ['subject'=>sanitize($_POST['subject']),'topic'=>sanitize($_POST['topic']),'class_name'=>sanitize($_POST['class_name']),'time'=>$time,'start_time'=>sanitize($_POST['start_time']??''),'end_time'=>sanitize($_POST['end_time']??''),'day'=>sanitize($_POST['day']),'teacher_name'=>sanitize($_POST['teacher_name']),'teacher_id'=>intval($_POST['teacher_id']??0),'students_count'=>intval($_POST['students_count']??0),'status'=>sanitize($_POST['status']??'upcoming')]); header('Location: ?page=class_schedule&msg=added'); exit; }
    if ($a === 'edit_class_schedule') { $time=sanitize($_POST['start_time']??'').' - '.sanitize($_POST['end_time']??''); $db->update('class_schedule',intval($_POST['id']),['subject'=>sanitize($_POST['subject']),'topic'=>sanitize($_POST['topic']),'class_name'=>sanitize($_POST['class_name']),'time'=>$time,'start_time'=>sanitize($_POST['start_time']??''),'end_time'=>sanitize($_POST['end_time']??''),'day'=>sanitize($_POST['day']),'teacher_name'=>sanitize($_POST['teacher_name']),'teacher_id'=>intval($_POST['teacher_id']??0),'students_count'=>intval($_POST['students_count']??0),'status'=>sanitize($_POST['status']??'upcoming')]); header('Location: ?page=class_schedule&msg=updated'); exit; }
    if ($a === 'add_report') { $db->insert('reports', ['student_id'=>intval($_POST['student_id']),'teacher_id'=>intval($_POST['teacher_id']??0),'subject'=>sanitize($_POST['subject']),'class'=>sanitize($_POST['class']),'grade'=>sanitize($_POST['grade']),'score'=>intval($_POST['score']),'behavior'=>sanitize($_POST['behavior']??'Good'),'comment'=>sanitize($_POST['comment']??''),'date'=>sanitize($_POST['date']??date('Y-m-d'))]); header('Location: ?page=reports&msg=added'); exit; }
    if ($a === 'edit_report') { $db->update('reports',intval($_POST['id']),['student_id'=>intval($_POST['student_id']),'teacher_id'=>intval($_POST['teacher_id']??0),'subject'=>sanitize($_POST['subject']),'class'=>sanitize($_POST['class']),'grade'=>sanitize($_POST['grade']),'score'=>intval($_POST['score']),'behavior'=>sanitize($_POST['behavior']??'Good'),'comment'=>sanitize($_POST['comment']??''),'date'=>sanitize($_POST['date']??date('Y-m-d'))]); header('Location: ?page=reports&msg=updated'); exit; }
    if ($a === 'add_badge') { $db->insert('badges', ['student_id'=>intval($_POST['student_id']),'name'=>sanitize($_POST['name']),'icon'=>sanitize($_POST['icon']??'award'),'description'=>sanitize($_POST['description']??''),'earned_date'=>date('Y-m-d')]); header('Location: ?page=student_progress&msg=badge_added'); exit; }
    if ($a === 'approve_sub') {
        $sid=intval($_POST['id']);
        $sub=$db->find('subscriptions','id',$sid);
        $db->update('subscriptions',$sid,['status'=>'approved','approved_at'=>date('Y-m-d H:i:s')]);
        if($sub&&($sub['type']??'teacher')==='platform'){
            $pkg=$db->find('packages','id',$sub['package_id']);
            $duration=$pkg?intval($pkg['duration']):30;
            $expiresAt=date('Y-m-d H:i:s',strtotime('+'.$duration.' days'));
            $db->update('users',$sub['student_id'],['is_premium'=>1,'premium_expires_at'=>$expiresAt]);
        }
        $tab=$_POST['tab']??'platform';
        header('Location: ?page=subscriptions&tab='.$tab.'&msg=approved');exit;
    }
    if ($a === 'reject_sub') {
        $sid=intval($_POST['id']);
        $db->update('subscriptions',$sid,['status'=>'rejected']);
        $tab=$_POST['tab']??'platform';
        header('Location: ?page=subscriptions&tab='.$tab.'&msg=rejected');exit;
    }
}

// Fetch data
$allUsers=$db->queryAll('SELECT * FROM users ORDER BY id DESC');
$allSubjects=$db->queryAll('SELECT * FROM subjects ORDER BY id');
$allHomework=$db->queryAll('SELECT * FROM homework ORDER BY id DESC');
$allExams=$db->queryAll('SELECT * FROM exams ORDER BY id DESC');
$allAnnouncements=$db->queryAll('SELECT * FROM announcements ORDER BY id DESC');
$allPackages=$db->queryAll('SELECT * FROM packages ORDER BY id');
$allChapters=$db->queryAll('SELECT * FROM chapters ORDER BY id DESC');
$allQuestions=$db->queryAll('SELECT * FROM question_bank ORDER BY id DESC');
$allLibrary=$db->queryAll('SELECT * FROM library ORDER BY id DESC');
$allLiveClasses=$db->queryAll('SELECT * FROM live_classes ORDER BY id DESC');
$allTeachersRaw=$db->query('teachers');
$allUsersForT=$db->query('users');
$tUserMap=[];
foreach($allUsersForT as $tu)$tUserMap[$tu['id']]=$tu;
$allTeachers=[];
foreach($allTeachersRaw as $t){
    $t['name']=$tUserMap[$t['user_id']]['name']??'';
    $t['email']=$tUserMap[$t['user_id']]['email']??'';
    $allTeachers[]=$t;
}
$allSettings=$db->queryAll('SELECT * FROM settings ORDER BY id');
$allSubmissions=$db->queryAll('SELECT * FROM homework_submissions ORDER BY id DESC');
$allExamResults=$db->queryAll('SELECT * FROM exam_results ORDER BY id DESC');
$allMessages=$db->queryAll('SELECT * FROM messages ORDER BY id DESC');
$allNotifications=$db->queryAll('SELECT * FROM notifications ORDER BY id DESC');
$allCalendarEvents=$db->queryAll('SELECT * FROM calendar_events ORDER BY date');
$allClassSchedule=$db->queryAll('SELECT * FROM class_schedule ORDER BY FIELD(day,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), time');
$allReports=$db->queryAll('SELECT * FROM reports ORDER BY id DESC');
$allBadges=$db->queryAll('SELECT * FROM badges ORDER BY id DESC');
$allActivityLog=$db->queryAll('SELECT * FROM activity_log ORDER BY id DESC');
$students=count($db->findAll('users','role','student'));
$teachersCount=count($db->findAll('users','role','teacher'));
$premiumUsers=count($db->findAll('users','is_premium',1));

$sidebar=[
['s'=>'Main','i'=>[['dashboard','layout-dashboard','Dashboard']]],
['s'=>'User Management','i'=>[['users','users','All Users'],['teachers','user-check','Teachers'],['student_progress','bar-chart-3','Student Progress']]],
['s'=>'Academics','i'=>[['subjects','book-open','Subjects'],['chapters','layers','Chapters'],['class_schedule','clock','Class Schedule']]],
['s'=>'Content','i'=>[['homework','file-text','Homework'],['exams','calendar','Exams'],['library','library','Library']]],
['s'=>'Assessment','i'=>[['questions','help-circle','Question Bank'],['submissions','inbox','Submissions'],['results','award','Exam Results'],['exam_analytics','bar-chart','Exam Analytics'],['hw_analytics','pie-chart','Homework Analytics'],['reports','file-bar-chart','Reports']]],
['s'=>'Communication','i'=>[['live_classes','tv','Live Classes'],['announcements','megaphone','Announcements'],['messages','message-square','Messages'],['notifications','bell','Notifications'],['calendar','calendar-days','Calendar'],['calendar_events','calendar-range','Calendar Events']]],
['s'=>'Business','i'=>[['packages','credit-card','Packages'],['subscriptions','user-check','Subscriptions'],['revenue','dollar-sign','Revenue']]],
['s'=>'System','i'=>[['settings','settings','General Settings'],['ai_settings','brain','AI Settings'],['activity_log','list','Activity Log']]]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Panel - School App</title>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',-apple-system,sans-serif;background:#F0F2F5;min-height:100vh;display:flex}
.sidebar{width:260px;background:linear-gradient(180deg,#1E1B4B 0%,#312E81 100%);color:white;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:200;display:flex;flex-direction:column;transition:transform .3s}
.sidebar::-webkit-scrollbar{width:4px}.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.2);border-radius:2px}
.sb-head{padding:20px;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:12px}
.sb-head .logo{width:40px;height:40px;background:linear-gradient(135deg,#4F46E5,#7C3AED);border-radius:12px;display:flex;align-items:center;justify-content:center}
.sb-head .logo i{width:22px;height:22px}.sb-head h2{font-size:16px;font-weight:700}.sb-head p{font-size:10px;opacity:0.6}
.sb-nav{flex:1;padding:12px 0}
.sb-sec{padding:6px 20px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.4);margin-top:8px}
.sb-item{display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.7);cursor:pointer;transition:all .2s;text-decoration:none;border-left:3px solid transparent}
.sb-item:hover{background:rgba(255,255,255,0.08);color:white}.sb-item.active{background:rgba(79,70,229,0.4);color:white;border-left-color:#818CF8}
.sb-item i{width:18px;height:18px;flex-shrink:0}
.sb-foot{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.1)}.sb-foot a{color:rgba(255,255,255,0.6);font-size:12px;text-decoration:none;display:flex;align-items:center;gap:8px}.sb-foot a:hover{color:white}
.sb-toggle{display:none;position:fixed;top:12px;left:12px;z-index:250;width:40px;height:40px;background:#4F46E5;color:white;border:none;border-radius:10px;cursor:pointer;align-items:center;justify-content:center}
.sb-toggle i{width:20px;height:20px}.sb-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:199}
.main{flex:1;margin-left:260px;min-height:100vh}
.topbar{background:white;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;border-bottom:1px solid #E5E7EB}
.topbar h1{font-size:18px;font-weight:700;color:#1F2937}
.topbar .info{display:flex;align-items:center;gap:10px}.topbar .info .name{font-size:13px;font-weight:600;color:#1F2937}.topbar .info .role{font-size:10px;color:#9CA3AF}
.content{padding:24px}
.msg{background:#DCFCE7;color:#16A34A;padding:10px 16px;border-radius:10px;font-size:12px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:6px}
.sg{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:24px}
.sc{background:white;border-radius:16px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 4px rgba(0,0,0,0.05)}
.si{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.si i{width:24px;height:24px;color:white}
.si.p{background:linear-gradient(135deg,#6366F1,#8B5CF6)}.si.g{background:linear-gradient(135deg,#10B981,#059669)}.si.o{background:linear-gradient(135deg,#F59E0B,#F97316)}
.si.b{background:linear-gradient(135deg,#3B82F6,#2563EB)}.si.r{background:linear-gradient(135deg,#EF4444,#DC2626)}.si.pk{background:linear-gradient(135deg,#EC4899,#F472B6)}
.si.c{background:linear-gradient(135deg,#06B6D4,#0891B2)}.si.ind{background:linear-gradient(135deg,#8B5CF6,#A78BFA)}
.st h3{font-size:22px;font-weight:800;color:#1F2937}.st p{font-size:11px;color:#9CA3AF;font-weight:500}
.card{background:white;border-radius:16px;padding:20px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,0.05)}
.card h3{font-size:16px;font-weight:700;color:#1F2937;margin-bottom:16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.card h3 i{width:20px;height:20px;color:#4F46E5}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 14px;text-align:left;font-size:13px;border-bottom:1px solid #F3F4F6}
th{font-weight:600;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:.5px;background:#F9FAFB}
td{color:#374151}
.badge{padding:4px 10px;border-radius:8px;font-size:11px;font-weight:600;display:inline-block}
.bg{background:#DCFCE7;color:#16A34A}.bb{background:#DBEAFE;color:#2563EB}.bo{background:#FEF3C7;color:#D97706}
.br{background:#FEE2E2;color:#DC2626}.bp{background:#EDE9FE;color:#7C3AED}.bgr{background:#D1FAE5;color:#059669}
.btn{padding:8px 16px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:5px;transition:all .2s}
.btn-primary{background:#4F46E5;color:white}.btn-primary:hover{background:#4338CA}
.btn-danger{background:#EF4444;color:white}.btn-danger:hover{background:#DC2626}
.btn-success{background:#10B981;color:white}.btn-success:hover{background:#059669}
.btn-sm{padding:5px 10px;font-size:11px}
.btn-outline{background:transparent;border:1.5px solid #E5E7EB;color:#374151}.btn-outline:hover{border-color:#4F46E5;color:#4F46E5}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;outline:none;font-family:inherit;transition:border .2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#4F46E5;box-shadow:0 0 0 3px rgba(79,70,229,0.1)}
.actions{display:flex;gap:6px;flex-wrap:wrap}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.empty{text-align:center;padding:40px;color:#9CA3AF}
.empty i{width:48px;height:48px;margin-bottom:12px;opacity:0.3}
.search-bar{margin-bottom:16px;position:relative}
.search-bar input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;outline:none}
.search-bar input:focus{border-color:#4F46E5}
.search-bar i{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#9CA3AF}
.toggle{position:relative;width:44px;height:24px;background:#E5E7EB;border-radius:12px;cursor:pointer;transition:background .2s}
.toggle.on{background:#10B981}
.toggle::after{content:'';position:absolute;top:2px;left:2px;width:20px;height:20px;background:white;border-radius:50%;transition:transform .2s}
.toggle.on::after{transform:translateX(20px)}
.chart-bar{display:flex;align-items:flex-end;gap:8px;height:120px;padding:10px 0}
.chart-bar .bar{flex:1;background:linear-gradient(180deg,#4F46E5,#818CF8);border-radius:4px 4px 0 0;min-height:4px;position:relative}
.chart-bar .bar span{position:absolute;bottom:-18px;left:50%;transform:translateX(-50%);font-size:9px;color:#6B7280;white-space:nowrap}
.quick-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:24px}
.qa{background:white;border-radius:12px;padding:14px;text-align:center;cursor:pointer;transition:all .2s;text-decoration:none;color:#374151;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.qa:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(79,70,229,0.15)}
.qa i{width:24px;height:24px;color:#4F46E5;margin-bottom:6px}
.qa span{font-size:11px;font-weight:600;display:block}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.cal-head{padding:6px;text-align:center;font-size:10px;font-weight:700;color:#6B7280;text-transform:uppercase}
.cal-day{padding:8px;text-align:center;font-size:12px;border-radius:8px;cursor:pointer;min-height:36px;display:flex;flex-direction:column;align-items:center;gap:2px}
.cal-day:hover{background:#F3F4F6}.cal-day.today{background:#EEF2FF;color:#4F46E5;font-weight:700}
.cal-day.has-event{position:relative}.cal-day.has-event::after{content:'';width:4px;height:4px;background:#EF4444;border-radius:50%;position:absolute;bottom:2px}
.cal-day.empty{opacity:0.3}
.breadcrumb{display:flex;align-items:center;margin-bottom:16px;padding:8px 14px;background:#F9FAFB;border-radius:10px;font-size:13px}
.breadcrumb a{color:#4F46E5;text-decoration:none;font-weight:500;display:flex;align-items:center}
.breadcrumb a:hover{text-decoration:underline}
.rank-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.rank-1{background:linear-gradient(135deg,#FFD700,#FFA500);color:white}.rank-2{background:linear-gradient(135deg,#C0C0C0,#A0A0A0);color:white}
.rank-3{background:linear-gradient(135deg,#CD7F32,#B8860B);color:white}.rank-other{background:#F3F4F6;color:#6B7280}
@media(max-width:768px){
.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.sb-toggle{display:flex}.sb-overlay.show{display:block}
.main{margin-left:0}.topbar{padding-left:60px}.content{padding:16px}
.sg{grid-template-columns:repeat(2,1fr);gap:10px}.form-row{grid-template-columns:1fr}.grid-2,.grid-3{grid-template-columns:1fr}
.quick-actions{grid-template-columns:repeat(2,1fr)}table{font-size:11px}th,td{padding:8px 10px}
}
@media print{
.sidebar,.topbar,.sb-toggle,.sb-overlay,.btn,.search-bar,.actions,.breadcrumb,.msg,.quick-actions,.sg,.chart-bar,.cal-grid,.sb-foot{display:none!important}
.main{margin-left:0!important;padding:0!important}
.content{padding:10px!important}
.card{box-shadow:none!important;border:1px solid #E5E7EB;break-inside:avoid}
table{font-size:10px}th,td{padding:6px 8px;border:1px solid #E5E7EB}
body{background:white!important;color:black!important;-webkit-print-color-adjust:exact}
}
</style>
</head>
<body>
<button class="sb-toggle" onclick="toggleSidebar()"><i data-lucide="menu"></i></button>
<div class="sb-overlay" onclick="toggleSidebar()"></div>
<aside class="sidebar">
<div class="sb-head"><div class="logo"><i data-lucide="shield"></i></div><div><h2>Admin Panel</h2><p>School Management</p></div></div>
<nav class="sb-nav">
<?php foreach($sidebar as $sec): ?>
<div class="sb-sec"><?php echo $sec['s']; ?></div>
<?php foreach($sec['i'] as $item): ?>
<a href="?page=<?php echo $item[0]; ?>" class="sb-item <?php echo $page===$item[0]?'active':''; ?>"><i data-lucide="<?php echo $item[1]; ?>"></i><?php echo $item[2]; ?></a>
<?php endforeach; endforeach; ?>
</nav>
<div class="sb-foot"><a href="/logout.php"><i data-lucide="log-out"></i>Logout</a></div>
</aside>

<div class="main">
<div class="topbar">
<h1><?php
$t=['dashboard'=>'Dashboard','users'=>'All Users','teachers'=>'Teachers','subjects'=>'Subjects','chapters'=>'Chapters','homework'=>'Homework','exams'=>'Exams','questions'=>'Question Bank','submissions'=>'Submissions','results'=>'Exam Results','live_classes'=>'Live Classes','announcements'=>'Announcements','messages'=>'Messages','packages'=>'Packages','subscriptions'=>'Subscriptions','revenue'=>'Revenue','settings'=>'General Settings','ai_settings'=>'AI Settings','library'=>'Library','student_progress'=>'Student Progress','exam_analytics'=>'Exam Analytics','hw_analytics'=>'Homework Analytics','notifications'=>'Notifications','calendar'=>'Calendar','calendar_events'=>'Calendar Events','class_schedule'=>'Class Schedule','reports'=>'Reports','activity_log'=>'Activity Log'];
echo $t[$page]??ucfirst(str_replace('_',' ',$page));
?></h1>
<div class="info"><div><div class="name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div><div class="role">Administrator</div></div></div>
</div>
<div class="content">
<?php if($msg): ?><div class="msg"><i data-lucide="check-circle" style="width:14px;height:14px"></i><?php echo $msg==='broadcast'?'Broadcast sent!':($msg==='bulk_upload'?'Bulk upload complete: '.($_GET['count']??0).' added, '.($_GET['skipped']??0).' skipped.':($msg==='bulk_upload_fail'?'Upload failed. Please check CSV format.':ucfirst($msg).' successfully!')); ?></div><?php endif; ?>
<?php if($page!=='dashboard'): ?>
<div class="breadcrumb"><a href="?page=dashboard"><i data-lucide="home" style="width:12px;height:12px"></i></a><span style="margin:0 6px;color:#D1D5DB">/</span><span style="color:#6B7280;font-size:12px"><?php echo $t[$page]??ucfirst(str_replace('_',' ',$page)); ?></span></div>
<?php
$pageDescs=['users'=>'Manage all registered users, roles, and permissions','teachers'=>'Add, edit, and feature teachers with rankings and video trailers','subjects'=>'Add and organize subjects','chapters'=>'Manage book chapters and content','homework'=>'Create and grade homework assignments','exams'=>'Schedule and manage exams','questions'=>'Build your question bank for exams','submissions'=>'Review student homework submissions','results'=>'View exam results and scores','live_classes'=>'Schedule and manage live class sessions','announcements'=>'Send announcements to students and teachers','messages'=>'View platform messages','packages'=>'Create and manage subscription packages','subscriptions'=>'Approve or reject student subscriptions','revenue'=>'Track subscription revenue','settings'=>'General platform settings','ai_settings'=>'Configure AI tutor settings','library'=>'Manage library resources','student_progress'=>'Track student learning progress','exam_analytics'=>'Detailed exam performance analytics','hw_analytics'=>'Homework completion analytics','notifications'=>'Manage push notifications','calendar'=>'Weekly class timetable','calendar_events'=>'Manage calendar events','class_schedule'=>'Master class schedule','reports'=>'Student academic reports','activity_log'=>'View all user activity across the platform'];
$desc=$pageDescs[$page]??''; ?>
<div style="margin-bottom:20px"><h2 style="font-size:18px;margin:0 0 4px"><?php echo $t[$page]??ucfirst(str_replace('_',' ',$page)); ?></h2>
<?php if($desc): ?><p style="font-size:12px;color:#9CA3AF;margin:0"><?php echo $desc; ?></p><?php endif; ?></div>
<?php endif; ?>

<?php /* === DASHBOARD === */ ?>
<?php if($page==='dashboard'):
$studentsThisMonth=count($db->query("SELECT * FROM users WHERE role='student' AND created_at >= '".date('Y-m-01')."'"));
$teachersThisMonth=count($db->query("SELECT * FROM users WHERE role='teacher' AND created_at >= '".date('Y-m-01')."'"));
$growthPct=$students>0?round($studentsThisMonth/$students*100):0;
$avgScore=count($allExamResults)>0?round(array_sum(array_column($allExamResults,'percentage'))/count($allExamResults)):0;
$passRate=count($allExamResults)>0?round(count(array_filter($allExamResults,fn($r)=>$r['percentage']>=50))/count($allExamResults)*100):0;
$rev=0;$approvedSubsForRev=array_filter($db->query('subscriptions'),function($s){return$s['status']==='approved';});foreach($approvedSubsForRev as $s){$pkg=$db->find('packages','id',$s['package_id']);$rev+=floatval($s['amount']);}
$lastMonthRev=0;$lastMonthSubs=array_filter($db->query('subscriptions'),function($s){return$s['status']==='approved'&&date('Y-m',strtotime($s['approved_at']))===date('Y-m',strtotime('-1 month'));});foreach($lastMonthSubs as $s){$pkg=$db->find('packages','id',$s['package_id']);$lastMonthRev+=floatval($s['amount']);}
$revGrowth=$lastMonthRev>0?round(($rev-$lastMonthRev)/$lastMonthRev*100):($rev>0?100:0);
$totalActivity=count($allSubmissions)+count($allExamResults);
$pendingSubs=count(array_filter($allSubmissions,fn($s)=>$s['status']==='pending'));
$activeStudents=count(array_filter($allUsers,fn($u)=>$u['role']==='student'));
$engagementRate=$students>0?round($activeStudents/$students*100):0;
?>
<div class="sg">
<div class="sc" style="border-left:4px solid #6366F1"><div class="si p"><i data-lucide="users"></i></div><div class="st"><h3><?php echo $students; ?></h3><p>Students</p><div style="font-size:10px;color:#10B981;font-weight:600;margin-top:2px">+<?php echo $studentsThisMonth; ?> this month</div></div></div>
<div class="sc" style="border-left:4px solid #10B981"><div class="si g"><i data-lucide="user-check"></i></div><div class="st"><h3><?php echo $teachersCount; ?></h3><p>Teachers</p><div style="font-size:10px;color:#10B981;font-weight:600;margin-top:2px">+<?php echo $teachersThisMonth; ?> this month</div></div></div>
<div class="sc" style="border-left:4px solid #F59E0B"><div class="si o"><i data-lucide="dollar-sign"></i></div><div class="st"><h3><?php echo number_format($rev); ?> BDT</h3><p>Revenue</p><div style="font-size:10px;color:<?php echo $revGrowth>=0?'#10B981':'#EF4444'; ?>;font-weight:600;margin-top:2px"><?php echo $revGrowth>=0?'+':''; ?><?php echo $revGrowth; ?>% vs last month</div></div></div>
<div class="sc" style="border-left:4px solid #3B82F6"><div class="si b"><i data-lucide="activity"></i></div><div class="st"><h3><?php echo $totalActivity; ?></h3><p>Total Activity</p><div style="font-size:10px;color:#9CA3AF;margin-top:2px">submissions + results</div></div></div>
<div class="sc" style="border-left:4px solid #EF4444"><div class="si r"><i data-lucide="inbox"></i></div><div class="st"><h3><?php echo count($allSubmissions); ?></h3><p>Submissions</p><div style="font-size:10px;color:<?php echo $pendingSubs>0?'#F59E0B':'#10B981'; ?>;font-weight:600;margin-top:2px"><?php echo $pendingSubs; ?> pending</div></div></div>
<div class="sc" style="border-left:4px solid #EC4899"><div class="si pk"><i data-lucide="message-square"></i></div><div class="st"><h3><?php echo count($allMessages); ?></h3><p>Messages</p></div></div>
<div class="sc" style="border-left:4px solid #06B6D4"><div class="si c"><i data-lucide="bar-chart"></i></div><div class="st"><h3><?php echo $avgScore; ?>%</h3><p>Avg Score</p><div style="font-size:10px;color:<?php echo $avgScore>=70?'#10B981':'#EF4444'; ?>;font-weight:600;margin-top:2px"><?php echo $avgScore>=70?'Above target':'Below target'; ?></div></div></div>
<div class="sc" style="border-left:4px solid #8B5CF6"><div class="si ind"><i data-lucide="check-circle"></i></div><div class="st"><h3><?php echo $passRate; ?>%</h3><p>Pass Rate</p><div style="font-size:10px;color:#10B981;font-weight:600;margin-top:2px"><?php echo $passRate>=80?'Excellent':'Needs improvement'; ?></div></div></div>
</div>

<!-- Trend Summary -->
<div class="card" style="background:linear-gradient(135deg,#4F46E5,#7C3AED);color:white">
<h3 style="color:white;margin-bottom:12px"><i data-lucide="trending-up"></i>Weekly Trend</h3>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
<div style="text-align:center"><div style="font-size:22px;font-weight:800">+<?php echo $growthPct; ?>%</div><div style="font-size:11px;opacity:0.8">Student Growth</div></div>
<div style="text-align:center"><div style="font-size:22px;font-weight:800"><?php echo $engagementRate; ?>%</div><div style="font-size:11px;opacity:0.8">Active Students</div></div>
<div style="text-align:center"><div style="font-size:22px;font-weight:800"><?php echo $totalActivity; ?></div><div style="font-size:11px;opacity:0.8">Total Activity</div></div>
</div>
</div>

<h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:#1F2937">Quick Actions</h3>
<div class="quick-actions">
<a href="?page=library" class="qa"><i data-lucide="library"></i><span>Library</span></a>
<a href="?page=chapters" class="qa"><i data-lucide="layers"></i><span>Chapters</span></a>
<a href="?page=ai_settings" class="qa"><i data-lucide="brain"></i><span>AI Settings</span></a>
<a href="?page=packages" class="qa"><i data-lucide="credit-card"></i><span>Packages</span></a>
<a href="?page=rankings" class="qa"><i data-lucide="trophy"></i><span>Rankings</span></a>
<a href="?page=questions" class="qa"><i data-lucide="help-circle"></i><span>Questions</span></a>
<a href="?page=notifications" class="qa"><i data-lucide="bell"></i><span>Notifications</span></a>
<a href="?page=calendar" class="qa"><i data-lucide="calendar-days"></i><span>Calendar</span></a>
</div>
<div class="grid-2">
<div class="card">
<h3><i data-lucide="bar-chart-3"></i>Score Distribution</h3>
<div class="chart-bar">
<?php $buckets=[0,0,0,0,0];foreach($allExamResults as $r){$i=min(intval($r['percentage']/20),4);$buckets[$i]++;}$mx=max(1,max($buckets)); ?>
<?php $labels=['0-20%','20-40%','40-60%','60-80%','80-100%'];for($i=0;$i<5;$i++): ?>
<div class="bar" style="height:<?php echo $buckets[$i]>0?max(8,$buckets[$i]/$mx*100):2; ?>%"><span><?php echo $labels[$i]; ?></span></div>
<?php endfor; ?>
</div>
</div>
<div class="card">
<h3><i data-lucide="users"></i>Recent Users</h3>
<table><tr><th>Name</th><th>Role</th><th>Status</th></tr>
<?php foreach(array_slice($allUsers,0,6) as $u): ?>
<tr><td><strong><?php echo htmlspecialchars($u['name']); ?></strong><br><span style="font-size:10px;color:#9CA3AF"><?php echo htmlspecialchars($u['email']); ?></span></td>
<td><span class="badge <?php echo $u['role']==='admin'?'br':($u['role']==='teacher'?'bg':'bb'); ?>"><?php echo ucfirst($u['role']); ?></span></td>
<td><?php echo $u['is_premium']?'<span class="badge bo">Pro</span>':'Free'; ?></td></tr>
<?php endforeach; ?></table>
</div>
</div>

<?php /* === USERS === */ ?>
<?php elseif($page==='users'):
$eu=$edit>0?$db->find('users','id',$edit):null;
$euTeacher=$eu&&$eu['role']==='teacher'?$db->queryOne('SELECT * FROM teachers WHERE user_id='.$eu['id']):null; ?>
<div class="card">
<h3><i data-lucide="<?php echo $eu?'edit':'user-plus'; ?>"></i><?php echo $eu?'Edit User':'Add New User'; ?></h3>
<form method="POST" id="userForm"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $eu?'edit_user':'add_user'; ?>">
<?php if($eu): ?><input type="hidden" name="id" value="<?php echo $eu['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?php echo $eu?htmlspecialchars($eu['name']):''; ?>" required></div><div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo $eu?htmlspecialchars($eu['email']):''; ?>" required></div></div>
<div class="form-row"><div class="form-group"><label>Password <?php echo $eu?'(leave blank to keep)':''; ?></label><input type="password" name="password" <?php echo $eu?'':'required'; ?>></div><div class="form-group"><label>Role</label><select name="role" id="userRoleSelect" onchange="toggleTeacherFields()"><option value="student" <?php echo $eu&&$eu['role']==='student'?'selected':''; ?>>Student</option><option value="teacher" <?php echo $eu&&$eu['role']==='teacher'?'selected':''; ?>>Teacher</option><option value="admin" <?php echo $eu&&$eu['role']==='admin'?'selected':''; ?>>Admin</option></select></div></div>
<div class="form-row"><div class="form-group"><label>Class / Grade</label><select name="class"><option value="">-- Select Class --</option><option value="Class 7" <?php echo $eu&&$eu['class']==='Class 7'?'selected':''; ?>>Class 7</option><option value="Class 8" <?php echo $eu&&$eu['class']==='Class 8'?'selected':''; ?>>Class 8</option><option value="Class 9 Science" <?php echo $eu&&$eu['class']==='Class 9 Science'?'selected':''; ?>>Class 9 - Science</option><option value="Class 9 Commerce" <?php echo $eu&&$eu['class']==='Class 9 Commerce'?'selected':''; ?>>Class 9 - Commerce</option><option value="Class 9 Arts" <?php echo $eu&&$eu['class']==='Class 9 Arts'?'selected':''; ?>>Class 9 - Arts</option><option value="Class 10 Science" <?php echo $eu&&$eu['class']==='Class 10 Science'?'selected':''; ?>>Class 10 - Science</option><option value="Class 10 Commerce" <?php echo $eu&&$eu['class']==='Class 10 Commerce'?'selected':''; ?>>Class 10 - Commerce</option><option value="Class 10 Arts" <?php echo $eu&&$eu['class']==='Class 10 Arts'?'selected':''; ?>>Class 10 - Arts</option></select></div><div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo $eu?htmlspecialchars($eu['phone']??''):''; ?>" placeholder="01712345678"></div></div>
<div class="form-row"><div class="form-group"><label>School</label><input type="text" name="school" value="<?php echo $eu?htmlspecialchars($eu['school']??''):''; ?>" placeholder="School name"></div>
<?php if($eu): ?><div class="form-group"><label>Premium</label><select name="is_premium"><option value="0" <?php echo !$eu['is_premium']?'selected':''; ?>>Free</option><option value="1" <?php echo $eu['is_premium']?'selected':''; ?>>Premium</option></select></div><?php else: ?><div class="form-group"></div><?php endif; ?></div>

<div id="teacherFields" style="display:<?php echo $eu&&$eu['role']==='teacher'?'block':'none'; ?>;border-top:1px solid #E5E7EB;padding-top:16px;margin-top:8px">
<h4 style="margin:0 0 12px;font-size:14px;color:#6B7280">Teacher Profile</h4>
<div class="form-row"><div class="form-group"><label>Subject</label><select name="t_subject"><option value="">-- Select Subject --</option><?php foreach($allSubjects as $s): ?><option value="<?php echo htmlspecialchars($s['name']); ?>" <?php echo $euTeacher&&$euTeacher['subject']===$s['name']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Class / Group</label><select name="t_class_name"><option value="All">All Classes</option><option value="Class 7" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 7'?'selected':''; ?>>Class 7</option><option value="Class 8" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 8'?'selected':''; ?>>Class 8</option><option value="Class 9 Science" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 9 Science'?'selected':''; ?>>Class 9 - Science</option><option value="Class 9 Commerce" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 9 Commerce'?'selected':''; ?>>Class 9 - Commerce</option><option value="Class 9 Arts" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 9 Arts'?'selected':''; ?>>Class 9 - Arts</option><option value="Class 10 Science" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 10 Science'?'selected':''; ?>>Class 10 - Science</option><option value="Class 10 Commerce" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 10 Commerce'?'selected':''; ?>>Class 10 - Commerce</option><option value="Class 10 Arts" <?php echo $euTeacher&&($euTeacher['class_name']??'')==='Class 10 Arts'?'selected':''; ?>>Class 10 - Arts</option></select></div></div>
<div class="form-row"><div class="form-group"><label>Experience (years)</label><input type="number" name="t_experience" value="<?php echo $euTeacher?$euTeacher['experience']:1; ?>" min="0"></div><div class="form-group"><label>Bio</label><input type="text" name="t_bio" value="<?php echo $euTeacher?htmlspecialchars($euTeacher['bio']??''):''; ?>" placeholder="Short bio"></div></div>
</div>

<div style="display:flex;gap:8px;margin-top:16px"><button type="submit" class="btn btn-primary"><?php echo $eu?'Update':'Add User'; ?></button><?php if($eu): ?><a href="?page=users" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card">
<h3><i data-lucide="upload"></i>Bulk Upload Users</h3>
<p style="font-size:12px;color:#9CA3AF;margin:0 0 12px">Upload a CSV file to add multiple students or teachers at once.</p>
<form method="POST" enctype="multipart/form-data" id="bulkUploadForm">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="action" value="bulk_upload_users">
<div style="margin-bottom:8px"><label style="font-size:13px;font-weight:600">CSV File</label></div>
<div style="display:flex;gap:8px;align-items:center">
<input type="file" name="csv_file" accept=".csv" required style="flex:1;padding:8px;border:1px solid #D1D5DB;border-radius:6px">
<button type="submit" class="btn btn-primary"><i data-lucide="upload" style="width:14px;height:14px"></i> Upload</button>
</div>
<div style="margin-top:12px;padding:12px;background:#F9FAFB;border-radius:8px;font-size:12px;color:#6B7280">
<strong>CSV Format:</strong> name, email, password, class, phone, school, role, subject, class_name, experience, bio<br>
<strong>Required fields:</strong> name, email, password, class (for students)<br>
<strong>Teacher fields:</strong> subject, class_name, experience, bio (only needed when role=teacher)<br>
<a href="../assets/sample-users.csv" download style="color:#4F46E5">Download sample file</a> to see examples.
</div>
</form>
</div>
<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h3 style="margin-bottom:0"><i data-lucide="users"></i>All Users (<?php echo count($allUsers); ?>)</h3>
<div style="display:flex;gap:8px"><button class="btn btn-outline btn-sm" onclick="exportTable('users-table','users.csv')"><i data-lucide="download" style="width:12px;height:12px"></i>Export CSV</button></div></div>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search users..." oninput="filterTable(this,'users-table')"></div>
<table id="users-table"><tr><th>Name</th><th>Email</th><th>Role</th><th>Class</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allUsers as $u): ?>
<tr><td><strong><?php echo htmlspecialchars($u['name']); ?></strong></td><td style="font-size:12px"><?php echo htmlspecialchars($u['email']); ?></td>
<td><span class="badge <?php echo $u['role']==='admin'?'br':($u['role']==='teacher'?'bg':'bb'); ?>"><?php echo ucfirst($u['role']); ?></span></td>
<td><?php echo htmlspecialchars($u['class']??'-'); ?></td><td><?php echo $u['is_premium']?'<span class="badge bo">Pro</span>':'Free'; ?></td>
<td class="actions"><a href="?page=users&edit=<?php echo $u['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=users&edit=<?php echo $u['id']; ?>&del_table=users" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === TEACHERS === */ ?>
<?php elseif($page==='teachers'):
$et=$edit>0?$db->find('teachers','id',$edit):null;
$ranked=$allTeachers;usort($ranked,fn($a,$b)=>$b['rating'] <=> $a['rating']);
$subjectsJson=json_encode(array_map(fn($s)=>$s['name'],$allSubjects));
$teachersJson=[];
foreach($allTeachers as $t){$teachersJson[]=['id'=>$t['id'],'name'=>$t['name'],'email'=>$t['email'],'subject'=>$t['subject'],'class_name'=>$t['class_name']??'All','experience'=>$t['experience'],'bio'=>$t['bio']??'','featured_video'=>$t['featured_video']??'','rating'=>$t['rating'],'is_featured'=>intval($t['is_featured']),'is_top_rated'=>intval($t['is_top_rated']??0),'is_popular'=>intval($t['is_popular']??0),'is_new'=>intval($t['is_new']??0),'is_active'=>intval($t['is_active'])];}
$teachersJson=json_encode($teachersJson);
?>

<div class="card">
<h3><i data-lucide="user-plus"></i>Add Teacher Profile</h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="add_teacher_profile">
<div class="form-row">
<div class="form-group"><label>User Account</label><select name="user_id"><?php foreach($allUsers as $u): if($u['role']==='teacher'): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endif; endforeach; ?></select></div>
<div class="form-group"><label>Subject</label><select name="subject" required><option value="">-- Select Subject --</option><?php foreach($allSubjects as $s): ?><option value="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div></div>
<div class="form-row">
<div class="form-group"><label>Class / Group</label><select name="class_name"><option value="All">All Classes</option><option value="Class 7">Class 7</option><option value="Class 8">Class 8</option><option value="Class 9 Science">Class 9 - Science</option><option value="Class 9 Commerce">Class 9 - Commerce</option><option value="Class 9 Arts">Class 9 - Arts</option><option value="Class 10 Science">Class 10 - Science</option><option value="Class 10 Commerce">Class 10 - Commerce</option><option value="Class 10 Arts">Class 10 - Arts</option></select></div>
<div class="form-group"><label>Experience (years)</label><input type="number" name="experience" value="1" min="0"></div></div>
<div class="form-group"><label>Bio</label><textarea name="bio" rows="2" placeholder="Short bio..."></textarea></div>
<div class="form-group"><label>YouTube Video Link</label><input type="url" name="featured_video" placeholder="https://www.youtube.com/watch?v=..."></div>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin:8px 0">
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_featured" value="1" style="width:16px;height:16px"> Featured</label>
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_top_rated" value="1" style="width:16px;height:16px"> Top Rated</label>
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_popular" value="1" style="width:16px;height:16px"> Popular</label>
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_new" value="1" style="width:16px;height:16px"> New</label>
</div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary">Add Teacher</button></div></form></div>

<div id="editTeacherModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center">
<div style="background:white;border-radius:16px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto;padding:24px;position:relative">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
<h3 style="margin:0"><i data-lucide="edit-2"></i> Edit Teacher</h3>
<button onclick="closeEditModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#9CA3AF;padding:4px 8px">&times;</button>
</div>
<div id="editTeacherBanner" style="padding:10px 14px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;margin-bottom:16px;font-size:13px"></div>
<form method="POST" id="editTeacherForm"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="edit_teacher"><input type="hidden" name="id" id="edit_id">
<div class="form-row">
<div class="form-group"><label>Subject</label><select name="subject" id="edit_subject" required><option value="">-- Select Subject --</option></select></div>
<div class="form-group"><label>Class / Group</label><select name="class_name" id="edit_class_name"><option value="All">All Classes</option><option value="Class 7">Class 7</option><option value="Class 8">Class 8</option><option value="Class 9 Science">Class 9 - Science</option><option value="Class 9 Commerce">Class 9 - Commerce</option><option value="Class 9 Arts">Class 9 - Arts</option><option value="Class 10 Science">Class 10 - Science</option><option value="Class 10 Commerce">Class 10 - Commerce</option><option value="Class 10 Arts">Class 10 - Arts</option></select></div></div>
<div class="form-row">
<div class="form-group"><label>Experience (years)</label><input type="number" name="experience" id="edit_experience" min="0"></div>
<div class="form-group"><label>Rating</label><input type="number" name="rating" id="edit_rating" step="0.1" min="0" max="5"></div></div>
<div class="form-group"><label>Bio</label><textarea name="bio" id="edit_bio" rows="2" placeholder="Short bio..."></textarea></div>
<div class="form-group"><label>YouTube Video Link</label><input type="url" name="featured_video" id="edit_featured_video" placeholder="https://www.youtube.com/watch?v=..."></div>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin:8px 0">
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_featured" id="edit_is_featured" value="1" style="width:16px;height:16px"> Featured</label>
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_top_rated" id="edit_is_top_rated" value="1" style="width:16px;height:16px"> Top Rated</label>
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_popular" id="edit_is_popular" value="1" style="width:16px;height:16px"> Popular</label>
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_new" id="edit_is_new" value="1" style="width:16px;height:16px"> New</label>
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width:16px;height:16px"> Active</label>
</div>
<div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" onclick="closeEditModal()" class="btn btn-outline">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div></form>
</div>
</div>

<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h3 style="margin-bottom:0"><i data-lucide="user-check"></i>All Teachers (<?php echo count($allTeachers); ?>)</h3>
<div style="display:flex;gap:8px"><button class="btn btn-outline btn-sm" onclick="exportTable('teachers-table','teachers.csv')"><i data-lucide="download" style="width:12px;height:12px"></i>Export CSV</button></div></div>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search teachers..." oninput="filterTable(this,'teachers-table')"></div>
<table id="teachers-table"><tr><th>Rank</th><th>Teacher</th><th>Subject</th><th>Class</th><th>Exp</th><th>Rating</th><th>Students</th><th>Badges</th><th>Actions</th></tr>
<?php $rank=1;foreach($ranked as $t):
$badges='';
if($t['is_featured']) $badges.='<span class="badge bo">Featured</span> ';
if($t['is_top_rated']??0) $badges.='<span class="badge bg">Top Rated</span> ';
if($t['is_popular']??0) $badges.='<span class="badge bb">Popular</span> ';
if($t['is_new']??0) $badges.='<span class="badge bp">New</span> ';
if(!$t['is_active']) $badges.='<span class="badge br">Inactive</span>';
?>
<tr><td><div class="rank-num <?php echo $rank<=3?'rank-'.$rank:'rank-other'; ?>"><?php echo $rank; ?></div></td>
<td><strong><?php echo htmlspecialchars($t['name']); ?></strong><br><span style="font-size:10px;color:#9CA3AF"><?php echo htmlspecialchars($t['email']); ?></span></td>
<td><?php echo htmlspecialchars($t['subject']); ?></td><td><?php echo htmlspecialchars($t['class_name']??'All'); ?></td><td><?php echo $t['experience']; ?>yr</td>
<td><span class="badge bg">&#9733; <?php echo $t['rating']; ?></span></td><td><?php echo $t['total_students']; ?></td>
<td><?php echo $badges?:'<span style="color:#9CA3AF">-</span>'; ?></td>
<td class="actions"><button onclick="openEditModal(<?php echo $t['id']; ?>)" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></button><a href="?page=teachers&edit=<?php echo $t['id']; ?>&del_table=teachers" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php $rank++;endforeach; ?></table></div>

<script>
var _teachersData=<?php echo $teachersJson; ?>;
var _subjectsList=<?php echo $subjectsJson; ?>;
function openEditModal(tid){
var t=null;for(var i=0;i<_teachersData.length;i++){if(_teachersData[i].id==tid){t=_teachersData[i];break;}}
if(!t)return;
document.getElementById('edit_id').value=t.id;
document.getElementById('editTeacherBanner').innerHTML='<strong style="color:#166534">Editing:</strong> <span style="font-weight:600">'+t.name+'</span> <span style="color:#6B7280">('+t.email+')</span>';
var subSel=document.getElementById('edit_subject');subSel.innerHTML='<option value="">-- Select Subject --</option>';
for(var i=0;i<_subjectsList.length;i++){subSel.innerHTML+='<option value="'+_subjectsList[i]+'"'+(_subjectsList[i]===t.subject?' selected':'')+'>'+_subjectsList[i]+'</option>';}
document.getElementById('edit_class_name').value=t.class_name||'All';
document.getElementById('edit_experience').value=t.experience;
document.getElementById('edit_rating').value=t.rating;
document.getElementById('edit_bio').value=t.bio||'';
document.getElementById('edit_featured_video').value=t.featured_video||'';
document.getElementById('edit_is_featured').checked=!!t.is_featured;
document.getElementById('edit_is_top_rated').checked=!!t.is_top_rated;
document.getElementById('edit_is_popular').checked=!!t.is_popular;
document.getElementById('edit_is_new').checked=!!t.is_new;
document.getElementById('edit_is_active').checked=!!t.is_active;
document.getElementById('editTeacherModal').style.display='flex';
lucide.createIcons();
}
function closeEditModal(){document.getElementById('editTeacherModal').style.display='none';}
document.getElementById('editTeacherModal').addEventListener('click',function(e){if(e.target===this)closeEditModal();});
</script>

<?php /* === SUBJECTS === */ ?>
<?php elseif($page==='subjects'):
$es=$edit>0?$db->find('subjects','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $es?'edit':'plus-circle'; ?>"></i><?php echo $es?'Edit Subject':'Add Subject'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $es?'edit_subject':'add_subject'; ?>">
<?php if($es): ?><input type="hidden" name="id" value="<?php echo $es['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Name</label><input type="text" name="name" value="<?php echo $es?htmlspecialchars($es['name']):''; ?>" required></div><div class="form-group"><label>Icon</label><input type="text" name="icon" value="<?php echo $es?htmlspecialchars($es['icon']):'calculator'; ?>" required></div></div>
<div class="form-row"><div class="form-group"><label>Color</label><input type="color" name="color" value="<?php echo $es?$es['color']:'#6366F1'; ?>"></div><div class="form-group" style="display:flex;align-items:flex-end;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $es?'Update':'Add'; ?></button><?php if($es): ?><a href="?page=subjects" class="btn btn-outline">Cancel</a><?php endif; ?></div></div></form></div>
<div class="card"><h3><i data-lucide="book-open"></i>All Subjects (<?php echo count($allSubjects); ?>)</h3>
<table><tr><th>ID</th><th>Name</th><th>Icon</th><th>Color</th><th>Chapters</th><th>Actions</th></tr>
<?php foreach($allSubjects as $s): $cc=count($db->findAll('chapters','subject_id',$s['id'])); ?>
<tr><td><?php echo $s['id']; ?></td><td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td><td><?php echo htmlspecialchars($s['icon']); ?></td>
<td><span style="display:inline-block;width:24px;height:24px;border-radius:8px;background:<?php echo $s['color']; ?>;vertical-align:middle"></span></td><td><?php echo $cc; ?></td>
<td class="actions"><a href="?page=subjects&edit=<?php echo $s['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=subjects&edit=<?php echo $s['id']; ?>&del_table=subjects" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === CHAPTERS === */ ?>
<?php elseif($page==='chapters'):
$ec=$edit>0?$db->find('chapters','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $ec?'edit':'plus-circle'; ?>"></i><?php echo $ec?'Edit Chapter':'Add Chapter'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $ec?'edit_chapter':'add_chapter'; ?>">
<?php if($ec): ?><input type="hidden" name="id" value="<?php echo $ec['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Subject</label><select name="subject_id"><?php foreach($allSubjects as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $ec&&$ec['subject_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Order</label><input type="number" name="order" value="<?php echo $ec?($ec['order']??1):1; ?>" min="1" required></div></div>
<div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo $ec?htmlspecialchars($ec['title']):''; ?>" required></div>
<div class="form-row"><div class="form-group"><label>Status</label><select name="status"><?php foreach(['not_started'=>'Not Started','in_progress'=>'In Progress','completed'=>'Completed','locked'=>'Locked'] as $sv=>$sl): ?><option value="<?php echo $sv; ?>" <?php echo $ec&&($ec['status']??'not_started')===$sv?'selected':''; ?>><?php echo $sl; ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Pages</label><input type="number" name="pages" value="<?php echo $ec?($ec['pages']??10):10; ?>" min="1" required></div></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $ec?'Update':'Add'; ?></button><?php if($ec): ?><a href="?page=chapters" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="layers"></i>All Chapters (<?php echo count($allChapters); ?>)</h3>
<table><tr><th>#</th><th>Subject</th><th>Title</th><th>Pages</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allChapters as $ch): $subj=$db->find('subjects','id',$ch['subject_id']); ?>
<tr><td><?php echo $ch['order'] ?? $ch['id']; ?></td><td><?php echo $subj?htmlspecialchars($subj['name']):''; ?></td>
<td><strong><?php echo htmlspecialchars($ch['title']); ?></strong></td><td><?php echo $ch['pages'] ?? 0; ?></td>
<td><span class="badge <?php echo ($ch['status']??'not_started')==='completed'?'bg':(($ch['status']??'')==='in_progress'?'bo':(($ch['status']??'')==='locked'?'br':'bb')); ?>"><?php echo ucfirst(str_replace('_',' ',$ch['status']??'not_started')); ?></span></td>
<td class="actions"><a href="?page=chapters&edit=<?php echo $ch['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=chapters&edit=<?php echo $ch['id']; ?>&del_table=chapters" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === HOMEWORK === */ ?>
<?php elseif($page==='homework'):
$eh=$edit>0?$db->find('homework','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $eh?'edit':'plus-circle'; ?>"></i><?php echo $eh?'Edit Homework':'Add Homework'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $eh?'edit_homework':'add_homework'; ?>">
<?php if($eh): ?><input type="hidden" name="id" value="<?php echo $eh['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo $eh?htmlspecialchars($eh['title']):''; ?>" required></div><div class="form-group"><label>Subject</label><select name="subject_id"><?php foreach($allSubjects as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $eh&&$eh['subject_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group"><label>Teacher</label><select name="teacher_id"><?php foreach($allTeachers as $t): ?><option value="<?php echo $t['user_id']; ?>" <?php echo $eh&&$eh['teacher_id']==$t['user_id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Due Date</label><input type="date" name="due_date" value="<?php echo $eh?$eh['due_date']:''; ?>" required></div></div>
<div class="form-group"><label>Description</label><textarea name="description" rows="2"><?php echo $eh?htmlspecialchars($eh['description']):''; ?></textarea></div>
<div class="form-row"><div class="form-group"><label>Total Marks</label><input type="number" name="total_marks" value="<?php echo $eh?$eh['total_marks']:10; ?>"></div></div>
<?php if($eh): ?><div class="form-group"><label>Status</label><select name="status"><option value="pending" <?php echo $eh['status']==='pending'?'selected':''; ?>>Pending</option><option value="completed" <?php echo $eh['status']==='completed'?'selected':''; ?>>Completed</option></select></div><?php endif; ?>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $eh?'Update':'Add'; ?></button><?php if($eh): ?><a href="?page=homework" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="file-text"></i>All Homework (<?php echo count($allHomework); ?>)</h3>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search homework..." oninput="filterTable(this,'hw-table')"></div>
<table id="hw-table"><tr><th>Title</th><th>Subject</th><th>Due</th><th>Marks</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allHomework as $h): $subj=$db->find('subjects','id',$h['subject_id']); ?>
<tr><td><strong><?php echo htmlspecialchars($h['title']); ?></strong></td><td><?php echo $subj?htmlspecialchars($subj['name']):''; ?></td><td><?php echo $h['due_date']; ?></td><td><?php echo $h['total_marks']; ?></td>
<td><span class="badge <?php echo $h['status']==='pending'?'bo':'bg'; ?>"><?php echo ucfirst($h['status']); ?></span></td>
<td class="actions"><a href="?page=homework&edit=<?php echo $h['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=homework&edit=<?php echo $h['id']; ?>&del_table=homework" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === EXAMS === */ ?>
<?php elseif($page==='exams'):
$ex=$edit>0?$db->find('exams','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $ex?'edit':'plus-circle'; ?>"></i><?php echo $ex?'Edit Exam':'Add Exam'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $ex?'edit_exam':'add_exam'; ?>">
<?php if($ex): ?><input type="hidden" name="id" value="<?php echo $ex['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo $ex?htmlspecialchars($ex['title']):''; ?>" required></div><div class="form-group"><label>Subject</label><select name="subject_id"><?php foreach($allSubjects as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $ex&&$ex['subject_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group"><label>Teacher</label><select name="teacher_id"><?php foreach($allTeachers as $t): ?><option value="<?php echo $t['user_id']; ?>" <?php echo $ex&&$ex['teacher_id']==$t['user_id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Type</label><select name="type"><option value="mcq" <?php echo $ex&&$ex['type']==='mcq'?'selected':''; ?>>MCQ</option><option value="written" <?php echo $ex&&$ex['type']==='written'?'selected':''; ?>>Written</option><option value="cq" <?php echo $ex&&$ex['type']==='cq'?'selected':''; ?>>CQ</option><option value="board" <?php echo $ex&&$ex['type']==='board'?'selected':''; ?>>Board</option></select></div></div>
<div class="form-row"><div class="form-group"><label>Date</label><input type="date" name="exam_date" value="<?php echo $ex?$ex['exam_date']:''; ?>" required></div><div class="form-group"><label>Total Marks</label><input type="number" name="total_marks" value="<?php echo $ex?$ex['total_marks']:100; ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Duration (min)</label><input type="number" name="duration" value="<?php echo $ex?$ex['duration']:60; ?>"></div></div>
<?php if($ex): ?><div class="form-group"><label>Status</label><select name="status"><option value="upcoming" <?php echo $ex['status']==='upcoming'?'selected':''; ?>>Upcoming</option><option value="ongoing" <?php echo $ex['status']==='ongoing'?'selected':''; ?>>Ongoing</option><option value="completed" <?php echo $ex['status']==='completed'?'selected':''; ?>>Completed</option></select></div><?php endif; ?>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $ex?'Update':'Add'; ?></button><?php if($ex): ?><a href="?page=exams" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="calendar"></i>All Exams (<?php echo count($allExams); ?>)</h3>
<table><tr><th>Title</th><th>Subject</th><th>Date</th><th>Type</th><th>Marks</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allExams as $e): $subj=$db->find('subjects','id',$e['subject_id']); ?>
<tr><td><strong><?php echo htmlspecialchars($e['title']); ?></strong></td><td><?php echo $subj?htmlspecialchars($subj['name']):''; ?></td><td><?php echo $e['exam_date']; ?></td>
<td><span class="badge bp"><?php echo strtoupper($e['type']); ?></span></td><td><?php echo $e['total_marks']; ?></td>
<td><span class="badge bb"><?php echo ucfirst($e['status']); ?></span></td>
<td class="actions"><a href="?page=exams&edit=<?php echo $e['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=exams&edit=<?php echo $e['id']; ?>&del_table=exams" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === QUESTIONS === */ ?>
<?php elseif($page==='questions'):
$eq=$edit>0?$db->find('question_bank','id',$edit):null;
$eo=is_array($eq['options'] ?? null) ? ($eq['options'] ?? []) : json_decode($eq['options'] ?? '[]', true); ?>
<div class="card"><h3><i data-lucide="<?php echo $eq?'edit':'plus-circle'; ?>"></i><?php echo $eq?'Edit Question':'Add Question'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $eq?'edit_question':'add_question'; ?>">
<?php if($eq): ?><input type="hidden" name="id" value="<?php echo $eq['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Subject</label><select name="subject_id"><?php foreach($allSubjects as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $eq&&$eq['subject_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Chapter</label><input type="text" name="chapter" value="<?php echo $eq?htmlspecialchars($eq['chapter']):''; ?>" placeholder="e.g. Algebra" required></div></div>
<div class="form-row"><div class="form-group"><label>Type</label><select name="type"><option value="mcq" <?php echo $eq&&$eq['type']==='mcq'?'selected':''; ?>>MCQ</option><option value="written" <?php echo $eq&&$eq['type']==='written'?'selected':''; ?>>Written</option><option value="cq" <?php echo $eq&&$eq['type']==='cq'?'selected':''; ?>>CQ</option></select></div><div class="form-group"><label>Difficulty</label><select name="difficulty"><option value="easy" <?php echo $eq&&$eq['difficulty']==='easy'?'selected':''; ?>>Easy</option><option value="medium" <?php echo $eq&&$eq['difficulty']==='medium'?'selected':''; ?>>Medium</option><option value="hard" <?php echo $eq&&$eq['difficulty']==='hard'?'selected':''; ?>>Hard</option></select></div></div>
<div class="form-group"><label>Question</label><textarea name="question" rows="2" required><?php echo $eq?htmlspecialchars($eq['question']):''; ?></textarea></div>
<div class="form-row"><div class="form-group"><label>Option 1</label><input type="text" name="opt_1" value="<?php echo isset($eo[0])?htmlspecialchars($eo[0]):''; ?>" required></div><div class="form-group"><label>Option 2</label><input type="text" name="opt_2" value="<?php echo isset($eo[1])?htmlspecialchars($eo[1]):''; ?>" required></div></div>
<div class="form-row"><div class="form-group"><label>Option 3</label><input type="text" name="opt_3" value="<?php echo isset($eo[2])?htmlspecialchars($eo[2]):''; ?>"></div><div class="form-group"><label>Option 4</label><input type="text" name="opt_4" value="<?php echo isset($eo[3])?htmlspecialchars($eo[3]):''; ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Option 5</label><input type="text" name="opt_5" value="<?php echo isset($eo[4])?htmlspecialchars($eo[4]):''; ?>"></div><div class="form-group"><label>Option 6</label><input type="text" name="opt_6" value="<?php echo isset($eo[5])?htmlspecialchars($eo[5]):''; ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Correct Answer (0-indexed)</label><input type="number" name="correct_answer" min="0" max="5" value="<?php echo $eq?($eq['correct']??0):0; ?>" required></div><div class="form-group"><label>Marks</label><input type="number" name="marks" value="<?php echo $eq?($eq['marks']??1):1; ?>"></div></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $eq?'Update':'Add'; ?></button><?php if($eq): ?><a href="?page=questions" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
<h3 style="margin-bottom:0"><i data-lucide="help-circle"></i>Question Bank (<?php echo count($allQuestions); ?>)</h3>
<div style="display:flex;gap:8px"><button class="btn btn-outline btn-sm" onclick="exportTable('q-table','questions.csv')"><i data-lucide="download" style="width:12px;height:12px"></i>Export</button></div></div>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search questions..." oninput="filterTable(this,'q-table')"></div>
<table id="q-table"><tr><th>ID</th><th>Subject</th><th>Chapter</th><th>Type</th><th>Question</th><th>Diff</th><th>Marks</th><th>Actions</th></tr>
<?php foreach($allQuestions as $q): $subj=$db->find('subjects','id',$q['subject_id']); ?>
<tr><td><?php echo $q['id']; ?></td><td><?php echo $subj?htmlspecialchars($subj['name']):''; ?></td><td><?php echo htmlspecialchars($q['chapter']??''); ?></td>
<td><span class="badge bp"><?php echo strtoupper($q['type']); ?></span></td>
<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($q['question']); ?></td>
<td><span class="badge <?php echo $q['difficulty']==='easy'?'bg':($q['difficulty']==='medium'?'bo':'br'); ?>"><?php echo ucfirst($q['difficulty']); ?></span></td><td><?php echo $q['marks']; ?></td>
<td class="actions"><a href="?page=questions&edit=<?php echo $q['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=questions&edit=<?php echo $q['id']; ?>&del_table=question_bank" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === SUBMISSIONS === */ ?>
<?php elseif($page==='submissions'):
$gs=$edit>0?$db->find('homework_submissions','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="inbox"></i>Homework Submissions (<?php echo count($allSubmissions); ?>)</h3>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search submissions..." oninput="filterTable(this,'sub-table')"></div>
<table id="sub-table"><tr><th>Student</th><th>Homework</th><th>Submitted</th><th>Marks</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allSubmissions as $sub): $st=$db->find('users','id',$sub['student_id']); $hw=$db->find('homework','id',$sub['homework_id']); ?>
<tr><td><strong><?php echo $st?htmlspecialchars($st['name']):'N/A'; ?></strong></td><td><?php echo $hw?htmlspecialchars($hw['title']):''; ?></td>
<td style="font-size:11px;color:#9CA3AF"><?php echo $sub['submitted_at']; ?></td>
<td><?php echo $sub['status']==='graded'?$sub['marks_obtained'].'/':''; ?>-</td>
<td><span class="badge <?php echo $sub['status']==='graded'?'bg':($sub['status']==='revision'?'br':'bo'); ?>"><?php echo ucfirst($sub['status']); ?></span></td>
<td class="actions"><a href="?page=submissions&edit=<?php echo $sub['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>
<?php if($gs): $gst=$db->find('users','id',$gs['student_id']); $ghw=$db->find('homework','id',$gs['homework_id']); ?>
<div class="card"><h3><i data-lucide="edit"></i>Grade Submission</h3>
<div style="background:#F9FAFB;padding:14px;border-radius:10px;margin-bottom:14px">
<div style="display:flex;gap:12px;align-items:center;margin-bottom:8px">
<div style="width:36px;height:36px;background:#4F46E5;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:14px"><?php echo $gst?ucfirst(substr($gst['name'],0,1)):'S'; ?></div>
<div><div style="font-size:14px;font-weight:600"><?php echo $gst?htmlspecialchars($gst['name']):''; ?></div><div style="font-size:11px;color:#9CA3AF"><?php echo $ghw?htmlspecialchars($ghw['title']):''; ?></div></div></div>
<p style="font-size:12px;color:#6B7280;line-height:1.5"><?php echo htmlspecialchars($gs['answer']); ?></p></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="grade_submission"><input type="hidden" name="id" value="<?php echo $gs['id']; ?>">
<div class="form-row"><div class="form-group"><label>Marks Obtained</label><input type="number" name="marks_obtained" value="<?php echo $gs['marks_obtained']; ?>" required></div><div class="form-group"><label>Status</label><select name="status"><option value="graded" <?php echo $gs['status']==='graded'?'selected':''; ?>>Graded</option><option value="revision" <?php echo $gs['status']==='revision'?'selected':''; ?>>Return for Revision</option></select></div></div>
<div class="form-group"><label>Comments / Feedback</label><textarea name="comments" rows="3" placeholder="Write feedback for the student..."><?php echo htmlspecialchars($gs['comments']??''); ?></textarea></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-success"><i data-lucide="check" style="width:14px;height:14px"></i>Save Grade</button><a href="?page=submissions" class="btn btn-outline">Cancel</a></div></form></div>
<?php endif; ?>

<?php /* === EXAM RESULTS === */ ?>
<?php elseif($page==='results'): ?>
<div class="card"><h3><i data-lucide="award"></i>Exam Results (<?php echo count($allExamResults); ?>)</h3>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search results..." oninput="filterTable(this,'res-table')"></div>
<table id="res-table"><tr><th>Student</th><th>Exam</th><th>Score</th><th>Total</th><th>%</th><th>Grade</th><th>Date</th></tr>
<?php foreach($allExamResults as $er): $st=$db->find('users','id',$er['student_id']); $ex=$db->find('exams','id',$er['exam_id']); ?>
<tr><td><strong><?php echo $st?htmlspecialchars($st['name']):'N/A'; ?></strong></td><td><?php echo $ex?htmlspecialchars($ex['title']):''; ?></td>
<td><strong style="color:#4F46E5"><?php echo $er['score']; ?></strong></td><td><?php echo $er['total_marks']; ?></td><td><?php echo $er['percentage']; ?>%</td>
<td><span class="badge <?php echo $er['grade']==='A+'?'bg':($er['grade']==='A'?'bgr':'bb'); ?>"><?php echo $er['grade']; ?></span></td>
<td style="font-size:11px;color:#9CA3AF"><?php echo $er['submitted_at']; ?></td></tr>
<?php endforeach; ?></table></div>

<?php /* === EXAM ANALYTICS === */ ?>
<?php elseif($page==='exam_analytics'):
$avg=count($allExamResults)>0?round(array_sum(array_column($allExamResults,'percentage'))/count($allExamResults)):0;
$pass=count($allExamResults)>0?round(count(array_filter($allExamResults,fn($r)=>$r['percentage']>=50))/count($allExamResults)*100):0;
$highest=count($allExamResults)>0?max(array_column($allExamResults,'percentage')):0;
$lowest=count($allExamResults)>0?min(array_column($allExamResults,'percentage')):0;
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:12px"><button class="btn btn-outline btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:12px;height:12px"></i> Print / Export PDF</button></div>
<div class="sg">
<div class="sc"><div class="si p"><i data-lucide="bar-chart"></i></div><div class="st"><h3><?php echo count($allExamResults); ?></h3><p>Total Results</p></div></div>
<div class="sc"><div class="si g"><i data-lucide="trending-up"></i></div><div class="st"><h3><?php echo $avg; ?>%</h3><p>Average Score</p></div></div>
<div class="sc"><div class="si b"><i data-lucide="check-circle"></i></div><div class="st"><h3><?php echo $pass; ?>%</h3><p>Pass Rate</p></div></div>
<div class="sc"><div class="si o"><i data-lucide="award"></i></div><div class="st"><h3><?php echo $highest; ?>%</h3><p>Highest Score</p></div></div>
</div>
<div class="grid-2">
<div class="card"><h3><i data-lucide="bar-chart-3"></i>Score Distribution</h3>
<div class="chart-bar">
<?php $b=[0,0,0,0,0];foreach($allExamResults as $r){$i=min(intval($r['percentage']/20),4);$b[$i]++;}$mx=max(1,max($b));$lb=['0-20%','20-40%','40-60%','60-80%','80-100%'];for($i=0;$i<5;$i++): ?>
<div class="bar" style="height:<?php echo $b[$i]>0?max(8,$b[$i]/$mx*100):2; ?>%"><span><?php echo $lb[$i]; ?></span></div>
<?php endfor; ?></div></div>
<div class="card"><h3><i data-lucide="pie-chart"></i>Grade Distribution</h3>
<?php $grades=[];foreach($allExamResults as $r){$g=$r['grade'];$grades[$g]=($grades[$g]??0)+1;} ?>
<?php foreach($grades as $g=>$c): ?>
<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
<span class="badge <?php echo $g==='A+'?'bg':($g==='A'?'bgr':'bb'); ?>" style="min-width:36px;text-align:center"><?php echo $g; ?></span>
<div style="flex:1;height:8px;background:#F3F4F6;border-radius:4px;overflow:hidden"><div style="height:100%;width:<?php echo $c/count($allExamResults)*100; ?>%;background:linear-gradient(90deg,#4F46E5,#818CF8);border-radius:4px"></div></div>
<span style="font-size:12px;font-weight:600;color:#374151"><?php echo $c; ?></span>
</div>
<?php endforeach; ?></div></div>
<div class="card"><h3><i data-lucide="list"></i>Results by Exam</h3>
<table><tr><th>Exam</th><th>Results</th><th>Avg Score</th><th>Highest</th><th>Lowest</th></tr>
<?php foreach($allExams as $ex):
$ers=array_filter($allExamResults,fn($r)=>$ex['id']===$r['exam_id']);
if(count($ers)>0):$pcts=array_column($ers,'percentage'); ?>
<tr><td><strong><?php echo htmlspecialchars($ex['title']); ?></strong></td><td><?php echo count($ers); ?></td>
<td><?php echo round(array_sum($pcts)/count($pcts)); ?>%</td><td><?php echo max($pcts); ?>%</td><td><?php echo min($pcts); ?>%</td></tr>
<?php endif; endforeach; ?></table></div>

<?php /* === HW ANALYTICS === */ ?>
<?php elseif($page==='hw_analytics'):
$totalSub=count($allSubmissions);$graded=count(array_filter($allSubmissions,fn($s)=>$s['status']==='graded'));$pending=$totalSub-$graded;
$rev=count(array_filter($allSubmissions,fn($s)=>$s['status']==='revision'));
$avgM=$graded>0?round(array_sum(array_column(array_filter($allSubmissions,fn($s)=>$s['status']==='graded'),'marks_obtained'))/$graded):0;
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:12px"><button class="btn btn-outline btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:12px;height:12px"></i> Print / Export PDF</button></div>
<div class="sg">
<div class="sc"><div class="si p"><i data-lucide="inbox"></i></div><div class="st"><h3><?php echo $totalSub; ?></h3><p>Total Submissions</p></div></div>
<div class="sc"><div class="si g"><i data-lucide="check-circle"></i></div><div class="st"><h3><?php echo $graded; ?></h3><p>Graded</p></div></div>
<div class="sc"><div class="si o"><i data-lucide="clock"></i></div><div class="st"><h3><?php echo $pending; ?></h3><p>Pending</p></div></div>
<div class="sc"><div class="si r"><i data-lucide="rotate-ccw"></i></div><div class="st"><h3><?php echo $rev; ?></h3><p>Revision</p></div></div>
</div>
<div class="grid-2">
<div class="card"><h3><i data-lucide="pie-chart"></i>Submission Status</h3>
<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
<div style="text-align:center"><div style="width:80px;height:80px;border-radius:50%;background:conic-gradient(#10B981 <?php echo $totalSub>0?$graded/$totalSub*360:0; ?>deg,#F59E0B <?php echo $totalSub>0?$graded/$totalSub*360:0; ?>deg <?php echo $totalSub>0?($graded+$pending)/$totalSub*360:0; ?>deg,#EF4444 <?php echo $totalSub>0?($graded+$pending)/$totalSub*360:0; ?>deg);display:flex;align-items:center;justify-content:center"><div style="width:50px;height:50px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#1F2937"><?php echo $totalSub; ?></div></div></div>
<div><div style="font-size:12px;margin-bottom:4px"><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#10B981;margin-right:6px"></span>Graded: <?php echo $graded; ?></div>
<div style="font-size:12px;margin-bottom:4px"><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#F59E0B;margin-right:6px"></span>Pending: <?php echo $pending; ?></div>
<div style="font-size:12px"><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#EF4444;margin-right:6px"></span>Revision: <?php echo $rev; ?></div></div>
</div></div>
<div class="card"><h3><i data-lucide="bar-chart-3"></i>Avg Marks by Subject</h3>
<?php $bySubj=[];
foreach($allSubmissions as $sub){if($sub['status']!=='graded')continue;$hw=$db->find('homework','id',$sub['homework_id']);if(!$hw)continue;$sid=$hw['subject_id'];if(!isset($bySubj[$sid]))$bySubj[$sid]=['sum'=>0,'cnt'=>0];$bySubj[$sid]['sum']+=$sub['marks_obtained'];$bySubj[$sid]['cnt']++;}
foreach($bySubj as $sid=>$data):$subj=$db->find('subjects','id',$sid);$avg=round($data['sum']/$data['cnt']); ?>
<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
<span style="font-size:12px;min-width:80px;font-weight:600"><?php echo $subj?htmlspecialchars($subj['name']):''; ?></span>
<div style="flex:1;height:8px;background:#F3F4F6;border-radius:4px;overflow:hidden"><div style="height:100%;width:<?php echo min(100,$avg); ?>%;background:linear-gradient(90deg,#4F46E5,#818CF8);border-radius:4px"></div></div>
<span style="font-size:12px;font-weight:600;color:#4F46E5"><?php echo $avg; ?></span>
</div>
<?php endforeach; ?></div></div>

<?php /* === LIBRARY === */ ?>
<?php elseif($page==='library'):
$el=$edit>0?$db->find('library','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $el?'edit':'plus-circle'; ?>"></i><?php echo $el?'Edit Library Item':'Add Library Item'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $el?'edit_library':'add_library'; ?>">
<?php if($el): ?><input type="hidden" name="id" value="<?php echo $el['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo $el?htmlspecialchars($el['title']):''; ?>" required></div><div class="form-group"><label>Subject</label><select name="subject_id"><?php foreach($allSubjects as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $el&&$el['subject_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group"><label>Class</label><select name="class"><option value="Class 7" <?php echo $el&&$el['class']==='Class 7'?'selected':''; ?>>Class 7</option><option value="Class 8" <?php echo (!$el||$el['class']==='Class 8')?'selected':''; ?>>Class 8</option><option value="Class 9 Science" <?php echo $el&&$el['class']==='Class 9 Science'?'selected':''; ?>>Class 9 Science</option><option value="Class 10 Science" <?php echo $el&&$el['class']==='Class 10 Science'?'selected':''; ?>>Class 10 Science</option><option value="Class 9 Commerce" <?php echo $el&&$el['class']==='Class 9 Commerce'?'selected':''; ?>>Class 9 Commerce</option><option value="Class 10 Commerce" <?php echo $el&&$el['class']==='Class 10 Commerce'?'selected':''; ?>>Class 10 Commerce</option><option value="Class 9 Arts" <?php echo $el&&$el['class']==='Class 9 Arts'?'selected':''; ?>>Class 9 Arts</option><option value="Class 10 Arts" <?php echo $el&&$el['class']==='Class 10 Arts'?'selected':''; ?>>Class 10 Arts</option></select></div><div class="form-group"><label>Type</label><select name="type"><option value="textbook" <?php echo $el&&$el['type']==='textbook'?'selected':''; ?>>Textbook</option><option value="guide" <?php echo $el&&$el['type']==='guide'?'selected':''; ?>>Guide</option><option value="reference" <?php echo $el&&$el['type']==='reference'?'selected':''; ?>>Reference</option><option value="notes" <?php echo $el&&$el['type']==='notes'?'selected':''; ?>>Notes</option><option value="video" <?php echo $el&&$el['type']==='video'?'selected':''; ?>>Video</option></select></div></div>
<div class="form-row"><div class="form-group"><label>File URL</label><input type="text" name="file_url" value="<?php echo $el?htmlspecialchars($el['file_url']):''; ?>"></div><div class="form-group"><label>Cover URL</label><input type="text" name="cover_url" value="<?php echo $el?htmlspecialchars($el['cover_url']):''; ?>"></div></div>
<div class="form-group"><label>Description</label><textarea name="description" rows="2"><?php echo $el?htmlspecialchars($el['description']):''; ?></textarea></div>
<?php if($el): ?><div class="form-group"><label>Status</label><select name="is_active"><option value="1" <?php echo $el['is_active']?'selected':''; ?>>Active</option><option value="0" <?php echo !$el['is_active']?'selected':''; ?>>Inactive</option></select></div><?php endif; ?>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $el?'Update':'Add'; ?></button><?php if($el): ?><a href="?page=library" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="library"></i>Library (<?php echo count($allLibrary); ?>)</h3>
<table><tr><th>Title</th><th>Subject</th><th>Class</th><th>Type</th><th>Downloads</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allLibrary as $l): $subj=$db->find('subjects','id',$l['subject_id']); ?>
<tr><td><strong><?php echo htmlspecialchars($l['title']); ?></strong></td><td><?php echo $subj?htmlspecialchars($subj['name']):''; ?></td><td><?php echo htmlspecialchars($l['class']??''); ?></td>
<td><span class="badge bp"><?php echo ucfirst($l['type']); ?></span></td><td><?php echo $l['downloads']; ?></td>
<td><span class="badge <?php echo $l['is_active']?'bg':'br'; ?>"><?php echo $l['is_active']?'Active':'Inactive'; ?></span></td>
<td class="actions"><a href="?page=library&edit=<?php echo $l['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=library&edit=<?php echo $l['id']; ?>&del_table=library" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === LIVE CLASSES === */ ?>
<?php elseif($page==='live_classes'):
$elc=$edit>0?$db->find('live_classes','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $elc?'edit':'plus-circle'; ?>"></i><?php echo $elc?'Edit Live Class':'Schedule Live Class'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $elc?'edit_live_class':'add_live_class'; ?>">
<?php if($elc): ?><input type="hidden" name="id" value="<?php echo $elc['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo $elc?htmlspecialchars($elc['title']):''; ?>" required></div><div class="form-group"><label>Subject</label><select name="subject_id"><?php foreach($allSubjects as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $elc&&$elc['subject_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group"><label>Teacher</label><select name="teacher_id"><?php foreach($allTeachers as $t): ?><option value="<?php echo $t['user_id']; ?>" <?php echo $elc&&$elc['teacher_id']==$t['user_id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['subject']); ?>)</option><?php endforeach; ?></select></div><div class="form-group"><label>Meeting Link</label><input type="text" name="meeting_link" value="<?php echo $elc?htmlspecialchars($elc['meeting_link']):'#'; ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Date</label><input type="date" name="class_date" value="<?php echo $elc?$elc['class_date']:''; ?>" required></div><div class="form-group"><label>Start</label><input type="time" name="start_time" value="<?php echo $elc?$elc['start_time']:''; ?>" required></div></div>
<div class="form-row"><div class="form-group"><label>End</label><input type="time" name="end_time" value="<?php echo $elc?$elc['end_time']:''; ?>" required></div>
<?php if($elc): ?><div class="form-group"><label>Status</label><select name="status"><option value="scheduled" <?php echo $elc['status']==='scheduled'?'selected':''; ?>>Scheduled</option><option value="live" <?php echo $elc['status']==='live'?'selected':''; ?>>Live</option><option value="completed" <?php echo $elc['status']==='completed'?'selected':''; ?>>Completed</option><option value="cancelled" <?php echo $elc['status']==='cancelled'?'selected':''; ?>>Cancelled</option></select></div><?php endif; ?></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $elc?'Update':'Schedule'; ?></button><?php if($elc): ?><a href="?page=live_classes" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="tv"></i>All Live Classes (<?php echo count($allLiveClasses); ?>)</h3>
<table><tr><th>Title</th><th>Subject</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allLiveClasses as $lc): $subj=$db->find('subjects','id',$lc['subject_id']); ?>
<tr><td><strong><?php echo htmlspecialchars($lc['title']); ?></strong></td><td><?php echo $subj?htmlspecialchars($subj['name']):''; ?></td><td><?php echo $lc['class_date']; ?></td><td><?php echo $lc['start_time'].'-'.$lc['end_time']; ?></td>
<td><span class="badge <?php echo $lc['status']==='live'?'bg':($lc['status']==='completed'?'bb':($lc['status']==='cancelled'?'br':'bo')); ?>"><?php echo ucfirst($lc['status']); ?></span></td>
<td class="actions"><a href="?page=live_classes&edit=<?php echo $lc['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=live_classes&edit=<?php echo $lc['id']; ?>&del_table=live_classes" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === ANNOUNCEMENTS === */ ?>
<?php elseif($page==='announcements'):
$ea=$edit>0?$db->find('announcements','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $ea?'edit':'megaphone'; ?>"></i><?php echo $ea?'Edit Announcement':'New Announcement'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $ea?'edit_announcement':'add_announcement'; ?>">
<?php if($ea): ?><input type="hidden" name="id" value="<?php echo $ea['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo $ea?htmlspecialchars($ea['title']):''; ?>" required></div><div class="form-group"><label>Category</label><select name="category"><option value="general" <?php echo $ea&&$ea['category']==='general'?'selected':''; ?>>General</option><option value="exam" <?php echo $ea&&$ea['category']==='exam'?'selected':''; ?>>Exam</option><option value="event" <?php echo $ea&&$ea['category']==='event'?'selected':''; ?>>Event</option><option value="urgent" <?php echo $ea&&$ea['category']==='urgent'?'selected':''; ?>>Urgent</option></select></div></div>
<div class="form-group"><label>Message</label><textarea name="message" rows="3" required><?php echo $ea?htmlspecialchars($ea['message']):''; ?></textarea></div>
<div class="form-row"><div class="form-group"><label>Target Class</label><input type="text" name="target_class" value="<?php echo $ea?htmlspecialchars($ea['target_class']):'all'; ?>"></div><div class="form-group"><label>Teacher</label><select name="teacher_id"><?php foreach($allTeachers as $t): ?><option value="<?php echo $t['user_id']; ?>" <?php echo $ea&&$ea['teacher_id']==$t['user_id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Pinned</label><select name="is_pinned"><option value="0" <?php echo !$ea||!$ea['is_pinned']?'selected':''; ?>>No</option><option value="1" <?php echo $ea&&$ea['is_pinned']?'selected':''; ?>>Yes</option></select></div></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $ea?'Update':'Publish'; ?></button><?php if($ea): ?><a href="?page=announcements" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="megaphone"></i>All Announcements (<?php echo count($allAnnouncements); ?>)</h3>
<?php foreach($allAnnouncements as $a): ?>
<div style="padding:12px 0;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:flex-start">
<div style="flex:1"><div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap">
<?php if(!empty($a['is_pinned'])): ?><i data-lucide="pin" style="width:12px;height:12px;color:#EF4444"></i><?php endif; ?>
<strong style="font-size:14px"><?php echo htmlspecialchars($a['title']); ?></strong>
<span class="badge <?php echo $a['category']==='urgent'?'br':($a['category']==='exam'?'bo':($a['category']==='event'?'bp':'bb')); ?>"><?php echo ucfirst($a['category']); ?></span></div>
<p style="font-size:12px;color:#6B7280;margin-bottom:4px"><?php echo htmlspecialchars($a['message']); ?></p>
<p style="font-size:10px;color:#9CA3AF">Target: <?php echo htmlspecialchars($a['target_class']); ?></p></div>
<div class="actions"><a href="?page=announcements&edit=<?php echo $a['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=announcements&edit=<?php echo $a['id']; ?>&del_table=announcements" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></div></div>
<?php endforeach; ?></div>

<?php /* === MESSAGES === */ ?>
<?php elseif($page==='messages'): ?>
<div class="card"><h3><i data-lucide="message-square"></i>Message Monitor (<?php echo count($allMessages); ?>)</h3>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search messages..." oninput="filterTable(this,'msg-table')"></div>
<table id="msg-table"><tr><th>From</th><th>To</th><th>Message</th><th>Read</th><th>Time</th></tr>
<?php foreach($allMessages as $m): $from=$db->find('users','id',$m['sender_id']); $to=$db->find('users','id',$m['receiver_id']); ?>
<tr><td><strong><?php echo $from?htmlspecialchars($from['name']):'N/A'; ?></strong></td><td><?php echo $to?htmlspecialchars($to['name']):'N/A'; ?></td>
<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px"><?php echo htmlspecialchars($m['message']); ?></td>
<td><?php echo $m['is_read']?'<span class="badge bg">Read</span>':'<span class="badge bo">Unread</span>'; ?></td>
<td style="font-size:11px;color:#9CA3AF"><?php echo $m['created_at']; ?></td></tr>
<?php endforeach; ?></table></div>

<?php /* === NOTIFICATIONS === */ ?>
<?php elseif($page==='notifications'): ?>
<div class="card"><h3><i data-lucide="bell"></i>Send Notification</h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="send_broadcast">
<div class="form-row"><div class="form-group"><label>Title</label><input type="text" name="title" required></div><div class="form-group"><label>Target</label><select name="target_role"><option value="student">All Students</option><option value="teacher">All Teachers</option><option value="all">Everyone</option></select></div></div>
<div class="form-group"><label>Message</label><textarea name="message" rows="2" required></textarea></div>
<button type="submit" class="btn btn-primary"><i data-lucide="send" style="width:14px;height:14px"></i>Send Broadcast</button></form></div>
<div class="card"><h3><i data-lucide="list"></i>Notification History (<?php echo count($allNotifications); ?>)</h3>
<table><tr><th>Title</th><th>Message</th><th>Type</th><th>Target</th><th>Read</th><th>Time</th></tr>
<?php foreach($allNotifications as $n): ?>
<tr><td><strong><?php echo htmlspecialchars($n['title']); ?></strong></td>
<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px"><?php echo htmlspecialchars($n['message']); ?></td>
<td><span class="badge bp"><?php echo ucfirst($n['type']); ?></span></td>
<td><span class="badge bb"><?php echo ucfirst($n['target_role']); ?></span></td>
<td><?php echo $n['is_read']?'<span class="badge bg">Read</span>':'<span class="badge bo">New</span>'; ?></td>
<td style="font-size:11px;color:#9CA3AF"><?php echo $n['created_at']; ?></td></tr>
<?php endforeach; ?></table></div>

<?php /* === CALENDAR === */ ?>
<?php elseif($page==='calendar'):
$cm=intval($_GET['month']??date('m'));$cy=intval($_GET['year']??date('Y'));
$firstDay=mktime(0,0,0,$cm,1,$cy);$daysInMonth=date('t',$firstDay);$startDay=date('w',$firstDay);
$monthNames=['','January','February','March','April','May','June','July','August','September','October','November','December'];
$events=[];
foreach($allLiveClasses as $lc){$d=$lc['class_date'];if(!isset($events[$d]))$events[$d]=[];$events[$d][]=$lc;}
foreach($allCalendarEvents as $ce){$d=$ce['date'];if(!isset($events[$d]))$events[$d]=[];$events[$d][]=array_merge($ce,['_type'=>'calendar_event']);}
?>
<div class="card"><h3><i data-lucide="calendar-days"></i><?php echo $monthNames[$cm].' '.$cy; ?></h3>
<div style="display:flex;gap:8px;margin-bottom:16px">
<a href="?page=calendar&month=<?php echo $cm===1?12:$cm-1; ?>&year=<?php echo $cm===1?$cy-1:$cy; ?>" class="btn btn-outline btn-sm"><i data-lucide="chevron-left" style="width:14px;height:14px"></i></a>
<a href="?page=calendar&month=<?php echo $cm===12?1:$cm+1; ?>&year=<?php echo $cm===12?$cy+1:$cy; ?>" class="btn btn-outline btn-sm"><i data-lucide="chevron-right" style="width:14px;height:14px"></i></a>
<a href="?page=calendar" class="btn btn-outline btn-sm">Today</a></div>
<div class="cal-grid">
<?php $days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];foreach($days as $d): ?><div class="cal-head"><?php echo $d; ?></div><?php endforeach; ?>
<?php for($i=0;$i<$startDay;$i++): ?><div class="cal-day empty"></div><?php endfor; ?>
<?php for($d=1;$d<=$daysInMonth;$d++): $dt=sprintf('%04d-%02d-%02d',$cy,$cm,$d); $isToday=$dt===date('Y-m-d'); $has=isset($events[$dt]); ?>
<div class="cal-day <?php echo $isToday?'today':''; ?><?php echo $has?' has-event':''; ?>"><?php echo $d; ?></div>
<?php endfor; ?>
</div></div>
<?php if(!empty($events)): ?>
<div class="card"><h3><i data-lucide="list"></i>Events This Month</h3>
<?php foreach($events as $dt=>$evts): foreach($evts as $ev): ?>
<div style="display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #F3F4F6">
<div style="min-width:50px;text-align:center"><div style="font-size:18px;font-weight:800;color:#4F46E5"><?php echo date('d',strtotime($dt)); ?></div><div style="font-size:10px;color:#9CA3AF"><?php echo date('D',strtotime($dt)); ?></div></div>
<div style="flex:1"><div style="font-size:13px;font-weight:600"><?php echo htmlspecialchars($ev['title']); ?></div><div style="font-size:11px;color:#9CA3AF"><?php if(($ev['_type']??'')==='calendar_event'){echo ucfirst($ev['type']??'Event');}else{$subj=$db->find('subjects','id',$ev['subject_id']??0);echo $subj?htmlspecialchars($subj['name']):''; echo ' | '.$ev['start_time'].' - '.$ev['end_time'];}?></div></div>
<span class="badge <?php if(($ev['_type']??'')==='calendar_event'){echo 'bp';}else{echo $ev['status']==='live'?'bg':($ev['status']==='completed'?'bb':'bo');} ?>"><?php echo ($ev['_type']??'')==='calendar_event'?ucfirst($ev['type']??'Event'):ucfirst($ev['status']); ?></span></div>
<?php endforeach; endforeach; ?></div>
<?php endif; ?>

<?php /* === PACKAGES === */ ?>
<?php elseif($page==='packages'):
$ep=$edit>0?$db->find('packages','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $ep?'edit':'plus-circle'; ?>"></i><?php echo $ep?'Edit Package':'Add Package'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $ep?'edit_package':'add_package'; ?>">
<?php if($ep): ?><input type="hidden" name="id" value="<?php echo $ep['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Name</label><input type="text" name="name" value="<?php echo $ep?htmlspecialchars($ep['name']):''; ?>" required></div><div class="form-group"><label>Price (BDT)</label><input type="number" name="price" value="<?php echo $ep?$ep['price']:''; ?>" step="1" required></div></div>
<div class="form-row"><div class="form-group"><label>Duration (days)</label><input type="number" name="duration" value="<?php echo $ep?$ep['duration']:30; ?>"></div>
<?php if($ep): ?><div class="form-group"><label>Status</label><select name="is_active"><option value="1" <?php echo $ep['is_active']?'selected':''; ?>>Active</option><option value="0" <?php echo !$ep['is_active']?'selected':''; ?>>Inactive</option></select></div><?php endif; ?></div>
<div class="form-group"><label>Features</label><input type="text" name="features" value="<?php echo $ep?htmlspecialchars($ep['features']):''; ?>" placeholder="AI Tutor, Live Classes"></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $ep?'Update':'Add'; ?></button><?php if($ep): ?><a href="?page=packages" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="credit-card"></i>All Packages (<?php echo count($allPackages); ?>)</h3>
<table><tr><th>Name</th><th>Price</th><th>Duration</th><th>Features</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allPackages as $p): ?>
<tr><td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td><td><strong style="color:#4F46E5"><?php echo $p['price']; ?> BDT</strong></td><td><?php echo $p['duration']; ?>d</td>
<td style="font-size:11px;max-width:200px"><?php echo htmlspecialchars($p['features']); ?></td>
<td><span class="badge <?php echo $p['is_active']?'bg':'br'; ?>"><?php echo $p['is_active']?'Active':'Inactive'; ?></span></td>
<td class="actions"><a href="?page=packages&edit=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=packages&edit=<?php echo $p['id']; ?>&del_table=packages" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === SUBSCRIPTIONS === */ ?>
<?php elseif($page==='subscriptions'):
$tab=$_GET['tab']??'platform';
$allSubs=$db->query('subscriptions');
$pendingCount=0;$approvedCount=0;$rejectedCount=0;
foreach($allSubs as $s){if($s['status']==='pending')$pendingCount++;if($s['status']==='approved')$approvedCount++;if($s['status']==='rejected')$rejectedCount++;}
$filteredSubs=array_filter($allSubs,function($s)use($tab){return($s['type']??'teacher')===$tab;});
?>
<div class="sg">
<div class="sc"><div class="si y"><i data-lucide="clock"></i></div><div class="st"><h3><?php echo $pendingCount; ?></h3><p>Pending</p></div></div>
<div class="sc"><div class="si g"><i data-lucide="check-circle"></i></div><div class="st"><h3><?php echo $approvedCount; ?></h3><p>Approved</p></div></div>
<div class="sc"><div class="si r"><i data-lucide="x-circle"></i></div><div class="st"><h3><?php echo $rejectedCount; ?></h3><p>Rejected</p></div></div>
<div class="sc"><div class="si p"><i data-lucide="users"></i></div><div class="st"><h3><?php echo count($allSubs); ?></h3><p>Total Requests</p></div></div></div>

<div style="display:flex;gap:8px;margin-bottom:16px">
<a href="?page=subscriptions&tab=platform" style="padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;<?php echo $tab==='platform'?'background:#4F46E5;color:white':'background:#F3F4F6;color:#374151'; ?>">Platform Subscriptions</a>
<a href="?page=subscriptions&tab=teacher" style="padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;<?php echo $tab==='teacher'?'background:#4F46E5;color:white':'background:#F3F4F6;color:#374151'; ?>">Teacher Subscriptions</a>
</div>

<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h3 style="margin-bottom:0"><i data-lucide="user-check"></i><?php echo $tab==='platform'?'Platform':'Teacher'; ?> Subscription Requests</h3></div>
<?php if(empty($filteredSubs)):?>
<div style="text-align:center;padding:40px 20px;color:#9CA3AF"><i data-lucide="inbox" style="width:48px;height:48px;margin-bottom:12px;opacity:0.3"></i><p style="font-size:14px;font-weight:600">No subscription requests</p><p style="font-size:12px">When students subscribe to <?php echo $tab==='platform'?'platform packages':'teachers'; ?>, requests will appear here.</p></div>
<?php else:?>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search subscriptions..." oninput="filterTable(this,'subs-table')"></div>
<table id="subs-table"><tr><th>Student</th><?php if($tab==='teacher'):?><th>Teacher</th><?php endif;?><th>Package</th><th>Amount</th><th>Transaction ID</th><th>Status</th><th>Date</th><th>Actions</th></tr>
<?php foreach($filteredSubs as $s):
$student=$db->find('users','id',$s['student_id']);
$teacherUser=null;
if($tab==='teacher'){
    $teachers=$db->query('teachers');
    foreach($teachers as $t){if(intval($t['user_id'])===intval($s['teacher_id'])){$teacherUser=$db->find('users','id',$t['user_id']);break;}}
}
$pkg=$db->find('packages','id',$s['package_id']);
?>
<tr>
<td><strong><?php echo htmlspecialchars($student['name']??'Unknown'); ?></strong><br><span style="font-size:10px;color:#9CA3AF"><?php echo htmlspecialchars($student['email']??''); ?></span></td>
<?php if($tab==='teacher'):?><td><strong><?php echo htmlspecialchars($teacherUser['name']??'Unknown'); ?></strong></td><?php endif;?>
<td><?php echo htmlspecialchars($pkg['name']??'Unknown'); ?></td>
<td><strong style="color:#4F46E5"><?php echo number_format($s['amount'],0); ?> BDT</strong></td>
<td><code style="background:#1E293B;padding:2px 6px;border-radius:4px;font-size:11px"><?php echo htmlspecialchars($s['transaction_id']); ?></code></td>
<td><span class="badge <?php echo $s['status']==='approved'?'bg':($s['status']==='rejected'?'br':($s['status']==='teacher_approved'?'bp':'bo')); ?>"><?php echo ucfirst(str_replace('_',' ',$s['status'])); ?></span></td>
<td style="font-size:11px"><?php echo date('M d, Y',strtotime($s['created_at'])); ?></td>
<td class="actions">
<?php if($s['status']==='pending'||$s['status']==='teacher_approved'):?>
<form method="POST" style="display:inline;margin:0"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="approve_sub"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><input type="hidden" name="tab" value="<?php echo $tab; ?>"><button type="submit" class="btn btn-sm" style="background:#10B981;color:white;padding:6px 14px;cursor:pointer">Approve</button></form>
<form method="POST" style="display:inline;margin:0"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="reject_sub"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><input type="hidden" name="tab" value="<?php echo $tab; ?>"><button type="submit" class="btn btn-danger btn-sm" style="padding:6px 14px;cursor:pointer" onclick="return confirm('Reject this subscription?')">Reject</button></form>
<?php else: ?>
<span style="font-size:10px;color:#9CA3AF"><?php echo $s['approved_at']?date('M d',strtotime($s['approved_at'])):'-'; ?></span>
<?php endif; ?>
</td></tr>
<?php endforeach; ?></table>
<?php endif;?></div>

<?php /* === REVENUE === */ ?>
<?php elseif($page==='revenue'):
$pu=count($db->findAll('users','is_premium',1));
$approvedSubs=array_filter($db->query('subscriptions'),function($s){return$s['status']==='approved';});
$tr=0;
foreach($approvedSubs as $s){$tr+=floatval($s['amount']);}
$platformSubs=array_filter($approvedSubs,function($s){return($s['type']??'teacher')==='platform';});
$teacherSubs=array_filter($approvedSubs,function($s){return($s['type']??'teacher')==='teacher';});
$platformRevenue=0;foreach($platformSubs as $s){$platformRevenue+=floatval($s['amount']);}
$teacherRevenue=0;foreach($teacherSubs as $s){$teacherRevenue+=floatval($s['amount']);}
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:12px"><button class="btn btn-outline btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:12px;height:12px"></i> Print / Export PDF</button></div>
<div class="sg">
<div class="sg">
<div class="sc"><div class="si g"><i data-lucide="dollar-sign"></i></div><div class="st"><h3><?php echo number_format($tr); ?> BDT</h3><p>Total Revenue</p></div></div>
<div class="sc"><div class="si p"><i data-lucide="users"></i></div><div class="st"><h3><?php echo $pu; ?></h3><p>Premium Users</p></div></div>
<div class="sc"><div class="si b"><i data-lucide="monitor"></i></div><div class="st"><h3><?php echo number_format($platformRevenue); ?> BDT</h3><p>Platform Revenue</p></div></div>
<div class="sc"><div class="si o"><i data-lucide="graduation-cap"></i></div><div class="st"><h3><?php echo number_format($teacherRevenue); ?> BDT</h3><p>Teacher Revenue</p></div></div></div>
<div class="card"><h3><i data-lucide="bar-chart-3"></i>Revenue by Package</h3>
<table><tr><th>Package</th><th>Price</th><th>Subscribers</th><th>Revenue</th></tr>
<?php foreach($allPackages as $p):
$pSubs=array_filter($approvedSubs,function($s)use($p){returnintval($s['package_id'])===intval($p['id']);});
$pRev=0;foreach($pSubs as $s){$pRev+=floatval($s['amount']);}
?>
<tr><td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td><td><?php echo number_format($p['price']); ?> BDT</td><td><?php echo count($pSubs); ?></td><td><strong style="color:#10B981"><?php echo number_format($pRev); ?> BDT</strong></td></tr>
<?php endforeach; ?>
<tr style="background:#F9FAFB;font-weight:700"><td>Total</td><td></td><td><?php echo count($approvedSubs); ?></td><td style="color:#10B981"><?php echo number_format($tr); ?> BDT</td></tr></table></div>

<?php /* === SETTINGS === */ ?>
<?php elseif($page==='settings'): ?>
<div class="card"><h3><i data-lucide="settings"></i>General Settings</h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="update_settings">
<div class="grid-2"><div>
<?php foreach(array_slice($allSettings,0,4) as $s): ?>
<div class="form-group"><label><?php echo ucfirst(str_replace('_',' ',$s['key'])); ?></label>
<?php if($s['key']==='ai_enabled'): ?><select name="<?php echo $s['key']; ?>"><option value="1" <?php echo $s['value']==='1'?'selected':''; ?>>Enabled</option><option value="0" <?php echo $s['value']==='0'?'selected':''; ?>>Disabled</option></select>
<?php else: ?><input type="text" name="<?php echo $s['key']; ?>" value="<?php echo htmlspecialchars($s['value']); ?>"><?php endif; ?></div>
<?php endforeach; ?></div><div>
<?php foreach(array_slice($allSettings,4) as $s): ?>
<div class="form-group"><label><?php echo ucfirst(str_replace('_',' ',$s['key'])); ?></label>
<?php if($s['key']==='ai_enabled'): ?><select name="<?php echo $s['key']; ?>"><option value="1" <?php echo $s['value']==='1'?'selected':''; ?>>Enabled</option><option value="0" <?php echo $s['value']==='0'?'selected':''; ?>>Disabled</option></select>
<?php else: ?><input type="text" name="<?php echo $s['key']; ?>" value="<?php echo htmlspecialchars($s['value']); ?>"><?php endif; ?></div>
<?php endforeach; ?></div></div>
<button type="submit" class="btn btn-primary">Save Settings</button></form></div>

<?php /* === AI SETTINGS === */ ?>
<?php elseif($page==='ai_settings'): ?>
<?php
$allSettings = $db->query('settings');
$sMap = [];
foreach($allSettings as $s) $sMap[$s['key']] = $s['value'];
?>
<div class="card"><h3><i data-lucide="user"></i>Chat Bot Identity</h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="update_settings">
<div class="form-group"><label>Bot Name</label><input type="text" name="ai_name" value="<?php echo htmlspecialchars($sMap['ai_name'] ?? 'Snorii AI'); ?>"></div>
<div class="form-group"><label>Greeting Message</label><textarea name="ai_greeting" rows="2"><?php echo htmlspecialchars($sMap['ai_greeting'] ?? ''); ?></textarea></div>
<div class="form-group"><label>Subtitle</label><input type="text" name="ai_subtitle" value="<?php echo htmlspecialchars($sMap['ai_subtitle'] ?? 'Always here to help'); ?>"></div>
<div class="form-group"><label>System Prompt</label><textarea name="ai_system_prompt" rows="3"><?php echo htmlspecialchars($sMap['ai_system_prompt'] ?? ''); ?></textarea></div>
<div class="form-group"><label>Suggested Prompts (pipe-separated)</label><input type="text" name="ai_suggested_prompts" value="<?php echo htmlspecialchars($sMap['ai_suggested_prompts'] ?? ''); ?>" placeholder="Prompt 1|Prompt 2|Prompt 3"></div>
<button type="submit" class="btn btn-primary">Save Bot Identity</button></form></div>
<div class="card"><h3><i data-lucide="brain"></i>AI Configuration</h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="update_settings">
<div class="form-group"><label>AI Status</label><select name="ai_enabled"><option value="1" <?php echo ($sMap['ai_enabled']??'1')==='1'?'selected':''; ?>>Enabled</option><option value="0" <?php echo ($sMap['ai_enabled']??'1')==='0'?'selected':''; ?>>Disabled</option></select></div>
<div class="form-row"><div class="form-group"><label>AI Provider</label><select name="ai_provider"><option value="openai">OpenAI</option><option value="gemini">Google Gemini</option><option value="claude">Anthropic Claude</option><option value="openrouter">OpenRouter</option><option value="moondream">Moondream</option></select></div><div class="form-group"><label>Max MCQ Options</label><input type="number" name="max_mcq_options" value="<?php echo htmlspecialchars($sMap['max_mcq_options'] ?? '6'); ?>" min="2" max="10"></div></div>
<div class="form-group"><label>OpenAI API Key</label><input type="password" name="openai_api_key" placeholder="sk-..."></div>
<div class="form-group"><label>Gemini API Key</label><input type="password" name="gemini_api_key" placeholder="AIza..."></div>
<div class="form-group"><label>Claude API Key</label><input type="password" name="claude_api_key" placeholder="sk-ant-..."></div>
<div class="form-group"><label>OpenRouter API Key</label><input type="password" name="openrouter_api_key" placeholder="sk-or-..."></div>
<div class="form-group"><label>Moondream API Key</label><input type="password" name="moondream_api_key" placeholder="Get free key at console.moondream.ai"></div>
<p style="font-size:11px;color:#6b7280;margin-top:-8px">Fallback: Puter.js (free, no key) → Moondream (free $5/mo) → configured provider</p>
<div class="form-row"><div class="form-group"><label>Temperature (0-1)</label><input type="number" name="ai_temperature" value="<?php echo htmlspecialchars($sMap['ai_temperature'] ?? '0.7'); ?>" step="0.1" min="0" max="1"></div><div class="form-group"><label>Max Tokens</label><input type="number" name="ai_max_tokens" value="<?php echo htmlspecialchars($sMap['ai_max_tokens'] ?? '2048'); ?>" min="256" max="8192"></div></div>
<button type="submit" class="btn btn-primary">Save AI Settings</button></form></div>
<div class="card"><h3><i data-lucide="activity"></i>AI Usage Stats</h3>
<div class="sg">
<div class="sc"><div class="si p"><i data-lucide="message-square"></i></div><div class="st"><h3><?php echo count($allMessages); ?></h3><p>Chat Messages</p></div></div>
<div class="sc"><div class="si g"><i data-lucide="check-circle"></i></div><div class="st"><h3><?php echo ($sMap['ai_enabled']??'1')==='1'?'Active':'Disabled'; ?></h3><p>AI Status</p></div></div>
<div class="sc"><div class="si o"><i data-lucide="cpu"></i></div><div class="st"><h3><?php echo ucfirst($sMap['ai_provider']??'openai'); ?></h3><p>Provider</p></div></div>
<div class="sc"><div class="si b"><i data-lucide="thermometer"></i></div><div class="st"><h3><?php echo $sMap['ai_temperature']??'0.7'; ?></h3><p>Temperature</p></div></div></div></div>

<?php /* === CALENDAR EVENTS === */ ?>
<?php elseif($page==='calendar_events'):
$ece=$edit>0?$db->find('calendar_events','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $ece?'edit':'plus-circle'; ?>"></i><?php echo $ece?'Edit Event':'Add Calendar Event'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $ece?'edit_calendar_event':'add_calendar_event'; ?>">
<?php if($ece): ?><input type="hidden" name="id" value="<?php echo $ece['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo $ece?htmlspecialchars($ece['title']):''; ?>" required></div><div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo $ece?$ece['date']:''; ?>" required></div></div>
<div class="form-row"><div class="form-group"><label>Type</label><select name="type"><?php foreach(['exam','homework','class','holiday','event'] as $tv): ?><option value="<?php echo $tv; ?>" <?php echo $ece&&$ece['type']===$tv?'selected':''; ?>><?php echo ucfirst($tv); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Color</label><input type="color" name="color" value="<?php echo $ece?($ece['color']??'#4F46E5'):'#4F46E5'; ?>"></div></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $ece?'Update':'Add'; ?></button><?php if($ece): ?><a href="?page=calendar_events" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="calendar-range"></i>All Events (<?php echo count($allCalendarEvents); ?>)</h3>
<table><tr><th>Title</th><th>Date</th><th>Type</th><th>Color</th><th>Actions</th></tr>
<?php foreach($allCalendarEvents as $ev): ?>
<tr><td><strong><?php echo htmlspecialchars($ev['title']); ?></strong></td><td><?php echo $ev['date']; ?></td>
<td><span class="badge <?php echo $ev['type']==='exam'?'br':($ev['type']==='homework'?'bo':($ev['type']==='holiday'?'bg':'bb')); ?>"><?php echo ucfirst($ev['type']); ?></span></td>
<td><div style="width:24px;height:24px;border-radius:6px;background:<?php echo $ev['color']??'#4F46E5'; ?>;border:1px solid #E5E7EB"></div></td>
<td class="actions"><a href="?page=calendar_events&edit=<?php echo $ev['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=calendar_events&edit=<?php echo $ev['id']; ?>&del_table=calendar_events" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === CLASS SCHEDULE === */ ?>
<?php elseif($page==='class_schedule'):
$cs=$edit>0?$db->find('class_schedule','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $cs?'edit':'plus-circle'; ?>"></i><?php echo $cs?'Edit Schedule':'Add Class Schedule'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $cs?'edit_class_schedule':'add_class_schedule'; ?>">
<?php if($cs): ?><input type="hidden" name="id" value="<?php echo $cs['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Subject</label><select name="subject"><?php foreach($allSubjects as $s): ?><option value="<?php echo htmlspecialchars($s['name']); ?>" <?php echo $cs&&$cs['subject']===$s['name']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Topic</label><input type="text" name="topic" value="<?php echo $cs?htmlspecialchars($cs['topic']):''; ?>" required></div></div>
<div class="form-row"><div class="form-group"><label>Class Name</label><select name="class_name"><option value="Class 7" <?php echo $cs&&$cs['class_name']==='Class 7'?'selected':''; ?>>Class 7</option><option value="Class 8" <?php echo (!$cs||$cs['class_name']==='Class 8')?'selected':''; ?>>Class 8</option><option value="Class 9 Science" <?php echo $cs&&$cs['class_name']==='Class 9 Science'?'selected':''; ?>>Class 9 Science</option><option value="Class 9 Commerce" <?php echo $cs&&$cs['class_name']==='Class 9 Commerce'?'selected':''; ?>>Class 9 Commerce</option><option value="Class 9 Arts" <?php echo $cs&&$cs['class_name']==='Class 9 Arts'?'selected':''; ?>>Class 9 Arts</option><option value="Class 10 Science" <?php echo $cs&&$cs['class_name']==='Class 10 Science'?'selected':''; ?>>Class 10 Science</option><option value="Class 10 Commerce" <?php echo $cs&&$cs['class_name']==='Class 10 Commerce'?'selected':''; ?>>Class 10 Commerce</option><option value="Class 10 Arts" <?php echo $cs&&$cs['class_name']==='Class 10 Arts'?'selected':''; ?>>Class 10 Arts</option></select></div><div class="form-group"><label>Day</label><select name="day"><?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $dv): ?><option value="<?php echo $dv; ?>" <?php echo $cs&&$cs['day']===$dv?'selected':''; ?>><?php echo $dv; ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group"><label>Start Time</label><input type="time" name="start_time" value="<?php echo $cs?htmlspecialchars($cs['start_time'] ?? $cs['time']):''; ?>" required></div><div class="form-group"><label>End Time</label><input type="time" name="end_time" value="<?php echo $cs?htmlspecialchars($cs['end_time'] ?? ''):''; ?>" required></div></div>
<div class="form-row"><div class="form-group"><label>Teacher</label><select name="teacher_id" id="csTeacherSelect" onchange="document.getElementById('csTeacherName').value=this.options[this.selectedIndex].text.split(' (')[0]"><?php foreach($allTeachers as $t): ?><option value="<?php echo $t['user_id']; ?>" data-name="<?php echo htmlspecialchars($t['name']); ?>" <?php echo $cs&&$cs['teacher_id']==$t['user_id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['subject']); ?>)</option><?php endforeach; ?></select></div><div class="form-group"><label>Students Count</label><input type="number" name="students_count" value="<?php echo $cs?$cs['students_count']:25; ?>"></div></div>
<input type="hidden" name="teacher_name" id="csTeacherName" value="<?php echo $cs?htmlspecialchars($cs['teacher_name']):''; ?>">
<div class="form-group"><label>Status</label><select name="status"><?php foreach(['upcoming','ongoing','completed'] as $sv): ?><option value="<?php echo $sv; ?>" <?php echo $cs&&$cs['status']===$sv?'selected':''; ?>><?php echo ucfirst($sv); ?></option><?php endforeach; ?></select></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $cs?'Update':'Add'; ?></button><?php if($cs): ?><a href="?page=class_schedule" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="clock"></i>All Schedules (<?php echo count($allClassSchedule); ?>)</h3>
<table><tr><th>Subject</th><th>Topic</th><th>Day</th><th>Time</th><th>Teacher</th><th>Status</th><th>Actions</th></tr>
<?php foreach($allClassSchedule as $cs2): ?>
<tr><td><strong><?php echo htmlspecialchars($cs2['subject']); ?></strong></td><td><?php echo htmlspecialchars($cs2['topic']); ?></td><td><?php echo $cs2['day']; ?></td>
<td><?php echo $cs2['time']; ?></td><td><?php echo htmlspecialchars($cs2['teacher_name']); ?></td>
<td><span class="badge <?php echo $cs2['status']==='completed'?'bg':($cs2['status']==='ongoing'?'bo':'bb'); ?>"><?php echo ucfirst($cs2['status']); ?></span></td>
<td class="actions"><a href="?page=class_schedule&edit=<?php echo $cs2['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=class_schedule&edit=<?php echo $cs2['id']; ?>&del_table=class_schedule" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === REPORTS === */ ?>
<?php elseif($page==='reports'):
$er2=$edit>0?$db->find('reports','id',$edit):null; ?>
<div class="card"><h3><i data-lucide="<?php echo $er2?'edit':'plus-circle'; ?>"></i><?php echo $er2?'Edit Report':'Add Report'; ?></h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="<?php echo $er2?'edit_report':'add_report'; ?>">
<?php if($er2): ?><input type="hidden" name="id" value="<?php echo $er2['id']; ?>"><?php endif; ?>
<div class="form-row"><div class="form-group"><label>Student</label><select name="student_id"><?php foreach($allUsers as $u): if($u['role']==='student'): ?><option value="<?php echo $u['id']; ?>" <?php echo $er2&&$er2['student_id']==$u['id']?'selected':''; ?>><?php echo htmlspecialchars($u['name']); ?></option><?php endif; endforeach; ?></select></div><div class="form-group"><label>Teacher</label><select name="teacher_id"><?php foreach($allTeachers as $t): ?><option value="<?php echo $t['user_id']; ?>" <?php echo $er2&&$er2['teacher_id']==$t['user_id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group"><label>Subject</label><select name="subject"><?php foreach($allSubjects as $s): ?><option value="<?php echo htmlspecialchars($s['name']); ?>" <?php echo $er2&&$er2['subject']===$s['name']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Class</label><select name="class"><option value="Class 7" <?php echo $er2&&$er2['class']==='Class 7'?'selected':''; ?>>Class 7</option><option value="Class 8" <?php echo (!$er2||$er2['class']==='Class 8')?'selected':''; ?>>Class 8</option><option value="Class 9 Science" <?php echo $er2&&$er2['class']==='Class 9 Science'?'selected':''; ?>>Class 9 Science</option><option value="Class 9 Commerce" <?php echo $er2&&$er2['class']==='Class 9 Commerce'?'selected':''; ?>>Class 9 Commerce</option><option value="Class 9 Arts" <?php echo $er2&&$er2['class']==='Class 9 Arts'?'selected':''; ?>>Class 9 Arts</option><option value="Class 10 Science" <?php echo $er2&&$er2['class']==='Class 10 Science'?'selected':''; ?>>Class 10 Science</option><option value="Class 10 Commerce" <?php echo $er2&&$er2['class']==='Class 10 Commerce'?'selected':''; ?>>Class 10 Commerce</option><option value="Class 10 Arts" <?php echo $er2&&$er2['class']==='Class 10 Arts'?'selected':''; ?>>Class 10 Arts</option></select></div></div>
<div class="form-row"><div class="form-group"><label>Grade</label><select name="grade"><?php foreach(['A+','A','B+','B','C','D','F'] as $gv): ?><option value="<?php echo $gv; ?>" <?php echo $er2&&$er2['grade']===$gv?'selected':''; ?>><?php echo $gv; ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Score</label><input type="number" name="score" value="<?php echo $er2?$er2['score']:0; ?>" min="0" max="100"></div></div>
<div class="form-row"><div class="form-group"><label>Behavior</label><select name="behavior"><?php foreach(['Excellent','Good','Satisfactory','Needs Improvement','Poor'] as $bv): ?><option value="<?php echo $bv; ?>" <?php echo $er2&&$er2['behavior']===$bv?'selected':''; ?>><?php echo $bv; ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo $er2?$er2['date']:date('Y-m-d'); ?>"></div></div>
<div class="form-group"><label>Comment</label><textarea name="comment" rows="2"><?php echo $er2?htmlspecialchars($er2['comment']):''; ?></textarea></div>
<div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><?php echo $er2?'Update':'Add'; ?></button><?php if($er2): ?><a href="?page=reports" class="btn btn-outline">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><h3><i data-lucide="file-bar-chart"></i>All Reports (<?php echo count($allReports); ?>)</h3>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search reports..." oninput="filterTable(this,'rep-table')"></div>
<table id="rep-table"><tr><th>Student</th><th>Subject</th><th>Grade</th><th>Score</th><th>Behavior</th><th>Date</th><th>Actions</th></tr>
<?php foreach($allReports as $rpt): $rptSt=$db->find('users','id',$rpt['student_id']); ?>
<tr><td><strong><?php echo $rptSt?htmlspecialchars($rptSt['name']):'N/A'; ?></strong></td><td><?php echo htmlspecialchars($rpt['subject']); ?></td>
<td><span class="badge <?php echo in_array($rpt['grade'],['A+','A'])?'bg':($rpt['grade']==='B+'?'bo':'bb'); ?>"><?php echo $rpt['grade']; ?></span></td>
<td><strong style="color:#4F46E5"><?php echo $rpt['score']; ?>%</strong></td><td><?php echo $rpt['behavior']; ?></td>
<td style="font-size:11px;color:#9CA3AF"><?php echo $rpt['date']; ?></td>
<td class="actions"><a href="?page=reports&edit=<?php echo $rpt['id']; ?>" class="btn btn-primary btn-sm"><i data-lucide="edit-2" style="width:12px;height:12px"></i></a><a href="?page=reports&edit=<?php echo $rpt['id']; ?>&del_table=reports" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === BADGES (in student_progress page) === */ ?>
<?php elseif($page==='student_progress'):
$progressRaw=$db->query('student_progress');
$progress=[];
foreach($progressRaw as $sp){
    $su=$db->find('users','id',$sp['student_id']);
    $sp['name']=$su['name']??'N/A';
    $sp['email']=$su['email']??'';
    $sp['class']=$su['class']??'';
    $progress[]=$sp;
}
$allBadgesRaw=$db->query('badges');
$allBadgesWithNames=[];
foreach($allBadgesRaw as $b){
    $bu=$db->find('users','id',$b['student_id']);
    $b['student_name']=$bu['name']??'N/A';
    $allBadgesWithNames[]=$b;
} ?>
<div class="card"><h3><i data-lucide="bar-chart-3"></i>Student Learning Progress</h3>
<div class="search-bar"><i data-lucide="search"></i><input type="text" placeholder="Search students..." oninput="filterTable(this,'prog-table')"></div>
<table id="prog-table"><tr><th>Student</th><th>Class</th><th>Books</th><th>HW Score</th><th>Exam Score</th><th>Streak</th><th>Badges</th><th>Hours</th><th>Last Active</th></tr>
<?php foreach($progress as $p): ?>
<tr><td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td><td><?php echo $p['class']; ?></td><td><?php echo $p['books_read']; ?></td>
<td><span class="badge <?php echo $p['homework_score']>=80?'bg':($p['homework_score']>=60?'bo':'br'); ?>"><?php echo $p['homework_score']; ?>%</span></td>
<td><span class="badge <?php echo $p['exam_score']>=80?'bg':($p['exam_score']>=60?'bo':'br'); ?>"><?php echo $p['exam_score']; ?>%</span></td>
<td><span class="badge bp"><?php echo $p['streak']; ?> days</span></td><td><?php echo $p['badges_count']; ?></td><td><?php echo $p['study_hours']; ?>h</td>
<td style="font-size:11px;color:#9CA3AF"><?php echo $p['last_active']; ?></td></tr>
<?php endforeach; ?></table></div>
<div class="card"><h3><i data-lucide="award"></i>Add Badge</h3>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="add_badge">
<div class="form-row"><div class="form-group"><label>Student</label><select name="student_id"><?php foreach($allUsers as $u): if($u['role']==='student'): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endif; endforeach; ?></select></div><div class="form-group"><label>Badge Name</label><input type="text" name="name" placeholder="e.g. Top Performer" required></div></div>
<div class="form-row"><div class="form-group"><label>Icon</label><input type="text" name="icon" placeholder="e.g. trophy" value="award"></div><div class="form-group"><label>Description</label><input type="text" name="description" placeholder="Badge description"></div></div>
<button type="submit" class="btn btn-primary">Add Badge</button></form></div>
<div class="card"><h3><i data-lucide="award"></i>All Badges (<?php echo count($allBadgesWithNames); ?>)</h3>
<table><tr><th>Student</th><th>Badge</th><th>Icon</th><th>Description</th><th>Earned</th><th>Actions</th></tr>
<?php foreach($allBadgesWithNames as $b): ?>
<tr><td><strong><?php echo htmlspecialchars($b['student_name']); ?></strong></td><td><?php echo htmlspecialchars($b['name']); ?></td>
<td><i data-lucide="<?php echo $b['icon']??'award'; ?>" style="width:16px;height:16px;color:#F59E0B"></i></td>
<td style="font-size:12px"><?php echo htmlspecialchars($b['description']); ?></td>
<td style="font-size:11px;color:#9CA3AF"><?php echo $b['earned_date']; ?></td>
<td class="actions"><a href="?page=student_progress&edit=<?php echo $b['id']; ?>&del_table=badges" class="btn btn-danger btn-sm" onclick="return confirm('Delete badge?')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></a></td></tr>
<?php endforeach; ?></table></div>

<?php /* === ACTIVITY LOG === */ ?>
<?php elseif($page==='activity_log'):
$allActivityLog=$db->queryAll('SELECT al.*, u.name as user_name, u.role as user_role FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC');
?>
<div class="card"><h3><i data-lucide="list"></i>Activity Log</h3>
<p style="font-size:12px;color:#9CA3AF;margin:0 0 12px">All user actions across the platform.</p>
<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
<div class="search-bar" style="flex:1"><i data-lucide="search"></i><input type="text" placeholder="Search activity..." oninput="filterTable(this,'actlog-table')"></div>
<select id="actlog-role-filter" class="form-input" style="width:auto;padding:6px 10px" onchange="filterActivityLog()">
<option value="">All Roles</option><option value="student">Students</option><option value="teacher">Teachers</option>
</select>
<select id="actlog-action-filter" class="form-input" style="width:auto;padding:6px 10px" onchange="filterActivityLog()">
<option value="">All Actions</option><option value="login">Login</option><option value="view_homework">View HW</option><option value="submit_homework">Submit HW</option><option value="create_homework">Create HW</option><option value="start_exam">Start Exam</option><option value="view_chapter">Read Chapter</option>
</select>
<button class="btn btn-outline btn-sm" onclick="exportTable('actlog-table','activity-log.csv')"><i data-lucide="download" style="width:12px;height:12px"></i> Export CSV</button>
<button class="btn btn-outline btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:12px;height:12px"></i> Print</button>
</div>
<table id="actlog-table"><tr><th>#</th><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>Date</th></tr>
<?php if(empty($allActivityLog)): ?>
<tr><td colspan="6" style="text-align:center;color:#9CA3AF;padding:20px">No activity recorded yet.</td></tr>
<?php else: foreach($allActivityLog as $al): ?>
<tr>
<td><?php echo $al['id']; ?></td>
<td><strong><?php echo htmlspecialchars($al['user_name']??'Unknown'); ?></strong></td>
<td><span class="badge <?php echo ($al['user_role']??'')==='teacher'?'bo':(($al['user_role']??'')==='admin'?'bp':'bg'); ?>"><?php echo ucfirst(htmlspecialchars($al['user_role']??'N/A')); ?></span></td>
<td><span class="badge br"><?php echo htmlspecialchars($al['action']); ?></span></td>
<td style="font-size:12px"><?php echo htmlspecialchars($al['details']??'-'); ?></td>
<td style="font-size:11px;color:#9CA3AF"><?php echo htmlspecialchars($al['created_at']); ?></td>
</tr>
<?php endforeach; endif; ?></table>
</div>
<script>
function filterActivityLog(){
var role=document.getElementById('actlog-role-filter').value.toLowerCase();
var action=document.getElementById('actlog-action-filter').value.toLowerCase();
var rows=document.getElementById('actlog-table').getElementsByTagName('tr');
for(var i=1;i<rows.length;i++){
var cells=rows[i].getElementsByTagName('td');
if(cells.length<6)continue;
var r=cells[2].textContent.toLowerCase();
var a=cells[3].textContent.toLowerCase();
var show=(role===''||r.indexOf(role)>-1)&&(action===''||a.indexOf(action)>-1);
rows[i].style.display=show?'':'none';
}}
</script>

<?php /* === BANK QUESTIONS removed (merged into Question Bank) === */ ?>
<?php elseif($page==='bank-questions'): ?>
<script>window.location.href='?page=questions';</script>

<?php endif; ?>
</div></div>

<script>
lucide.createIcons();
function toggleSidebar(){document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sb-overlay').classList.toggle('show');}
function toggleTeacherFields(){var r=document.getElementById('userRoleSelect');var tf=document.getElementById('teacherFields');if(r&&tf){tf.style.display=r.value==='teacher'?'block':'none';}}
function filterTable(input,tableId){
var q=input.value.toLowerCase();var rows=document.getElementById(tableId).getElementsByTagName('tr');
for(var i=1;i<rows.length;i++){var txt=rows[i].textContent.toLowerCase();rows[i].style.display=txt.indexOf(q)>-1?'':'none';}
}
function exportTable(tableId,filename){
var csv=[];var rows=document.getElementById(tableId).querySelectorAll('tr');
for(var i=0;i<rows.length;i++){var cols=rows[i].querySelectorAll('td,th');var row=[];
for(var j=0;j<cols.length;j++)row.push('"'+cols[j].textContent.replace(/"/g,'""').trim()+'"');
csv.push(row.join(','));}
var blob=new Blob([csv.join('\n')],{type:'text/csv'});var a=document.createElement('a');
a.href=URL.createObjectURL(blob);a.download=filename;a.click();
}

// --- PAGINATION ---
var _paginatedTables={};
function paginateTable(tableId,perPage){
var table=document.getElementById(tableId);if(!table)return;
var rows=[];for(var i=1;i<table.rows.length;i++)rows.push(table.rows[i]);
var total=rows.length;var pages=Math.ceil(total/perPage);var currentPage=1;
_paginatedTables[tableId]={rows:rows,perPage:perPage,total:total,pages:pages,currentPage:currentPage};
renderPagination(tableId);
}
function renderPagination(tableId){
var s=_paginatedTables[tableId];if(!s)return;
var table=document.getElementById(tableId);
s.rows.forEach(function(r){r.style.display='none';});
var start=(s.currentPage-1)*s.perPage;
for(var i=start;i<Math.min(start+s.perPage,s.total);i++)s.rows[i].style.display='';
var existing=document.getElementById('pag-'+tableId);
if(existing)existing.remove();
if(s.pages<=1)return;
var div=document.createElement('div');div.id='pag-'+tableId;
div.style.cssText='display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;padding:10px 0';
var prevBtn=document.createElement('button');prevBtn.textContent='← Prev';prevBtn.className='btn btn-outline btn-sm';
prevBtn.disabled=s.currentPage===1;prevBtn.onclick=function(){s.currentPage--;renderPagination(tableId);};
div.appendChild(prevBtn);
for(var p=1;p<=s.pages;p++){
if(s.pages>7&&p>2&&p<s.pages-1&&Math.abs(p-s.currentPage)>1){if(p===3||p===s.pages-2){var dots=document.createElement('span');dots.textContent='...';dots.style.cssText='color:#9CA3AF;font-size:12px';div.appendChild(dots);}continue;}
var btn=document.createElement('button');btn.textContent=p;btn.className='btn btn-sm '+(p===s.currentPage?'btn-primary':'btn-outline');
btn.onclick=(function(pg){return function(){s.currentPage=pg;renderPagination(tableId);};})(p);
div.appendChild(btn);
}
var nextBtn=document.createElement('button');nextBtn.textContent='Next →';nextBtn.className='btn btn-outline btn-sm';
nextBtn.disabled=s.currentPage===s.pages;nextBtn.onclick=function(){s.currentPage++;renderPagination(tableId);};
div.appendChild(nextBtn);
var info=document.createElement('span');info.style.cssText='font-size:11px;color:#9CA3AF;margin-left:8px';
info.textContent=s.total+' items';div.appendChild(info);
table.parentNode.insertBefore(div,table.nextSibling);
}

// --- TOAST NOTIFICATIONS ---
function showToast(msg,type){
type=type||'success';
var existing=document.querySelector('.admin-toast');if(existing)existing.remove();
var t=document.createElement('div');t.className='admin-toast';
t.style.cssText='position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;color:white;box-shadow:0 8px 24px rgba(0,0,0,0.15);display:flex;align-items:center;gap:8px;animation:toastIn .3s ease;max-width:350px';
var colors={success:'#10B981',error:'#EF4444',info:'#3B82F6',warning:'#F59E0B'};
var icons={success:'check-circle',error:'alert-circle',info:'info',warning:'alert-triangle'};
t.style.background=colors[type]||colors.success;
t.innerHTML='<i data-lucide="'+(icons[type]||'check-circle')+'" style="width:18px;height:18px;flex-shrink:0"></i><span>'+msg+'</span>';
document.body.appendChild(t);
if(typeof lucide!=='undefined')lucide.createIcons();
setTimeout(function(){t.style.animation='toastOut .3s ease forwards';setTimeout(function(){t.remove();},300);},3000);
}
var toastStyle=document.createElement('style');
toastStyle.textContent='@keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}@keyframes toastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}';
document.head.appendChild(toastStyle);

// Replace flash banner with toast
(function(){var msg=document.querySelector('.msg');if(msg){var txt=msg.textContent.trim();showToast(txt,'success');msg.style.display='none';}})();

// --- CONFIRMATION MODALS ---
function confirmModal(title,text,onConfirm){
var overlay=document.createElement('div');
overlay.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:10000;display:flex;align-items:center;justify-content:center;animation:fadeIn .2s';
var modal=document.createElement('div');
modal.style.cssText='background:white;border-radius:16px;padding:28px;max-width:360px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2);animation:modalIn .2s';
modal.innerHTML='<h3 style="font-size:16px;font-weight:700;margin-bottom:8px;color:#1F2937">'+title+'</h3><p style="font-size:13px;color:#6B7280;margin-bottom:20px;line-height:1.5">'+text+'</p><div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn btn-outline" id="modalCancel">Cancel</button><button class="btn btn-danger" id="modalConfirm">Confirm</button></div>';
overlay.appendChild(modal);
document.body.appendChild(overlay);
modal.querySelector('#modalCancel').onclick=function(){overlay.remove();};
modal.querySelector('#modalConfirm').onclick=function(){overlay.remove();onConfirm();};
overlay.onclick=function(e){if(e.target===overlay)overlay.remove();};
}
var modalStyle=document.createElement('style');
modalStyle.textContent='@keyframes fadeIn{from{opacity:0}to{opacity:1}}@keyframes modalIn{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}';
document.head.appendChild(modalStyle);

// Override confirm() calls on delete links
document.addEventListener('click',function(e){
var link=e.target.closest('a[href*="del_table="]');
if(link&&link.getAttribute('onclick')&&link.getAttribute('onclick').indexOf('confirm(')>-1){
e.preventDefault();
var href=link.getAttribute('href');
confirmModal('Delete Item','Are you sure you want to delete this item? This action cannot be undone.',function(){window.location.href=href;}
);
}
});

// --- TABLE SORT ---
document.querySelectorAll('table th').forEach(function(th){
th.style.cursor='pointer';th.title='Click to sort';
th.addEventListener('click',function(){
var table=th.closest('table');var idx=Array.from(th.parentNode.children).indexOf(th);
var rows=Array.from(table.querySelectorAll('tr:not(:first-child)'));
var dir=th.dataset.sortDir==='asc'?'desc':'asc';
th.dataset.sortDir=dir;
table.querySelectorAll('th').forEach(function(h){if(h!==th)h.dataset.sortDir='';});
rows.sort(function(a,b){
var av=(a.children[idx]||{}).textContent||'';var bv=(b.children[idx]||{}).textContent|| '';
var an=parseFloat(av),bn=parseFloat(bv);
if(!isNaN(an)&&!isNaN(bn))return dir==='asc'?an-bn:bn-an;
return dir==='asc'?av.localeCompare(bv):bv.localeCompare(av);
});
rows.forEach(function(r){table.appendChild(r);});
});
});

// --- LOADING SPINNER ON FORM SUBMIT ---
document.querySelectorAll('form[method="POST"]').forEach(function(f){
f.addEventListener('submit',function(){
var btn=f.querySelector('button[type="submit"]');
if(btn){
btn.dataset.origText=btn.textContent;
btn.innerHTML='<svg style="width:16px;height:16px;animation:spin 1s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="30 60"/></svg> Saving...';
btn.disabled=true;
}
});
});
var spinStyle=document.createElement('style');
spinStyle.textContent='@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(spinStyle);

// --- TABLE OVERFLOW ---
document.querySelectorAll('.card').forEach(function(c){
var t=c.querySelector('table');if(t){
var w=document.createElement('div');w.style.cssText='overflow-x:auto;-webkit-overflow-scrolling:touch';
t.parentNode.insertBefore(w,t);w.appendChild(t);
}
});

// Init pagination on key tables (25 per page)
['users-table','bq-table','ss-table'].forEach(function(id){
var t=document.getElementById(id);if(t&&t.rows.length>10)paginateTable(id,25);
});
// Auto-paginate tables with 15+ rows that have IDs
document.querySelectorAll('table[id]').forEach(function(t){
if(t.id&&t.rows.length>15&&!_paginatedTables[t.id])paginateTable(t.id,25);
});
</script>
</body>
</html>