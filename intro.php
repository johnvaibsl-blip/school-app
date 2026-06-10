<?php
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
$errorType = $_GET['error'] ?? '';

if ($errorType === 'desktop_required') {
    $error = 'Admin Panel is available only on desktop devices.';
} elseif ($errorType === 'unauthorized') {
    $error = 'You do not have access to that area.';
} elseif ($errorType === 'logout') {
    $error = 'You have been logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? 'login';
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($action === 'signup') {
        $name = sanitize($_POST['name'] ?? '');
        $class = sanitize($_POST['class'] ?? '');

        if (empty($name) || empty($email) || empty($password) || empty($class)) {
            $error = 'Please fill in all fields including class.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $db = getDB();
            $existing = $db->find('users', 'email', $email);
            if ($existing) {
                $error = 'An account with this email already exists.';
            } else {
                $id = $db->insert('users', [
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'student',
                    'avatar' => '',
                    'class' => $class,
                    'phone' => '',
                    'school' => '',
                    'is_premium' => 0,
                    'premium_expires_at' => null
                ]);
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_class'] = $class;
                $_SESSION['role'] = 'student';
                header('Location: index.php#screen-home');
                exit;
            }
        }
    } else {
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $db = getDB();
            $user = $db->find('users', 'email', $email);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'] ?? '';
                $_SESSION['user_class'] = $user['class'] ?? 'Class 8';
                $_SESSION['role'] = $user['role'];

                redirectByRole($user['role']);
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>EduAI - Sign In</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(160deg, #0F0A2E 0%, #1E1B4B 30%, #312E81 60%, #4338CA 100%);
            min-height: 100vh;
            max-width: 430px;
            margin: 0 auto;
            overflow-y: auto;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .screen-badge {
            position: absolute;
            top: 60px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            letter-spacing: 1px;
            z-index: 100;
        }

        .sparkles {
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .sparkle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: #A78BFA;
            border-radius: 50%;
            animation: sparkle 2s ease-in-out infinite;
        }

        .sparkle:nth-child(1) { top: 15%; left: 15%; animation-delay: 0s; }
        .sparkle:nth-child(2) { top: 25%; right: 18%; animation-delay: 0.5s; background: #F472B6; }
        .sparkle:nth-child(3) { bottom: 35%; left: 12%; animation-delay: 1s; background: #60A5FA; }
        .sparkle:nth-child(4) { bottom: 45%; right: 15%; animation-delay: 1.5s; background: #34D399; }
        .sparkle:nth-child(5) { top: 40%; left: 8%; animation-delay: 0.3s; background: #FBBF24; }

        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: scale(0); }
            50% { opacity: 1; transform: scale(1); }
        }

        .main-wrapper {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 100px 30px 40px;
            z-index: 2;
        }

        .slide-label {
            font-size: 13px;
            font-weight: 600;
            color: #A78BFA;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .slide-label i { width: 16px; height: 16px; }

        .slide-title {
            font-size: 30px;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 10px;
            text-align: center;
            background: linear-gradient(135deg, #FFFFFF 0%, #C4B5FD 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .slide-desc {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
            text-align: center;
            margin-bottom: 28px;
        }

        .login-form-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 28px 22px;
            width: 100%;
        }

        .login-form-card .form-group { margin-bottom: 14px; }

        .login-form-card label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-form-card input, .login-form-card select {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.08);
            color: white;
            outline: none;
            transition: border-color 0.2s;
        }

        .login-form-card input::placeholder { color: rgba(255, 255, 255, 0.35); }
        .login-form-card input:focus { border-color: #A78BFA; }

        .login-form-card select { appearance: auto; }
        .login-form-card select option { background: #1E1B4B; color: white; }

        .login-form-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #8B5CF6, #A78BFA);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            transition: transform 0.2s;
        }

        .login-form-btn:active { transform: scale(0.97); }

        .error-msg-intro {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            margin-bottom: 14px;
            text-align: center;
        }

        .toggle-tabs {
            display: flex;
            gap: 4px;
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 14px;
        }

        .toggle-tab {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-tab.active { background: rgba(139,92,246,0.3); color: white; }
        .toggle-tab:not(.active) { background: transparent; color: rgba(255,255,255,0.5); }

        .demo-section-intro {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .demo-section-intro h4 {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            text-align: center;
        }

        .demo-row-intro { display: flex; gap: 8px; margin-bottom: 8px; }

        .demo-btn-intro {
            flex: 1;
            padding: 8px;
            border: 1.5px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.2s;
        }

        .demo-btn-intro:hover { border-color: #A78BFA; background: rgba(139, 92, 246, 0.15); }
        .demo-btn-intro strong { display: block; font-size: 11px; color: white; margin-bottom: 2px; }

        .features-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .feature-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 8px 14px;
            border-radius: 50px;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            font-weight: 600;
        }

        .feature-chip i { width: 14px; height: 14px; }
    </style>
</head>
<body>
    <div class="screen-badge">EDUAI</div>

    <div class="sparkles">
        <div class="sparkle"></div>
        <div class="sparkle"></div>
        <div class="sparkle"></div>
        <div class="sparkle"></div>
        <div class="sparkle"></div>
    </div>

    <div class="main-wrapper">
        <div class="slide-label"><i data-lucide="sparkles"></i> EduAI</div>
        <h1 class="slide-title">Learn Smarter with AI</h1>
        <p class="slide-desc">Your personal AI tutor, live classes & smart exams — all in one place.</p>

        <div class="features-row">
            <div class="feature-chip"><i data-lucide="brain"></i> AI Tutor</div>
            <div class="feature-chip"><i data-lucide="tv"></i> Live Classes</div>
            <div class="feature-chip"><i data-lucide="file-text"></i> Smart Homework</div>
        </div>

        <?php if ($error): ?>
            <div class="error-msg-intro"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="toggle-tabs">
            <button type="button" id="tabLogin" class="toggle-tab active" onclick="showAuthForm('login')">Sign In</button>
            <button type="button" id="tabSignup" class="toggle-tab" onclick="showAuthForm('signup')">Sign Up</button>
        </div>

        <!-- Login Form -->
        <form method="POST" class="login-form-card" id="loginForm">
            <input type="hidden" name="form_action" value="login">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="login-form-btn">Sign In</button>

            <div class="demo-section-intro">
                <h4>Demo Accounts (password: password)</h4>
                <div class="demo-row-intro">
                    <button type="button" class="demo-btn-intro" onclick="fillDemo('arman@student.com')">
                        <strong>Student</strong>arman@student.com
                    </button>
                    <button type="button" class="demo-btn-intro" onclick="fillDemo('rahim@teacher.com')">
                        <strong>Teacher</strong>rahim@teacher.com
                    </button>
                </div>
                <button type="button" class="demo-btn-intro" onclick="fillDemo('admin@school.com')" style="width:100%">
                    <strong>Admin</strong>admin@school.com
                </button>
            </div>
        </form>

        <!-- Signup Form -->
        <form method="POST" class="login-form-card" id="signupForm" style="display:none">
            <input type="hidden" name="form_action" value="signup">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Your full name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min 6 characters" required minlength="6">
            </div>
            <div class="form-group">
                <label>Class / Grade</label>
                <select name="class" required>
                    <option value="">Select your class</option>
                    <option value="Class 7">Class 7</option>
                    <option value="Class 8">Class 8</option>
                    <option value="Class 9 Science">Class 9 - Science</option>
                    <option value="Class 9 Commerce">Class 9 - Commerce</option>
                    <option value="Class 9 Arts">Class 9 - Arts</option>
                    <option value="Class 10 Science">Class 10 - Science</option>
                    <option value="Class 10 Commerce">Class 10 - Commerce</option>
                    <option value="Class 10 Arts">Class 10 - Arts</option>
                </select>
            </div>
            <button type="submit" class="login-form-btn">Create Account</button>
            <p style="text-align:center;font-size:11px;color:rgba(255,255,255,0.45);margin-top:12px">Want to teach? <span style="color:#818CF8;font-weight:600">Contact Us</span> for teacher registration.</p>
        </form>
    </div>

    <script>
        lucide.createIcons();

        function fillDemo(email) {
            document.querySelector('#loginForm input[name="email"]').value = email;
            document.querySelector('#loginForm input[name="password"]').value = 'password';
        }

        function showAuthForm(type) {
            var loginForm = document.getElementById('loginForm');
            var signupForm = document.getElementById('signupForm');
            var tabLogin = document.getElementById('tabLogin');
            var tabSignup = document.getElementById('tabSignup');
            if (type === 'signup') {
                loginForm.style.display = 'none';
                signupForm.style.display = 'block';
                tabSignup.classList.add('active');
                tabLogin.classList.remove('active');
            } else {
                loginForm.style.display = 'block';
                signupForm.style.display = 'none';
                tabLogin.classList.add('active');
                tabSignup.classList.remove('active');
            }
        }
    </script>
</body>
</html>
