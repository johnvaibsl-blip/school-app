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
            ['id'=>1,'name'=>'Arman Khan','email'=>'arman@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'8','phone'=>'01712345678','school'=>'Green Valley School','is_premium'=>1,'created_at'=>'2026-01-15 08:00:00'],
            ['id'=>2,'name'=>'Rahim Ahmed','email'=>'rahim@teacher.com','password'=>$hash,'role'=>'teacher','avatar'=>'','class'=>'','phone'=>'01798765432','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-01-10 09:00:00'],
            ['id'=>3,'name'=>'Admin User','email'=>'admin@school.com','password'=>$hash,'role'=>'admin','avatar'=>'','class'=>'','phone'=>'01700000000','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-01-01 08:00:00'],
            ['id'=>4,'name'=>'Sarah Khan','email'=>'sarah@teacher.com','password'=>$hash,'role'=>'teacher','avatar'=>'','class'=>'','phone'=>'01711111111','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-01-10 09:00:00'],
            ['id'=>5,'name'=>'Sakib Hasan','email'=>'sakib@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'8','phone'=>'01722222222','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-02-01 10:00:00'],
            ['id'=>6,'name'=>'Nusrat Jahan','email'=>'nusrat@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'8','phone'=>'01733333333','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-02-05 10:00:00'],
            ['id'=>7,'name'=>'Tanvir Rahman','email'=>'tanvir@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'9','phone'=>'01744444444','school'=>'Green Valley School','is_premium'=>1,'created_at'=>'2026-01-20 10:00:00'],
            ['id'=>8,'name'=>'Fatima Begum','email'=>'fatima@teacher.com','password'=>$hash,'role'=>'teacher','avatar'=>'','class'=>'','phone'=>'01755555555','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-01-12 09:00:00'],
            ['id'=>9,'name'=>'Kamal Hossain','email'=>'kamal@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'9','phone'=>'01766666666','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-03-01 10:00:00'],
            ['id'=>10,'name'=>'Ruma Akter','email'=>'ruma@student.com','password'=>$hash,'role'=>'student','avatar'=>'','class'=>'8','phone'=>'01777777777','school'=>'Green Valley School','is_premium'=>0,'created_at'=>'2026-03-05 10:00:00'],
        ];
        
        $this->data['teachers'] = [
            ['id'=>1,'user_id'=>2,'subject'=>'Mathematics','experience'=>12,'bio'=>'Passionate mathematics teacher with expertise in algebra and geometry. Makes complex concepts simple and fun for students.','rating'=>4.8,'total_students'=>120,'total_classes'=>500,'is_featured'=>1,'is_active'=>1],
            ['id'=>2,'user_id'=>4,'subject'=>'English Literature','experience'=>8,'bio'=>'English literature specialist focusing on creative writing, grammar, and literature analysis. Loves inspiring young writers.','rating'=>4.9,'total_students'=>95,'total_classes'=>380,'is_featured'=>1,'is_active'=>1],
            ['id'=>3,'user_id'=>8,'subject'=>'Science','experience'=>6,'bio'=>'Science teacher with hands-on approach to physics, chemistry, and biology. Believes in learning by doing experiments.','rating'=>4.7,'total_students'=>85,'total_classes'=>320,'is_featured'=>0,'is_active'=>1],
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
            ['id'=>1,'title'=>'Quadratic Equations Practice','subject_id'=>1,'teacher_id'=>1,'description'=>'Solve exercises from Chapter 5, problems 1-20. Show all working steps clearly.','due_date'=>'2026-06-15','total_marks'=>20,'status'=>'pending','created_at'=>'2026-06-01 09:00:00'],
            ['id'=>2,'title'=>'Essay Writing - My Village','subject_id'=>2,'teacher_id'=>2,'description'=>'Write a 500 word essay about your village. Include vivid descriptions and personal experiences.','due_date'=>'2026-06-12','total_marks'=>15,'status'=>'pending','created_at'=>'2026-06-02 10:00:00'],
            ['id'=>3,'title'=>'Physics Lab Report','subject_id'=>5,'teacher_id'=>1,'description'=>"Complete lab report on Newton's Laws experiment. Include hypothesis, procedure, observations, and conclusion.",'due_date'=>'2026-06-18','total_marks'=>25,'status'=>'pending','created_at'=>'2026-06-03 08:00:00'],
            ['id'=>4,'title'=>'Bangla Grammar Worksheet','subject_id'=>4,'teacher_id'=>2,'description'=>'Complete worksheet on verb conjugation. All 12 tenses must be covered.','due_date'=>'2026-06-10','total_marks'=>10,'status'=>'completed','created_at'=>'2026-05-28 09:00:00'],
            ['id'=>5,'title'=>'Chemistry Formula Practice','subject_id'=>6,'teacher_id'=>1,'description'=>'Memorize and write down all chemical formulas for Chapter 3. Practice balancing equations.','due_date'=>'2026-06-20','total_marks'=>15,'status'=>'pending','created_at'=>'2026-06-05 10:00:00'],
            ['id'=>6,'title'=>'English Poetry Analysis','subject_id'=>2,'teacher_id'=>2,'description'=>'Analyze the poem "The Ball Poem" by John Berryman. Discuss themes, literary devices, and personal reflection.','due_date'=>'2026-06-14','total_marks'=>20,'status'=>'pending','created_at'=>'2026-06-04 11:00:00'],
            ['id'=>7,'title'=>'Math Geometry Proofs','subject_id'=>1,'teacher_id'=>1,'description'=>'Complete 10 geometry proofs involving triangle congruence and similarity theorems.','due_date'=>'2026-06-22','total_marks'=>30,'status'=>'pending','created_at'=>'2026-06-06 09:00:00'],
            ['id'=>8,'title'=>'ICT Project - Database Design','subject_id'=>8,'teacher_id'=>2,'description'=>'Design a database for a school library system. Include ER diagram and normalized tables.','due_date'=>'2026-06-25','total_marks'=>25,'status'=>'pending','created_at'=>'2026-06-07 10:00:00'],
        ];
        
        $this->data['exams'] = [
            ['id'=>1,'title'=>'Mid-term Math Test','subject_id'=>1,'teacher_id'=>1,'exam_date'=>'2026-06-20','total_marks'=>100,'duration'=>60,'type'=>'mcq','status'=>'upcoming','created_at'=>'2026-06-01 09:00:00'],
            ['id'=>2,'title'=>'English Grammar Quiz','subject_id'=>2,'teacher_id'=>2,'exam_date'=>'2026-06-25','total_marks'=>50,'duration'=>30,'type'=>'mcq','status'=>'upcoming','created_at'=>'2026-06-02 10:00:00'],
            ['id'=>3,'title'=>'Physics Chapter Test','subject_id'=>5,'teacher_id'=>1,'exam_date'=>'2026-06-28','total_marks'=>75,'duration'=>45,'type'=>'written','status'=>'upcoming','created_at'=>'2026-06-03 08:00:00'],
            ['id'=>4,'title'=>'Science Monthly Exam','subject_id'=>3,'teacher_id'=>8,'exam_date'=>'2026-06-30','total_marks'=>100,'duration'=>90,'type'=>'cq','status'=>'upcoming','created_at'=>'2026-06-04 09:00:00'],
            ['id'=>5,'title'=>'Chemistry Lab Practical','subject_id'=>6,'teacher_id'=>1,'exam_date'=>'2026-07-02','total_marks'=>50,'duration'=>60,'type'=>'board','status'=>'upcoming','created_at'=>'2026-06-05 10:00:00'],
            ['id'=>6,'title'=>'Bangla Literature Exam','subject_id'=>4,'teacher_id'=>2,'exam_date'=>'2026-07-05','total_marks'=>80,'duration'=>60,'type'=>'written','status'=>'upcoming','created_at'=>'2026-06-06 09:00:00'],
        ];
        
        $this->data['live_classes'] = [
            ['id'=>1,'title'=>'Math - Algebra Chapter 5','subject_id'=>1,'teacher_id'=>1,'class_date'=>'2026-06-09','start_time'=>'10:00','end_time'=>'11:00','status'=>'live','meeting_link'=>'#'],
            ['id'=>2,'title'=>'English - Grammar Rules','subject_id'=>2,'teacher_id'=>2,'class_date'=>'2026-06-09','start_time'=>'14:00','end_time'=>'15:00','status'=>'scheduled','meeting_link'=>'#'],
            ['id'=>3,'title'=>'Physics - Newton Laws','subject_id'=>5,'teacher_id'=>1,'class_date'=>'2026-06-10','start_time'=>'09:00','end_time'=>'10:00','status'=>'scheduled','meeting_link'=>'#'],
            ['id'=>4,'title'=>'Science - Photosynthesis','subject_id'=>3,'teacher_id'=>8,'class_date'=>'2026-06-11','start_time'=>'11:00','end_time'=>'12:00','status'=>'scheduled','meeting_link'=>'#'],
            ['id'=>5,'title'=>'Math - Geometry Review','subject_id'=>1,'teacher_id'=>1,'class_date'=>'2026-06-12','start_time'=>'10:00','end_time'=>'11:00','status'=>'scheduled','meeting_link'=>'#'],
            ['id'=>6,'title'=>'English - Essay Workshop','subject_id'=>2,'teacher_id'=>2,'class_date'=>'2026-06-13','start_time'=>'14:00','end_time'=>'15:30','status'=>'scheduled','meeting_link'=>'#'],
        ];
        
        $this->data['announcements'] = [
            ['id'=>1,'title'=>'Mid-term Exam Schedule','message'=>'Mid-term exams will start from June 20. All students must prepare well. Study materials are available in the library.','category'=>'exam','target_class'=>'all','teacher_id'=>1,'created_at'=>'2026-06-01 09:00:00'],
            ['id'=>2,'title'=>'New Physics Lab Equipment','message'=>'New lab equipment has arrived including oscilloscopes and signal generators. Lab sessions starting next week for Class 8 and 9.','category'=>'general','target_class'=>'8','teacher_id'=>8,'created_at'=>'2026-06-03 10:00:00'],
            ['id'=>3,'title'=>'Math Competition','message'=>'School math competition on June 30. Top 3 winners will receive certificates and prizes. Register with your math teacher by June 20.','category'=>'event','target_class'=>'all','teacher_id'=>1,'created_at'=>'2026-06-04 11:00:00'],
            ['id'=>4,'title'=>'Emergency: School Closure Tomorrow','message'=>'Due to severe weather forecast, school will be closed tomorrow. All live classes will be conducted online.','category'=>'urgent','target_class'=>'all','teacher_id'=>3,'created_at'=>'2026-06-08 16:00:00'],
            ['id'=>5,'title'=>'Science Fair Registration','message'=>'Annual science fair registration is now open. Teams of 2-3 students can register. Project proposals due by June 25.','category'=>'event','target_class'=>'9','teacher_id'=>8,'created_at'=>'2026-06-05 09:00:00'],
            ['id'=>6,'title'=>'Parent-Teacher Meeting','message'=>'Parent-teacher meeting scheduled for June 22. Parents of Class 8 students are requested to attend between 10 AM - 2 PM.','category'=>'general','target_class'=>'8','teacher_id'=>2,'created_at'=>'2026-06-06 10:00:00'],
        ];
        
        $this->data['packages'] = [
            ['id'=>1,'name'=>'Monthly Basic','price'=>299,'duration'=>30,'features'=>'AI Tutor, Homework Help','is_active'=>1],
            ['id'=>2,'name'=>'Monthly Pro','price'=>499,'duration'=>30,'features'=>'AI Tutor, Live Classes, Homework Review','is_active'=>1],
            ['id'=>3,'name'=>'Yearly Premium','price'=>4999,'duration'=>365,'features'=>'All Features, Priority Support, Analytics','is_active'=>1],
        ];
        
        $this->data['library'] = [
            // === CLASS 8: Mathematics (subject_id=1) ===
            ['id'=>1,'title'=>'Mathematics Textbook Class 8','subject_id'=>1,'class'=>'Class 8','type'=>'textbook','description'=>'Complete mathematics textbook covering algebra, geometry, data handling, and number theory.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>245,'is_active'=>1,'created_at'=>'2026-01-15 10:00:00'],
            ['id'=>2,'title'=>'Algebra Tutorial Video','subject_id'=>1,'class'=>'Class 8','type'=>'video','description'=>'Video tutorial on linear equations, quadratic expressions, and factorization.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>200,'is_active'=>1,'created_at'=>'2026-02-01 10:00:00'],
            ['id'=>3,'title'=>'Math Practice Worksheets','subject_id'=>1,'class'=>'Class 8','type'=>'notes','description'=>'Printable worksheets with 200+ practice problems sorted by topic and difficulty.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>180,'is_active'=>1,'created_at'=>'2026-03-01 10:00:00'],
            // === CLASS 8: English (subject_id=2) ===
            ['id'=>4,'title'=>'English Grammar Guide','subject_id'=>2,'class'=>'Class 8','type'=>'guide','description'=>'Comprehensive English grammar covering tenses, parts of speech, active/passive voice, and direct/indirect speech.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>189,'is_active'=>1,'created_at'=>'2026-01-20 10:00:00'],
            ['id'=>5,'title'=>'English Essay Guide','subject_id'=>2,'class'=>'Class 8','type'=>'notes','description'=>'Essay writing techniques, 50 sample essays, and vocabulary builders for exams.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>150,'is_active'=>1,'created_at'=>'2026-02-10 10:00:00'],
            ['id'=>6,'title'=>'English Literature Reader','subject_id'=>2,'class'=>'Class 8','type'=>'textbook','description'=>'Collection of prose and poetry from the Class 8 English syllabus with chapter summaries.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>130,'is_active'=>1,'created_at'=>'2026-03-05 10:00:00'],
            // === CLASS 8: Science (subject_id=3) ===
            ['id'=>7,'title'=>'Science Lab Manual','subject_id'=>3,'class'=>'Class 8','type'=>'guide','description'=>'Step-by-step lab instructions for physics, chemistry, and biology experiments.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>95,'is_active'=>1,'created_at'=>'2026-01-25 10:00:00'],
            ['id'=>8,'title'=>'Science Video Lectures','subject_id'=>3,'class'=>'Class 8','type'=>'video','description'=>'Animated video lectures covering all science topics with real-world examples.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>175,'is_active'=>1,'created_at'=>'2026-02-15 10:00:00'],
            ['id'=>9,'title'=>'Science Quick Reference','subject_id'=>3,'class'=>'Class 8','type'=>'reference','description'=>'One-page cheat sheets for each science chapter with key formulas and diagrams.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>220,'is_active'=>1,'created_at'=>'2026-03-10 10:00:00'],
            // === CLASS 8: Bangla (subject_id=4) ===
            ['id'=>10,'title'=>'Bangla Grammar Class 8','subject_id'=>4,'class'=>'Class 8','type'=>'textbook','description'=>'Complete Bangla grammar rules, kar (কার), visheshan (বিশেষণ), and sentence construction.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>120,'is_active'=>1,'created_at'=>'2026-01-18 10:00:00'],
            ['id'=>11,'title'=>'Bangla Sahitya Guide','subject_id'=>4,'class'=>'Class 8','type'=>'notes','description'=>'Guides and summaries for all Bangla literature chapters with poet biographies.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>85,'is_active'=>1,'created_at'=>'2026-02-20 10:00:00'],
            // === CLASS 8: Physics (subject_id=5) ===
            ['id'=>12,'title'=>'Physics Textbook Class 8','subject_id'=>5,'class'=>'Class 8','type'=>'textbook','description'=>'Covers force, motion, energy, light, sound, and electricity with diagrams.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>160,'is_active'=>1,'created_at'=>'2026-01-22 10:00:00'],
            ['id'=>13,'title'=>'Physics Formula Sheet','subject_id'=>5,'class'=>'Class 8','type'=>'reference','description'=>'All physics formulas for Class 8 in one printable sheet with examples.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>312,'is_active'=>1,'created_at'=>'2026-02-05 10:00:00'],
            // === CLASS 8: Chemistry (subject_id=6) ===
            ['id'=>14,'title'=>'Chemistry Basics Class 8','subject_id'=>6,'class'=>'Class 8','type'=>'textbook','description'=>'Introduction to elements, compounds, mixtures, chemical reactions, and atomic structure.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>110,'is_active'=>1,'created_at'=>'2026-01-28 10:00:00'],
            ['id'=>15,'title'=>'Chemistry Lab Guide','subject_id'=>6,'class'=>'Class 8','type'=>'guide','description'=>'Safe lab procedures, chemical handling, and 15 hands-on experiments.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>70,'is_active'=>1,'created_at'=>'2026-03-15 10:00:00'],
            // === CLASS 8: Biology (subject_id=7) ===
            ['id'=>16,'title'=>'Biology Textbook Class 8','subject_id'=>7,'class'=>'Class 8','type'=>'textbook','description'=>'Covers cell biology, photosynthesis, human body systems, and ecosystems.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>140,'is_active'=>1,'created_at'=>'2026-02-01 10:00:00'],
            ['id'=>17,'title'=>'Biology Diagram Guide','subject_id'=>7,'class'=>'Class 8','type'=>'notes','description'=>'Labelled diagrams for all biology topics with exam-focused annotations.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>90,'is_active'=>1,'created_at'=>'2026-03-20 10:00:00'],
            // === CLASS 8: ICT (subject_id=8) ===
            ['id'=>18,'title'=>'ICT Fundamentals','subject_id'=>8,'class'=>'Class 8','type'=>'textbook','description'=>'Computer basics, binary number system, networking, and internet safety.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>80,'is_active'=>1,'created_at'=>'2026-02-10 10:00:00'],
            ['id'=>19,'title'=>'Programming Basics Guide','subject_id'=>8,'class'=>'Class 8','type'=>'guide','description'=>'Introduction to Scratch programming with 10 beginner projects.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>65,'is_active'=>1,'created_at'=>'2026-03-01 10:00:00'],
            // === CLASS 8: Geography (subject_id=9) ===
            ['id'=>20,'title'=>'Geography Atlas Class 8','subject_id'=>9,'class'=>'Class 8','type'=>'textbook','description'=>'Bangladesh geography, climate zones, natural resources, and map reading skills.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>75,'is_active'=>1,'created_at'=>'2026-02-18 10:00:00'],
            // === CLASS 8: Religion (subject_id=10) ===
            ['id'=>21,'title'=>'Islamic Studies Class 8','subject_id'=>10,'class'=>'Class 8','type'=>'textbook','description'=>'Quran studies, Hadith, Islamic history, and moral values for Class 8.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>60,'is_active'=>1,'created_at'=>'2026-02-22 10:00:00'],
            // === CLASS 9 Science: Physics (subject_id=5) ===
            ['id'=>22,'title'=>'Physics Formula Sheet Class 9','subject_id'=>5,'class'=>'Class 9 Science','type'=>'reference','description'=>'Complete physics formula collection for Class 9 Science group.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>195,'is_active'=>1,'created_at'=>'2026-01-30 10:00:00'],
            ['id'=>23,'title'=>'Physics Video Lectures','subject_id'=>5,'class'=>'Class 9 Science','type'=>'video','description'=>'In-depth video lectures on motion, force, work, power, and energy.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>160,'is_active'=>1,'created_at'=>'2026-02-25 10:00:00'],
            // === CLASS 9 Science: Chemistry (subject_id=6) ===
            ['id'=>24,'title'=>'Chemistry Class 9 Textbook','subject_id'=>6,'class'=>'Class 9 Science','type'=>'textbook','description'=>'Covers periodic table, chemical bonding, acids/bases, and organic chemistry basics.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>130,'is_active'=>1,'created_at'=>'2026-02-12 10:00:00'],
            // === CLASS 9 Science: Math (subject_id=1) ===
            ['id'=>25,'title'=>'Mathematics Class 9 Guide','subject_id'=>1,'class'=>'Class 9 Science','type'=>'guide','description'=>'Complete math guide for Class 9 Science with solutions to all textbook exercises.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>175,'is_active'=>1,'created_at'=>'2026-03-08 10:00:00'],
            // === CLASS 9 Science: English (subject_id=2) ===
            ['id'=>26,'title'=>'English Class 9 Notes','subject_id'=>2,'class'=>'Class 9 Science','type'=>'notes','description'=>'Chapter-wise English notes with grammar rules, vocabulary, and composition practice.','file_url'=>'','cover_url'=>'','uploader_id'=>0,'uploader_type'=>'admin','downloads'=>110,'is_active'=>1,'created_at'=>'2026-03-12 10:00:00'],
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
            ['id'=>8,'key'=>'ai_name','value'=>'Snorii AI'],
            ['id'=>9,'key'=>'ai_greeting','value'=>"Hello! I'm <strong>Snorii AI</strong>. Ask me anything about Math, Science, English and more!"],
            ['id'=>10,'key'=>'ai_subtitle','value'=>'Always here to help'],
            ['id'=>11,'key'=>'ai_system_prompt','value'=>'You are a helpful school tutor for Class 8 students. Answer clearly and simply.'],
            ['id'=>12,'key'=>'ai_suggested_prompts','value'=>'Explain photosynthesis|Solve quadratic equations|Summarize Chapter 3'],
            ['id'=>13,'key'=>'ai_moondream_api_key','value'=>''],
            ['id'=>14,'key'=>'featured_teacher_id','value'=>'1'],
            ['id'=>15,'key'=>'featured_teacher_video','value'=>''],
            ['id'=>16,'key'=>'top_rated_teacher_id','value'=>'2'],
            ['id'=>17,'key'=>'popular_teacher_id','value'=>'3'],
            ['id'=>18,'key'=>'new_teacher_id','value'=>''],
        ];
        
        $this->data['homework_submissions'] = [
            ['id'=>1,'homework_id'=>1,'student_id'=>1,'answer'=>'Solved all 20 problems using quadratic formula and factoring methods. Detailed working shown for each problem.','marks_obtained'=>18,'status'=>'graded','comments'=>'Excellent work! Very clear solutions. Minor calculation error in problem 17.','submitted_at'=>'2026-06-10 09:30:00','graded_at'=>'2026-06-11 10:00:00'],
            ['id'=>2,'homework_id'=>2,'student_id'=>1,'answer'=>'Wrote a 520 word essay about my village with vivid descriptions of the river, mango orchards, and local market.','marks_obtained'=>14,'status'=>'graded','comments'=>'Good essay with nice descriptions. Could improve transition between paragraphs.','submitted_at'=>'2026-06-09 14:20:00','graded_at'=>'2026-06-10 09:00:00'],
            ['id'=>3,'homework_id'=>3,'student_id'=>5,'answer'=>'Completed lab report with all three experiments documented. Included hypothesis, materials, procedure, observations, and conclusion.','marks_obtained'=>0,'status'=>'submitted','comments'=>'','submitted_at'=>'2026-06-12 11:00:00','graded_at'=>''],
            ['id'=>4,'homework_id'=>4,'student_id'=>5,'answer'=>'Worksheet completed with verb conjugations for all 12 tenses. Each tense has 5 example sentences.','marks_obtained'=>9,'status'=>'graded','comments'=>'Almost perfect! Check past perfect continuous tense on page 3.','submitted_at'=>'2026-06-08 16:45:00','graded_at'=>'2026-06-09 08:00:00'],
            ['id'=>5,'homework_id'=>1,'student_id'=>5,'answer'=>'Solved 15 out of 20 problems. Struggled with quadratic formula application in problems 15-20.','marks_obtained'=>12,'status'=>'revision','comments'=>'Please redo problems 15-20 using the correct formula. Remember: x = (-b ± √(b²-4ac)) / 2a','submitted_at'=>'2026-06-11 10:00:00','graded_at'=>'2026-06-12 09:00:00'],
            ['id'=>6,'homework_id'=>2,'student_id'=>6,'answer'=>'Beautiful essay about my village with poetic descriptions of nature and daily life. 480 words.','marks_obtained'=>15,'status'=>'graded','comments'=>'Outstanding essay! Your descriptions are vivid and engaging. Keep writing!','submitted_at'=>'2026-06-09 12:00:00','graded_at'=>'2026-06-10 11:00:00'],
            ['id'=>7,'homework_id'=>1,'student_id'=>6,'answer'=>'Solved all 20 problems with clear step-by-step working. Used both factoring and quadratic formula methods.','marks_obtained'=>20,'status'=>'graded','comments'=>'Perfect score! Excellent problem-solving approach.','submitted_at'=>'2026-06-10 08:00:00','graded_at'=>'2026-06-11 09:00:00'],
            ['id'=>8,'homework_id'=>4,'student_id'=>1,'answer'=>'Completed all verb conjugation exercises. Covered simple, continuous, perfect, and perfect continuous tenses.','marks_obtained'=>10,'status'=>'graded','comments'=>'Perfect work! All tenses correctly conjugated.','submitted_at'=>'2026-06-08 15:00:00','graded_at'=>'2026-06-09 07:30:00'],
        ];
        
        $this->data['exam_results'] = [
            ['id'=>1,'exam_id'=>1,'student_id'=>1,'score'=>85,'total_marks'=>100,'percentage'=>85,'grade'=>'A+','submitted_at'=>'2026-05-20 11:00:00'],
            ['id'=>2,'exam_id'=>2,'student_id'=>1,'score'=>42,'total_marks'=>50,'percentage'=>84,'grade'=>'A','submitted_at'=>'2026-05-25 10:30:00'],
            ['id'=>3,'exam_id'=>1,'student_id'=>5,'score'=>72,'total_marks'=>100,'percentage'=>72,'grade'=>'A','submitted_at'=>'2026-05-20 11:00:00'],
            ['id'=>4,'exam_id'=>3,'student_id'=>1,'score'=>65,'total_marks'=>75,'percentage'=>87,'grade'=>'A+','submitted_at'=>'2026-05-28 11:00:00'],
            ['id'=>5,'exam_id'=>3,'student_id'=>5,'score'=>55,'total_marks'=>75,'percentage'=>73,'grade'=>'A','submitted_at'=>'2026-05-28 11:00:00'],
            ['id'=>6,'exam_id'=>1,'student_id'=>6,'score'=>88,'total_marks'=>100,'percentage'=>88,'grade'=>'A+','submitted_at'=>'2026-05-20 11:00:00'],
            ['id'=>7,'exam_id'=>2,'student_id'=>6,'score'=>46,'total_marks'=>50,'percentage'=>92,'grade'=>'A+','submitted_at'=>'2026-05-25 10:30:00'],
            ['id'=>8,'exam_id'=>1,'student_id'=>7,'score'=>92,'total_marks'=>100,'percentage'=>92,'grade'=>'A+','submitted_at'=>'2026-05-20 11:00:00'],
            ['id'=>9,'exam_id'=>3,'student_id'=>7,'score'=>70,'total_marks'=>75,'percentage'=>93,'grade'=>'A+','submitted_at'=>'2026-05-28 11:00:00'],
            ['id'=>10,'exam_id'=>2,'student_id'=>7,'score'=>48,'total_marks'=>50,'percentage'=>96,'grade'=>'A+','submitted_at'=>'2026-05-25 10:30:00'],
            ['id'=>11,'exam_id'=>1,'student_id'=>9,'score'=>68,'total_marks'=>100,'percentage'=>68,'grade'=>'B','submitted_at'=>'2026-05-20 11:00:00'],
            ['id'=>12,'exam_id'=>1,'student_id'=>10,'score'=>78,'total_marks'=>100,'percentage'=>78,'grade'=>'A','submitted_at'=>'2026-05-20 11:00:00'],
        ];
        
        $this->data['messages'] = [
            ['id'=>1,'sender_id'=>2,'receiver_id'=>1,'message'=>'Hello Arman, how are you finding the new algebra chapter? Need any help?', 'is_read'=>1,'created_at'=>'2026-06-05 10:00:00'],
            ['id'=>2,'sender_id'=>1,'receiver_id'=>2,'message'=>'It is great sir! I especially liked the quadratic equations part. The video tutorials were very helpful.','is_read'=>1,'created_at'=>'2026-06-05 10:15:00'],
            ['id'=>3,'sender_id'=>4,'receiver_id'=>1,'message'=>'Your essay was excellent, Arman. Keep up the good work! I especially liked your description of the river.','is_read'=>0,'created_at'=>'2026-06-08 09:30:00'],
            ['id'=>4,'sender_id'=>1,'receiver_id'=>4,'message'=>'Thank you maam! I will work harder on the next one. Could you recommend any good essay books?','is_read'=>0,'created_at'=>'2026-06-08 11:00:00'],
            ['id'=>5,'sender_id'=>2,'receiver_id'=>5,'message'=>'Sakib, please submit your physics lab report by tomorrow. You are running late.','is_read'=>1,'created_at'=>'2026-06-08 14:00:00'],
            ['id'=>6,'sender_id'=>5,'receiver_id'=>2,'message'=>'Sorry sir, I was sick yesterday. Will submit it by tonight.','is_read'=>1,'created_at'=>'2026-06-08 15:00:00'],
            ['id'=>7,'sender_id'=>8,'receiver_id'=>7,'message'=>'Tanvir, your science project proposal looks promising. Let us discuss it in the next class.','is_read'=>0,'created_at'=>'2026-06-09 10:00:00'],
            ['id'=>8,'sender_id'=>1,'receiver_id'=>6,'message'=>'Hey Nusrat, want to study together for the math exam? I have some good notes.','is_read'=>0,'created_at'=>'2026-06-09 12:00:00'],
            ['id'=>9,'sender_id'=>6,'receiver_id'=>1,'message'=>'That would be great! Let us meet in the library after school tomorrow.','is_read'=>0,'created_at'=>'2026-06-09 12:30:00'],
            ['id'=>10,'sender_id'=>2,'receiver_id'=>1,'message'=>'Arman, I have uploaded extra practice problems for the upcoming exam. Check the library section.','is_read'=>0,'created_at'=>'2026-06-09 08:00:00'],
        ];
        
        $this->data['notifications'] = [
            ['id'=>1,'title'=>'New Homework Assigned','message'=>'Math homework on Quadratic Equations has been assigned. Due date: June 15.','type'=>'homework','target_role'=>'student','is_read'=>0,'created_at'=>'2026-06-01 09:00:00'],
            ['id'=>2,'title'=>'Exam Reminder','message'=>'Mid-term Math Test is scheduled for June 20. Start preparing now!','type'=>'exam','target_role'=>'student','is_read'=>0,'created_at'=>'2026-06-15 09:00:00'],
            ['id'=>3,'title'=>'Live Class Starting','message'=>'Math Algebra class starts in 30 minutes. Join now!','type'=>'class','target_role'=>'student','is_read'=>1,'created_at'=>'2026-06-09 09:30:00'],
            ['id'=>4,'title'=>'New Students Registered','message'=>'3 new students have registered this month. Welcome them!','type'=>'system','target_role'=>'teacher','is_read'=>0,'created_at'=>'2026-06-05 10:00:00'],
            ['id'=>5,'title'=>'System Update','message'=>'AI Tutor feature has been enabled for all premium students.','type'=>'system','target_role'=>'all','is_read'=>1,'created_at'=>'2026-06-03 12:00:00'],
            ['id'=>6,'title'=>'Homework Graded','message'=>'Your English essay has been graded. Check your results.','type'=>'homework','target_role'=>'student','is_read'=>0,'created_at'=>'2026-06-08 10:00:00'],
            ['id'=>7,'title'=>'Science Fair Reminder','message'=>'Science fair registration closes June 25. Don\'t miss out!','type'=>'event','target_role'=>'student','is_read'=>0,'created_at'=>'2026-06-07 09:00:00'],
        ];
        
        $this->data['student_progress'] = [
            ['id'=>1,'student_id'=>1,'books_read'=>12,'homework_score'=>85,'exam_score'=>84,'streak'=>7,'badges_count'=>5,'study_hours'=>40,'last_active'=>'2026-06-09'],
            ['id'=>2,'student_id'=>5,'books_read'=>8,'homework_score'=>72,'exam_score'=>73,'streak'=>3,'badges_count'=>2,'study_hours'=>45,'last_active'=>'2026-06-09'],
            ['id'=>3,'student_id'=>6,'books_read'=>15,'homework_score'=>88,'exam_score'=>86,'streak'=>5,'badges_count'=>3,'study_hours'=>72,'last_active'=>'2026-06-09'],
            ['id'=>4,'student_id'=>7,'books_read'=>20,'homework_score'=>90,'exam_score'=>91,'streak'=>10,'badges_count'=>6,'study_hours'=>110,'last_active'=>'2026-06-09'],
            ['id'=>5,'student_id'=>9,'books_read'=>6,'homework_score'=>68,'exam_score'=>70,'streak'=>2,'badges_count'=>1,'study_hours'=>30,'last_active'=>'2026-06-08'],
            ['id'=>6,'student_id'=>10,'books_read'=>10,'homework_score'=>80,'exam_score'=>78,'streak'=>4,'badges_count'=>2,'study_hours'=>55,'last_active'=>'2026-06-09'],
        ];

        $this->data['badges'] = [
            ['id'=>1,'student_id'=>1,'name'=>'Top Performer','icon'=>'trophy','description'=>'Scored above 90% in exams','earned_date'=>'2026-05-20'],
            ['id'=>2,'student_id'=>1,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 10+ books','earned_date'=>'2026-05-15'],
            ['id'=>3,'student_id'=>1,'name'=>'7 Day Streak','icon'=>'flame','description'=>'Maintained 7 day streak','earned_date'=>'2026-06-01'],
            ['id'=>4,'student_id'=>1,'name'=>'Quick Learner','icon'=>'zap','description'=>'Completed 5 lessons in a day','earned_date'=>'2026-06-05'],
            ['id'=>5,'student_id'=>1,'name'=>'Honor Roll','icon'=>'award','description'=>'Top 10% in class','earned_date'=>'2026-06-10'],
            ['id'=>6,'student_id'=>5,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 5+ books','earned_date'=>'2026-06-01'],
            ['id'=>7,'student_id'=>5,'name'=>'Quick Learner','icon'=>'zap','description'=>'Completed 3 lessons in a day','earned_date'=>'2026-06-10'],
            ['id'=>8,'student_id'=>6,'name'=>'Top Performer','icon'=>'trophy','description'=>'Scored above 85% in English','earned_date'=>'2026-05-25'],
            ['id'=>9,'student_id'=>6,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 12+ books','earned_date'=>'2026-06-02'],
            ['id'=>10,'student_id'=>6,'name'=>'Streak Master','icon'=>'flame','description'=>'5 day study streak','earned_date'=>'2026-06-08'],
            ['id'=>11,'student_id'=>7,'name'=>'Science Star','icon'=>'star','description'=>'Top score in science exam','earned_date'=>'2026-05-18'],
            ['id'=>12,'student_id'=>7,'name'=>'Math Wizard','icon'=>'calculator','description'=>'Perfect score in math quiz','earned_date'=>'2026-05-22'],
            ['id'=>13,'student_id'=>7,'name'=>'10 Day Streak','icon'=>'flame','description'=>'10 day study streak','earned_date'=>'2026-06-08'],
            ['id'=>14,'student_id'=>7,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 15+ books','earned_date'=>'2026-05-28'],
            ['id'=>15,'student_id'=>7,'name'=>'Honor Roll','icon'=>'award','description'=>'Top 5% in class','earned_date'=>'2026-06-10'],
            ['id'=>16,'student_id'=>7,'name'=>'Quick Learner','icon'=>'zap','description'=>'Completed 8 lessons in a day','earned_date'=>'2026-06-06'],
            ['id'=>17,'student_id'=>9,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 5+ books','earned_date'=>'2026-06-05'],
            ['id'=>18,'student_id'=>10,'name'=>'Quick Learner','icon'=>'zap','description'=>'Completed 4 lessons in a day','earned_date'=>'2026-06-07'],
            ['id'=>19,'student_id'=>10,'name'=>'Bookworm','icon'=>'book-open','description'=>'Read 8+ books','earned_date'=>'2026-06-03'],
        ];

        $this->data['reviews'] = [
            ['id'=>1,'teacher_id'=>1,'student_id'=>1,'rating'=>5,'comment'=>'Best math teacher ever! Makes everything so easy to understand. Highly recommended.','created_at'=>'2026-06-10'],
            ['id'=>2,'teacher_id'=>1,'student_id'=>5,'rating'=>4,'comment'=>'Very helpful and explains concepts clearly. Could use more practice problems.','created_at'=>'2026-06-08'],
            ['id'=>3,'teacher_id'=>2,'student_id'=>1,'rating'=>5,'comment'=>'Excellent English teacher, really engaging classes. Love the creative writing sessions.','created_at'=>'2026-06-09'],
            ['id'=>4,'teacher_id'=>1,'student_id'=>6,'rating'=>5,'comment'=>'Amazing teacher! Math is actually fun now. Thank you sir!','created_at'=>'2026-06-07'],
            ['id'=>5,'teacher_id'=>2,'student_id'=>6,'rating'=>4,'comment'=>'Great at explaining grammar rules. Very patient with students.','created_at'=>'2026-06-06'],
            ['id'=>6,'teacher_id'=>3,'student_id'=>7,'rating'=>5,'comment'=>'Fantastic science teacher! The experiments are so cool and educational.','created_at'=>'2026-06-05'],
            ['id'=>7,'teacher_id'=>1,'student_id'=>7,'rating'=>4,'comment'=>'Good math teacher. Helped me improve my geometry scores significantly.','created_at'=>'2026-06-04'],
            ['id'=>8,'teacher_id'=>2,'student_id'=>5,'rating'=>4,'comment'=>'Very supportive teacher. Always available for extra help.','created_at'=>'2026-06-03'],
        ];

        $this->data['activity_log'] = [
            ['id'=>1,'user_id'=>1,'action'=>'login','details'=>'Logged in from mobile','created_at'=>'2026-06-09 08:00:00'],
            ['id'=>2,'user_id'=>1,'action'=>'view_homework','details'=>'Viewed Mathematics homework','created_at'=>'2026-06-09 08:15:00'],
            ['id'=>3,'user_id'=>1,'action'=>'start_exam','details'=>'Started English Grammar quiz','created_at'=>'2026-06-08 16:00:00'],
            ['id'=>4,'user_id'=>2,'action'=>'login','details'=>'Logged in from desktop','created_at'=>'2026-06-09 07:30:00'],
            ['id'=>5,'user_id'=>2,'action'=>'create_homework','details'=>'Created new Mathematics homework','created_at'=>'2026-06-09 08:45:00'],
            ['id'=>6,'user_id'=>5,'action'=>'login','details'=>'Logged in from mobile','created_at'=>'2026-06-09 09:00:00'],
            ['id'=>7,'user_id'=>5,'action'=>'submit_homework','details'=>'Submitted English Essay homework','created_at'=>'2026-06-08 18:00:00'],
            ['id'=>8,'user_id'=>8,'action'=>'login','details'=>'Logged in from desktop','created_at'=>'2026-06-09 08:00:00'],
            ['id'=>9,'user_id'=>1,'action'=>'view_chapter','details'=>'Read Math Chapter 5: Algebra','created_at'=>'2026-06-09 10:30:00'],
            ['id'=>10,'user_id'=>6,'action'=>'login','details'=>'Logged in from mobile','created_at'=>'2026-06-09 09:30:00'],
        ];

        $this->data['activity_feed'] = [
            ['id'=>1,'user_id'=>1,'type'=>'read','title'=>'Read: Science Chapter 2 - Photosynthesis','subject'=>'Science','time'=>'Today, 10:30 AM','icon'=>'book-open'],
            ['id'=>2,'user_id'=>1,'type'=>'homework','title'=>'Homework: Math (Quadratic Equations)','subject'=>'Mathematics','time'=>'Today, 08:45 AM','icon'=>'file-text'],
            ['id'=>3,'user_id'=>1,'type'=>'quiz','title'=>'Quiz: English Grammar','subject'=>'English','time'=>'Yesterday, 04:20 PM','icon'=>'help-circle'],
            ['id'=>4,'user_id'=>1,'type'=>'ai','title'=>'AI Tutor: Photosynthesis Explanation','subject'=>'Science','time'=>'Yesterday, 03:10 PM','icon'=>'bot'],
            ['id'=>5,'user_id'=>1,'type'=>'read','title'=>'Read: Math Chapter 5 - Algebra','subject'=>'Mathematics','time'=>'Yesterday, 02:00 PM','icon'=>'book-open'],
            ['id'=>6,'user_id'=>5,'type'=>'read','title'=>'Read: Math Chapter 3 - Quadrilaterals','subject'=>'Mathematics','time'=>'Today, 09:00 AM','icon'=>'book-open'],
            ['id'=>7,'user_id'=>5,'type'=>'homework','title'=>'Homework: English Essay','subject'=>'English','time'=>'Yesterday, 06:00 PM','icon'=>'file-text'],
            ['id'=>8,'user_id'=>5,'type'=>'quiz','title'=>'Quiz: Physics Laws of Motion','subject'=>'Physics','time'=>'Yesterday, 11:00 AM','icon'=>'help-circle'],
            ['id'=>9,'user_id'=>1,'type'=>'exam','title'=>'Exam: Mid-term Math Test (Upcoming)','subject'=>'Mathematics','time'=>'Jun 20, 2026','icon'=>'clipboard'],
            ['id'=>10,'user_id'=>1,'type'=>'ai','title'=>'AI Tutor: Geometry Proofs Help','subject'=>'Mathematics','time'=>'2 days ago','icon'=>'bot'],
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
            // Math chapters
            ['id'=>1,'chapter_id'=>1,'title'=>'Chapter 1: Rational Numbers','content'=>'Rational numbers are numbers that can be expressed as a fraction p/q where p and q are integers and q is not zero.\n\nProperties of Rational Numbers:\n1. Closure: The sum or product of two rational numbers is always a rational number.\n2. Commutative: a + b = b + a and a × b = b × a\n3. Associative: (a + b) + c = a + (b + c)\n4. Distributive: a(b + c) = ab + ac\n\nExamples: 1/2, -3/4, 5/1, 0.75\n\nEvery integer is a rational number (e.g., 5 = 5/1).\nBetween any two rational numbers, there are infinitely many rational numbers.','pages_total'=>12,'pages_read'=>12],
            ['id'=>2,'chapter_id'=>2,'title'=>'Chapter 2: Linear Equations','content'=>'A linear equation is an equation where the highest power of the variable is 1.\n\nStandard form: ax + b = c\n\nSolving steps:\n1. Move constant terms to one side\n2. Isolate the variable by dividing\n3. Verify the solution\n\nExample: 3x + 7 = 22\n3x = 22 - 7\n3x = 15\nx = 5\n\nWord problems: Translate the problem into an equation, solve, and interpret the result in context.','pages_total'=>15,'pages_read'=>12],
            ['id'=>3,'chapter_id'=>3,'title'=>'Chapter 3: Quadrilaterals','content'=>'A quadrilateral is a polygon with four sides and four vertices. The word "quadrilateral" is derived from the Latin words "quadri" (four) and "latus" (side).\n\nTypes of Quadrilaterals:\n\n1. Parallelogram: A quadrilateral with both pairs of opposite sides parallel. Properties: opposite sides are equal, opposite angles are equal, and diagonals bisect each other.\n\n2. Rectangle: A parallelogram with all angles equal to 90 degrees. Properties: all properties of a parallelogram plus diagonals are equal.\n\n3. Square: A rectangle with all sides equal. Properties: all properties of a rectangle plus all sides are equal and diagonals are perpendicular.\n\n4. Rhombus: A parallelogram with all sides equal. Properties: all sides are equal, opposite angles are equal, and diagonals bisect each other at right angles.\n\n5. Trapezium: A quadrilateral with exactly one pair of parallel sides.','pages_total'=>18,'pages_read'=>6],
            ['id'=>4,'chapter_id'=>4,'title'=>'Chapter 4: Data Handling','content'=>'Data handling involves collecting, organizing, and presenting information.\n\nKey concepts:\n1. Data: Facts and figures collected for analysis\n2. Frequency: How often a value occurs\n3. Bar graph: Rectangular bars representing data\n4. Pie chart: Circle divided into sectors\n5. Histogram: Bars with no gaps between them\n\nCentral Tendency:\n- Mean: Sum of all values divided by number of values\n- Median: Middle value when data is arranged in order\n- Mode: Most frequently occurring value\n\nExample: Data: 2, 3, 5, 5, 7, 8, 10\nMean = (2+3+5+5+7+8+10)/7 = 40/7 = 5.71\nMedian = 5\nMode = 5','pages_total'=>14,'pages_read'=>0],
            ['id'=>5,'chapter_id'=>5,'title'=>'Chapter 5: Squares and Square Roots','content'=>'Square of a number: Multiply the number by itself.\n5² = 5 × 5 = 25\n\nProperties of squares:\n- Square of even number is even\n- Square of odd number is odd\n- Unit digit of squares can only be 0, 1, 4, 5, 6, 9\n\nSquare Root: The inverse operation of squaring.\n√25 = 5 (because 5² = 25)\n\nMethods to find square roots:\n1. Prime factorization method\n2. Long division method\n\nPerfect squares: 1, 4, 9, 16, 25, 36, 49, 64, 81, 100\n\nNon-perfect squares have irrational square roots (e.g., √2 = 1.414...)','pages_total'=>16,'pages_read'=>0],
            ['id'=>6,'chapter_id'=>6,'title'=>'Chapter 6: Cubes and Cube Roots','content'=>'Cube of a number: Multiply the number by itself three times.\n3³ = 3 × 3 × 3 = 27\n\nProperties:\n- Cube of even number is even\n- Cube of odd number is odd\n- Cube of negative number is negative: (-2)³ = -8\n\nCube Root: The inverse operation of cubing.\n³√27 = 3\n\nPerfect cubes: 1, 8, 27, 64, 125, 216, 343, 512, 729, 1000\n\nFinding cube root by prime factorization:\n³√216 = ³√(2³ × 3³) = 2 × 3 = 6','pages_total'=>13,'pages_read'=>0],
            // English chapters
            ['id'=>7,'chapter_id'=>7,'title'=>'Chapter 1: Food Sources','content'=>'Food is essential for survival. It provides energy, helps in growth, and repairs worn-out body tissues.\n\nSources of Food:\n- Plants: fruits, vegetables, grains, herbs\n- Animals: milk, eggs, meat, fish\n\nComponents of Food:\nCarbohydrates, Proteins, Fats, Vitamins, Minerals, Water, and Roughage.','pages_total'=>10,'pages_read'=>10],
            ['id'=>8,'chapter_id'=>8,'title'=>'Chapter 2: Components of Food','content'=>'Food components are nutrients that our body needs:\n\n1. Carbohydrates: Main source of energy. Found in rice, bread, potatoes.\n2. Proteins: Build and repair body tissues. Found in fish, meat, eggs, pulses.\n3. Fats: Store energy. Found in butter, oil, ghee.\n4. Vitamins: Protect against diseases. Found in fruits and vegetables.\n5. Minerals: Help in body functions. Iron, calcium, iodine.\n6. Water: Essential for digestion and temperature regulation.\n7. Roughage: Dietary fibre that aids digestion.\n\nBalanced Diet: A diet containing all nutrients in correct proportions.','pages_total'=>12,'pages_read'=>8],
            ['id'=>9,'chapter_id'=>9,'title'=>'Chapter 3: Fibre to Fabric','content'=>'Textile fibres come from natural and synthetic sources.\n\nNatural Fibres:\n- Cotton: From cotton plant bolls. Spun into thread, woven into cloth.\n- Wool: From sheep, goats, rabbits. Sheared, cleaned, carded, spun.\n- Silk: From silkworm cocoons. Reeling process extracts silk thread.\n\nSynthetic Fibres:\n- Nylon, Polyester, Acrylic: Made from chemicals.\n- Strong, durable, quick-drying.\n\nProcess: Fibre → Spinning → Weaving → Dyeing → Finished Fabric\n\nWeaving: Two sets of threads (warp and weft) interlace at right angles.','pages_total'=>11,'pages_read'=>0],
            // Science chapters
            ['id'=>10,'chapter_id'=>10,'title'=>'Chapter 1: The Happy Prince','content'=>'"The Happy Prince" is a fairy tale by Oscar Wilde about a jewel-encrusted statue who sacrifices everything to help the poor.\n\nThemes:\n1. Compassion and selflessness\n2. Social inequality\n3. True beauty lies in kindness\n\nCharacters:\n- The Happy Prince: A golden statue overlooking the city\n- The Swallow: A bird who helps distribute the prince\'s jewels\n- The Reed: The Swallow\'s love interest\n\nLiterary Devices:\n- Symbolism (the prince represents selfless love)\n- Irony (the prince is unhappy seeing suffering)\n- Allegory (the story represents Christian charity)','pages_total'=>8,'pages_read'=>5],
            ['id'=>11,'chapter_id'=>11,'title'=>'Chapter 2: The Ball Poem','content'=>'"The Ball Poem" by John Berryman is a reflective poem about a boy who loses his ball.\n\nSummary:\nA boy loses his ball in the water. He stands watching it drift away, learning his first lesson of loss. The poet observes that this is how we learn about mortality and grief.\n\nKey Themes:\n1. Loss and growing up\n2. The nature of grief\n3. Acceptance of impermanence\n\nImportant Quotes:\n"The ball / He will not learn... / The epistemology of loss"\n\nThis means: We cannot teach someone about loss through words; they must experience it personally.\n\nPoetic Devices:\n- Metaphor (ball = childhood innocence)\n- Alliteration ("ball bobbing")\n- Imagery (the ball floating in the water)','pages_total'=>9,'pages_read'=>4],
        ];

        $this->data['calendar_events'] = [
            ['id'=>1,'title'=>'Science Exam','date'=>'2026-06-30','type'=>'exam','color'=>'#EF4444'],
            ['id'=>2,'title'=>'Math Live Class','date'=>'2026-06-09','type'=>'class','color'=>'#4F46E5'],
            ['id'=>3,'title'=>'English Essay Due','date'=>'2026-06-12','type'=>'homework','color'=>'#F59E0B'],
            ['id'=>4,'title'=>'Science Viva','date'=>'2026-07-02','type'=>'exam','color'=>'#EF4444'],
            ['id'=>5,'title'=>'Holiday - Weekend','date'=>'2026-06-14','type'=>'holiday','color'=>'#10B981'],
            ['id'=>6,'title'=>'Parent Meeting','date'=>'2026-06-22','type'=>'event','color'=>'#8B5CF6'],
            ['id'=>7,'title'=>'Math Competition','date'=>'2026-06-30','type'=>'event','color'=>'#8B5CF6'],
            ['id'=>8,'title'=>'Physics Lab Due','date'=>'2026-06-18','type'=>'homework','color'=>'#F59E0B'],
            ['id'=>9,'title'=>'Mid-term Math Test','date'=>'2026-06-20','type'=>'exam','color'=>'#EF4444'],
            ['id'=>10,'title'=>'Science Fair','date'=>'2026-07-05','type'=>'event','color'=>'#8B5CF6'],
        ];

        $this->data['reports'] = [
            ['id'=>1,'student_id'=>1,'teacher_id'=>2,'subject'=>'Mathematics','class'=>'Class 8','grade'=>'A+','score'=>92,'behavior'=>'Excellent','comment'=>'Outstanding performance in algebra and geometry. Shows great problem-solving skills.','date'=>'2026-06-10'],
            ['id'=>2,'student_id'=>1,'teacher_id'=>3,'subject'=>'Science','class'=>'Class 8','grade'=>'A','score'=>88,'behavior'=>'Good','comment'=>'Good understanding of concepts. Needs more practice in physics numerical problems.','date'=>'2026-06-08'],
            ['id'=>3,'student_id'=>1,'teacher_id'=>4,'subject'=>'English','class'=>'Class 8','grade'=>'B+','score'=>82,'behavior'=>'Good','comment'=>'Good writing skills. Improve grammar and vocabulary for better scores.','date'=>'2026-06-05'],
            ['id'=>4,'student_id'=>5,'teacher_id'=>2,'subject'=>'Mathematics','class'=>'Class 8','grade'=>'B','score'=>75,'behavior'=>'Good','comment'=>'Shows improvement. Keep working on problem solving and conceptual clarity.','date'=>'2026-06-10'],
            ['id'=>5,'student_id'=>5,'teacher_id'=>3,'subject'=>'Science','class'=>'Class 8','grade'=>'A+','score'=>94,'behavior'=>'Excellent','comment'=>'Excellent grasp of scientific concepts. Very active in lab sessions.','date'=>'2026-06-08'],
            ['id'=>6,'student_id'=>6,'teacher_id'=>2,'subject'=>'Mathematics','class'=>'Class 8','grade'=>'A','score'=>85,'behavior'=>'Good','comment'=>'Consistent performance. Good at geometry but needs work on algebra.','date'=>'2026-06-10'],
            ['id'=>7,'student_id'=>6,'teacher_id'=>4,'subject'=>'English','class'=>'Class 8','grade'=>'A+','score'=>91,'behavior'=>'Excellent','comment'=>'Excellent creative writing skills. Very expressive and imaginative.','date'=>'2026-06-05'],
            ['id'=>8,'student_id'=>7,'teacher_id'=>2,'subject'=>'Mathematics','class'=>'Class 9','grade'=>'A','score'=>87,'behavior'=>'Good','comment'=>'Strong in algebra. Should practice more geometry proofs.','date'=>'2026-06-10'],
            ['id'=>9,'student_id'=>7,'teacher_id'=>3,'subject'=>'Science','class'=>'Class 9','grade'=>'A+','score'=>95,'behavior'=>'Excellent','comment'=>'Top performer in science. Exceptional experimental skills.','date'=>'2026-06-08'],
            ['id'=>10,'student_id'=>9,'teacher_id'=>3,'subject'=>'Science','class'=>'Class 9','grade'=>'B+','score'=>80,'behavior'=>'Good','comment'=>'Good overall performance. Needs to participate more in class discussions.','date'=>'2026-06-08'],
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
            ['id'=>1,'subject'=>'Mathematics','topic'=>'Algebra - Linear Equations','class_name'=>'Class 8','time'=>'09:00 - 10:00','day'=>'Monday','teacher_name'=>'Mr. Rahim','teacher_id'=>2,'students_count'=>25,'status'=>'completed'],
            ['id'=>2,'subject'=>'Science','topic'=>'Physics - Newton Laws','class_name'=>'Class 8','time'=>'10:30 - 11:30','day'=>'Monday','teacher_name'=>'Ms. Fatima','teacher_id'=>8,'students_count'=>28,'status'=>'upcoming'],
            ['id'=>3,'subject'=>'English','topic'=>'Grammar - Parts of Speech','class_name'=>'Class 8','time'=>'13:00 - 14:00','day'=>'Monday','teacher_name'=>'Ms. Sarah','teacher_id'=>4,'students_count'=>22,'status'=>'upcoming'],
            ['id'=>4,'subject'=>'Mathematics','topic'=>'Geometry - Triangles','class_name'=>'Class 8','time'=>'09:00 - 10:00','day'=>'Tuesday','teacher_name'=>'Mr. Rahim','teacher_id'=>2,'students_count'=>25,'status'=>'upcoming'],
            ['id'=>5,'subject'=>'Bangla','topic'=>'Grammar - Verbs','class_name'=>'Class 8','time'=>'10:30 - 11:30','day'=>'Tuesday','teacher_name'=>'Ms. Sarah','teacher_id'=>4,'students_count'=>24,'status'=>'upcoming'],
            ['id'=>6,'subject'=>'Science','topic'=>'Chemistry - Atoms','class_name'=>'Class 8','time'=>'13:00 - 14:00','day'=>'Tuesday','teacher_name'=>'Ms. Fatima','teacher_id'=>8,'students_count'=>28,'status'=>'upcoming'],
            ['id'=>7,'subject'=>'Mathematics','topic'=>'Data Handling','class_name'=>'Class 9','time'=>'09:00 - 10:00','day'=>'Wednesday','teacher_name'=>'Mr. Rahim','teacher_id'=>2,'students_count'=>20,'status'=>'upcoming'],
            ['id'=>8,'subject'=>'English','topic'=>'Essay Writing','class_name'=>'Class 8','time'=>'10:30 - 11:30','day'=>'Wednesday','teacher_name'=>'Ms. Sarah','teacher_id'=>4,'students_count'=>22,'status'=>'upcoming'],
            ['id'=>9,'subject'=>'Science','topic'=>'Biology - Cells','class_name'=>'Class 8','time'=>'13:00 - 14:00','day'=>'Wednesday','teacher_name'=>'Ms. Fatima','teacher_id'=>8,'students_count'=>28,'status'=>'upcoming'],
            ['id'=>10,'subject'=>'ICT','topic'=>'Database Fundamentals','class_name'=>'Class 8','time'=>'09:00 - 10:00','day'=>'Thursday','teacher_name'=>'Ms. Sarah','teacher_id'=>4,'students_count'=>22,'status'=>'upcoming'],
        ];
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) { header('Location: /intro.php'); exit; }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) { header('Location: /intro.php?error=unauthorized'); exit; }
}

function isMobile() {
    return preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

function requireDesktop() {
    if (isMobile()) {
        header('Location: /intro.php?error=desktop_required');
        exit;
    }
}

function redirectByRole($role) {
    switch ($role) {
        case 'admin': header('Location: /admin/'); break;
        case 'teacher': header('Location: /?role=teacher#screen-teacher-dash'); break;
        case 'student': header('Location: /?role=student#screen-home'); break;
        default: header('Location: /intro.php'); break;
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