<?php
session_start();

define('DATA_DIR', __DIR__ . '/../data');
define('DB_FILE', DATA_DIR . '/school.json');

function getDataDir() {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
    return DATA_DIR;
}

function getDB() {
    getDataDir();
    return new JsonDB(DB_FILE);
}

class JsonDB {
    private $file;
    private $data;
    
    public function __construct($file) {
        $this->file = $file;
        if (file_exists($file)) {
            $this->data = json_decode(file_get_contents($file), true) ?: [];
        } else {
            $this->data = [];
            $this->seed();
            $this->save();
        }
    }
    
    public function save() {
        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    public function query($table) {
        return $this->data[$table] ?? [];
    }
    
    public function querySingle($sql) {
        // Simple: "SELECT COUNT(*) FROM table WHERE field='value'"
        if (preg_match('/COUNT\(\*\)\s+FROM\s+(\w+)\s+WHERE\s+(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]/i', $sql, $m)) {
            $table = $m[1]; $field = $m[2]; $val = $m[3];
            $count = 0;
            foreach (($this->data[$table] ?? []) as $row) {
                if (isset($row[$field]) && (string)$row[$field] === $val) $count++;
            }
            return $count;
        }
        // "SELECT COUNT(*) FROM table"
        if (preg_match('/COUNT\(\*\)\s+FROM\s+(\w+)/i', $sql, $m)) {
            return count($this->data[$m[1]] ?? []);
        }
        return 0;
    }
    
    public function queryAll($sql) {
        // "SELECT * FROM table WHERE field='value' ORDER BY field DESC LIMIT N"
        // "SELECT * FROM table ORDER BY field DESC LIMIT N"
        // "SELECT t.*, u.name FROM table t JOIN users u ON t.user_id = u.id ..."
        $table = null; $where = []; $order = null; $orderDir = 'ASC'; $limit = null;
        
        if (preg_match('/FROM\s+(\w+)/i', $sql, $m)) $table = $m[1];
        if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER|\s+LIMIT|$)/i', $sql, $m)) {
            // Parse conditions like "field='value' AND field2='value2'"
            preg_match_all('/(\w+)\s*=\s*[\'"]([^\'"]*)[\'"]/i', $m[1], $conds);
            for ($i = 0; $i < count($conds[0]); $i++) {
                $where[$conds[1][$i]] = $conds[2][$i];
            }
        }
        if (preg_match('/ORDER\s+BY\s+(\w+)(?:\s+(DESC|ASC))?/i', $sql, $m)) {
            $order = $m[1]; $orderDir = isset($m[2]) ? strtoupper($m[2]) : 'ASC';
        }
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $m)) $limit = intval($m[1]);
        
        $rows = $this->data[$table] ?? [];
        
        // Apply WHERE
        if (!empty($where)) {
            $rows = array_filter($rows, function($row) use ($where) {
                foreach ($where as $k => $v) {
                    if (!isset($row[$k]) || (string)$row[$k] !== $v) return false;
                }
                return true;
            });
        }
        
        // Apply ORDER
        if ($order) {
            usort($rows, function($a, $b) use ($order, $orderDir) {
                $va = $a[$order] ?? ''; $vb = $b[$order] ?? '';
                $cmp = strcmp((string)$va, (string)$vb);
                return $orderDir === 'DESC' ? -$cmp : $cmp;
            });
        }
        
        // Apply LIMIT
        if ($limit) $rows = array_slice($rows, 0, $limit);
        
        return array_values($rows);
    }
    
    public function insert($table, $row) {
        $maxId = 0;
        foreach (($this->data[$table] ?? []) as $r) {
            if (isset($r['id']) && $r['id'] > $maxId) $maxId = $r['id'];
        }
        $row['id'] = $maxId + 1;
        if (!isset($row['created_at'])) $row['created_at'] = date('Y-m-d H:i:s');
        $this->data[$table][] = $row;
        $this->save();
        return $row['id'];
    }
    
    public function update($table, $id, $data) {
        foreach (($this->data[$table] ?? []) as &$row) {
            if (isset($row['id']) && $row['id'] == $id) {
                $row = array_merge($row, $data);
                $this->save();
                return true;
            }
        }
        return false;
    }
    
    public function delete($table, $id) {
        if (!isset($this->data[$table])) return false;
        $this->data[$table] = array_values(array_filter($this->data[$table], function($row) use ($id) {
            return isset($row['id']) && $row['id'] != $id;
        }));
        $this->save();
        return true;
    }
    
    public function find($table, $field, $value) {
        foreach (($this->data[$table] ?? []) as $row) {
            if (isset($row[$field]) && (string)$row[$field] === (string)$value) return $row;
        }
        return null;
    }
    
    public function findAll($table, $field, $value) {
        return array_values(array_filter(($this->data[$table] ?? []), function($row) use ($field, $value) {
            return isset($row[$field]) && (string)$row[$field] === (string)$value;
        }));
    }
    
    private function seed() {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        
        $this->data['users'] = [
            ['id'=>1,'name'=>'Arman Khan','email'=>'arman@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'8','phone'=>'','school'=>'','is_premium'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>2,'name'=>'Rahim Ahmed','email'=>'rahim@teacher.com','password'=>$hash,'role'=>'teacher','avatar'=>'','class'=>'','phone'=>'','school'=>'','is_premium'=>0,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>3,'name'=>'Admin User','email'=>'admin@school.com','password'=>$hash,'role'=>'admin','avatar'=>'','class'=>'','phone'=>'','school'=>'','is_premium'=>0,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>4,'name'=>'Sarah Khan','email'=>'sarah@teacher.com','password'=>$hash,'role'=>'teacher','avatar'=>'','class'=>'','phone'=>'','school'=>'','is_premium'=>0,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>5,'name'=>'Sakib Hasan','email'=>'sakib@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'8','phone'=>'','school'=>'','is_premium'=>0,'created_at'=>'2025-06-01 10:00:00'],
        ];
        
        $this->data['teachers'] = [
            ['id'=>1,'user_id'=>2,'subject'=>'Mathematics','experience'=>12,'bio'=>'Passionate mathematics teacher.','rating'=>4.8,'total_students'=>120,'total_classes'=>500,'is_featured'=>1,'is_active'=>1],
            ['id'=>2,'user_id'=>4,'subject'=>'English Literature','experience'=>8,'bio'=>'English literature specialist.','rating'=>4.9,'total_students'=>95,'total_classes'=>380,'is_featured'=>1,'is_active'=>1],
        ];
        
        $this->data['subjects'] = [
            ['id'=>1,'name'=>'Mathematics','icon'=>'calculator','color'=>'#6366F1'],
            ['id'=>2,'name'=>'English','icon'=>'book-open','color'=>'#EC4899'],
            ['id'=>3,'name'=>'Science','icon'=>'flask-conical','color'=>'#22C55E'],
            ['id'=>4,'name'=>'Bangla','icon'=>'languages','color'=>'#F59E0B'],
            ['id'=>5,'name'=>'Physics','icon'=>'atom','color'=>'#3B82F6'],
            ['id'=>6,'name'=>'Chemistry','icon'=>'flask-conical','color'=>'#06B6D4'],
            ['id'=>7,'name'=>'Biology','icon'=>'leaf','color'=>'#10B981'],
            ['id'=>8,'name'=>'ICT','icon'=>'monitor','color'=>'#8B5CF6'],
            ['id'=>9,'name'=>'Geography','icon'=>'globe','color'=>'#F97316'],
            ['id'=>10,'name'=>'Religion','icon'=>'landmark','color'=>'#14B8A6'],
        ];
        
        $this->data['homework'] = [
            ['id'=>1,'title'=>'Quadratic Equations Practice','subject_id'=>1,'teacher_id'=>1,'description'=>'Solve exercises from Chapter 5, problems 1-20.','due_date'=>'2026-06-15','total_marks'=>20,'status'=>'pending','created_at'=>'2025-06-01 10:00:00'],
            ['id'=>2,'title'=>'Essay Writing - My Village','subject_id'=>2,'teacher_id'=>2,'description'=>'Write a 500 word essay about your village.','due_date'=>'2026-06-12','total_marks'=>15,'status'=>'pending','created_at'=>'2025-06-01 10:00:00'],
            ['id'=>3,'title'=>'Physics Lab Report','subject_id'=>5,'teacher_id'=>1,'description'=>"Complete lab report on Newton's Laws experiment.",'due_date'=>'2026-06-18','total_marks'=>25,'status'=>'pending','created_at'=>'2025-06-01 10:00:00'],
            ['id'=>4,'title'=>'Bangla Grammar Worksheet','subject_id'=>4,'teacher_id'=>2,'description'=>'Complete worksheet on verb conjugation.','due_date'=>'2026-06-10','total_marks'=>10,'status'=>'submitted','created_at'=>'2025-06-01 10:00:00'],
        ];
        
        $this->data['exams'] = [
            ['id'=>1,'title'=>'Mid-term Math Test','subject_id'=>1,'teacher_id'=>1,'exam_date'=>'2026-06-20','total_marks'=>100,'duration'=>60,'type'=>'mcq','status'=>'upcoming','created_at'=>'2025-06-01 10:00:00'],
            ['id'=>2,'title'=>'English Grammar Quiz','subject_id'=>2,'teacher_id'=>2,'exam_date'=>'2026-06-25','total_marks'=>50,'duration'=>30,'type'=>'mcq','status'=>'upcoming','created_at'=>'2025-06-01 10:00:00'],
            ['id'=>3,'title'=>'Physics Chapter Test','subject_id'=>5,'teacher_id'=>1,'exam_date'=>'2026-06-28','total_marks'=>75,'duration'=>45,'type'=>'written','status'=>'upcoming','created_at'=>'2025-06-01 10:00:00'],
        ];
        
        $this->data['live_classes'] = [
            ['id'=>1,'title'=>'Math - Algebra Chapter 5','subject_id'=>1,'teacher_id'=>1,'class_date'=>'2026-06-07','start_time'=>'10:00','end_time'=>'11:00','status'=>'live','meeting_link'=>'#'],
            ['id'=>2,'title'=>'English - Grammar Rules','subject_id'=>2,'teacher_id'=>2,'class_date'=>'2026-06-07','start_time'=>'14:00','end_time'=>'15:00','status'=>'scheduled','meeting_link'=>'#'],
            ['id'=>3,'title'=>'Physics - Newton Laws','subject_id'=>5,'teacher_id'=>1,'class_date'=>'2026-06-07','start_time'=>'16:30','end_time'=>'17:30','status'=>'scheduled','meeting_link'=>'#'],
        ];
        
        $this->data['announcements'] = [
            ['id'=>1,'title'=>'Mid-term Exam Schedule','message'=>'Mid-term exams will start from June 20. Prepare well!','category'=>'exam','target_class'=>'all','teacher_id'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>2,'title'=>'New Physics Lab Equipment','message'=>'New lab equipment has arrived. Lab sessions starting next week.','category'=>'general','target_class'=>'8','teacher_id'=>2,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>3,'title'=>'Math Competition','message'=>'School math competition on June 30. Register with your math teacher.','category'=>'event','target_class'=>'all','teacher_id'=>1,'created_at'=>'2025-06-01 10:00:00'],
        ];
        
        $this->data['packages'] = [
            ['id'=>1,'name'=>'Monthly Basic','price'=>299,'duration'=>30,'features'=>'AI Tutor, Homework Help','is_active'=>1],
            ['id'=>2,'name'=>'Monthly Pro','price'=>499,'duration'=>30,'features'=>'AI Tutor, Live Classes, Homework Review','is_active'=>1],
            ['id'=>3,'name'=>'Yearly Premium','price'=>4999,'duration'=>365,'features'=>'All Features, Priority Support, Analytics','is_active'=>1],
        ];
        
        $this->data['library'] = [
            ['id'=>1,'title'=>'Mathematics Textbook Class 8','subject_id'=>1,'class'=>'Class 8','type'=>'textbook','description'=>'Complete mathematics textbook for class 8 students.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>245,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>2,'title'=>'English Grammar Guide','subject_id'=>2,'class'=>'Class 8','type'=>'guide','description'=>'Comprehensive English grammar reference guide.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>189,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>3,'title'=>'Physics Formula Sheet','subject_id'=>5,'class'=>'Class 9 Science','type'=>'reference','description'=>'Quick reference sheet for all physics formulas.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>312,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>4,'title'=>'Bangla Grammar Class 8','subject_id'=>4,'class'=>'Class 8','type'=>'textbook','description'=>'Bangla grammar rules and exercises.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>120,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>5,'title'=>'Science Lab Manual','subject_id'=>3,'class'=>'Class 8','type'=>'guide','description'=>'Science practical lab manual for class 8.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>95,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>6,'title'=>'ICT Fundamentals','subject_id'=>8,'class'=>'Class 8','type'=>'textbook','description'=>'ICT basics and computer fundamentals.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>80,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>7,'title'=>'Algebra Tutorial Video','subject_id'=>1,'class'=>'Class 8','type'=>'video','description'=>'Video tutorial on algebra basics.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>200,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
            ['id'=>8,'title'=>'English Essay Guide','subject_id'=>2,'class'=>'Class 8','type'=>'notes','description'=>'Essay writing techniques and samples.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>150,'is_active'=>1,'created_at'=>'2025-06-01 10:00:00'],
        ];
        
        $this->data['chapters'] = [
            ['id'=>1,'subject_id'=>1,'title'=>'Algebra - Linear Equations','chapter_no'=>1,'description'=>'Introduction to linear equations and their solutions.','is_active'=>1],
            ['id'=>2,'subject_id'=>1,'title'=>'Geometry - Triangles','chapter_no'=>2,'description'=>'Properties of triangles and theorems.','is_active'=>1],
            ['id'=>3,'subject_id'=>2,'title'=>'Parts of Speech','chapter_no'=>1,'description'=>'Nouns, verbs, adjectives and other parts of speech.','is_active'=>1],
            ['id'=>4,'subject_id'=>5,'title'=>'Newton Laws of Motion','chapter_no'=>1,'description'=>'Three laws of motion by Isaac Newton.','is_active'=>1],
        ];
        
        $this->data['questions'] = [
            ['id'=>1,'subject_id'=>1,'chapter_id'=>1,'type'=>'mcq','question'=>'What is the solution of 2x + 5 = 15?','options'=>json_encode(['5','10','7.5','3']),'correct_answer'=>'0','marks'=>1,'difficulty'=>'easy','is_active'=>1],
            ['id'=>2,'subject_id'=>1,'chapter_id'=>1,'type'=>'mcq','question'=>'Simplify: 3(x + 2) = ?','options'=>json_encode(['3x + 2','3x + 6','x + 6','3x + 5']),'correct_answer'=>'1','marks'=>1,'difficulty'=>'easy','is_active'=>1],
            ['id'=>3,'subject_id'=>2,'chapter_id'=>3,'type'=>'mcq','question'=>'Which is a noun?','options'=>json_encode(['Run','Beautiful','School','Quickly']),'correct_answer'=>'2','marks'=>1,'difficulty'=>'easy','is_active'=>1],
            ['id'=>4,'subject_id'=>5,'chapter_id'=>4,'type'=>'mcq','question'=>'Newton first law is also known as?','options'=>json_encode(['Law of Acceleration','Law of Inertia','Law of Action','Law of Gravity']),'correct_answer'=>'1','marks'=>1,'difficulty'=>'medium','is_active'=>1],
        ];
        
        $this->data['settings'] = [
            ['id'=>1,'key'=>'school_name','value'=>'Green Valley School'],
            ['id'=>2,'key'=>'school_email','value'=>'admin@greenschool.com'],
            ['id'=>3,'key'=>'school_phone','value'=>'+880 1700-000000'],
            ['id'=>4,'key'=>'ai_enabled','value'=>'1'],
            ['id'=>5,'key'=>'max_mcq_options','value'=>'6'],
            ['id'=>6,'key'=>'currency','value'=>'BDT'],
            ['id'=>7,'key'=>'currency_symbol','value'=>'৳'],
        ];
        
        $this->data['homework_submissions'] = [
            ['id'=>1,'homework_id'=>1,'student_id'=>1,'answer'=>'Solved all 20 problems. Used quadratic formula for problems 15-20.','marks_obtained'=>18,'status'=>'graded','comments'=>'Excellent work! Very clear solutions.','submitted_at'=>'2026-06-10 09:30:00','graded_at'=>'2026-06-11 10:00:00'],
            ['id'=>2,'homework_id'=>2,'student_id'=>1,'answer'=>'Wrote a 520 word essay about my village with detailed descriptions.','marks_obtained'=>14,'status'=>'graded','comments'=>'Good essay, nice descriptions.','submitted_at'=>'2026-06-09 14:20:00','graded_at'=>'2026-06-10 09:00:00'],
            ['id'=>3,'homework_id'=>3,'student_id'=>5,'answer'=>'Completed lab report with all three experiments documented.','marks_obtained'=>0,'status'=>'submitted','comments'=>'','submitted_at'=>'2026-06-12 11:00:00','graded_at'=>''],
            ['id'=>4,'homework_id'=>4,'student_id'=>5,'answer'=>'Worksheet completed with verb conjugations for all tenses.','marks_obtained'=>9,'status'=>'graded','comments'=>'Almost perfect! Check past tense on page 3.','submitted_at'=>'2026-06-08 16:45:00','graded_at'=>'2026-06-09 08:00:00'],
            ['id'=>5,'homework_id'=>1,'student_id'=>5,'answer'=>'Solved 15 out of 20 problems. Struggled with quadratic formula.','marks_obtained'=>12,'status'=>'revision','comments'=>'Please redo problems 15-20 using the correct formula.','submitted_at'=>'2026-06-11 10:00:00','graded_at'=>'2026-06-12 09:00:00'],
        ];
        
        $this->data['exam_results'] = [
            ['id'=>1,'exam_id'=>1,'student_id'=>1,'score'=>85,'total_marks'=>100,'percentage'=>85,'grade'=>'A+','submitted_at'=>'2026-06-20 11:00:00'],
            ['id'=>2,'exam_id'=>2,'student_id'=>1,'score'=>42,'total_marks'=>50,'percentage'=>84,'grade'=>'A','submitted_at'=>'2026-06-25 10:30:00'],
            ['id'=>3,'exam_id'=>1,'student_id'=>5,'score'=>72,'total_marks'=>100,'percentage'=>72,'grade'=>'A','submitted_at'=>'2026-06-20 11:00:00'],
            ['id'=>4,'exam_id'=>3,'student_id'=>1,'score'=>65,'total_marks'=>75,'percentage'=>87,'grade'=>'A+','submitted_at'=>'2026-06-28 11:00:00'],
            ['id'=>5,'exam_id'=>3,'student_id'=>5,'score'=>55,'total_marks'=>75,'percentage'=>73,'grade'=>'A','submitted_at'=>'2026-06-28 11:00:00'],
        ];
        
        $this->data['messages'] = [
            ['id'=>1,'sender_id'=>2,'receiver_id'=>1,'message'=>'Hello Arman, how are you finding the new algebra chapter?','is_read'=>1,'created_at'=>'2026-06-05 10:00:00'],
            ['id'=>2,'sender_id'=>1,'receiver_id'=>2,'message'=>'It is great sir! I especially liked the quadratic equations part.','is_read'=>1,'created_at'=>'2026-06-05 10:15:00'],
            ['id'=>3,'sender_id'=>4,'receiver_id'=>1,'message'=>'Your essay was excellent. Keep up the good work!','is_read'=>0,'created_at'=>'2026-06-10 09:30:00'],
            ['id'=>4,'sender_id'=>1,'receiver_id'=>4,'message'=>'Thank you maam! I will work harder on the next one.','is_read'=>0,'created_at'=>'2026-06-10 11:00:00'],
            ['id'=>5,'sender_id'=>2,'receiver_id'=>5,'message'=>'Sakib, please submit your physics lab report by tomorrow.','is_read'=>1,'created_at'=>'2026-06-11 14:00:00'],
        ];
        
        $this->data['notifications'] = [
            ['id'=>1,'title'=>'New Homework Assigned','message'=>'Math homework on Quadratic Equations has been assigned.','type'=>'homework','target_role'=>'student','is_read'=>0,'created_at'=>'2026-06-10 08:00:00'],
            ['id'=>2,'title'=>'Exam Reminder','message'=>'Mid-term Math Test is scheduled for June 20.','type'=>'exam','target_role'=>'student','is_read'=>0,'created_at'=>'2026-06-18 09:00:00'],
            ['id'=>3,'title'=>'Live Class Starting','message'=>'Math Algebra class starts in 30 minutes.','type'=>'class','target_role'=>'student','is_read'=>1,'created_at'=>'2026-06-07 09:30:00'],
            ['id'=>4,'title'=>'New Student Registered','message'=>'Sakib Hasan has registered as a new student.','type'=>'system','target_role'=>'teacher','is_read'=>0,'created_at'=>'2026-06-01 10:00:00'],
            ['id'=>5,'title'=>'System Update','message'=>'AI Tutor feature has been enabled for all premium students.','type'=>'system','target_role'=>'all','is_read'=>1,'created_at'=>'2026-06-05 12:00:00'],
        ];
        
        $this->data['student_progress'] = [
            ['id'=>1,'student_id'=>1,'books_read'=>12,'homework_score'=>85,'exam_score'=>84,'streak'=>7,'badges_count'=>5,'study_hours'=>86,'last_active'=>'2026-06-12'],
            ['id'=>2,'student_id'=>5,'books_read'=>8,'homework_score'=>72,'exam_score'=>73,'streak'=>3,'badges_count'=>2,'study_hours'=>45,'last_active'=>'2026-06-12'],
        ];

        $this->data['badges'] = [
            ['id'=>1,'student_id'=>1,'name'=>'Top Performer','icon'=>'trophy','description'=>'Scored above 90% in exams','earned_date'=>'2026-05-20'],
            ['id'=>2,'student_id'=>1,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 10+ books','earned_date'=>'2026-05-15'],
            ['id'=>3,'student_id'=>1,'name'=>'7 Day Streak','icon'=>'flame','description'=>'Maintained 7 day streak','earned_date'=>'2026-06-01'],
            ['id'=>4,'student_id'=>1,'name'=>'Quick Learner','icon'=>'zap','description'=>'Completed 5 lessons in a day','earned_date'=>'2026-06-05'],
            ['id'=>5,'student_id'=>1,'name'=>'Honor Roll','icon'=>'award','description'=>'Top 10% in class','earned_date'=>'2026-06-10'],
            ['id'=>6,'student_id'=>5,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 5+ books','earned_date'=>'2026-06-01'],
            ['id'=>7,'student_id'=>5,'name'=>'Quick Learner','icon'=>'zap','description'=>'Completed 3 lessons in a day','earned_date'=>'2026-06-10'],
        ];

        $this->data['reviews'] = [
            ['id'=>1,'teacher_id'=>1,'student_id'=>1,'rating'=>5,'comment'=>'Best math teacher ever! Makes everything so easy to understand.','created_at'=>'2026-06-10'],
            ['id'=>2,'teacher_id'=>1,'student_id'=>5,'rating'=>4,'comment'=>'Very helpful and explains concepts clearly.','created_at'=>'2026-06-08'],
            ['id'=>3,'teacher_id'=>2,'student_id'=>1,'rating'=>5,'comment'=>'Excellent English teacher, really engaging classes.','created_at'=>'2026-06-09'],
            ['id'=>4,'teacher_id'=>3,'student_id'=>5,'rating'=>4,'comment'=>'Great science teacher with fun experiments.','created_at'=>'2026-06-07'],
        ];

        $this->data['activity_log'] = [
            ['id'=>1,'user_id'=>1,'action'=>'login','details'=>'Logged in from mobile','created_at'=>'2026-06-12 08:00:00'],
            ['id'=>2,'user_id'=>1,'action'=>'view_homework','details'=>'Viewed Mathematics homework','created_at'=>'2026-06-12 08:15:00'],
            ['id'=>3,'user_id'=>1,'action'=>'start_exam','details'=>'Started Science exam','created_at'=>'2026-06-12 09:00:00'],
            ['id'=>4,'user_id'=>2,'action'=>'login','details'=>'Logged in from desktop','created_at'=>'2026-06-12 07:30:00'],
            ['id'=>5,'user_id'=>2,'action'=>'create_homework','details'=>'Created new Mathematics homework','created_at'=>'2026-06-12 08:45:00'],
        ];

        $this->data['activity_feed'] = [
            ['id'=>1,'user_id'=>1,'type'=>'read','title'=>'Read: Science Chapter 2','subject'=>'Science','time'=>'Today, 10:30 AM','icon'=>'book-open'],
            ['id'=>2,'user_id'=>1,'type'=>'homework','title'=>'Homework: Math (Quadratic)','subject'=>'Mathematics','time'=>'Yesterday, 07:45 PM','icon'=>'file-text'],
            ['id'=>3,'user_id'=>1,'type'=>'quiz','title'=>'Quiz: English Grammar','subject'=>'English','time'=>'Yesterday, 04:20 PM','icon'=>'help-circle'],
            ['id'=>4,'user_id'=>1,'type'=>'ai','title'=>'AI Tutor: Photosynthesis','subject'=>'Science','time'=>'Yesterday, 03:10 PM','icon'=>'bot'],
            ['id'=>5,'user_id'=>5,'type'=>'read','title'=>'Read: Math Chapter 3','subject'=>'Mathematics','time'=>'Today, 09:00 AM','icon'=>'book-open'],
            ['id'=>6,'user_id'=>5,'type'=>'homework','title'=>'Homework: English Essay','subject'=>'English','time'=>'Yesterday, 06:00 PM','icon'=>'file-text'],
        ];

        $this->data['chapters'] = [
            ['id'=>1,'subject_id'=>1,'title'=>'Rational Numbers','status'=>'completed','pages'=>12,'order'=>1],
            ['id'=>2,'subject_id'=>1,'title'=>'Linear Equations','status'=>'completed','pages'=>15,'order'=>2],
            ['id'=>3,'subject_id'=>1,'title'=>'Quadrilaterals','status'=>'in_progress','pages'=>18,'order'=>3],
            ['id'=>4,'subject_id'=>1,'title'=>'Data Handling','status'=>'not_started','pages'=>14,'order'=>4],
            ['id'=>5,'subject_id'=>1,'title'=>'Squares & Square Roots','status'=>'locked','pages'=>16,'order'=>5],
            ['id'=>6,'subject_id'=>1,'title'=>'Cubes & Cube Roots','status'=>'locked','pages'=>13,'order'=>6],
            ['id'=>7,'subject_id'=>2,'title'=>'Food: Where Does It Come From?','status'=>'completed','pages'=>10,'order'=>1],
            ['id'=>8,'subject_id'=>2,'title'=>'Components of Food','status'=>'in_progress','pages'=>12,'order'=>2],
            ['id'=>9,'subject_id'=>2,'title'=>'Fibre to Fabric','status'=>'not_started','pages'=>11,'order'=>3],
            ['id'=>10,'subject_id'=>3,'title'=>'The Happy Prince','status'=>'completed','pages'=>8,'order'=>1],
            ['id'=>11,'subject_id'=>3,'title'=>'The Ball Poem','status'=>'in_progress','pages'=>9,'order'=>2],
        ];

        $this->data['book_content'] = [
            ['id'=>1,'chapter_id'=>3,'title'=>'Chapter 3: Quadrilaterals','content'=>'A quadrilateral is a polygon with four sides and four vertices. The word "quadrilateral" is derived from the Latin words "quadri" (four) and "latus" (side).\n\nTypes of Quadrilaterals:\n\n1. Parallelogram: A quadrilateral with both pairs of opposite sides parallel. Properties: opposite sides are equal, opposite angles are equal, and diagonals bisect each other.\n\n2. Rectangle: A parallelogram with all angles equal to 90 degrees. Properties: all properties of a parallelogram plus diagonals are equal.\n\n3. Square: A rectangle with all sides equal. Properties: all properties of a rectangle plus all sides are equal and diagonals are perpendicular.\n\n4. Rhombus: A parallelogram with all sides equal. Properties: all sides are equal, opposite angles are equal, and diagonals bisect each other at right angles.\n\n5. Trapezium: A quadrilateral with exactly one pair of parallel sides.','pages_total'=>18,'pages_read'=>6],
            ['id'=>2,'chapter_id'=>7,'title'=>'Chapter 1: Food Sources','content'=>'Food is essential for survival. It provides energy, helps in growth, and repairs worn-out body tissues.\n\nSources of Food:\n- Plants: fruits, vegetables, grains, herbs\n- Animals: milk, eggs, meat, fish\n\nComponents of Food:\nCarbohydrates, Proteins, Fats, Vitamins, Minerals, Water, and Roughage.','pages_total'=>10,'pages_read'=>10],
        ];

        $this->data['calendar_events'] = [
            ['id'=>1,'title'=>'Science Exam','date'=>'2026-06-23','type'=>'exam','color'=>'#EF4444'],
            ['id'=>2,'title'=>'Math Live Class','date'=>'2026-06-13','type'=>'class','color'=>'#4F46E5'],
            ['id'=>3,'title'=>'English Essay Due','date'=>'2026-06-19','type'=>'homework','color'=>'#F59E0B'],
            ['id'=>4,'title'=>'Science Viva','date'=>'2026-06-25','type'=>'exam','color'=>'#EF4444'],
            ['id'=>5,'title'=>'Holiday - Weekend','date'=>'2026-06-14','type'=>'holiday','color'=>'#10B981'],
        ];

        $this->data['reports'] = [
            ['id'=>1,'student_id'=>1,'teacher_id'=>2,'subject'=>'Mathematics','class'=>'Class 8','grade'=>'A+','score'=>92,'behavior'=>'Excellent','comment'=>'Outstanding performance in algebra and geometry.','date'=>'2026-06-10'],
            ['id'=>2,'student_id'=>1,'teacher_id'=>3,'subject'=>'Science','class'=>'Class 8','grade'=>'A','score'=>88,'behavior'=>'Good','comment'=>'Good understanding of concepts. Needs more practice in physics.','date'=>'2026-06-08'],
            ['id'=>3,'student_id'=>1,'teacher_id'=>4,'subject'=>'English','class'=>'Class 8','grade'=>'B+','score'=>82,'behavior'=>'Good','comment'=>'Good writing skills. Improve grammar and vocabulary.','date'=>'2026-06-05'],
            ['id'=>4,'student_id'=>5,'teacher_id'=>2,'subject'=>'Mathematics','class'=>'Class 8','grade'=>'B','score'=>75,'behavior'=>'Good','comment'=>'Shows improvement. Keep working on problem solving.','date'=>'2026-06-10'],
            ['id'=>5,'student_id'=>5,'teacher_id'=>3,'subject'=>'Science','class'=>'Class 8','grade'=>'A+','score'=>94,'behavior'=>'Excellent','comment'=>'Excellent grasp of scientific concepts.','date'=>'2026-06-08'],
        ];

        $this->data['question_bank'] = [
            ['id'=>1,'subject_id'=>1,'chapter'=>'Quadrilaterals','question'=>'What is the value of √144?','type'=>'mcq','options'=>['12','14','11','13'],'correct'=>0,'marks'=>2,'difficulty'=>'easy'],
            ['id'=>2,'subject_id'=>2,'chapter'=>'Photosynthesis','question'=>'Explain the process of photosynthesis.','type'=>'written','model_answer'=>'Photosynthesis is the process by which green plants use sunlight, water, and CO2 to produce glucose and oxygen.','marks'=>5,'difficulty'=>'medium'],
            ['id'=>3,'subject_id'=>3,'chapter'=>'Grammar','question'=>'Write an essay on "My School".','type'=>'written','model_answer'=>'My school is a place where I learn and grow. It has a beautiful campus with green gardens...','marks'=>10,'difficulty'=>'medium'],
            ['id'=>4,'subject_id'=>1,'chapter'=>'Algebra','question'=>'Solve: x² - 5x + 6 = 0','type'=>'mcq','options'=>['x=2, x=3','x=1, x=6','x=-2, x=-3','x=0, x=5'],'correct'=>0,'marks'=>3,'difficulty'=>'medium'],
            ['id'=>5,'subject_id'=>1,'chapter'=>'Geometry','question'=>'If a triangle has sides 3, 4, 5, what type of triangle is it?','type'=>'mcq','options'=>['Right-angled','Equilateral','Isosceles','Scalene'],'correct'=>0,'marks'=>2,'difficulty'=>'easy'],
        ];

        $this->data['student_evaluations'] = [
            ['id'=>1,'student_id'=>1,'homework_id'=>1,'subject_id'=>1,'title'=>'Quadratic Equation Practice','submitted_answer'=>'x² - 5x + 6 = 0\n(x-2)(x-3) = 0\nx = 2 or x = 3\n\nUsing quadratic formula:\nx = (5 ± √(25-24)) / 2\nx = (5 ± 1) / 2\nx = 3 or x = 2','status'=>'graded','score'=>92,'teacher_comment'=>'Excellent work! Clear step-by-step solution.','submitted_at'=>'2026-06-12 14:30:00','graded_at'=>'2026-06-12 16:00:00'],
        ];

        $this->data['class_schedule'] = [
            ['id'=>1,'subject'=>'Mathematics','topic'=>'Algebra','class_name'=>'Class 8','time'=>'09:00 - 10:00','day'=>'Monday','teacher_name'=>'Mr. Rahim','teacher_id'=>2,'students_count'=>25,'status'=>'completed'],
            ['id'=>2,'subject'=>'Science','topic'=>'Physics','class_name'=>'Class 8','time'=>'11:00 - 12:00','day'=>'Monday','teacher_name'=>'Ms. Sarah','teacher_id'=>3,'students_count'=>28,'status'=>'upcoming'],
            ['id'=>3,'subject'=>'English','topic'=>'Grammar','class_name'=>'Class 8','time'=>'02:00 - 03:00','day'=>'Monday','teacher_name'=>'Mr. Karim','teacher_id'=>4,'students_count'=>22,'status'=>'upcoming'],
            ['id'=>4,'subject'=>'Mathematics','topic'=>'Geometry','class_name'=>'Class 8','time'=>'09:00 - 10:00','day'=>'Tuesday','teacher_name'=>'Mr. Rahim','teacher_id'=>2,'students_count'=>25,'status'=>'upcoming'],
        ];
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) { header('Location: /login.php'); exit; }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) { header('Location: /login.php?error=unauthorized'); exit; }
}

function isMobile() {
    return preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

function requireDesktop() {
    if (isMobile()) {
        header('Location: /login.php?error=desktop_required');
        exit;
    }
}

function redirectByRole($role) {
    switch ($role) {
        case 'admin': header('Location: /admin/'); break;
        case 'teacher': header('Location: /?role=teacher#screen-teacher-dash'); break;
        case 'student': header('Location: /?role=student#screen-home'); break;
        default: header('Location: /login.php'); break;
    }
    exit;
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    return $db->find('users', 'id', $_SESSION['user_id']);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonResponseError($message, $code = 400) {
    jsonResponse(['error' => $message], $code);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>