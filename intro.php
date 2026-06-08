<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Welcome - School App</title>
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
            overflow: hidden;
            position: relative;
        }

        /* ============ SCREEN BADGE ============ */
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

        /* ============ SLIDES ============ */
        .intro-slide {
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .intro-slide.active {
            opacity: 1;
            visibility: visible;
        }

        /* ============ ROBOT ============ */
        .robot-container {
            width: 220px;
            height: 220px;
            margin-bottom: 40px;
            position: relative;
            animation: robotFloat 3s ease-in-out infinite;
        }

        @keyframes robotFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        .robot-container svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 20px 40px rgba(139, 92, 246, 0.4));
        }

        .robot-glow {
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 30px;
            background: radial-gradient(ellipse, rgba(139, 92, 246, 0.5) 0%, transparent 70%);
            border-radius: 50%;
            animation: glowPulse 2s ease-in-out infinite;
        }

        @keyframes glowPulse {
            0%, 100% { opacity: 0.6; transform: translateX(-50%) scale(1); }
            50% { opacity: 1; transform: translateX(-50%) scale(1.2); }
        }

        /* ============ SPARKLES ============ */
        .sparkles {
            position: absolute;
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

        /* ============ TEXT ============ */
        .slide-content {
            text-align: center;
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
            font-size: 32px;
            font-weight: 900;
            color: white;
            line-height: 1.2;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #FFFFFF 0%, #C4B5FD 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .slide-desc {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            max-width: 300px;
            margin: 0 auto;
        }

        /* ============ FEATURES CHIPS ============ */
        .features-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 24px;
        }

        .feature-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 10px 16px;
            border-radius: 50px;
            color: white;
            font-size: 13px;
            font-weight: 600;
        }

        .feature-chip i { width: 18px; height: 18px; }

        .chip-live { border-color: rgba(239, 68, 68, 0.4); }
        .chip-hw { border-color: rgba(251, 191, 36, 0.4); }
        .chip-ai { border-color: rgba(167, 139, 250, 0.4); }
        .chip-teacher { border-color: rgba(52, 211, 153, 0.4); }

        /* ============ TEACHER AVATARS ============ */
        .teacher-avatars {
            display: flex;
            justify-content: center;
        }

        .teacher-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: white;
            border: 3px solid rgba(30, 27, 75, 0.8);
            margin-left: -12px;
        }

        .teacher-avatar:first-child { margin-left: 0; }
        .teacher-avatar:nth-child(1) { background: linear-gradient(135deg, #6366F1, #8B5CF6); }
        .teacher-avatar:nth-child(2) { background: linear-gradient(135deg, #EC4899, #F472B6); }
        .teacher-avatar:nth-child(3) { background: linear-gradient(135deg, #22C55E, #4ADE80); }
        .teacher-avatar:nth-child(4) { background: linear-gradient(135deg, #F59E0B, #F97316); }
        .teacher-avatar:nth-child(5) { background: linear-gradient(135deg, #3B82F6, #60A5FA); }

        /* ============ BUTTONS ============ */
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 14px;
            width: 100%;
            max-width: 300px;
            margin-top: 30px;
        }

        .btn-intro {
            padding: 16px 24px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-intro i { width: 20px; height: 20px; }

        .btn-signup {
            background: linear-gradient(135deg, #8B5CF6, #A78BFA);
            color: white;
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
        }

        .btn-signup:active {
            transform: scale(0.97);
        }

        .btn-signin {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .btn-signin:active {
            transform: scale(0.97);
        }

        /* ============ DOTS ============ */
        .dots-container {
            position: absolute;
            bottom: 50px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 100;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dot.active {
            background: #A78BFA;
            width: 28px;
            border-radius: 5px;
        }
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

    <!-- ========== SLIDE 1: Learn With AI ========== -->
    <div class="intro-slide active" data-slide="0">
        <div class="robot-container">
            <svg viewBox="0 0 200 200" fill="none"><ellipse cx="100" cy="85" rx="55" ry="50" fill="url(#rg)"/><ellipse cx="78" cy="78" rx="12" ry="14" fill="#1E1B4B"/><ellipse cx="122" cy="78" rx="12" ry="14" fill="#1E1B4B"/><ellipse cx="78" cy="76" rx="6" ry="7" fill="#60A5FA"/><ellipse cx="122" cy="76" rx="6" ry="7" fill="#60A5FA"/><circle cx="76" cy="74" r="2" fill="white"/><circle cx="120" cy="74" r="2" fill="white"/><path d="M82 100 Q100 115 118 100" stroke="#1E1B4B" stroke-width="3" fill="none" stroke-linecap="round"/><line x1="100" y1="35" x2="100" y2="20" stroke="#A78BFA" stroke-width="4" stroke-linecap="round"/><circle cx="100" cy="16" r="6" fill="#F472B6"/><rect x="42" y="70" width="12" height="24" rx="6" fill="#A78BFA"/><rect x="146" y="70" width="12" height="24" rx="6" fill="#A78BFA"/><rect x="70" y="130" width="60" height="45" rx="15" fill="url(#bg)"/><circle cx="100" cy="150" r="8" fill="#34D399"/><circle cx="100" cy="150" r="4" fill="#A7F3D0"/><rect x="45" y="135" width="20" height="35" rx="10" fill="#8B5CF6"/><rect x="135" y="135" width="20" height="35" rx="10" fill="#8B5CF6"/><defs><linearGradient id="rg" x1="45" y1="35" x2="155" y2="135" gradientUnits="userSpaceOnUse"><stop stop-color="#C4B5FD"/><stop offset="1" stop-color="#8B5CF6"/></linearGradient><linearGradient id="bg" x1="70" y1="130" x2="130" y2="175" gradientUnits="userSpaceOnUse"><stop stop-color="#A78BFA"/><stop offset="1" stop-color="#7C3AED"/></linearGradient></defs></svg>
            <div class="robot-glow"></div>
        </div>
        <div class="slide-content">
            <div class="slide-label"><i data-lucide="sparkles"></i> Powered by AI</div>
            <h1 class="slide-title">Learn With AI</h1>
            <p class="slide-desc">Your personal AI tutor available 24/7. Ask anything, get instant explanations and solve any problem.</p>
        </div>
    </div>

    <!-- ========== SLIDE 2: Real Teachers ========== -->
    <div class="intro-slide" data-slide="1">
        <div class="robot-container" style="margin-bottom: 30px;">
            <div class="teacher-avatars">
                <div class="teacher-avatar">R</div>
                <div class="teacher-avatar">S</div>
                <div class="teacher-avatar">K</div>
                <div class="teacher-avatar">N</div>
                <div class="teacher-avatar">T</div>
            </div>
        </div>
        <div class="slide-content">
            <div class="slide-label"><i data-lucide="award"></i> Expert Educators</div>
            <h1 class="slide-title">Real Teachers, Real Results</h1>
            <p class="slide-desc">Learn from experienced teachers. Live classes, personalized guidance, and instant feedback.</p>
            <div class="features-grid">
                <div class="feature-chip chip-live"><i data-lucide="tv"></i> Live Classes</div>
                <div class="feature-chip chip-hw"><i data-lucide="file-text"></i> Smart Homework</div>
                <div class="feature-chip chip-ai"><i data-lucide="brain"></i> AI Exam Prep</div>
                <div class="feature-chip chip-teacher"><i data-lucide="trending-up"></i> Progress Track</div>
            </div>
        </div>
    </div>

    <!-- ========== SLIDE 3: Get Started ========== -->
    <div class="intro-slide" data-slide="2">
        <div class="robot-container">
            <svg viewBox="0 0 200 200" fill="none"><ellipse cx="100" cy="85" rx="55" ry="50" fill="url(#rg)"/><ellipse cx="78" cy="78" rx="12" ry="14" fill="#1E1B4B"/><ellipse cx="122" cy="78" rx="12" ry="14" fill="#1E1B4B"/><ellipse cx="78" cy="76" rx="6" ry="7" fill="#60A5FA"/><ellipse cx="122" cy="76" rx="6" ry="7" fill="#60A5FA"/><circle cx="76" cy="74" r="2" fill="white"/><circle cx="120" cy="74" r="2" fill="white"/><path d="M82 100 Q100 115 118 100" stroke="#1E1B4B" stroke-width="3" fill="none" stroke-linecap="round"/><line x1="100" y1="35" x2="100" y2="20" stroke="#A78BFA" stroke-width="4" stroke-linecap="round"/><circle cx="100" cy="16" r="6" fill="#F472B6"/><rect x="42" y="70" width="12" height="24" rx="6" fill="#A78BFA"/><rect x="146" y="70" width="12" height="24" rx="6" fill="#A78BFA"/><rect x="70" y="130" width="60" height="45" rx="15" fill="url(#bg)"/><circle cx="100" cy="150" r="8" fill="#34D399"/><circle cx="100" cy="150" r="4" fill="#A7F3D0"/><rect x="45" y="135" width="20" height="35" rx="10" fill="#8B5CF6"/><rect x="135" y="135" width="20" height="35" rx="10" fill="#8B5CF6"/><defs><linearGradient id="rg" x1="45" y1="35" x2="155" y2="135" gradientUnits="userSpaceOnUse"><stop stop-color="#C4B5FD"/><stop offset="1" stop-color="#8B5CF6"/></linearGradient><linearGradient id="bg" x1="70" y1="130" x2="130" y2="175" gradientUnits="userSpaceOnUse"><stop stop-color="#A78BFA"/><stop offset="1" stop-color="#7C3AED"/></linearGradient></defs></svg>
            <div class="robot-glow"></div>
        </div>
        <div class="slide-content">
            <div class="slide-label"><i data-lucide="rocket"></i> Start Your Journey</div>
            <h1 class="slide-title">Ready to Excel?</h1>
            <p class="slide-desc">Join thousands of students learning smarter with AI and expert teachers.</p>
            <div class="btn-group">
                <a href="login.php" class="btn-intro btn-signup">
                    <i data-lucide="user-plus"></i> Sign Up
                </a>
                <a href="login.php" class="btn-intro btn-signin">
                    <i data-lucide="log-in"></i> Sign In
                </a>
            </div>
        </div>
    </div>

    <!-- Dots -->
    <div class="dots-container">
        <div class="dot active" onclick="goToSlide(0)"></div>
        <div class="dot" onclick="goToSlide(1)"></div>
        <div class="dot" onclick="goToSlide(2)"></div>
    </div>

    <script>
        lucide.createIcons();

        const slides = document.querySelectorAll('.intro-slide');
        const dots = document.querySelectorAll('.dot');
        let currentSlide = 0;
        const totalSlides = 3;
        let startX = 0;
        let isDragging = false;

        function goToSlide(index) {
            if (index < 0 || index >= totalSlides) return;
            slides[currentSlide].classList.remove('active');
            dots[currentSlide].classList.remove('active');
            currentSlide = index;
            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.add('active');
        }

        // Touch/Swipe
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
        });

        document.addEventListener('touchend', (e) => {
            if (!isDragging) return;
            const endX = e.changedTouches[0].clientX;
            const diff = startX - endX;
            if (Math.abs(diff) > 50) {
                if (diff > 0 && currentSlide < totalSlides - 1) {
                    goToSlide(currentSlide + 1);
                } else if (diff < 0 && currentSlide > 0) {
                    goToSlide(currentSlide - 1);
                }
            }
            isDragging = false;
        });

        // Mouse drag (for desktop)
        document.addEventListener('mousedown', (e) => {
            startX = e.clientX;
            isDragging = true;
        });

        document.addEventListener('mouseup', (e) => {
            if (!isDragging) return;
            const diff = startX - e.clientX;
            if (Math.abs(diff) > 50) {
                if (diff > 0 && currentSlide < totalSlides - 1) {
                    goToSlide(currentSlide + 1);
                } else if (diff < 0 && currentSlide > 0) {
                    goToSlide(currentSlide - 1);
                }
            }
            isDragging = false;
        });

        // Keyboard
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' && currentSlide < totalSlides - 1) {
                goToSlide(currentSlide + 1);
            } else if (e.key === 'ArrowLeft' && currentSlide > 0) {
                goToSlide(currentSlide - 1);
            }
        });
    </script>
</body>
</html>