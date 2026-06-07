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
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        $user = $db->find('users', 'email', $email);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            redirectByRole($user['role']);
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School App</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #1E1B4B 0%, #312E81 50%, #4338CA 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 32px 24px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .login-logo .icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .login-logo .icon svg { width: 28px; height: 28px; }
        .login-logo h1 { font-size: 22px; font-weight: 800; color: #1E1B4B; }
        .login-logo p { font-size: 12px; color: #6B7280; margin-top: 4px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            font-size: 14px;
            transition: border-color 0.2s;
            outline: none;
        }
        .form-group input:focus { border-color: #4F46E5; }
        .login-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }
        .login-btn:active { transform: scale(0.98); }
        .error-msg {
            background: #FEE2E2;
            color: #DC2626;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            margin-bottom: 16px;
            text-align: center;
        }
        .back-link {
            text-align: center;
            margin-top: 16px;
        }
        .back-link a {
            color: #4F46E5;
            font-size: 13px;
            text-decoration: none;
            font-weight: 600;
        }
        .demo-accounts {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #E5E7EB;
        }
        .demo-accounts h4 { font-size: 11px; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; text-align: center; }
        .demo-row { display: flex; gap: 8px; margin-bottom: 8px; }
        .demo-btn {
            flex: 1;
            padding: 8px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            background: #F9FAFB;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
        }
        .demo-btn:hover { border-color: #4F46E5; background: #EEF2FF; }
        .demo-btn strong { display: block; font-size: 11px; color: #1E1B4B; margin-bottom: 2px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <div class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </div>
            <h1>School App</h1>
            <p>Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="login-btn">Sign In</button>
        </form>

        <div class="back-link">
            <a href="/">&larr; Back to Home</a>
        </div>

        <div class="demo-accounts">
            <h4>Demo Accounts (password: password)</h4>
            <div class="demo-row">
                <button class="demo-btn" onclick="fillDemo('arman@student.com')">
                    <strong>Student</strong>arman@student.com
                </button>
                <button class="demo-btn" onclick="fillDemo('rahim@teacher.com')">
                    <strong>Teacher</strong>rahim@teacher.com
                </button>
            </div>
            <button class="demo-btn" onclick="fillDemo('admin@school.com')" style="width:100%">
                <strong>Admin</strong>admin@school.com
            </button>
        </div>
    </div>
    <script>
        function fillDemo(email) {
            document.querySelector('input[name="email"]').value = email;
            document.querySelector('input[name="password"]').value = 'password';
        }
    </script>
</body>
</html>
