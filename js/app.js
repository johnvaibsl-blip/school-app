// === ROLE-BASED SPA ===
var USER_ROLE = window.__USER_ROLE || '';
var USER_NAME = window.__USER_NAME || '';
var USER_CLASS = window.__USER_CLASS || 'Class 8';
var USER_EMAIL = window.__USER_EMAIL || '';
var _previousScreen = 'screen-home';
var _isPremium = false;

var PREMIUM_LOCKED_SCREENS = ['screen-homework','screen-hw-detail','screen-exam','screen-exam-interface','screen-exam-result','screen-live-lobby','screen-live-class','screen-reader','screen-my-study'];

var ROLE_SCREENS = {
    landing: ['screen-landing'],
    student: [
        'screen-home','screen-chat','screen-tutors','screen-teacher-profile',
        'screen-profile','screen-edit-profile','screen-homework','screen-hw-detail','screen-exam',
        'screen-exam-interface','screen-exam-result','screen-subject',
        'screen-reader','screen-live-lobby','screen-live-class','screen-search',
        'screen-pricing','screen-payment','screen-payment-success','screen-settings','screen-notif',
        'screen-my-study','screen-library-subject','screen-messages'
    ],
    teacher: [
        'screen-teacher-dash','screen-upload-content','screen-content-library',
        'screen-my-students','screen-student-progress','screen-messages',
        'screen-announcements','screen-create-announcement','screen-class-schedule',
        'screen-calendar','screen-exam-analytics','screen-hw-analytics',
        'screen-create-hw','screen-create-exam','screen-evaluate','screen-earnings',
        'screen-reports','screen-give-report','screen-edit-profile',
        'screen-teacher-profile-view','screen-teacher-edit-profile',
        'screen-settings'
    ],
    admin: [
        'screen-admin','screen-library-mgmt','screen-chapter-mgmt',
        'screen-ai-settings','screen-packages','screen-teacher-ranking',
        'screen-question-bank'
    ]
};

var ROLE_NAV = {
    student: [
        {id:'screen-home',icon:'home',label:'Home'},
        {id:'screen-tutors',icon:'users',label:'Tutors'},
        {id:'screen-my-study',icon:'book-open',label:'My Study'},
        {id:'screen-profile',icon:'user',label:'Profile'}
    ],
    teacher: [
        {id:'screen-teacher-dash',icon:'layout-dashboard',label:'Home'},
        {id:'screen-messages',icon:'message-circle',label:'Messages'},
        {id:'screen-teacher-profile-view',icon:'user',label:'Profile'}
    ],
    admin: []
};

// === INIT ===
document.addEventListener('DOMContentLoaded', function() {
    try { _loadSubjectsCache(); } catch(e) {}
    // Auth check: if logged in but no role, redirect to login
    if (!USER_ROLE && window.location.hash && window.location.hash !== '#screen-landing') {
        window.location.href = '/intro.php';
        return;
    }
    applyRoleVisibility();
    buildBottomNav();
    if (typeof lucide !== 'undefined') lucide.createIcons();
    if (USER_ROLE === 'student') {
        checkPremiumAccess().then(function() { handleHashRoute(); }).catch(function() { handleHashRoute(); });
    } else {
        handleHashRoute();
    }
    try { bindEvents(); } catch(e) {}
    if (USER_ROLE === 'student') try { loadLibraryGrid(); } catch(e) {}
});

function applyRoleVisibility() {
    if (!USER_ROLE) return;
    var allowed = [];
    if (ROLE_SCREENS[USER_ROLE]) allowed = allowed.concat(ROLE_SCREENS[USER_ROLE]);
    allowed.push('screen-landing');
    document.querySelectorAll('.screen').forEach(function(s) {
        if (allowed.indexOf(s.id) === -1) s.style.display = 'none';
    });
}

function buildBottomNav() {
    var nav = document.getElementById('bottomNav');
    if (!nav) return;
    if (!USER_ROLE || !ROLE_NAV[USER_ROLE] || ROLE_NAV[USER_ROLE].length === 0) {
        nav.style.display = 'none';
        return;
    }
    var items = ROLE_NAV[USER_ROLE];
    var html = '';
    items.forEach(function(item, i) {
        html += '<button class="nav-item' + (i === 0 ? ' active' : '') + '" data-screen="' + item.id + '" onclick="navTo(this,\'' + item.id + '\')">' +
            '<span class="nav-icon"><i data-lucide="' + item.icon + '"></i></span>' +
            '<span class="nav-label">' + item.label + '</span></button>';
    });
    nav.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function handleHashRoute() {
    var hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById(hash)) {
        showScreen(hash);
    } else if (USER_ROLE === 'student') {
        showScreen('screen-home');
    } else if (USER_ROLE === 'teacher') {
        showScreen('screen-teacher-dash');
    } else if (USER_ROLE === 'admin') {
        showScreen('screen-admin');
    }
}
window.addEventListener('hashchange', handleHashRoute);

// === TOAST ===
function showToast(message, type) {
    type = type || 'success';
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;color:white;z-index:9999;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:toastIn 0.3s ease';
    var icons = {
        success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>',
        error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
        info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
    };
    var colors = {success:'linear-gradient(135deg,#10B981,#059669)',error:'linear-gradient(135deg,#EF4444,#DC2626)',info:'linear-gradient(135deg,#6366F1,#4F46E5)'};
    toast.style.background = colors[type] || colors.info;
    toast.innerHTML = (icons[type] || icons.info) + ' ' + message;
    document.body.appendChild(toast);
    setTimeout(function() { toast.style.opacity='0'; toast.style.transition='opacity 0.3s'; setTimeout(function(){toast.remove();},300); }, 2500);
}

// === PREMIUM ACCESS CHECK ===
function checkPremiumAccess() {
    return api('is_premium').then(function(data) {
        _isPremium = data.is_premium || false;
        if (_isPremium && data.expires_at) {
            var exp = new Date(data.expires_at);
            if (exp < new Date()) { _isPremium = false; }
        }
        return _isPremium;
    }).catch(function() { return false; });
}

function showPremiumOverlay() {
    var overlay = document.getElementById('premiumOverlay');
    if (overlay) overlay.style.display = 'flex';
}

function closePremiumOverlay() {
    var overlay = document.getElementById('premiumOverlay');
    if (overlay) overlay.style.display = 'none';
}

// === SCREEN NAVIGATION ===
function showScreen(id) {
    // Role check: only allow screens for current role
    if (USER_ROLE && ROLE_SCREENS[USER_ROLE]) {
        var allowed = ROLE_SCREENS[USER_ROLE].concat(['screen-landing']);
        if (allowed.indexOf(id) === -1) return; // block cross-panel access
    }
    // Premium gating for students
    if (USER_ROLE === 'student' && PREMIUM_LOCKED_SCREENS.indexOf(id) !== -1 && !_isPremium) {
        showPremiumOverlay();
        return;
    }
    document.querySelectorAll('.screen').forEach(function(s) { s.classList.remove('active'); });
    var target = document.getElementById(id);
    if (target) {
        target.classList.add('active');
        target.style.display = '';
        window.scrollTo(0, 0);
    }
    document.querySelectorAll('.nav-item').forEach(function(n) { n.classList.remove('active'); });
    var navBtn = document.querySelector('[data-screen="' + id + '"]');
    if (navBtn) navBtn.classList.add('active');
    if (window.location.hash !== '#' + id) history.replaceState(null, '', '#' + id);
    if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    if (id === 'screen-exam-interface') setTimeout(startExamTimer, 100);
    else if (examTimerInterval) clearInterval(examTimerInterval);
    if (id === 'screen-my-study' && USER_ROLE === 'student') loadLibraryGrid();
    if (id === 'screen-content-library' && (USER_ROLE === 'teacher' || USER_ROLE === 'admin')) loadTeacherContentLibrary();
    if (id === 'screen-home' && USER_ROLE === 'student') loadStudentHome();
    if (id === 'screen-teacher-dash' && USER_ROLE === 'teacher') loadTeacherDashboard();
    if (id === 'screen-notif') loadNotifications();
    if (id === 'screen-homework') loadHomeworkList();
    if (id === 'screen-exam') loadExamList();
    if (id === 'screen-announcements') loadAnnouncements();
    if (id !== 'screen-chat') _chatSettingsLoaded = false;
    if (id === 'screen-chat') loadChatSettings();
    if (id === 'screen-pricing') { loadPackages(); checkPremiumAccess(); }
    if (id === 'screen-tutors') loadTeacherRankings();
    if (id === 'screen-profile' && USER_ROLE === 'student') { loadStudentProfile(); }
    if (id === 'screen-edit-profile') loadEditProfile();
    if (id === 'screen-teacher-profile') loadTeacherProfile();
    if (id === 'screen-teacher-profile-view') loadTeacherOwnProfile();
    if (id === 'screen-teacher-edit-profile') loadTeacherEditProfile();
    if (id === 'screen-my-students') loadMyStudents();
    if (id === 'screen-student-progress') loadStudentDetail();
    if (id === 'screen-messages') loadMessages();
    if (id === 'screen-earnings') loadEarnings();
    if (id === 'screen-live-lobby') loadLiveSchedule();
    if (id === 'screen-my-study') { loadMyStudyStats(); loadSubjectChapters(1); }
    if (id === 'screen-tutors' && USER_ROLE === 'student') loadTutorsScreen();
    if (id === 'screen-teacher-dash' && USER_ROLE === 'teacher') loadTeacherDashStats();
    if (id === 'screen-exam-analytics') loadExamAnalytics();
    if (id === 'screen-hw-analytics') loadHwAnalytics();
    if (id === 'screen-reports') loadReports();
    if (id === 'screen-class-schedule') loadClassSchedule();
    if (id === 'screen-calendar') loadCalendar();
    if (id === 'screen-admin') loadAdminDashboard();
    if (id === 'screen-teacher-ranking') loadAdminRankings();
    if (id === 'screen-question-bank') loadQuestionBankScreen();
    if (id === 'screen-chapter-mgmt') loadChapterManager();
    if (id === 'screen-library-mgmt') loadLibraryMgmt();
    if (id === 'screen-packages') loadAdminPackages();
    if (id === 'screen-evaluate') loadStudentEvaluation();
    if (id === 'screen-give-report') loadGiveReportStudents();
    if (id === 'screen-hw-detail' && _currentHwId) loadHomeworkDetail(_currentHwId);
    if (id === 'screen-exam-result') loadExamResult();
}

function navTo(btn, screenId) {
    document.querySelectorAll('.nav-item').forEach(function(n) { n.classList.remove('active'); });
    btn.classList.add('active');
    showScreen(screenId);
}

function goBackFromChat() {
    chatThemes.forEach(function(t) { if (t) { document.body.classList.remove(t); } });
    showScreen(_previousScreen);
}

function goBackFromMessages() {
    var back = _previousScreen || (USER_ROLE === 'teacher' ? 'screen-teacher-dash' : 'screen-home');
    showScreen(back);
}

function openChatFromFAB() {
    var activeScreen = document.querySelector('.screen.active');
    _previousScreen = activeScreen ? activeScreen.id : 'screen-home';
    showScreen('screen-chat');
}

// === OPEN SUBJECT FOLDER ===
var SUBJECTS = {
    mathematics: { name: 'Mathematics', icon: 'calculator', color: '#3B82F6', color2: '#2563EB' },
    science: { name: 'Science', icon: 'flask-conical', color: '#22C55E', color2: '#16A34A' },
    english: { name: 'English', icon: 'book-text', color: '#8B5CF6', color2: '#7C3AED' },
    bangla: { name: 'Bangla', icon: 'pen-tool', color: '#F59E0B', color2: '#D97706' },
    ict: { name: 'ICT', icon: 'laptop', color: '#06B6D4', color2: '#0891B2' },
    religion: { name: 'Religion', icon: 'landmark', color: '#EC4899', color2: '#DB2777' }
};

var CLASS_SUBJECTS = {
    'Class 7': ['bangla','english','math','science','ict','religion'],
    'Class 8': ['bangla','english','math','science','ict','religion'],
    'Class 9 Science': ['bangla','english','math','physics','chemistry','biology','ict'],
    'Class 10 Science': ['bangla','english','math','physics','chemistry','biology','ict'],
    'Class 9 Commerce': ['bangla','english','math','accounting','finance','religion'],
    'Class 10 Commerce': ['bangla','english','math','accounting','finance','religion'],
    'Class 9 Arts': ['bangla','english','math','history','geography','ict'],
    'Class 10 Arts': ['bangla','english','math','history','geography','ict']
};

function openSubjectFolderDynamic(subjectId, subjectKey) {
    currentLibrarySubjectId = subjectId;
    currentLibrarySubjectKey = subjectKey;
    var s = SUBJECTS[subjectKey];
    if (!s) {
        var fallbackMap = { 5:{name:'Physics',icon:'atom',color:'#3B82F6',color2:'#2563EB'}, 6:{name:'Chemistry',icon:'test-tubes',color:'#06B6D4',color2:'#0891B2'}, 7:{name:'Biology',icon:'bug',color:'#10B981',color2:'#059669'}, 9:{name:'Geography',icon:'globe',color:'#F97316',color2:'#EA580C'}, 11:{name:'Accounting',icon:'calculator',color:'#F59E0B',color2:'#D97706'}, 12:{name:'Finance',icon:'wallet',color:'#06B6D4',color2:'#0891B2'}, 13:{name:'History',icon:'landmark',color:'#8B5CF6',color2:'#7C3AED'} };
        s = fallbackMap[subjectId] || { name: subjectKey, icon: 'book-open', color: '#6366F1', color2: '#4F46E5' };
    }
    document.getElementById('libSubjectTitle').textContent = s.name;
    document.getElementById('libSubjectName').textContent = s.name;
    document.getElementById('libSubjectIcon').setAttribute('data-lucide', s.icon);
    var header = document.getElementById('libSubjectHeader');
    header.style.background = 'linear-gradient(135deg,' + s.color + ',' + s.color2 + ')';
    loadSubjectFiles(subjectId);
    showFolderView();
    showScreen('screen-library-subject');
}

function loadSubjectFiles(subjectId) {
    var fileContainer = document.getElementById('libFileList');
    if (!fileContainer) return;
    var userClass = USER_CLASS || 'Class 8';
    fetch('/api/index.php?action=library&class=' + encodeURIComponent(userClass) + '&subject_id=' + subjectId)
        .then(function(r) { return r.json(); })
        .then(function(files) {
            var bookCount = 0, notesCount = 0, videoCount = 0;
            var typeMap = { textbook: 'book', guide: 'book', reference: 'book', notes: 'notes', video: 'video' };
            var html = '';
            (files || []).forEach(function(f) {
                var mappedType = typeMap[f.type] || 'book';
                if (mappedType === 'book') bookCount++;
                else if (mappedType === 'notes') notesCount++;
                else videoCount++;
                var typeIcon = mappedType === 'book' ? 'book-open' : mappedType === 'notes' ? 'file-text' : 'video';
                var typeColor = mappedType === 'book' ? '#3B82F6' : mappedType === 'notes' ? '#8B5CF6' : '#EF4444';
                var boxClass = mappedType === 'book' ? 'blue' : mappedType === 'notes' ? 'purple' : 'red';
                var badge = '';
                if (f.uploader_type === 'admin') badge = '<span class="list-item-badge" style="background:#EEF2FF;color:#6366F1">Admin</span>';
                html += '<div class="list-item card-enter" data-type="' + mappedType + '" onclick="openLibraryFile(' + (f.subject_id || 0) + ',\'' + mappedType + '\',\'' + (f.title || '').replace(/'/g, "\\'") + '\')">' +
                    '<div class="icon-box ' + boxClass + ' sm"><i data-lucide="' + typeIcon + '" class="icon-sm"></i></div>' +
                    '<div class="list-item-content"><h5>' + (f.title || 'Untitled') + '</h5>' +
                    '<p>' + (f.type || 'file') + ' - ' + (f.description || '') + '</p></div>' + badge + '</div>';
            });
            if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No files available for this subject.</p>';
            fileContainer.innerHTML = html;
            var bc = document.getElementById('bookCount');
            var nc = document.getElementById('notesCount');
            var vc = document.getElementById('videoCount');
            if (bc) bc.textContent = bookCount + ' book' + (bookCount !== 1 ? 's' : '');
            if (nc) nc.textContent = notesCount + ' note' + (notesCount !== 1 ? 's' : '');
            if (vc) vc.textContent = videoCount + ' video' + (videoCount !== 1 ? 's' : '');
            if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
        })
        .catch(function() {
            fileContainer.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load files.</p>';
        });
}

// === OPEN LIBRARY FILE ===
function openLibraryFile(subjectId, type, title) {
    if (type === 'video') {
        showToast('Video player coming soon!', 'info');
        return;
    }
    if (type === 'notes') {
        api('chapters&subject_id=' + subjectId).then(function(chapters) {
            var ch = (chapters || [])[0];
            if (ch) {
                openBookReader(ch.id, ch.title);
            } else {
                showToast('No content available yet', 'info');
            }
        });
        return;
    }
    api('chapters&subject_id=' + subjectId).then(function(chapters) {
        var ch = (chapters || [])[0];
        if (ch) {
            openBookReader(ch.id, ch.title);
        } else {
            showToast('No chapters available for this subject', 'info');
        }
    });
}

function openBookReader(chapterId, chapterTitle) {
    _previousScreen = document.querySelector('.screen.active') ? document.querySelector('.screen.active').id : 'screen-my-study';
    loadBookContent(chapterId);
    showScreen('screen-reader');
}

// === FILE FOLDER VIEW ===
function openFileFolder(type) {
    var folderView = document.getElementById('folderView');
    var fileListView = document.getElementById('fileListView');
    var items = document.querySelectorAll('#libFileList .list-item');
    var typeNames = { book: 'Books', notes: 'Notes', video: 'Videos' };
    var typeIcons = { book: 'book-open', notes: 'file-text', video: 'video' };
    var typeColors = { book: '#3B82F6', notes: '#8B5CF6', video: '#EF4444' };

    folderView.style.display = 'none';
    fileListView.style.display = '';
    document.getElementById('fileListTitle').innerHTML = '<i data-lucide="' + typeIcons[type] + '" style="width:14px;height:14px;color:' + typeColors[type] + '"></i> ' + typeNames[type];

    items.forEach(function(item) {
        item.style.display = item.getAttribute('data-type') === type ? '' : 'none';
    });

    if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
}

function showFolderView() {
    var folderView = document.getElementById('folderView');
    var fileListView = document.getElementById('fileListView');
    var items = document.querySelectorAll('#libFileList .list-item');
    folderView.style.display = '';
    fileListView.style.display = 'none';
    items.forEach(function(item) { item.style.display = ''; });
}

// === DYNAMIC LIBRARY GRID ===
var LIBRARY_DATA = [];
var currentLibrarySubjectId = 0;
var currentLibrarySubjectKey = '';

function loadLibraryGrid() {
    var grid = document.getElementById('myMaterialsGrid');
    if (!grid) return;
    var userClass = USER_CLASS || 'Class 8';
    fetch('/api/index.php?action=library&class=' + encodeURIComponent(userClass))
        .then(function(r) { return r.json(); })
        .then(function(items) {
            LIBRARY_DATA = items || [];
            var subjects = {};
            items.forEach(function(item) {
                var sid = item.subject_id;
                if (!subjects[sid]) subjects[sid] = { count: 0, types: {} };
                subjects[sid].count++;
                var t = item.type || 'textbook';
                subjects[sid].types[t] = (subjects[sid].types[t] || 0) + 1;
            });
            var html = '';
            var allowed = CLASS_SUBJECTS[USER_CLASS] || CLASS_SUBJECTS['Class 8'];
            Object.keys(subjects).forEach(function(sid) {
                var info = _subjectsCache ? _subjectsCache[sid] : null;
                if (!info) return;
                var key = (info.name || '').toLowerCase().replace(/\s+/g, '');
                if (allowed.indexOf(key) === -1) return;
                var s = subjects[sid];
                var pct = Math.min(s.count * 25, 100);
                html += '<div class="material-card" onclick="openSubjectFolderDynamic(' + sid + ',\'' + key + '\')">' +
                    '<div class="material-icon ' + key + '"><i data-lucide="' + (info.icon || 'book-open') + '" style="width:18px;height:18px"></i></div>' +
                    '<h5>' + info.name + '</h5>' +
                    '<div class="material-bar"><div class="material-bar-fill progress-green" style="width:' + pct + '%"></div></div>' +
                    '</div>';
            });
            if (!html) html = '<p style="grid-column:1/-1;text-align:center;padding:20px;font-size:12px;color:var(--text3)">No content available for your class yet.</p>';
            grid.innerHTML = html;
            if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
        })
        .catch(function() {
            LIBRARY_DATA = [];
        });
}

// === FILTER SUBJECT FILES ===
function filterSubjectFiles(btn, type) {
    btn.parentElement.querySelectorAll('.filter-chip').forEach(function(c) { c.classList.remove('active'); });
    btn.classList.add('active');
    var items = document.querySelectorAll('#libFileList .list-item');
    items.forEach(function(item) {
        if (type === 'all') {
            item.style.display = '';
        } else {
            item.style.display = item.getAttribute('data-type') === type ? '' : 'none';
        }
    });
}

// === FILTER MESSAGES ===
function filterMessages() {
    var input = document.getElementById('msgSearchInput');
    if (!input) return;
    var filter = input.value.toLowerCase();
    var items = document.querySelectorAll('#messagesList .list-item');
    items.forEach(function(item) {
        var text = item.textContent.toLowerCase();
        item.style.display = text.indexOf(filter) > -1 ? '' : 'none';
    });
}

// === FILTER REPORTS ===
function filterReports() {
    var input = document.getElementById('reportsSearchInput');
    if (!input) return;
    var filter = input.value.toLowerCase();
    var cards = document.querySelectorAll('#screen-reports .hw-card');
    cards.forEach(function(card) {
        var text = card.textContent.toLowerCase();
        card.style.display = text.indexOf(filter) > -1 ? '' : 'none';
    });
}

// === BIND ALL EVENTS ===
function bindEvents() {
    // Event delegation for all clicks
    document.addEventListener('click', function(e) {
        var t = e.target;

        // AI Hero send button (home screen)
        if (t.closest('.send-btn')) {
            var input = document.querySelector('.ai-hero-input');
            if (input && input.value.trim()) {
                var msg = input.value.trim();
                input.value = '';
                _previousScreen = 'screen-home';
                showScreen('screen-chat');
                addChatMessage(msg, 'user');
                setTimeout(function() { addChatMessage(getAIResponse(msg), 'ai'); }, 1000);
            }
            return;
        }

        // Upload chips (home screen AI hero)
        if (t.closest('.upload-chip')) {
            showToast('File picker coming soon!', 'info');
            return;
        }

        // Chat send (messenger style)
        if (t.closest('.messenger-send')) {
            var messengerInput = document.querySelector('.messenger-input');
            if (messengerInput && messengerInput.value.trim()) {
                addChatMessage(messengerInput.value, 'user');
                var userMsg = messengerInput.value;
                messengerInput.value = '';
                setTimeout(function() { addChatMessage(getAIResponse(userMsg), 'ai'); }, 1000);
            }
            return;
        }

        // Chat send (legacy)
        if (t.closest('.chat-send')) {
            var chatInput = document.querySelector('.chat-input');
            if (chatInput && chatInput.value.trim()) {
                addChatMessage(chatInput.value, 'user');
                var userMsg = chatInput.value;
                chatInput.value = '';
                setTimeout(function() { addChatMessage(getAIResponse(userMsg), 'ai'); }, 1000);
            }
            return;
        }

        // Chat action buttons (image, mic)
        if (t.closest('.chat-action-btn')) {
            showToast('Feature coming soon!', 'info');
            return;
        }

        // Subscribe buttons on tutors
        if (t.closest('.tt-pop-subscribe')) {
            e.stopPropagation();
            showToast('Subscribed!', 'success');
            return;
        }

        // Activity item without onclick
        if (t.closest('.activity-item') && !t.closest('.activity-item').getAttribute('onclick')) {
            showToast('Opening...', 'info');
            return;
        }

        // Live class controls
        if (t.closest('.live-control-btn')) {
            var btn = t.closest('.live-control-btn');
            if (btn.classList.contains('mute')) {
                btn.classList.toggle('active');
                showToast(btn.classList.contains('active') ? 'Microphone muted' : 'Microphone unmuted', 'info');
            } else if (btn.classList.contains('video')) {
                btn.classList.toggle('active');
                showToast(btn.classList.contains('active') ? 'Camera off' : 'Camera on', 'info');
            } else if (btn.classList.contains('hand')) {
                btn.classList.toggle('active');
                showToast(btn.classList.contains('active') ? 'Hand raised' : 'Hand lowered', 'info');
            }
            return;
        }

        // Content library items
        if (t.closest('#screen-content-library .list-item')) {
            showToast('Opening content...', 'info');
            return;
        }

        // Message items
        if (t.closest('#screen-messages .msg-item, #screen-messages .list-item')) {
            showToast('Opening conversation...', 'info');
            return;
        }

        // Notification items
        if (t.closest('.notif-item')) {
            var notif = t.closest('.notif-item');
            notif.style.opacity = '0.5';
            showToast('Notification opened', 'info');
            return;
        }

        // Calendar day cells
        if (t.closest('.cal-day') && !t.closest('.cal-day').classList.contains('empty')) {
            document.querySelectorAll('.cal-day').forEach(function(d) { d.classList.remove('selected'); });
            t.closest('.cal-day').classList.add('selected');
            showToast('Viewing events for this day', 'info');
            return;
        }

        // AI edit/delete buttons
        if (t.closest('.ai-edit-btn')) {
            showToast('Edit mode coming soon!', 'info');
            return;
        }
        if (t.closest('.ai-del-btn')) {
            t.closest('.ai-question-card').remove();
            showToast('Question removed', 'success');
            return;
        }

        // Filter chips
        if (t.closest('.filter-chip')) {
            var parent = t.closest('.filter-chip').parentElement;
            parent.querySelectorAll('.filter-chip').forEach(function(c) { c.classList.remove('active'); });
            t.closest('.filter-chip').classList.add('active');
            return;
        }

        // Day filter chips (class schedule)
        if (t.closest('#screen-class-schedule .filter-chip')) {
            var dayParent = t.closest('.filter-chip').parentElement;
            dayParent.querySelectorAll('.filter-chip').forEach(function(c) { c.classList.remove('active'); });
            t.closest('.filter-chip').classList.add('active');
            showToast('Showing ' + t.closest('.filter-chip').textContent + ' classes', 'info');
            return;
        }

        // Calendar nav arrows
        if (t.closest('.cal-prev')) {
            showToast('Previous month', 'info');
            return;
        }
        if (t.closest('.cal-next')) {
            showToast('Next month', 'info');
            return;
        }

        // Announcement edit/delete
        if (t.closest('.ann-edit')) {
            showToast('Opening editor...', 'info');
            return;
        }
        if (t.closest('.ann-delete')) {
            if (confirm('Delete this announcement?')) {
                t.closest('.list-item, .hw-item').remove();
                showToast('Announcement deleted', 'success');
            }
            return;
        }

        // Mark all notifications read
        if (t.closest('.mark-all-read')) {
            document.querySelectorAll('.notif-item').forEach(function(n) { n.style.opacity = '0.5'; });
            showToast('All marked as read', 'success');
            return;
        }

        // Payment method selection
        if (t.closest('.payment-method')) {
            selectPayment(t.closest('.payment-method'));
            return;
        }

        // Touch ripple
        var rippleTarget = t.closest('.btn-primary, .quick-card-btn, .landing-cta');
        if (rippleTarget) {
            var ripple = document.createElement('span');
            ripple.style.cssText = 'position:absolute;border-radius:50%;background:rgba(255,255,255,0.3);width:0;height:0;pointer-events:none;transform:translate(-50%,-50%)';
            var rect = rippleTarget.getBoundingClientRect();
            ripple.style.left = (e.clientX - rect.left) + 'px';
            ripple.style.top = (e.clientY - rect.top) + 'px';
            rippleTarget.style.position = 'relative';
            rippleTarget.style.overflow = 'hidden';
            rippleTarget.appendChild(ripple);
            setTimeout(function() {
                ripple.style.width = '200px'; ripple.style.height = '200px';
                ripple.style.transition = 'width 0.4s, height 0.4s, opacity 0.4s';
                ripple.style.opacity = '0';
                setTimeout(function() { ripple.remove(); }, 400);
            }, 10);
        }
    });

    // Enter key on chat input
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && (e.target.classList.contains('chat-input') || e.target.classList.contains('messenger-input'))) {
            e.preventDefault();
            var sendBtn = document.querySelector('.messenger-send') || document.querySelector('.chat-send');
            if (sendBtn) sendBtn.click();
        }
        if (e.key === 'Enter' && e.target.classList.contains('ai-hero-input')) {
            e.preventDefault();
            var heroBtn = document.querySelector('.send-btn');
            if (heroBtn) heroBtn.click();
        }
    });

    // Search input
    document.addEventListener('input', function(e) {
        if (!e.target.classList.contains('search-big')) return;
        var query = e.target.value.toLowerCase();
        var container = document.querySelector('#screen-search .main-content');
        if (!container || query.length < 2) return;
        var filtered = searchData.filter(function(item) {
            return item.title.toLowerCase().indexOf(query) !== -1 || item.type.toLowerCase().indexOf(query) !== -1;
        });
        var html = '<div class="section-header"><h2 class="section-title">Results</h2></div>';
        if (filtered.length === 0) {
            html += '<div style="text-align:center;padding:24px;color:var(--text3)"><p style="font-size:12px">No results</p></div>';
        } else {
            filtered.forEach(function(item) {
                html += '<div class="activity-item" style="margin-bottom:8px" onclick="showScreen(\'' + item.screen + '\')"><div class="act-icon ' + item.color + '"><i data-lucide="' + item.icon + '" style="width:14px;height:14px"></i></div><div class="act-info"><h5>' + item.title + '</h5><p>' + item.type + '</p></div></div>';
            });
        }
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}

// === LOAD CHAT AI SETTINGS FROM ADMIN ===
var _chatSettingsLoaded = false;
function loadChatSettings() {
    if (_chatSettingsLoaded) return;
    api('settings').then(function(s) {
        _chatSettingsLoaded = true;
        if (!s) return;
        var name = s.ai_name || 'Snorii AI';
        var greeting = s.ai_greeting || 'Hello! I\'m <strong>Snorii AI</strong>. Ask me anything about Math, Science, English and more!';
        var subtitle = s.ai_subtitle || 'Always here to help';
        var prompts = (s.ai_suggested_prompts || 'Explain photosynthesis|Solve quadratic equations|Summarize Chapter 3').split('|');
        var enabled = s.ai_enabled !== '0';
        var nameEl = document.getElementById('chatBotName');
        var subEl = document.getElementById('chatBotSubtitle');
        var greetEl = document.getElementById('aiGreeting');
        var promptsEl = document.getElementById('chatSuggestedPrompts');
        var inputEl = document.querySelector('.messenger-input');
        if (nameEl) nameEl.textContent = name;
        if (subEl) subEl.textContent = subtitle;
        if (greetEl) greetEl.innerHTML = greeting;
        if (inputEl) inputEl.placeholder = 'Message ' + name + '...';
        if (promptsEl) {
            promptsEl.innerHTML = '';
            prompts.forEach(function(p) {
                p = p.trim();
                if (!p) return;
                var btn = document.createElement('button');
                btn.className = 'prompt-chip';
                btn.textContent = p;
                btn.onclick = function() { sendPrompt(btn); };
                promptsEl.appendChild(btn);
            });
        }
        if (!enabled) {
            var container = document.querySelector('#screen-chat .chat-messages');
            if (container) {
                var disabledMsg = document.createElement('div');
                disabledMsg.className = 'msg ai';
                disabledMsg.innerHTML = '<div class="msg-bubble" style="background:#FEF2F2;color:#B91C1C;text-align:center">AI is currently disabled by admin.</div>';
                container.appendChild(disabledMsg);
            }
            var inputBar = document.querySelector('#screen-chat .messenger-input-bar');
            if (inputBar) inputBar.style.opacity = '0.5';
            var sendBtn = document.querySelector('#screen-chat .messenger-send');
            if (sendBtn) sendBtn.onclick = function() { showToast('AI is disabled by admin', 'error'); };
        }
    }).catch(function(){});
}

// === AI TUTOR ===
function getAIResponse(q) {
    var lower = q.toLowerCase();
    if (lower.indexOf('quadrilateral') !== -1) return 'A <strong>quadrilateral</strong> has 4 sides. Sum of angles = 360. Types: square, rectangle, parallelogram, trapezium.';
    if (lower.indexOf('fraction') !== -1) return '<strong>Fraction</strong> = numerator/denominator. 3/4 means 3 parts of 4. Add fractions: find LCM first.';
    if (lower.indexOf('algebra') !== -1 || lower.indexOf('equation') !== -1) return '<strong>Algebra</strong>: Solve 2x+5=11. Subtract 5: 2x=6. Divide by 2: x=3.';
    if (lower.indexOf('triangle') !== -1) return '<strong>Triangle</strong>: 3 sides, angles sum to 180. Area = (1/2) x base x height.';
    if (lower.indexOf('percentage') !== -1) return '<strong>Percentage</strong> = (Part/Total) x 100. 50% = half, 25% = quarter.';
    if (lower.indexOf('hello') !== -1 || lower.indexOf('hi') !== -1) return 'Hello! Ask me about Math, Science, or English!';
    if (lower.indexOf('thank') !== -1) return 'You are welcome! Feel free to ask more.';
    return 'Great question! Could you specify the subject and topic for a more detailed answer?';
}

function addChatMessage(text, type) {
    var container = document.querySelector('.chat-messages');
    if (!container) return;
    var snoriiSvg = '<svg viewBox="0 0 40 40" width="20" height="20"><ellipse cx="20" cy="18" rx="16" ry="14" fill="#F0F9FF"/><rect x="10" y="12" width="20" height="16" rx="6" fill="#0F172A"/><circle cx="15" cy="20" r="3" fill="#22D3EE"/><circle cx="25" cy="20" r="3" fill="#22D3EE"/><path d="M16 25 Q20 28 24 25" stroke="#22D3EE" stroke-width="1.5" stroke-linecap="round" fill="none"/></svg>';
    var avatar = type === 'ai' ? '<div class="msg-avatar snorii-avatar-sm">' + snoriiSvg + '</div>' : '<div class="msg-avatar">' + (USER_NAME ? USER_NAME[0] : 'A') + '</div>';
    var msg = document.createElement('div');
    msg.className = 'msg ' + type;
    msg.innerHTML = avatar + '<div class="msg-bubble">' + text + '</div>';
    container.appendChild(msg);
    var prompts = container.querySelector('.suggested-prompts');
    if (prompts) prompts.remove();
    container.scrollTop = container.scrollHeight;
}

function handleChatFile(input, type) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var name = file.name;
    if (type === 'image' && file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var imageData = e.target.result;
            addChatMessage('<img src="' + imageData + '" class="chat-img" alt="' + name + '">', 'user');
            input.value = '';
            var thinking = document.createElement('div');
            thinking.className = 'msg ai';
            thinking.innerHTML = '<div class="msg-avatar snorii-avatar-sm"><svg viewBox="0 0 40 40" width="20" height="20"><ellipse cx="20" cy="18" rx="16" ry="14" fill="#F0F9FF"/><rect x="10" y="12" width="20" height="16" rx="6" fill="#0F172A"/><circle cx="15" cy="20" r="3" fill="#22D3EE"/><circle cx="25" cy="20" r="3" fill="#22D3EE"/><path d="M16 25 Q20 28 24 25" stroke="#22D3EE" stroke-width="1.5" stroke-linecap="round" fill="none"/></svg></div><div class="msg-bubble ai-thinking"><span class="thinking-dots"><span></span><span></span><span></span></span> Analyzing image...</div>';
            var container = document.querySelector('#screen-chat .chat-messages');
            container.appendChild(thinking);
            container.scrollTop = container.scrollHeight;
            var sysPrompt = 'You are a helpful school tutor for Class 8 students. Analyze this image clearly and simply. If it contains a math problem, solve it step by step. If it contains text, read and summarize it. If it is a diagram or science image, explain it educationally. Keep answers concise and student-friendly.';
            tryProviderChain(imageData, name, sysPrompt, thinking, container);
        };
        reader.readAsDataURL(file);
    } else {
        var tag = '<div class="chat-file-card"><i data-lucide="file-text"></i><div><strong>' + name + '</strong><br><span>' + (file.size / 1024).toFixed(1) + ' KB</span></div></div>';
        addChatMessage(tag, 'user');
        input.value = '';
        setTimeout(function() {
            addChatMessage('I received your file <strong>' + name + '</strong>. What would you like me to do with it?', 'ai');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }, 1200);
    }
}

function tryProviderChain(imageData, name, sysPrompt, thinking, container) {
    apiRaw('ai_analyze_image', { image: imageData, name: name, system_prompt: sysPrompt }).then(function(res) {
        if (res && res.analysis) {
            thinking.remove();
            var provLabel = res.configured ? ' <span style="font-size:10px;opacity:0.5">via ' + (res.provider || 'AI') + '</span>' : '';
            addChatMessage(res.analysis + provLabel, 'ai');
            return;
        }
        if (res && res.needs_fallback) {
            return tryMoondreamFallback(imageData, name, sysPrompt, thinking, container);
        }
        thinking.remove();
        addChatMessage('I received your image <strong>' + name + '</strong>, but could not analyze it. Please try again.', 'ai');
    }).catch(function() {
        tryMoondreamFallback(imageData, name, sysPrompt, thinking, container);
    });
}

function tryMoondreamFallback(imageData, name, sysPrompt, thinking, container) {
    apiRaw('ai_analyze_image', { image: imageData, name: name, system_prompt: sysPrompt, provider_override: 'moondream' }).then(function(res) {
        thinking.remove();
        if (res && res.analysis) {
            addChatMessage(res.analysis + ' <span style="font-size:10px;opacity:0.5">via Moondream</span>', 'ai');
        } else {
            addChatMessage('Image analysis requires an API key. Please ask the admin to configure one in <strong>Admin Panel → AI Settings</strong>.<br><br>Supported providers: OpenAI, Gemini, Claude, OpenRouter, Moondream (free $5/mo at <a href="https://console.moondream.ai" target="_blank">console.moondream.ai</a>)', 'ai');
        }
    }).catch(function() {
        thinking.remove();
        addChatMessage('Image analysis unavailable. Please configure an API key in <strong>Admin Panel → AI Settings</strong>.', 'ai');
    });
}

function sendPrompt(btn) {
    var text = btn.textContent;
    addChatMessage(text, 'user');
    setTimeout(function() { addChatMessage(getAIResponse(text), 'ai'); }, 800);
}

// === CHAT MENU FEATURES ===
var chatThemes = ['', 'chat-theme-sunset', 'chat-theme-ocean', 'chat-theme-forest', 'chat-theme-lavender', 'chat-theme-night'];

function toggleChatMenu() {
    var dd = document.getElementById('chatDropdown');
    if (!dd) return;
    dd.classList.toggle('active');
    if (dd.classList.contains('active')) {
        setTimeout(function() {
            document.addEventListener('click', closeChatMenuOutside);
        }, 10);
    }
}

function closeChatMenuOutside(e) {
    var dd = document.getElementById('chatDropdown');
    var btn = document.getElementById('chatMenuBtn');
    if (dd && !dd.contains(e.target) && btn && !btn.contains(e.target)) {
        dd.classList.remove('active');
        document.removeEventListener('click', closeChatMenuOutside);
    }
}

function searchInChat() {
    var dd = document.getElementById('chatDropdown');
    if (dd) dd.classList.remove('active');
    var bar = document.getElementById('chatSearchBar');
    if (bar) {
        bar.classList.toggle('active');
        if (bar.classList.contains('active')) {
            document.getElementById('chatSearchInput').focus();
        }
    }
}

function filterChatMessages(query) {
    var msgs = document.querySelectorAll('#screen-chat .chat-messages .msg');
    var q = query.toLowerCase();
    msgs.forEach(function(m) {
        if (!q) { m.style.display = ''; return; }
        var text = m.textContent.toLowerCase();
        m.style.display = text.indexOf(q) !== -1 ? '' : 'none';
        if (text.indexOf(q) !== -1 && q) m.classList.add('highlight');
        else m.classList.remove('highlight');
    });
}

function closeChatSearch() {
    var bar = document.getElementById('chatSearchBar');
    if (bar) bar.classList.remove('active');
    var input = document.getElementById('chatSearchInput');
    if (input) input.value = '';
    filterChatMessages('');
}

function openThemePicker() {
    var dd = document.getElementById('chatDropdown');
    if (dd) dd.classList.remove('active');
    var overlay = document.getElementById('themePickerOverlay');
    if (overlay) overlay.classList.add('active');
}

function closeThemePicker() {
    var overlay = document.getElementById('themePickerOverlay');
    if (overlay) overlay.classList.remove('active');
}

function applyChatTheme(el) {
    var theme = el.getAttribute('data-theme');
    var chatScreen = document.getElementById('screen-chat');
    if (!chatScreen) return;
    chatThemes.forEach(function(t) { if (t) { chatScreen.classList.remove(t); document.body.classList.remove(t); } });
    if (theme) { chatScreen.classList.add(theme); document.body.classList.add(theme); }
    document.querySelectorAll('.theme-thumb').forEach(function(t) { t.classList.remove('active'); });
    el.classList.add('active');
    setTimeout(function() { closeThemePicker(); }, 200);
}

var searchData = [
    {title:'Quadratic Equations',type:'Books',icon:'book-open',color:'green',screen:'screen-subject'},
    {title:'English Grammar',type:'Books',icon:'book-open',color:'green',screen:'screen-subject'},
    {title:'Photosynthesis',type:'Books',icon:'book-open',color:'green',screen:'screen-subject'},
    {title:'Mr. Rahim - Math',type:'Teachers',icon:'user',color:'purple',screen:'screen-teacher-profile'},
    {title:'Ms. Sarah - English',type:'Teachers',icon:'user',color:'purple',screen:'screen-teacher-profile'},
    {title:'Algebra Formulas',type:'Notes',icon:'file-text',color:'orange',screen:'screen-reader'},
    {title:'Geometry Notes',type:'Notes',icon:'file-text',color:'orange',screen:'screen-reader'}
];

// === EXAM TIMER ===
var examTimerInterval = null;
function startExamTimer() {
    var timerEl = document.querySelector('#screen-exam-interface span[style*="background:#FEE2E2"]');
    if (!timerEl) return;
    var parts = timerEl.textContent.replace(/[^0-9:]/g, '').split(':');
    var total = parseInt(parts[0]) * 60 + parseInt(parts[1]);
    if (examTimerInterval) clearInterval(examTimerInterval);
    examTimerInterval = setInterval(function() {
        total--;
        if (total <= 0) { clearInterval(examTimerInterval); logActivity('quiz', 'Completed exam (time up)'); showToast('Time up! Submitting...', 'error'); showScreen('screen-exam-result'); return; }
        var m = Math.floor(total / 60), s = total % 60;
        timerEl.innerHTML = '<i data-lucide="clock"></i> ' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }, 1000);
}

// === EXAM QUESTION NAVIGATION ===
var currentQuestion = 1;
var totalQuestions = 10;
function examNav(direction) {
    currentQuestion += direction;
    if (currentQuestion < 1) currentQuestion = 1;
    if (currentQuestion > totalQuestions) currentQuestion = totalQuestions;
    showToast('Question ' + currentQuestion + ' of ' + totalQuestions, 'info');
}

// === QUESTION BUILDER (Teacher Create Exam) ===
var questionCount = 2;
function addQuestion() {
    questionCount++;
    var container = document.getElementById('source-manual') || document.querySelector('.source-section');
    if (!container) return;
    var div = document.createElement('div');
    div.className = 'question-builder';
    div.id = 'q' + questionCount;
    div.innerHTML = '<div class="qb-header"><span class="qb-num">Q' + questionCount + '</span>' +
        '<select class="qb-type"><option>MCQ</option><option>Written</option><option>CQ</option></select>' +
        '<input type="number" class="qb-marks" placeholder="Marks" value="2">' +
        '<button class="qb-delete" onclick="this.closest(\'.question-builder\').remove()"><i data-lucide="trash-2"></i></button></div>' +
        '<textarea class="input-field" placeholder="Type your question here..." style="min-height:60px;margin-bottom:8px"></textarea>' +
        '<div class="option-row"><span class="option-label">A</span><input type="text" class="input-field" placeholder="Option A"></div>' +
        '<div class="option-row"><span class="option-label">B</span><input type="text" class="input-field" placeholder="Option B"></div>' +
        '<div class="option-row"><span class="option-label">C</span><input type="text" class="input-field" placeholder="Option C"></div>' +
        '<div class="option-row"><span class="option-label">D</span><input type="text" class="input-field" placeholder="Option D"></div>';
    var addBtn = container.querySelector('.btn-outline, .btn-primary');
    if (addBtn) container.insertBefore(div, addBtn);
    else container.appendChild(div);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    showToast('Question ' + questionCount + ' added', 'success');
}

// === AI QUESTION GENERATOR ===
var selectedOptCount = 4;
function selectOptCount(btn) {
    document.querySelectorAll('.opt-count-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    selectedOptCount = parseInt(btn.getAttribute('data-count'));
}
function getOptLetter(i) { return String.fromCharCode(65 + i); }

function generateAIQuestions() {
    var numInput = document.getElementById('ai-num-questions');
    var n = Math.min(Math.max(parseInt(numInput.value) || 5, 1), 50);
    var result = document.getElementById('ai-result');
    result.style.display = 'block';
    result.innerHTML = '<div style="text-align:center;padding:30px"><p style="font-size:12px;color:var(--text3)">Generating ' + n + ' questions...</p></div>';
    setTimeout(function() {
        var samples = [
            {q:'What is sqrt(144)?',a:0},{q:'Solve: x^2-5x+6=0',a:1},
            {q:'What is 15% of 200?',a:2},{q:'Simplify: 3x+5x-2x',a:1}
        ];
        var answers = ['12','14','10','16'];
        var html = '<div class="ai-result-header"><h4>' + n + ' Questions Generated</h4>' +
            '<button class="ai-regen" onclick="generateAIQuestions()"><i data-lucide="refresh-cw"></i></button></div>';
        for (var q = 1; q <= n; q++) {
            var d = samples[(q-1) % samples.length];
            html += '<div class="ai-question-card"><div class="ai-q-header"><span class="ai-q-num">' + q + '</span><span class="badge purple">MCQ</span></div>' +
                '<p style="font-size:12px;margin-bottom:8px">' + d.q + '</p>' +
                '<p class="ai-answer-label">Correct: <span class="ai-correct-text">Option ' + getOptLetter(d.a) + ' (' + answers[d.a] + ')</span></p>' +
                '<div class="ai-q-actions"><button class="ai-edit-btn"><i data-lucide="pencil"></i> Edit</button>' +
                '<button class="ai-del-btn" onclick="this.closest(\'.ai-question-card\').remove();showToast(\'Removed\',\'success\')"><i data-lucide="trash-2"></i></button></div></div>';
        }
        result.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }, 2000);
}

function selectAIOption(el, containerId) {
    var c = document.getElementById(containerId);
    if (!c) return;
    c.querySelectorAll('.ai-opt').forEach(function(o) { o.classList.remove('selected'); });
    el.classList.add('selected');
}

function selectContentType(btn) {
    btn.parentElement.querySelectorAll('.opt-count-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
}

function selectSource(btn, type) {
    document.querySelectorAll('.source-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var m = document.getElementById('source-manual');
    var a = document.getElementById('source-ai');
    if (m) m.style.display = type === 'manual' ? 'block' : 'none';
    if (a) a.style.display = type === 'ai' ? 'block' : 'none';
}

function selectCategory(el) {
    document.querySelectorAll('.category-card').forEach(function(c) { c.classList.remove('active'); });
    el.classList.add('active');
}

function selectPayment(el) {
    document.querySelectorAll('.payment-method').forEach(function(m) {
        m.classList.remove('selected');
        m.querySelector('.payment-check').innerHTML = '';
    });
    el.classList.add('selected');
    el.querySelector('.payment-check').innerHTML = '<i data-lucide="check"></i>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// === CARD ANIMATION ===
function animateCards() {
    document.querySelectorAll('.hw-card, .exam-card, .notif-item, .activity-item, .list-item').forEach(function(card, i) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(12px)';
        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        setTimeout(function() { card.style.opacity = '1'; card.style.transform = 'translateY(0)'; }, 50 + i * 40);
    });
}

var origShowScreen = showScreen;
showScreen = function(id) { origShowScreen(id); setTimeout(animateCards, 80); };

// === PROGRESS BAR ANIMATION ===
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.material-bar-fill, .xp-bar-fill').forEach(function(bar) {
        var w = bar.style.width;
        bar.style.width = '0';
        setTimeout(function() { bar.style.width = w; }, 100);
    });
});

// === PULL TO REFRESH ===
var touchStartY = 0;
document.addEventListener('touchstart', function(e) { touchStartY = e.touches[0].clientY; }, {passive:true});
document.addEventListener('touchmove', function(e) {
    if (e.touches[0].clientY - touchStartY > 60 && window.scrollY === 0) {
        var ind = document.querySelector('.pull-indicator');
        if (!ind) { ind = document.createElement('div'); ind.className='pull-indicator'; ind.textContent='Pull to refresh...'; document.body.prepend(ind); }
        ind.style.display = 'block';
    }
}, {passive:true});
document.addEventListener('touchend', function() {
    var ind = document.querySelector('.pull-indicator');
    if (ind) ind.style.display = 'none';
}, {passive:true});

// === TEACHER UPLOAD CONTENT ===
function teacherUploadContent() {
    var titleInput = document.querySelector('#screen-upload-content input[placeholder*="Chapter"]');
    var descInput = document.querySelector('#screen-upload-content textarea');
    var classSelect = document.querySelector('#screen-upload-content select');
    var subjectSelect = document.querySelectorAll('#screen-upload-content select')[1];
    var typeBtn = document.querySelector('#screen-upload-content .opt-count-btn.active');
    if (!titleInput || !titleInput.value.trim()) { showToast('Please enter a title', 'error'); return; }
    var title = titleInput.value.trim();
    var desc = descInput ? descInput.value.trim() : '';
    var cls = classSelect ? classSelect.value : USER_CLASS;
    var subjectName = subjectSelect ? subjectSelect.value : '';
    var typeText = typeBtn ? typeBtn.textContent.trim().toLowerCase() : 'books';
    var typeMap = { 'books': 'textbook', 'notes': 'notes', 'video': 'video' };
    var type = typeMap[typeText] || 'textbook';
    var subjectIdMap = { 'Mathematics': 1, 'English': 2, 'Science': 3, 'Physics': 5, 'Chemistry': 6 };
    var subjectId = subjectIdMap[subjectName] || 1;
    fetch('/api/index.php?action=library_add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: title, subject_id: subjectId, class: cls, type: type, description: desc, file_url: '', cover_url: '' })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            showToast('Content uploaded successfully!', 'success');
            titleInput.value = '';
            if (descInput) descInput.value = '';
            showScreen('screen-teacher-dash');
        } else {
            showToast('Upload failed', 'error');
        }
    })
    .catch(function() { showToast('Upload failed', 'error'); });
}

// === TEACHER CONTENT LIBRARY (DYNAMIC) ===
function loadTeacherContentLibrary() {
    var container = document.getElementById('teacherContentList');
    if (!container) return;
    fetch('/api/index.php?action=library')
        .then(function(r) { return r.json(); })
        .then(function(items) {
            var myUploads = [];
            var adminUploads = [];
            (items || []).forEach(function(item) {
                if (item.uploader_type === 'teacher') myUploads.push(item);
                else adminUploads.push(item);
            });
            var statsMy = document.getElementById('tcStatMy');
            var statsAdmin = document.getElementById('tcStatAdmin');
            var statsTotal = document.getElementById('tcStatTotal');
            if (statsMy) statsMy.textContent = myUploads.length;
            if (statsAdmin) statsAdmin.textContent = adminUploads.length;
            if (statsTotal) statsTotal.textContent = items.length;
            var html = '';
            var typeIconMap = { textbook: 'book-open', guide: 'book-open', reference: 'book-open', notes: 'file-text', video: 'video' };
            var typeColorMap = { textbook: 'green', guide: 'green', reference: 'blue', notes: 'purple', video: 'red' };
            myUploads.forEach(function(item) {
                var icon = typeIconMap[item.type] || 'file';
                var color = typeColorMap[item.type] || 'green';
                html += '<div class="list-item" onclick="showToast(\'Opening content...\',\'info\')">' +
                    '<div class="icon-box ' + color + ' sm"><i data-lucide="' + icon + '" class="icon-sm"></i></div>' +
                    '<div class="list-item-content"><h5>' + (item.title || 'Untitled') + '</h5>' +
                    '<p>' + (item.type || 'file') + ' - ' + (item.class || '') + '</p></div>' +
                    '<span class="list-item-badge" style="background:#DCFCE7;color:#16A34A">Published</span></div>';
            });
            if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No uploads yet.</p>';
            container.innerHTML = html;
            if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
        })
        .catch(function() {
            container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load content.</p>';
        });
}

// === PHASE 1: DYNAMIC LOADING FUNCTIONS ===

// Helper: fetch JSON
function api(action, params) {
    var url = '/api/index.php?action=' + action;
    if (params) { Object.keys(params).forEach(function(k) { url += '&' + k + '=' + encodeURIComponent(params[k]); }); }
    return fetch(url).then(function(r) { return r.json(); });
}

function apiRaw(action, data) {
    return fetch('/api/index.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); });
}

function logActivity(type, details) {
    fetch('/api/index.php?action=log_activity', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: type, details: details }),
        credentials: 'same-origin'
    }).catch(function() {});
}

// Helper: get subject name by id
var _subjectsCache = null;
function _loadSubjectsCache() {
    if (_subjectsCache) return Promise.resolve(_subjectsCache);
    return api('subjects').then(function(items) {
        _subjectsCache = {};
        (items || []).forEach(function(s) { _subjectsCache[s.id] = s; });
        return _subjectsCache;
    }).catch(function() { _subjectsCache = {}; return _subjectsCache; });
}
function getSubjectName(id) {
    if (_subjectsCache && _subjectsCache[id]) return _subjectsCache[id].name;
    return 'General';
}
function getSubjectIcon(id) {
    if (_subjectsCache && _subjectsCache[id]) return _subjectsCache[id].icon || 'book-open';
    return 'book-open';
}
function getSubjectColor(id) {
    if (_subjectsCache && _subjectsCache[id]) return _subjectsCache[id].color || '#6366F1';
    return '#6366F1';
}

// Helper: format date
function formatDate(d) {
    if (!d) return '';
    var dt = new Date(d);
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return dt.getDate() + ' ' + months[dt.getMonth()] + ', ' + dt.getFullYear();
}

// === STUDENT HOME ===
function loadStudentHome() {
    // Set name
    var nameEl = document.getElementById('homeUserName');
    if (nameEl) nameEl.textContent = USER_NAME.split(' ')[0];
    var avatarEl = document.getElementById('homeUserAvatar');
    if (avatarEl) avatarEl.textContent = (USER_NAME || 'U')[0].toUpperCase();
    var classEl = document.getElementById('homeUserClass');
    if (classEl) classEl.textContent = USER_CLASS || 'Class 8';
    // Premium badge
    var premBadge = document.querySelector('.premium-badge');
    if (premBadge) premBadge.style.display = _isPremium ? '' : 'none';
    var premBanner = document.getElementById('homePremiumBanner');
    if (premBanner) premBanner.style.display = _isPremium ? 'none' : '';

    // Load progress
    api('student_progress').then(function(prog) {
        if (prog && prog.id) {
            var streakEl = document.getElementById('homeStreak');
            var levelEl = document.getElementById('homeLevel');
            var xpEl = document.getElementById('homeXP');
            var xpBar = document.getElementById('homeXPBar');
            if (streakEl) streakEl.textContent = prog.streak || 0;
            if (levelEl) levelEl.textContent = 'Level ' + Math.floor((prog.books_read || 0) / 2 + 1) + ' Learner';
            if (xpEl) xpEl.textContent = ((prog.study_hours || 0) * 50) + ' / 3,000 XP';
            if (xpBar) xpBar.style.width = Math.min(((prog.study_hours || 0) * 50 / 3000) * 100, 100) + '%';
            loadStudentHomeProgress(prog);
        }
    }).catch(function(){});

    // Load notification count
    api('notifications').then(function(nots) {
        var unread = (nots || []).filter(function(n) { return !n.is_read; }).length;
        var badge = document.getElementById('homeNotifBadge');
        if (badge) badge.textContent = unread || 0;
    }).catch(function(){});

    // Load homework count
    api('homework').then(function(hw) {
        var pending = (hw || []).filter(function(h) { return h.status === 'pending'; }).length;
        var el = document.getElementById('homeHWPending');
        if (el) el.textContent = pending + ' pending';
    }).catch(function(){});

    // Load exam count
    api('exams').then(function(exams) {
        var upcoming = (exams || []).filter(function(e) { return e.status === 'upcoming'; }).length;
        var el = document.getElementById('homeExamUpcoming');
        if (el) el.textContent = upcoming + ' upcoming';
    }).catch(function(){});

    // Load live class
    api('live_schedule').then(function(items) {
        var all = items || [];
        var live = all.find(function(c) { return c.status === 'live'; });
        var next = live || all.find(function(c) { return c.status === 'scheduled'; });
        var el = document.getElementById('homeLiveClass');
        var teacherEl = document.getElementById('homeLiveTeacher');
        var avatarEl = document.getElementById('homeMentorAvatar');
        var liveBadge = document.querySelector('.live-badge');
        if (liveBadge) liveBadge.style.display = live ? '' : 'none';
        if (el && next) {
            var subjName = next.subject || getSubjectName(next.subject_id);
            el.textContent = subjName + ' - ' + (next.start_time || next.time || '');
            if (teacherEl && next.teacher_name) teacherEl.textContent = next.teacher_name;
            if (avatarEl && next.teacher_name) avatarEl.textContent = next.teacher_name[0];
        } else if (el) {
            el.textContent = 'No upcoming class';
        }
    }).catch(function(){});

    // Load badges
    loadHomeBadges();

    // Load activity feed
    loadActivityFeed();

    // Load daily goals
    loadDailyGoals();

    // Load mentors count
    api('teachers').then(function(teachers) {
        var el = document.getElementById('homeMentors');
        if (el) el.textContent = (teachers || []).length + ' Mentors Active';
    }).catch(function(){});
}

// === TEACHER DASHBOARD ===
function loadTeacherDashboard() {
    // Set name
    var nameEl = document.getElementById('teacherDashName');
    if (nameEl) nameEl.textContent = USER_NAME.split(' ')[0];
    var avatarEl = document.getElementById('teacherDashAvatar');
    if (avatarEl) avatarEl.textContent = (USER_NAME || 'T')[0].toUpperCase();
    var greetingEl = document.getElementById('teacherGreetingName');
    if (greetingEl) greetingEl.textContent = USER_NAME.split(' ')[0];

    // Load teacher profile
    api('teachers').then(function(teachers) {
        var t = (teachers || []).find(function(te) { return te.name === USER_NAME; });
        if (t) {
            var el = document.getElementById('teacherDashSubject');
            if (el) el.textContent = t.subject || 'Teacher';
        }
    }).catch(function(){});

    // Load stats
    api('teachers').then(function(teachers) {
        var t = (teachers || []).find(function(te) { return te.name === USER_NAME; });
        if (t) {
            var els = { students: document.getElementById('teacherStatStudents'), reports: document.getElementById('teacherStatReports'), pending: document.getElementById('teacherStatPending'), classes: document.getElementById('teacherStatClasses') };
            if (els.students) els.students.textContent = t.total_students || 0;
        }
    }).catch(function(){});

    // Load pending homework submissions count
    api('homework_submissions').then(function(subs) {
        var pending = (subs || []).filter(function(s) { return s.status === 'submitted'; }).length;
        var el = document.getElementById('teacherStatPending');
        if (el) el.textContent = pending || 0;
    }).catch(function(){});

    // Load today's classes
    api('live_classes').then(function(classes) {
        var container = document.getElementById('teacherTodayClasses');
        if (!container) return;
        var html = '';
        (classes || []).forEach(function(c) {
            var subjName = getSubjectName(c.subject_id);
            var color = getSubjectColor(c.subject_id);
            var statusColor = c.status === 'live' ? '#EF4444' : '#10B981';
            var statusText = c.status === 'live' ? 'LIVE' : 'Scheduled';
            html += '<div class="list-item" style="cursor:pointer">' +
                '<div class="icon-box sm" style="background:' + color + '20;color:' + color + '"><i data-lucide="' + getSubjectIcon(c.subject_id) + '" class="icon-sm"></i></div>' +
                '<div class="list-item-content"><h5>' + subjName + '</h5><p>' + (c.start_time || '') + ' - ' + (c.end_time || '') + '</p></div>' +
                '<span class="list-item-badge" style="background:' + statusColor + '20;color:' + statusColor + '">' + statusText + '</span></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:16px;font-size:12px;color:var(--text3)">No classes today</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function(){});

    // Load recent submissions
    api('homework_submissions').then(function(subs) {
        var container = document.getElementById('teacherRecentSubs');
        if (!container) return;
        var recent = (subs || []).slice(0, 3);
        var html = '';
        recent.forEach(function(s) {
            var color = s.status === 'graded' ? '#10B981' : s.status === 'revision' ? '#EF4444' : '#F59E0B';
            var statusText = s.status.charAt(0).toUpperCase() + s.status.slice(1);
            html += '<div class="list-item" style="cursor:pointer">' +
                '<div class="icon-box sm" style="background:' + color + '20;color:' + color + '"><i data-lucide="file-text" class="icon-sm"></i></div>' +
                '<div class="list-item-content"><h5>Submission #' + s.id + '</h5><p>' + statusText + ' - HW #' + s.homework_id + '</p></div>' +
                '<span class="list-item-badge" style="background:' + color + '20;color:' + color + '">' + statusText + '</span></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:16px;font-size:12px;color:var(--text3)">No submissions yet</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function(){});
}

// === NOTIFICATIONS ===
function loadNotifications() {
    var container = document.getElementById('notifList');
    if (!container) return;
    api('notifications').then(function(items) {
        var html = '';
        (items || []).forEach(function(n) {
            var iconMap = { homework: 'file-text', exam: 'clipboard', class: 'tv', broadcast: 'megaphone', system: 'info' };
            var colorMap = { homework: '#F59E0B', exam: '#EF4444', class: '#3B82F6', broadcast: '#8B5CF6', system: '#6366F1' };
            var icon = iconMap[n.type] || 'bell';
            var color = colorMap[n.type] || '#6366F1';
            var readStyle = n.is_read ? 'opacity:0.6' : '';
            html += '<div class="list-item" style="' + readStyle + '">' +
                '<div class="icon-box sm" style="background:' + color + '20;color:' + color + '"><i data-lucide="' + icon + '" class="icon-sm"></i></div>' +
                '<div class="list-item-content"><h5>' + (n.title || '') + '</h5><p>' + (n.message || '') + '</p></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No notifications</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load</p>';
    });
}

// === HOMEWORK LIST ===
function loadHomeworkList() {
    var container = document.getElementById('homeworkList');
    if (!container) return;
    api('homework').then(function(items) {
        var html = '';
        (items || []).forEach(function(h) {
            var subjName = getSubjectName(h.subject_id);
            var color = getSubjectColor(h.subject_id);
            var icon = getSubjectIcon(h.subject_id);
            var statusColor = h.status === 'submitted' ? '#10B981' : '#F59E0B';
            html += '<div class="glass-card" style="padding:14px;cursor:pointer" onclick="loadHomeworkDetail(' + h.id + ');showScreen(\'screen-hw-detail\')">' +
                '<div style="display:flex;align-items:center;gap:10px">' +
                '<div class="icon-box sm" style="background:' + color + '20;color:' + color + '"><i data-lucide="' + icon + '" class="icon-sm"></i></div>' +
                '<div style="flex:1"><h5 style="font-size:13px;font-weight:600">' + (h.title || '') + '</h5>' +
                '<p style="font-size:10px;color:var(--text3)">' + subjName + ' - Due: ' + formatDate(h.due_date) + '</p></div>' +
                '<span class="list-item-badge" style="background:' + statusColor + '20;color:' + statusColor + '">' + (h.status || '') + '</span>' +
                '</div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No homework assigned</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load</p>';
    });
}

// === EXAM LIST ===
function loadExamList() {
    var container = document.getElementById('examList');
    if (!container) return;
    api('exams').then(function(items) {
        var html = '';
        (items || []).forEach(function(e) {
            var subjName = getSubjectName(e.subject_id);
            var color = getSubjectColor(e.subject_id);
            var statusColor = e.status === 'upcoming' ? '#F59E0B' : e.status === 'completed' ? '#10B981' : '#EF4444';
            html += '<div class="glass-card" style="padding:14px;cursor:pointer" onclick="loadExamQuestions(' + e.id + ');showScreen(\'screen-exam-interface\')">' +
                '<div style="display:flex;align-items:center;gap:10px">' +
                '<div class="icon-box sm" style="background:' + color + '20;color:' + color + '"><i data-lucide="clipboard" class="icon-sm"></i></div>' +
                '<div style="flex:1"><h5 style="font-size:13px;font-weight:600">' + (e.title || '') + '</h5>' +
                '<p style="font-size:10px;color:var(--text3)">' + subjName + ' - ' + formatDate(e.exam_date) + ' - ' + (e.total_marks || 0) + ' marks</p></div>' +
                '<span class="list-item-badge" style="background:' + statusColor + '20;color:' + statusColor + '">' + (e.status || '') + '</span>' +
                '</div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No exams scheduled</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load</p>';
    });
}

// === ANNOUNCEMENTS ===
function loadAnnouncements() {
    var container = document.getElementById('announcementList');
    if (!container) return;
    api('announcements').then(function(items) {
        var html = '';
        (items || []).forEach(function(a) {
            var iconMap = { exam: 'clipboard', general: 'info', event: 'calendar', homework: 'file-text' };
            var colorMap = { exam: '#EF4444', general: '#6366F1', event: '#10B981', homework: '#F59E0B' };
            var icon = iconMap[a.category] || 'megaphone';
            var color = colorMap[a.category] || '#6366F1';
            html += '<div class="glass-card" style="padding:14px">' +
                '<div style="display:flex;align-items:start;gap:10px">' +
                '<div class="icon-box sm" style="background:' + color + '20;color:' + color + '"><i data-lucide="' + icon + '" class="icon-sm"></i></div>' +
                '<div style="flex:1"><h5 style="font-size:13px;font-weight:600">' + (a.title || '') + '</h5>' +
                '<p style="font-size:11px;color:var(--text3);margin-top:2px">' + (a.message || '') + '</p>' +
                '<p style="font-size:9px;color:var(--text3);margin-top:4px">' + formatDate(a.created_at) + '</p></div>' +
                '</div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No announcements</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load</p>';
    });
}

// === PACKAGES/PRICING ===
function loadPackages() {
    var container = document.getElementById('pricingList');
    if (!container) return;
    api('packages').then(function(items) {
        var html = '';
        (items || []).forEach(function(p) {
            var features = (p.features || '').split(',');
            var featureHtml = features.map(function(f) {
                return '<div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text2)"><i data-lucide="check" style="width:12px;height:12px;color:#10B981"></i>' + f.trim() + '</div>';
            }).join('');
            var price = (p.price || 0).toLocaleString();
            html += '<div class="glass-card" style="padding:20px;text-align:center;position:relative">' +
                (p.is_active ? '<span style="position:absolute;top:10px;right:10px;background:linear-gradient(135deg,#10B981,#059669);color:white;font-size:9px;padding:3px 8px;border-radius:20px;font-weight:600">Popular</span>' : '') +
                '<h3 style="font-size:16px;font-weight:700;margin-bottom:4px">' + (p.name || '') + '</h3>' +
                '<div style="font-size:28px;font-weight:800;color:var(--primary);margin:12px 0">৳' + price + '</div>' +
                '<p style="font-size:10px;color:var(--text3);margin-bottom:12px">' + (p.duration || 30) + ' days</p>' +
                '<div style="text-align:left;padding:0 8px;margin-bottom:16px">' + featureHtml + '</div>' +
                '<button class="btn-primary glass-btn" style="width:100%" onclick="selectPlatformPackage(' + p.id + ',\'' + (p.name||'').replace(/'/g,"\\'") + '\',' + (p.price||0) + ',' + (p.duration||30) + ')">Choose Plan</button></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No packages available</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load</p>';
    });
}

function loadAdminPackages() {
    var container = document.getElementById('adminPackagesList');
    if (!container) return;
    api('packages').then(function(items) {
        var html = '<button class="btn-primary" style="width:100%;margin-bottom:16px" onclick="showAdminPackageForm()"><i data-lucide="plus" style="width:14px;height:14px"></i> Add New Package</button>';
        (items || []).forEach(function(p) {
            html += '<div class="glass-card" style="padding:16px;margin-bottom:10px">' +
                '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">' +
                '<div><div style="font-size:14px;font-weight:700">' + (p.name || '') + '</div>' +
                '<div style="font-size:20px;font-weight:800;color:var(--primary);margin-top:4px">৳' + (p.price || 0).toLocaleString() + '<span style="font-size:11px;font-weight:500;color:var(--text3)"> / ' + (p.duration || 0) + ' days</span></div></div>' +
                '<span class="badge ' + (p.is_active ? 'bg' : 'br') + '">' + (p.is_active ? 'Active' : 'Inactive') + '</span></div>' +
                '<div style="font-size:11px;color:var(--text3);margin-bottom:10px">' + (p.features || 'No features listed') + '</div>' +
                '<div style="display:flex;gap:6px">' +
                '<button class="btn-outline" style="font-size:11px;padding:6px 12px" onclick="editAdminPackage(' + p.id + ')"><i data-lucide="edit-2" style="width:12px;height:12px"></i> Edit</button>' +
                '<button style="font-size:11px;padding:6px 12px;border:1px solid #FCA5A5;background:rgba(254,226,226,0.5);color:#DC2626;border-radius:8px;cursor:pointer" onclick="deleteAdminPackage(' + p.id + ')"><i data-lucide="trash-2" style="width:12px;height:12px"></i> Delete</button>' +
                '</div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No packages yet. Create one above.</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load packages.</p>';
    });
}

function showAdminPackageForm(pkg) {
    var name = prompt('Package name:', pkg ? pkg.name : '');
    if (name === null) return;
    var price = prompt('Price (BDT):', pkg ? pkg.price : '');
    if (price === null) return;
    var duration = prompt('Duration (days):', pkg ? pkg.duration : '30');
    if (duration === null) return;
    var features = prompt('Features (comma separated):', pkg ? pkg.features : '');
    if (features === null) return;
    var action = pkg ? 'edit_package' : 'add_package';
    var body = { name: name, price: parseFloat(price) || 0, duration: parseInt(duration) || 30, features: features };
    if (pkg) body.id = pkg.id;
    fetch('/admin/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + action + '&' + Object.keys(body).map(function(k) { return k + '=' + encodeURIComponent(body[k]); }).join('&')
    }).then(function(r) { return r.text(); }).then(function() {
        showToast(pkg ? 'Package updated!' : 'Package added!', 'success');
        loadAdminPackages();
    }).catch(function() { showToast('Failed', 'error'); });
}

function editAdminPackage(id) {
    api('packages').then(function(items) {
        var pkg = (items || []).find(function(p) { return p.id === id; });
        if (pkg) showAdminPackageForm(pkg);
    });
}

function deleteAdminPackage(id) {
    if (!confirm('Delete this package?')) return;
    fetch('/admin/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=del_item&id=' + id + '&table=packages'
    }).then(function() {
        showToast('Package deleted', 'success');
        loadAdminPackages();
    }).catch(function() { showToast('Failed', 'error'); });
}

// === TEACHER RANKINGS ===
function loadTeacherRankings() {
    var container = document.getElementById('teacherRankingsList');
    if (!container) return;
    api('teachers').then(function(items) {
        var sorted = (items || []).sort(function(a, b) { return (b.rating || 0) - (a.rating || 0); });
        var html = '';
        sorted.forEach(function(t, i) {
            var medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '#' + (i + 1);
            var medalStyle = i < 3 ? 'font-size:16px' : 'font-size:12px;font-weight:700;color:var(--text3)';
            html += '<div class="list-item" style="cursor:pointer" onclick="viewTeacherProfile(' + (t.user_id || t.id || 0) + ',\'' + (t.name || '').replace(/'/g, "\\'") + '\',\'' + (t.subject || '').replace(/'/g, "\\'") + '\')">' +
                '<div style="' + medalStyle + ';min-width:28px;text-align:center">' + medal + '</div>' +
                '<div class="user-avatar" style="background:linear-gradient(135deg,' + getSubjectColor(t.subject === 'Mathematics' ? 1 : t.subject === 'English Literature' ? 2 : 3) + ',' + getSubjectColor(t.subject === 'Mathematics' ? 1 : t.subject === 'English Literature' ? 2 : 3) + '80);width:36px;height:36px;font-size:14px">' + (t.name || 'T')[0] + '</div>' +
                '<div class="list-item-content"><h5>' + (t.name || '') + '</h5><p>' + (t.subject || '') + '</p></div>' +
                '<div style="text-align:right"><div style="font-size:14px;font-weight:700;color:#F59E0B">⭐ ' + (t.rating || 0) + '</div><div style="font-size:9px;color:var(--text3)">' + (t.total_students || 0) + ' students</div></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No teachers found</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load</p>';
    });
}

// ============================================
// PHASE 2 — Dynamic Loading Functions
// ============================================

// --- STUDENT PROFILE ---
function loadStudentProfile() {
    checkPremiumAccess().then(function() {
    api('student_profile').then(function(data) {
        var u = data.user || {};
        var p = data.progress || {};
        var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setEl('profileUserName', u.name || USER_NAME);
        setEl('profileUserClass', (u.class || USER_CLASS) + (u.school ? ' · ' + u.school : ''));
        var emailEl = document.getElementById('profileUserEmail');
        if (emailEl) emailEl.textContent = 'Passionate learner exploring new horizons every day!';
        var avatarEl = document.getElementById('profileUserAvatar');
        if (avatarEl) avatarEl.textContent = (u.name || USER_NAME || 'S')[0].toUpperCase();
        var proBadge = document.querySelector('.sp-pro-badge');
        if (proBadge) proBadge.style.display = _isPremium ? '' : 'none';
        setEl('profileStat1', p.books_read || 0);
        setEl('profileStat2', (p.homework_score || 0) + '%');
        setEl('profileStat3', p.streak || 0);
        setEl('profileStat4', p.badges_count || 0);
        // Subscription status in profile menu
        var subText = document.getElementById('profileSubText');
        var subDesc = document.getElementById('profileSubDesc');
        if (_isPremium) {
            if (subText) subText.textContent = 'Premium Active';
            if (subDesc) subDesc.textContent = 'View subscription details';
        } else {
            if (subText) subText.textContent = 'Go Premium';
            if (subDesc) subDesc.textContent = 'Unlock all features';
        }
        var badgeContainer = document.getElementById('profileBadges');
        if (badgeContainer && data.badges && data.badges.length > 0) {
            var colors = ['gold','green','blue','purple','red'];
            var bhtml = '';
            data.badges.forEach(function(b, i) {
                var c = colors[i % colors.length];
                bhtml += '<div class="sp-badge-chip"><div class="sp-badge-icon ' + c + '"><i data-lucide="' + (b.icon || 'award') + '"></i></div><div class="sp-badge-info"><span class="sp-badge-name">' + b.name + '</span><span class="sp-badge-desc">' + (b.description || '') + '</span></div></div>';
            });
            badgeContainer.innerHTML = bhtml;
            if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
        } else if (badgeContainer) {
            badgeContainer.innerHTML = '<p style="font-size:12px;color:var(--text3);padding:8px 0">No badges earned yet</p>';
        }
    });
    });
}

// --- EDIT PROFILE ---
function loadEditProfile() {
    api('profile').then(function(u) {
        if (!u) return;
        var setVal = function(id, val) { var el = document.getElementById(id); if (el) el.value = val || ''; };
        setVal('epName', u.name);
        setVal('epEmail', u.email);
        setVal('epPhone', u.phone);
        setVal('epSchool', u.school);
        setVal('epClass', u.class || USER_CLASS);
        var avatarEl = document.getElementById('epAvatar');
        if (avatarEl) avatarEl.textContent = (u.name || 'U')[0].toUpperCase();
    });
}

function saveProfile() {
    var name = (document.getElementById('epName') || {}).value || '';
    var phone = (document.getElementById('epPhone') || {}).value || '';
    var school = (document.getElementById('epSchool') || {}).value || '';
    var cls = (document.getElementById('epClass') || {}).value || '';
    var password = (document.getElementById('epPassword') || {}).value || '';
    if (!name.trim()) { showToast('Name is required', 'error'); return; }
    var body = { name: name.trim(), phone: phone.trim(), school: school.trim(), class: cls };
    if (password.trim()) body.password = password.trim();
    fetch('/api/index.php?action=edit_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            showToast('Profile updated successfully!', 'success');
            USER_NAME = name.trim();
            if (cls) USER_CLASS = cls;
            var nameEl = document.getElementById('profileUserName');
            if (nameEl) nameEl.textContent = name.trim();
            var avatarEl = document.getElementById('profileUserAvatar');
            if (avatarEl) avatarEl.textContent = name.trim()[0].toUpperCase();
            setTimeout(function() { showScreen('screen-profile'); }, 800);
        } else {
            showToast(data.error || 'Failed to update profile', 'error');
        }
    }).catch(function() {
        showToast('Network error. Please try again.', 'error');
    });
}

// --- TEACHER PROFILE ---
function loadTeacherProfile() {
    if (!_viewingTeacherId) {
        _viewingTeacherId = window.__USER_ID || 1;
    }
    api('teachers').then(function(teachers) {
        var teacher = null;
        var allTeachers = teachers || [];
        for (var i = 0; i < allTeachers.length; i++) {
            if (parseInt(allTeachers[i].user_id) === parseInt(_viewingTeacherId) || parseInt(allTeachers[i].id) === parseInt(_viewingTeacherId)) {
                teacher = allTeachers[i];
                break;
            }
        }
        if (!teacher && allTeachers.length > 0) {
            teacher = allTeachers[0];
            _viewingTeacherId = teacher.user_id;
        }
        if (!teacher) return;
        var name = teacher.name || 'Teacher';
        var subject = teacher.subject || 'Teacher';
        _viewingTeacherName = name;
        _viewingTeacherSubject = subject;
        var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setEl('tpName', name);
        setEl('tpSubject', subject);
        setEl('tpExp', (teacher.experience || 0) + ' years experience');
        setEl('tpRating', teacher.rating || 0);
        setEl('tpStudents', teacher.total_students || 0);
        setEl('tpLessons', 0);
        var avatarEl = document.getElementById('tpAvatar');
        if (avatarEl) avatarEl.textContent = name[0].toUpperCase();
        var descEl = document.getElementById('tpBio');
        if (descEl) descEl.textContent = (teacher.bio || name) + ' is a passionate teacher.';
        var reviewContainer = document.getElementById('tpReviews');
        if (reviewContainer) {
            reviewContainer.innerHTML = '<p style="font-size:12px;color:var(--text3);margin-bottom:8px">Reviews</p>';
        }
        var msgBtn = document.querySelector('#screen-teacher-profile .tutor-action-btn');
        if (msgBtn && USER_ROLE === 'student') {
            checkSubscriptionStatus(_viewingTeacherId, function(res) {
                if (res.subscribed) {
                    msgBtn.innerHTML = '<i data-lucide="message-circle"></i>';
                    msgBtn.onclick = function() { openChat(_viewingTeacherId, _viewingTeacherName); };
                    msgBtn.style.background = 'linear-gradient(135deg,var(--primary),#7C3AED)';
                } else if (res.status === 'pending') {
                    msgBtn.innerHTML = '<i data-lucide="clock"></i>';
                    msgBtn.onclick = function() { showToast('Subscription pending admin approval', 'info'); };
                    msgBtn.style.background = '#F59E0B';
                } else {
                    msgBtn.innerHTML = '<i data-lucide="plus"></i>';
                    msgBtn.onclick = function() { openSubscribeModal(_viewingTeacherId, _viewingTeacherName, _viewingTeacherSubject); };
                    msgBtn.style.background = 'var(--bg)';
                }
                if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
            });
        } else if (msgBtn && USER_ROLE === 'teacher') {
            msgBtn.innerHTML = '<i data-lucide="message-circle"></i>';
            msgBtn.onclick = function() { openChat(_viewingTeacherId, _viewingTeacherName); };
            msgBtn.style.background = 'linear-gradient(135deg,var(--primary),#7C3AED)';
            if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
        }
    });
}

// --- SUBSCRIPTION SYSTEM ---
var _viewingTeacherId = null;
var _viewingTeacherName = '';
var _viewingTeacherSubject = '';
var _selectedSubPayment = 'bkash';

function viewTeacherProfile(teacherUserId, teacherName, teacherSubject) {
    _viewingTeacherId = teacherUserId;
    _viewingTeacherName = teacherName || '';
    _viewingTeacherSubject = teacherSubject || '';
    showScreen('screen-teacher-profile');
}

function openSubscribeModal(teacherId, teacherName, teacherSubject) {
    _viewingTeacherId = teacherId;
    _viewingTeacherName = teacherName || 'Teacher';
    _viewingTeacherSubject = teacherSubject || '';
    var overlay = document.getElementById('subscribeModalOverlay');
    var avatarEl = document.getElementById('subModalAvatar');
    var nameEl = document.getElementById('subModalTeacherName');
    var subjectEl = document.getElementById('subModalTeacherSubject');
    var pkgSelect = document.getElementById('subModalPackage');
    var amountEl = document.getElementById('subModalAmount');
    var txInput = document.getElementById('subModalTxId');
    var errorEl = document.getElementById('subModalError');
    if (avatarEl) avatarEl.textContent = _viewingTeacherName[0].toUpperCase();
    if (nameEl) nameEl.textContent = _viewingTeacherName;
    if (subjectEl) subjectEl.textContent = _viewingTeacherSubject;
    if (txInput) txInput.value = '';
    if (errorEl) { errorEl.style.display = 'none'; errorEl.textContent = ''; }
    if (amountEl) amountEl.value = '';
    if (pkgSelect) {
        pkgSelect.innerHTML = '<option value="">Loading packages...</option>';
        api('packages').then(function(pkgs) {
            var html = '';
            (pkgs || []).forEach(function(p) {
                if (p.is_active) html += '<option value="' + p.id + '" data-price="' + p.price + '">' + p.name + ' - \u09F3' + (p.price || 0).toLocaleString() + ' (' + (p.duration || 30) + ' days)</option>';
            });
            pkgSelect.innerHTML = html || '<option value="">No packages available</option>';
            if (pkgSelect.options.length > 0) {
                pkgSelect.selectedIndex = 0;
                onSubPackageChange();
            }
        });
    }
    if (overlay) { overlay.style.display = 'flex'; }
    if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
}

function closeSubscribeModal() {
    var overlay = document.getElementById('subscribeModalOverlay');
    if (overlay) overlay.style.display = 'none';
}

function showMySubscriptions() {
    api('my_subscriptions').then(function(subs) {
        var html = '<div style="padding:16px">';
        if (!subs || subs.length === 0) {
            html += '<div style="text-align:center;padding:30px"><i data-lucide="credit-card" style="width:40px;height:40px;color:var(--text3);margin-bottom:12px"></i><h4 style="font-size:14px;margin-bottom:6px">No Subscriptions</h4><p style="font-size:12px;color:var(--text3)">You haven\'t subscribed to any teacher yet</p></div>';
        } else {
            subs.forEach(function(s) {
                var statusColor = s.status === 'approved' ? '#10B981' : s.status === 'pending' ? '#F59E0B' : '#EF4444';
                html += '<div style="padding:14px;border:1px solid var(--border);border-radius:12px;margin-bottom:10px">' +
                    '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">' +
                    '<div><h4 style="font-size:14px;font-weight:600">' + (s.teacher_name || 'Unknown') + '</h4>' +
                    '<p style="font-size:11px;color:var(--text3)">' + (s.teacher_subject || '') + '</p></div>' +
                    '<span style="padding:3px 10px;border-radius:12px;font-size:10px;font-weight:600;color:white;background:' + statusColor + '">' + s.status.charAt(0).toUpperCase() + s.status.slice(1) + '</span></div>' +
                    '<div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3)">' +
                    '<span>' + (s.package_name || '') + '</span><span style="font-weight:600;color:var(--primary)">৳' + (s.amount || 0) + '</span></div>' +
                    '<div style="font-size:10px;color:var(--text3);margin-top:4px">Tx: ' + (s.transaction_id || '') + ' · ' + (s.created_at || '').split(' ')[0] + '</div></div>';
            });
        }
        html += '</div>';
        var overlay = document.createElement('div');
        overlay.id = 'subHistoryOverlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:200;display:flex;align-items:center;justify-content:center';
        overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
        overlay.innerHTML = '<div style="background:var(--card);border-radius:16px;width:90%;max-width:400px;max-height:70vh;overflow-y:auto">' +
            '<div style="padding:16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">' +
            '<h3 style="font-size:16px;font-weight:700;margin:0">My Subscriptions</h3>' +
            '<button onclick="document.getElementById(\'subHistoryOverlay\').remove()" style="background:none;border:none;cursor:pointer"><i data-lucide="x" style="width:20px;height:20px"></i></button></div>' + html + '</div>';
        document.body.appendChild(overlay);
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

function onSubPackageChange() {
    var sel = document.getElementById('subModalPackage');
    var amountEl = document.getElementById('subModalAmount');
    if (sel && amountEl && sel.selectedIndex >= 0) {
        var opt = sel.options[sel.selectedIndex];
        amountEl.value = opt.getAttribute('data-price') || '';
    }
}

function selectSubPayment(method) {
    _selectedSubPayment = method;
    var bkash = document.getElementById('subPayBkash');
    var nagad = document.getElementById('subPayNagad');
    if (bkash) bkash.style.borderColor = method === 'bkash' ? 'var(--primary)' : 'var(--border)';
    if (nagad) nagad.style.borderColor = method === 'nagad' ? 'var(--primary)' : 'var(--border)';
}

function submitSubscription() {
    var pkgSelect = document.getElementById('subModalPackage');
    var amountEl = document.getElementById('subModalAmount');
    var txInput = document.getElementById('subModalTxId');
    var errorEl = document.getElementById('subModalError');
    var btn = document.getElementById('subModalSubmitBtn');
    if (!pkgSelect || !amountEl || !txInput) return;
    var packageId = parseInt(pkgSelect.value);
    var amount = parseFloat(amountEl.value);
    var txId = txInput.value.trim();
    if (!packageId || !amount || !txId) {
        if (errorEl) { errorEl.style.display = 'block'; errorEl.textContent = 'Please fill all fields including Transaction ID'; }
        return;
    }
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
    fetch('/api/index.php?action=subscribe_teacher', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ teacher_id: parseInt(_viewingTeacherId), package_id: packageId, amount: amount, transaction_id: txId }),
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (res.error) {
            if (errorEl) { errorEl.style.display = 'block'; errorEl.textContent = res.error; }
        } else {
            closeSubscribeModal();
            showToast('Subscription request sent! Admin will approve shortly.', 'success');
        }
        if (btn) { btn.disabled = false; btn.textContent = 'Submit Subscription Request'; }
    }).catch(function() {
        if (errorEl) { errorEl.style.display = 'block'; errorEl.textContent = 'Network error. Please try again.'; }
        if (btn) { btn.disabled = false; btn.textContent = 'Submit Subscription Request'; }
    });
}

function checkSubscriptionStatus(teacherId, callback) {
    api('is_subscribed&teacher_id=' + teacherId).then(function(res) {
        if (callback) callback(res);
    }).catch(function() {
        if (callback) callback({ subscribed: false, status: null });
    });
}

// --- TEACHER OWN PROFILE ---
function loadTeacherOwnProfile() {
    api('teacher_profile').then(function(data) {
        var u = data.user || {};
        var t = data.teacher || {};
        var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setEl('tpvName', u.name || USER_NAME);
        setEl('tpvSubject', (t.subject || 'Teacher') + (t.experience ? ' · ' + t.experience + ' yrs exp' : ''));
        var avatarEl = document.getElementById('tpvAvatar');
        if (avatarEl) avatarEl.textContent = (u.name || 'T')[0].toUpperCase();
        var descEl = document.getElementById('tpvBio');
        if (descEl) descEl.textContent = t.bio || (u.name || 'Teacher') + ' is a passionate teacher dedicated to helping students succeed.';
        var reviewContainer = document.getElementById('tpvReviews');
        if (reviewContainer) {
            var rhtml = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><span style="font-size:24px;font-weight:800;color:var(--text)">' + (t.rating || data.avg_rating || 0) + '</span><div><div style="font-size:12px;font-weight:600;color:var(--text)">Average Rating</div><div style="font-size:11px;color:var(--text3)">' + (data.review_count || 0) + ' reviews</div></div></div>';
            reviewContainer.innerHTML = rhtml;
        }
    });
}

// --- TEACHER EDIT PROFILE ---
function loadTeacherEditProfile() {
    api('profile').then(function(u) {
        if (!u) return;
        var setVal = function(id, val) { var el = document.getElementById(id); if (el) el.value = val || ''; };
        setVal('tepName', u.name);
        setVal('tepEmail', u.email);
        setVal('tepPhone', u.phone);
        setVal('tepSchool', u.school);
        var avatarEl = document.getElementById('tepAvatar');
        if (avatarEl) avatarEl.textContent = (u.name || 'T')[0].toUpperCase();
    });
    api('teacher_profile').then(function(data) {
        var t = data.teacher || {};
        var setVal = function(id, val) { var el = document.getElementById(id); if (el) el.value = val || ''; };
        setVal('tepSubject', t.subject);
        setVal('tepExperience', t.experience);
        setVal('tepRate', t.hourly_rate);
        setVal('tepBio', t.bio);
    });
}

function saveTeacherProfile() {
    var name = (document.getElementById('tepName') || {}).value || '';
    var phone = (document.getElementById('tepPhone') || {}).value || '';
    var school = (document.getElementById('tepSchool') || {}).value || '';
    var password = (document.getElementById('tepPassword') || {}).value || '';
    var subject = (document.getElementById('tepSubject') || {}).value || '';
    var experience = (document.getElementById('tepExperience') || {}).value || '';
    var rate = (document.getElementById('tepRate') || {}).value || '';
    var bio = (document.getElementById('tepBio') || {}).value || '';
    if (!name.trim()) { showToast('Name is required', 'error'); return; }
    var body = { name: name.trim(), phone: phone.trim(), school: school.trim() };
    if (password.trim()) body.password = password.trim();
    if (subject.trim()) body.subject = subject.trim();
    if (experience) body.experience = parseInt(experience) || 0;
    if (rate) body.hourly_rate = parseInt(rate) || 0;
    if (bio.trim()) body.bio = bio.trim();
    fetch('/api/index.php?action=edit_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            showToast('Profile updated successfully!', 'success');
            USER_NAME = name.trim();
            var nameEl = document.getElementById('tpvName');
            if (nameEl) nameEl.textContent = name.trim();
            var avatarEl = document.getElementById('tpvAvatar');
            if (avatarEl) avatarEl.textContent = name.trim()[0].toUpperCase();
            setTimeout(function() { showScreen('screen-teacher-profile-view'); loadTeacherOwnProfile(); }, 800);
        } else {
            showToast(data.error || 'Failed to update profile', 'error');
        }
    }).catch(function() {
        showToast('Network error. Please try again.', 'error');
    });
}

// --- MY STUDENTS (Teacher) ---
function loadMyStudents() {
    var container = document.getElementById('myStudentsList');
    var statEl = document.getElementById('myStudentsStat');
    if (!container) return;
    api('my_students').then(function(students) {
        if (statEl) {
            var pendingSubs = students.filter(function(s){ return s.sub_status === 'pending'; }).length;
            statEl.innerHTML = '<div style="text-align:center"><div style="font-size:20px;font-weight:800;color:#4F46E5">' + students.length + '</div><div style="font-size:10px;color:var(--text3)">Total</div></div>' +
                '<div style="text-align:center"><div style="font-size:20px;font-weight:800;color:#10B981">' + students.filter(function(s){ return s.avg_score > 70; }).length + '</div><div style="font-size:10px;color:var(--text3)">Active</div></div>' +
                '<div style="text-align:center"><div style="font-size:20px;font-weight:800;color:#F59E0B">' + pendingSubs + '</div><div style="font-size:10px;color:var(--text3)">Sub Requests</div></div>';
        }
        var html = '';
        students.forEach(function(s) {
            var color = s.avg_score >= 80 ? '#10B981' : s.avg_score >= 60 ? '#F59E0B' : '#EF4444';
            var subBadge = '';
            if (s.sub_status === 'pending') {
                subBadge = '<div style="display:flex;align-items:center;gap:4px;margin-top:4px">' +
                    '<button class="btn btn-sm" style="background:#10B981;color:white;padding:3px 8px;font-size:10px" onclick="event.stopPropagation();approveTeacherSub(' + s.sub_id + ',\'approve\',this)"><i data-lucide="check" style="width:10px;height:10px"></i> Accept</button>' +
                    '<button class="btn btn-danger btn-sm" style="padding:3px 8px;font-size:10px" onclick="event.stopPropagation();approveTeacherSub(' + s.sub_id + ',\'reject\',this)"><i data-lucide="x" style="width:10px;height:10px"></i> Reject</button>' +
                    '</div>';
            } else if (s.sub_status === 'teacher_approved') {
                subBadge = '<div style="margin-top:4px"><span style="padding:2px 8px;border-radius:6px;font-size:9px;font-weight:600;background:#EDE9FE;color:#7C3AED">Pending Admin</span></div>';
            } else if (s.sub_status === 'approved') {
                subBadge = '<div style="margin-top:4px"><span style="padding:2px 8px;border-radius:6px;font-size:9px;font-weight:600;background:#DCFCE7;color:#10B981">Subscribed</span></div>';
            }
            html += '<div class="list-item" style="cursor:pointer" onclick="openStudentDetail(' + s.id + ')">' +
                '<div class="user-avatar" style="background:linear-gradient(135deg,' + color + ',' + color + '80);width:36px;height:36px;font-size:14px">' + (s.name || 'S')[0] + '</div>' +
                '<div class="list-item-content"><h5>' + (s.name || '') + '</h5><p>' + (s.class || '') + ' · Streak: ' + (s.streak || 0) + 'd</p>' + subBadge + '</div>' +
                '<div style="text-align:right"><div style="font-size:14px;font-weight:700;color:' + color + '">' + (s.avg_score || 0) + '%</div></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No students found</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

function approveTeacherSub(subId, action, btn) {
    api('approve_teacher_subscription', {
        method: 'POST',
        body: JSON.stringify({ id: subId, action: action })
    }).then(function(res) {
        showToast(action === 'approve' ? 'Subscription approved!' : 'Subscription rejected', action === 'approve' ? 'success' : 'info');
        loadMyStudents();
    }).catch(function(err) {
        showToast(err.message || 'Failed', 'error');
    });
}

function openStudentDetail(studentId) {
    window._viewingStudentId = studentId;
    showScreen('screen-student-progress');
}

// --- STUDENT PROGRESS DETAIL (Teacher view) ---
function loadStudentDetail() {
    var sid = window._viewingStudentId || 0;
    if (!sid) return;
    var container = document.getElementById('studentDetailContent');
    if (!container) return;
    api('student_detail&student_id=' + sid).then(function(data) {
        var u = data.user || {};
        var p = data.progress || {};
        var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setEl('spdName', u.name || 'Student');
        setEl('spdClass', (u.class || '') + ' - Roll: ' + (u.roll || '-'));
        setEl('spdAvg', data.avg_score + '%');
        setEl('spdHwDone', data.hw_done);
        setEl('spdHwPending', data.hw_pending);
        setEl('spdStreak', p.streak || 0);
        var avatarEl = document.getElementById('spdAvatar');
        if (avatarEl) avatarEl.textContent = (u.name || 'S')[0].toUpperCase();
        // Exam results
        var examContainer = document.getElementById('spdExams');
        if (examContainer) {
            var ehtml = '';
            (data.exam_results || []).forEach(function(e) {
                var scoreColor = e.score >= 80 ? '#10B981' : e.score >= 60 ? '#F59E0B' : '#EF4444';
                ehtml += '<div class="list-item"><div class="list-item-content"><h5>' + (e.exam_name || '') + '</h5><p>' + (e.date || '') + '</p></div>' +
                    '<div style="font-size:14px;font-weight:700;color:' + scoreColor + '">' + (e.score || 0) + '%</div></div>';
            });
            examContainer.innerHTML = ehtml || '<p style="font-size:12px;color:var(--text3)">No exam results</p>';
        }
    });
}

// --- LIVE CLASS SCHEDULE ---
function loadLiveSchedule() {
    api('live_schedule').then(function(classes) {
        var container = document.getElementById('liveScheduleList');
        if (container) {
            var html = '';
            (classes || []).forEach(function(c) {
                var timeStr = c.time || '10:00 AM';
                html += '<div class="list-item">' +
                    '<div class="list-item-content"><h5>' + (c.subject || '') + '</h5><p>' + (c.topic || '') + ' · ' + (c.class_name || '') + '</p></div>' +
                    '<div style="text-align:right;font-size:11px;color:var(--text3)">' + timeStr + '<br><span style="color:#10B981;font-weight:600">' + (c.status || 'upcoming') + '</span></div></div>';
            });
            if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No upcoming classes</p>';
            container.innerHTML = html;
        }
        // Populate live lobby
        if (classes && classes.length > 0) {
            var c = classes[0];
            var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
            setEl('liveLobbyName', c.teacher_name || 'Teacher');
            setEl('liveLobbySubject', (c.subject || '') + ' - ' + (c.topic || ''));
            setEl('liveLobbyClass', c.class_name || '');
            var tName = c.teacher_name || 'T';
            var lobbyAvatar = document.getElementById('liveLobbyAvatar');
            if (lobbyAvatar) lobbyAvatar.textContent = tName[0].toUpperCase();
            setEl('liveClassTitle', 'Live: ' + (c.subject || '') + ' ' + (c.topic || ''));
            var classAvatar = document.getElementById('liveClassAvatar');
            if (classAvatar) classAvatar.textContent = tName[0].toUpperCase();
            setEl('liveClassName', tName);
            setEl('liveStudentCount', 'Students (' + (c.students_joined || 0) + ')');
        }
    });
}

// --- MESSAGES / CONVERSATIONS ---
function loadMessages() {
    var container = document.getElementById('messagesList');
    var unreadEl = document.getElementById('msgUnreadCount');
    var totalEl = document.getElementById('msgTotalCount');
    if (!container) return;
    showChatList();
    api('conversations').then(function(convos) {
        var unreadTotal = convos.reduce(function(sum, c) { return sum + (c.unread || 0); }, 0);
        if (unreadEl) unreadEl.textContent = unreadTotal;
        if (totalEl) totalEl.textContent = convos.length;
        var html = '';
        convos.forEach(function(c) {
            html += '<div class="list-item" style="cursor:pointer" onclick="openChat(' + (c.user_id || c.id) + ',\'' + (c.name || '').replace(/'/g, "\\'") + '\')">' +
                '<div class="user-avatar" style="background:linear-gradient(135deg,#4F46E5,#7C3AED);width:36px;height:36px;font-size:14px">' + (c.name || 'U')[0] + '</div>' +
                '<div class="list-item-content"><h5>' + (c.name || '') + '</h5><p style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px">' + (c.last_message || '') + '</p></div>' +
                '<div style="text-align:right"><div style="font-size:9px;color:var(--text3)">' + (c.time || '') + '</div>' +
                (c.unread ? '<div style="background:#4F46E5;color:white;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;margin-top:4px">' + c.unread + '</div>' : '') +
                '</div></div>';
        });
        if (!html) {
            if (USER_ROLE === 'student') {
                html = '<div style="text-align:center;padding:30px 20px"><div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#7C3AED);display:flex;align-items:center;justify-content:center;margin:0 auto 12px"><i data-lucide="lock" style="width:24px;height:24px;color:white"></i></div>' +
                    '<h4 style="font-size:14px;font-weight:700;margin-bottom:6px">No Active Subscriptions</h4>' +
                    '<p style="font-size:12px;color:var(--text3);margin-bottom:16px">Subscribe to a teacher to start chatting with them</p>' +
                    '<button onclick="showScreen(\'screen-tutors\')" style="padding:10px 24px;background:linear-gradient(135deg,var(--primary),#7C3AED);color:white;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer">Browse Teachers</button></div>';
            } else {
                html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No conversations</p>';
            }
        }
        container.innerHTML = html;
    });
}

var _chatUserId = null;
function showChatList() {
    var lv = document.getElementById('msgListView');
    var cv = document.getElementById('msgChatView');
    if (lv) lv.style.display = '';
    if (cv) cv.style.display = 'none';
    _chatUserId = null;
}

function openChat(userId, userName) {
    _chatUserId = userId;
    var lv = document.getElementById('msgListView');
    var cv = document.getElementById('msgChatView');
    if (lv) lv.style.display = 'none';
    if (cv) cv.style.display = 'flex';
    cv.style.flexDirection = 'column';
    cv.style.height = 'calc(100vh - 0px)';
    var nameEl = document.getElementById('chatUserName');
    if (nameEl) nameEl.textContent = userName;
    var avatarEl = document.getElementById('chatAvatar');
    if (avatarEl) avatarEl.textContent = (userName || 'U')[0].toUpperCase();
    loadChatMessages(userId);
}

function loadChatMessages(userId) {
    var container = document.getElementById('chatMessages');
    if (!container) return;
    container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Loading...</p>';
    api('chat_messages&user_id=' + userId).then(function(msgs) {
        var html = '';
        (msgs || []).forEach(function(m) {
            var isMine = String(m.from_id) === String(window.__USER_ID || '');
            var align = isMine ? 'flex-end' : 'flex-start';
            var bg = isMine ? 'linear-gradient(135deg,var(--primary),var(--primary-dark))' : 'var(--card)';
            var color = isMine ? 'white' : 'var(--text)';
            var time = m.created_at ? m.created_at.split(' ')[1] || '' : '';
            html += '<div style="display:flex;flex-direction:column;align-items:' + align + ';max-width:80%">' +
                '<div style="padding:10px 14px;border-radius:16px;background:' + bg + ';color:' + color + ';font-size:13px;line-height:1.5;box-shadow:var(--shadow-sm)">' + (m.message || '') + '</div>' +
                '<span style="font-size:9px;color:var(--text3);margin-top:2px">' + time + '</span></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No messages yet. Say hello!</p>';
        container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

function sendChatMessage() {
    var input = document.getElementById('chatInput');
    var msg = input ? input.value.trim() : '';
    if (!msg || !_chatUserId) return;
    input.value = '';
    var container = document.getElementById('chatMessages');
    var isMine = true;
    var html = '<div style="display:flex;flex-direction:column;align-items:flex-end;max-width:80%">' +
        '<div style="padding:10px 14px;border-radius:16px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;font-size:13px;line-height:1.5;box-shadow:var(--shadow-sm)">' + msg.replace(/</g, '&lt;') + '</div>' +
        '<span style="font-size:9px;color:var(--text3);margin-top:2px">Just now</span></div>';
    if (container) {
        container.insertAdjacentHTML('beforeend', html);
        container.scrollTop = container.scrollHeight;
    }
    fetch('/api/index.php?action=send_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ to_id: parseInt(_chatUserId), message: msg }),
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) showToast('Failed to send', 'error');
    }).catch(function() { showToast('Network error', 'error'); });
}

// --- TEACHER EARNINGS ---
function loadEarnings() {
    api('earnings').then(function(data) {
        var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setEl('earnName', USER_NAME);
        setEl('earnSubject', 'Teacher');
        setEl('earnEmail', USER_EMAIL);
        setEl('earnMonthly', '৳' + (data.monthly || 0).toLocaleString());
        setEl('earnTotal', '৳' + (data.total || 0).toLocaleString());
        setEl('earnStudents', data.students || 0);
        setEl('earnClasses', data.class_count || 0);
        var avatarEl = document.getElementById('earnAvatar');
        if (avatarEl) avatarEl.textContent = (USER_NAME || 'T')[0].toUpperCase();
    });
}

// ============================================
// PHASE 4 — All Remaining Dynamic Conversions
// ============================================

// --- ACTIVITY FEED (Student Home) ---
function loadActivityFeed() {
    var c = document.getElementById('homeActivityFeed');
    if (!c) return;
    api('activity_feed').then(function(items) {
        var html = '';
        (items || []).slice(0, 5).forEach(function(a) {
            html += '<div class="list-item"><div class="icon-box ' + (a.type === 'read' ? 'green' : a.type === 'homework' ? 'blue' : a.type === 'quiz' ? 'orange' : 'purple') + ' sm"><i data-lucide="' + (a.icon || 'activity') + '" class="icon-sm"></i></div><div class="list-item-content"><h5>' + (a.title || '') + '</h5><p>' + (a.time || '') + '</p></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:8px;font-size:10px;color:var(--text3)">No recent activity</p>';
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- STUDENT HOME PROGRESS BARS ---
function loadStudentHomeProgress(prog) {
    function render(p) {
        if (!p) return;
        var setBar = function(id, pct) { var el = document.getElementById(id); if (el) el.style.width = pct + '%'; };
        var setText = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setText('homeBooksVal', (p.books_read || 0) + ' / 30');
        setBar('homeBooksBar', Math.min((p.books_read || 0) * 8, 100));
        setText('homeHwScoreVal', (p.homework_score || 0) + '%');
        setBar('homeHwScoreBar', p.homework_score || 0);
        setText('homeExamScoreVal', (p.exam_score || 0) + '%');
        setBar('homeExamScoreBar', p.exam_score || 0);
    }
    if (prog) { render(prog); }
    else { api('student_progress').then(render); }
    api('live_schedule').then(function(items) {
        var count = (items || []).length;
        var setText = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setText('homeLiveVal', count);
        var setBar = function(id, pct) { var el = document.getElementById(id); if (el) el.style.width = pct + '%'; };
        setBar('homeLiveBar', Math.min(count * 25, 100));
    });
}

// --- CHAT BUBBLE CLICK ---
function sendChatBubble(text) {
    openChatFromFAB();
    setTimeout(function() {
        addChatMessage(text, 'user');
        setTimeout(function() { addChatMessage(getAIResponse(text), 'ai'); }, 800);
    }, 300);
}

// --- NOTIFICATION FILTER ---
function filterNotifications(type, btn) {
    document.querySelectorAll('#screen-notif .filter-chip').forEach(function(c) { c.classList.remove('active'); });
    btn.classList.add('active');
    var items = document.querySelectorAll('#screen-notif .notif-item');
    items.forEach(function(item) {
        if (type === 'all') { item.style.display = ''; return; }
        var text = (item.textContent || '').toLowerCase();
        item.style.display = text.indexOf(type) > -1 ? '' : 'none';
    });
}

// --- HOME BADGES ---
function loadHomeBadges() {
    var earned = [];
    try { earned = JSON.parse(localStorage.getItem('earnedBadges_' + (USER_NAME || ''))) || []; } catch(e) {}
    if (!earned.length) {
        earned = ['First HW', '7-Day Streak', 'Speed Star'];
        try { localStorage.setItem('earnedBadges_' + (USER_NAME || ''), JSON.stringify(earned)); } catch(e) {}
    }
    api('badges').then(function(badges) {
        var grid = document.getElementById('homeBadgeGrid');
        if (!grid || !badges || !badges.length) return;
        grid.innerHTML = '';
        var icons = { 'First HW': '&#127942;', '7-Day Streak': '&#128293;', 'Speed Star': '&#128640;', 'Top Scorer': '&#127941;', 'Perfect Attendance': '&#127881;' };
        var colors = { 'First HW': '#FEF3C7', '7-Day Streak': '#DCFCE7', 'Speed Star': '#DBEAFE', 'Top Scorer': '#F3F4F6', 'Perfect Attendance': '#EDE9FE' };
        badges.slice(0, 4).forEach(function(b) {
            var isEarned = earned.indexOf(b.name) > -1;
            grid.innerHTML += '<div class="badge-item' + (isEarned ? '' : ' locked') + '"><div class="badge-icon" style="background:' + (colors[b.name] || '#F3F4F6') + '">' + (icons[b.name] || '&#127942;') + '</div><div class="badge-name">' + b.name + '</div></div>';
        });
    }).catch(function(){});
}

// --- DAILY GOALS (localStorage) ---
function _goalKey() {
    var today = new Date().toISOString().slice(0, 10);
    return 'dailyGoal_' + (USER_NAME || 'user') + '_' + today;
}

function _getGoals() {
    try { return JSON.parse(localStorage.getItem(_goalKey())) || []; } catch(e) { return []; }
}

function _saveGoals(goals) {
    localStorage.setItem(_goalKey(), JSON.stringify(goals));
}

function loadDailyGoals() {
    var goals = _getGoals();
    var checklist = document.getElementById('goalChecklist');
    var textEl = document.getElementById('homeGoalText');
    var barEl = document.getElementById('homeGoalBar');
    if (!checklist) return;
    if (!goals.length) {
        checklist.innerHTML = '<div class="goal-empty">Tap + to add a goal</div>';
        if (textEl) textEl.textContent = '0 / 0 Tasks';
        if (barEl) barEl.style.width = '0%';
        return;
    }
    var html = '';
    goals.forEach(function(g, i) {
        html += '<div class="goal-check-item' + (g.done ? ' done' : '') + '" onclick="toggleDailyGoal(' + i + ')">' +
            '<input type="checkbox"' + (g.done ? ' checked' : '') + ' onclick="event.stopPropagation();toggleDailyGoal(' + i + ')">' +
            '<span>' + _escHtml(g.text) + '</span>' +
            '<button class="goal-del" onclick="event.stopPropagation();deleteDailyGoal(' + i + ')">&times;</button>' +
            '</div>';
    });
    checklist.innerHTML = html;
    var done = goals.filter(function(g) { return g.done; }).length;
    var total = goals.length;
    var pct = total ? Math.round((done / total) * 100) : 0;
    if (textEl) textEl.textContent = done + ' / ' + total + ' Tasks';
    if (barEl) barEl.style.width = pct + '%';
}

function openGoalPopup() {
    var overlay = document.getElementById('goalPopupOverlay');
    var input = document.getElementById('goalInput');
    if (overlay) overlay.classList.add('active');
    if (input) { input.value = ''; setTimeout(function() { input.focus(); }, 100); }
    _renderPopupGoals();
}

function closeGoalPopup() {
    var overlay = document.getElementById('goalPopupOverlay');
    if (overlay) overlay.classList.remove('active');
}

function _renderPopupGoals() {
    var container = document.getElementById('goalPopupGoals');
    if (!container) return;
    var goals = _getGoals();
    if (!goals.length) {
        container.innerHTML = '<div class="goal-popup-empty">No goals yet. Add one below!</div>';
        return;
    }
    var html = '';
    goals.forEach(function(g, i) {
        html += '<div class="goal-popup-goal">' +
            '<input type="checkbox"' + (g.done ? ' checked' : '') + ' onchange="toggleDailyGoal(' + i + ')">' +
            '<span class="goal-popup-goal-text' + (g.done ? ' done' : '') + '">' + _escHtml(g.text) + '</span>' +
            '<button class="goal-popup-goal-del" onclick="deleteDailyGoal(' + i + ')">&times;</button>' +
            '</div>';
    });
    container.innerHTML = html;
}

function addDailyGoal() {
    var input = document.getElementById('goalInput');
    if (!input) return;
    var text = input.value.trim();
    if (!text) return;
    var goals = _getGoals();
    goals.push({ text: text, done: false });
    _saveGoals(goals);
    input.value = '';
    input.focus();
    loadDailyGoals();
    _renderPopupGoals();
}

function toggleDailyGoal(index) {
    var goals = _getGoals();
    if (!goals[index]) return;
    goals[index].done = !goals[index].done;
    _saveGoals(goals);
    loadDailyGoals();
    _renderPopupGoals();
}

function deleteDailyGoal(index) {
    var goals = _getGoals();
    goals.splice(index, 1);
    _saveGoals(goals);
    loadDailyGoals();
    _renderPopupGoals();
}

function _escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// --- MY STUDY STATS ---
function loadMyStudyStats() {
    var u = USER_ROLE === 'student' ? window._studentProgress : null;
    if (!u) {
        api('student_progress').then(function(p) {
            window._studentProgress = p;
            renderMyStudyStats(p);
        });
    } else {
        renderMyStudyStats(u);
    }
}
function renderMyStudyStats(p) {
    if (!p) return;
    var setText = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
    setText('myStudyBooks', p.books_read || 0);
    setText('myStudyScore', (p.homework_score || 0) + '%');
    setText('myStudyStreak', p.streak || 0);
    setText('myStudyBadges', p.badges_count || 0);
    setText('myStudyGoalPct', Math.round((p.homework_score || 0)) + '%');
    setText('myStudyGoalTasks', Math.min(Math.round((p.homework_score || 0) / 25), 4) + ' of 4 tasks completed');
    var goalRing = document.getElementById('myStudyGoalRing');
    if (goalRing) {
        var pct = Math.min(p.homework_score || 0, 100);
        goalRing.style.background = 'conic-gradient(#4F46E5 ' + pct + '%, rgba(255,255,255,0.1) ' + pct + '%)';
    }
    var avatarEl = document.getElementById('myStudyAvatar');
    if (avatarEl) avatarEl.textContent = (USER_NAME || 'S')[0].toUpperCase();
    api('homework').then(function(hw) {
        var pending = (hw || []).filter(function(h) { return h.status === 'pending'; }).length;
        setText('myStudyHwPending', pending + ' pending');
    });
    api('exams').then(function(exams) {
        var upcoming = (exams || []).filter(function(e) { return e.status === 'upcoming'; }).length;
        setText('myStudyExamUpcoming', upcoming + ' upcoming');
    });
}

// --- HOMEWORK DETAIL ---
var _currentHwId = null;
function loadHomeworkDetail(id) {
    if (!id) return;
    _currentHwId = id;
    var answerEl = document.getElementById('hwAnswerInput');
    if (answerEl) answerEl.value = '';
    var statusEl = document.getElementById('hwSubmissionStatus');
    if (statusEl) statusEl.style.display = 'none';
    var submitBtn = document.getElementById('hwSubmitBtn');
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Homework'; }
    api('homework').then(function(list) {
        var hw = null;
        (list || []).forEach(function(h) { if (String(h.id) === String(id)) hw = h; });
        if (!hw) return;
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('hwDetailSubject', getSubjectName(hw.subject_id));
        setText('hwDetailTitle', hw.title || '');
        setText('hwDetailClass', hw.class_name || USER_CLASS);
        setText('hwDetailDue', 'Due: ' + (hw.due_date || 'Tomorrow'));
        setText('hwDetailInstructions', hw.description || hw.instructions || '');
        var iconEl = document.getElementById('hwDetailIcon');
        if (iconEl) iconEl.setAttribute('data-lucide', getSubjectIcon(hw.subject_id));
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

function submitHomework() {
    var answer = (document.getElementById('hwAnswerInput') || {}).value || '';
    if (!answer.trim()) { showToast('Please write your answer before submitting', 'error'); return; }
    if (!_currentHwId) { showToast('No homework selected', 'error'); return; }
    var submitBtn = document.getElementById('hwSubmitBtn');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }
    fetch('/api/index.php?action=submit_homework', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ homework_id: parseInt(_currentHwId), answer: answer.trim() }),
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            showToast('Homework submitted successfully!', 'success');
            var statusEl = document.getElementById('hwSubmissionStatus');
            if (statusEl) {
                statusEl.style.display = 'block';
                statusEl.style.background = '#DCFCE7';
                statusEl.style.color = '#16A34A';
                statusEl.textContent = 'Submitted! Waiting for teacher to grade.';
            }
            if (submitBtn) submitBtn.textContent = 'Submitted';
            logActivity('homework', 'Submitted homework');
        } else {
            showToast(data.error || 'Failed to submit', 'error');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Homework'; }
        }
    }).catch(function() {
        showToast('Network error. Please try again.', 'error');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Homework'; }
    });
}

// --- EXAM INTERFACE ---
function loadExamQuestions(examId) {
    if (!examId) return;
    Promise.all([api('exams'), api('question_bank')]).then(function(results) {
        var list = results[0], allQs = results[1];
        var exam = null;
        (list || []).forEach(function(e) { if (String(e.id) === String(examId)) exam = e; });
        if (!exam) return;
        window._examData = exam;
        window._examQIndex = 0;
        window._examAnswers = {};
        var examQs = (allQs || []).filter(function(q) { return String(q.subject_id) === String(exam.subject_id); });
        if (examQs.length === 0) examQs = (allQs || []).slice(0, 5);
        exam.questions = examQs;
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('examIfaceTitle', exam.title || 'Exam');
        setText('examIfaceTimer', (exam.duration || 45) + ':00');
        showExamQuestion(0);
    });
}
function showExamQuestion(idx) {
    var exam = window._examData;
    if (!exam) return;
    var questions = exam.questions || [];
    if (idx >= questions.length) return;
    window._examQIndex = idx;
    var q = questions[idx];
    var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
    setText('examQNum', 'Question ' + (idx + 1) + ' of ' + questions.length);
    setText('examQProgress', Math.round(((idx) / questions.length) * 100) + '%');
    var bar = document.getElementById('examQProgressBar');
    if (bar) bar.style.width = ((idx) / questions.length * 100) + '%';
    setText('examQText', q.question || '');
    var optsHtml = '';
    var colors = ['#4F46E5','#10B981','#F59E0B','#EF4444'];
    (q.options || []).forEach(function(opt, i) {
        optsHtml += '<button class="btn-outline" style="text-align:left;margin-bottom:8px;border-left:4px solid ' + colors[i % 4] + '" onclick="selectExamAnswer(' + idx + ',' + i + ')">' + opt + '</button>';
    });
    var optsEl = document.getElementById('examOptions');
    if (optsEl) optsEl.innerHTML = optsHtml;
}

var _examAnswers = {};
function selectExamAnswer(qIdx, optIdx) {
    _examAnswers[qIdx] = optIdx;
    var btns = document.querySelectorAll('#examOptions .btn-outline');
    btns.forEach(function(btn, i) {
        if (i === optIdx) {
            btn.style.background = 'var(--primary)';
            btn.style.color = 'white';
            btn.style.borderColor = 'var(--primary)';
        } else {
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = ['#4F46E5','#10B981','#F59E0B','#EF4444'][i % 4];
        }
    });
    showToast('Answer selected: Option ' + (optIdx + 1), 'info');
}

// --- EXAM RESULT ---
function loadExamResult() {
    var data = window._examResult;
    if (data) {
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('examResultScore', (data.score || 0) + '%');
        setText('examResultCorrect', data.correct || 0);
        setText('examResultWrong', data.wrong || 0);
        setText('examResultRank', '#' + (data.rank || 1));
        setText('examResultTitle', data.title || 'Exam Result');
        return;
    }
    api('exam_results').then(function(results) {
        if (!results || !results.length) {
            var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
            setText('examResultScore', '0%');
            setText('examResultTitle', 'No exam results yet');
            setText('examResultCorrect', '0');
            setText('examResultWrong', '0');
            setText('examResultRank', '-');
            return;
        }
        var latest = results[results.length - 1];
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('examResultScore', (latest.score || 0) + '%');
        setText('examResultCorrect', latest.correct || 0);
        setText('examResultWrong', latest.wrong || 0);
        setText('examResultRank', '#' + (latest.rank || results.length));
        setText('examResultTitle', latest.exam_name || 'Exam Result');
    });
}

// --- SUBJECT CHAPTERS ---
function loadSubjectChapters(subjectId) {
    var c = document.getElementById('chapterList');
    if (!c) return;
    api('chapters&subject_id=' + subjectId).then(function(chapters) {
        var html = '';
        (chapters || []).forEach(function(ch) {
            var statusIcon = ch.status === 'completed' ? 'check-circle' : ch.status === 'in_progress' ? 'play-circle' : ch.status === 'locked' ? 'lock' : 'circle';
            var statusColor = ch.status === 'completed' ? '#10B981' : ch.status === 'in_progress' ? '#4F46E5' : '#9CA3AF';
            var pct = ch.status === 'completed' ? 100 : ch.status === 'in_progress' ? 50 : 0;
            html += '<div class="list-item" style="cursor:pointer' + (ch.status === 'locked' ? 'opacity:0.5' : '') + '"' + (ch.status !== 'locked' ? ' onclick="openBookReader(' + ch.id + ',\'' + (ch.title || '').replace(/'/g, "\\'") + '\')"' : '') + '>' +
                '<div class="icon-box sm" style="background:' + statusColor + '20;color:' + statusColor + '"><i data-lucide="' + statusIcon + '" class="icon-sm"></i></div>' +
                '<div class="list-item-content"><h5>' + ch.title + '</h5><p>' + ch.pages + ' pages · ' + pct + '% complete</p>' +
                '<div style="background:rgba(255,255,255,0.1);border-radius:4px;height:4px;margin-top:4px"><div style="background:' + statusColor + ';height:100%;border-radius:4px;width:' + pct + '%"></div></div></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No chapters found</p>';
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- BOOK CONTENT ---
var _currentBookChapterId = null;
var _currentBookSubjectId = null;

function loadBookContent(chapterId) {
    if (!chapterId) return;
    _currentBookChapterId = chapterId;
    api('book_content&chapter_id=' + chapterId).then(function(book) {
        if (!book || !book.content) {
            var contentEl = document.getElementById('bookContent');
            if (contentEl) contentEl.innerHTML = '<p style="text-align:center;padding:40px;color:var(--text3)">No content available for this chapter yet.</p>';
            return;
        }
        _currentBookSubjectId = book.subject_id || null;
        logActivity('read', 'Read ' + (book.title || 'chapter'));
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('bookTitle', book.title || '');
        var pct = book.pages_total ? Math.round((book.pages_read || 0) / book.pages_total * 100) : 0;
        setText('bookProgress', pct + '% complete');
        var bar = document.getElementById('bookProgressBar');
        if (bar) bar.style.width = pct + '%';
        setText('bookChapterInfo', 'Page ' + (book.pages_read || 0) + ' of ' + (book.pages_total || 1));
        var contentEl = document.getElementById('bookContent');
        if (contentEl) {
            var lines = (book.content || '').split(/\n/);
            contentEl.innerHTML = '<h3 style="font-size:16px;font-weight:700;margin-bottom:12px">' + (book.title || '') + '</h3>' +
                lines.map(function(l) { return '<p style="font-size:13px;line-height:1.8;color:var(--text2);margin-bottom:8px">' + l + '</p>'; }).join('');
        }
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

function goBackFromReader() {
    var back = _previousScreen || 'screen-my-study';
    showScreen(back);
}

function readerNavigate(dir) {
    if (!_currentBookSubjectId) { showToast('No more chapters', 'info'); return; }
    api('chapters&subject_id=' + _currentBookSubjectId).then(function(chapters) {
        var list = chapters || [];
        var idx = -1;
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === _currentBookChapterId) { idx = i; break; }
        }
        var nextIdx = idx + dir;
        if (nextIdx < 0 || nextIdx >= list.length) { showToast('No more chapters', 'info'); return; }
        openBookReader(list[nextIdx].id, list[nextIdx].title);
    });
}

// --- TUTORS SCREEN (Full Dynamic) ---
var _tutorVideoUrl = '';
function loadTutorsScreen() {
    var apis = [api('teachers'), api('settings'), api('live_schedule')];
    if (USER_ROLE === 'student') apis.push(api('conversations'), api('student_progress'));
    Promise.all(apis).then(function(results) {
        var teachers = results[0] || [];
        var settings = results[1] || [];
        var liveSchedule = results[2] || [];
        var convos = USER_ROLE === 'student' ? (results[3] || []) : [];
        var progress = USER_ROLE === 'student' ? (results[4] || {}) : {};
        if (Array.isArray(settings)) {
            var map = {};
            settings.forEach(function(s) { map[s.key] = s.value; });
            settings = map;
        }
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        var setHtml = function(eid, val) { var el = document.getElementById(eid); if (el) el.innerHTML = val; };

        var sorted = teachers.slice().sort(function(a, b) { return (b.rating || 0) - (a.rating || 0); });

        var ftId = parseInt(settings.featured_teacher_id) || 0;
        var trId = parseInt(settings.top_rated_teacher_id) || 0;
        var fpId = parseInt(settings.popular_teacher_id) || 0;
        var fnId = parseInt(settings.new_teacher_id) || 0;
        _tutorVideoUrl = settings.featured_teacher_video || '';

        function findById(list, id) {
            if (!id) return null;
            for (var i = 0; i < list.length; i++) { if (list[i].id === id) return list[i]; }
            return null;
        }
        var ftTeacher = findById(teachers, ftId) || sorted[0];
        var trTeacher = findById(teachers, trId) || sorted[0];
        var fpTeacher = findById(teachers, fpId) || sorted.reduce(function(best, t) {
            return (t.total_students || 0) > (best.total_students || 0) ? t : best;
        }, sorted[0]);
        var fnTeacher = findById(teachers, fnId) || sorted.reduce(function(best, t) {
            return (t.experience || 0) < (best.experience || 999) ? t : best;
        }, sorted[sorted.length - 1]);

        // Stats - use real data
        setText('tutorStatActive', teachers.length);
        var upcomingCount = liveSchedule.filter(function(c) { return c.status === 'scheduled' || c.status === 'live'; }).length;
        setText('tutorStatAttended', upcomingCount || '0');
        setText('tutorStatStudyHrs', (progress.study_hours || 0) + 'h');

        if (ftTeacher) {
            setText('videoTeacherName', ftTeacher.name || 'Featured Teacher');
            setText('videoTeacherSubject', ftTeacher.subject || '');
            var statsHtml = '<span><i data-lucide="star"></i> ' + (ftTeacher.rating || 0) + '</span>' +
                '<span><i data-lucide="users"></i> ' + (ftTeacher.total_students || 0) + ' students</span>' +
                '<span><i data-lucide="book-open"></i> ' + (ftTeacher.experience || 0) + 'yr exp</span>';
            setHtml('videoTeacherStats', statsHtml);
            var poster = document.getElementById('videoPoster');
            if (poster) {
                var hue = (ftTeacher.id || 0) * 40;
                poster.style.background = 'linear-gradient(135deg, hsl(' + hue + ',60%,25%), hsl(' + (hue + 30) + ',70%,40%))';
            }
        }

        var secondTeacher = null;
        var usedIds = [ftTeacher ? ftTeacher.id : 0];
        for (var i = 0; i < sorted.length; i++) {
            if (usedIds.indexOf(sorted[i].id) === -1) { secondTeacher = sorted[i]; break; }
        }
        var container = document.getElementById('tutorHeroCards');
        var existingSecond = container.querySelector('.tt-hero-card');
        if (existingSecond) existingSecond.remove();
        if (secondTeacher) {
            var card2 = document.createElement('div');
            card2.className = 'tt-hero-card';
            card2.onclick = function() { viewTeacherProfile(secondTeacher.user_id || secondTeacher.id, secondTeacher.name, secondTeacher.subject); };
            card2.style.background = 'linear-gradient(135deg, #EC4899, #F472B6)';
            card2.innerHTML = '<div class="tt-hero-badge"><i data-lucide="award"></i> Featured Teacher</div>' +
                '<div class="tt-hero-row"><div class="tt-hero-avatar" style="background:rgba(255,255,255,0.2)">' + (secondTeacher.name || 'T')[0] + '</div>' +
                '<div class="tt-hero-info"><h3>' + secondTeacher.name + '</h3>' +
                '<div class="tt-hero-subject">' + (secondTeacher.subject || '') + '</div>' +
                '<div class="tt-hero-stats">' +
                '<div class="tt-hero-stat"><span class="val">' + (secondTeacher.rating || 0) + '</span><span class="lbl">Rating</span></div>' +
                '<div class="tt-hero-stat"><span class="val">' + (secondTeacher.total_students || 0) + '</span><span class="lbl">Students</span></div>' +
                '<div class="tt-hero-stat"><span class="val">' + (secondTeacher.experience || 0) + 'yr</span><span class="lbl">Exp</span></div>' +
                '</div></div></div>' +
                '<button class="tt-hero-cta"><i data-lucide="eye"></i> View Profile</button>';
            container.appendChild(card2);
        }
        var dotsEl = document.getElementById('tutorHeroDots');
        if (dotsEl) {
            var dotsHtml = '<div class="tt-dot active"></div>';
            if (secondTeacher) dotsHtml += '<div class="tt-dot"></div>';
            dotsEl.innerHTML = dotsHtml;
        }

        if (trTeacher) setText('tutorNextClassTeacher', trTeacher.name);

        var upHtml = '';
        var times = ['10:00 AM', '02:00 PM', '04:30 PM'];
        var subs = ['Math - Algebra', 'English - Grammar', 'Science - Physics'];
        var tNames = [trTeacher, fpTeacher, fnTeacher].filter(Boolean);
        tNames.slice(0, 3).forEach(function(t, i) {
            upHtml += '<div class="tt-upcoming-item" onclick="showScreen(\'screen-live-class\')">' +
                '<div class="tt-upcoming-time"><div class="t">' + (times[i] || '').split(' ')[0] + '</div><div class="p">' + ((times[i] || '').split(' ')[1] || '') + '</div></div>' +
                '<div class="tt-upcoming-vline"></div>' +
                '<div class="tt-upcoming-info"><h4>' + (subs[i] || '') + '</h4><p>' + (t.name || '') + ' - Live Class</p></div>' +
                '<button class="tt-upcoming-join" onclick="event.stopPropagation();showScreen(\'screen-live-class\')">Join</button></div>';
        });
        setHtml('tutorUpcomingClasses', upHtml);

        var popSorted = teachers.slice().sort(function(a, b) { return (b.total_students || 0) - (a.total_students || 0); });
        var popHtml = '';
        popSorted.slice(0, 3).forEach(function(t) {
            popHtml += '<div class="tt-popular-card" onclick="viewTeacherProfile(' + (t.user_id || t.id || 0) + ',\'' + (t.name || '').replace(/'/g, "\\'") + '\',\'' + (t.subject || '').replace(/'/g, "\\'") + '\')">' +
                '<div class="tt-popular-avatar" style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">' + (t.name || 'T')[0] + '</div>' +
                '<div class="tt-popular-info"><h4>' + (t.name || '') + '</h4>' +
                '<div class="tt-pop-subject">' + (t.subject || '') + ' - ' + (t.experience || '5') + ' years exp</div>' +
                '<div class="tt-pop-meta"><span><i data-lucide="star" style="color:#F59E0B"></i> ' + (t.rating || 0) + '</span>' +
                '<span><i data-lucide="users" style="color:#6366F1"></i> ' + (t.total_students || 0) + '</span></div></div>' +
                '<button class="tt-pop-subscribe" onclick="event.stopPropagation();openSubscribeModal(' + (t.user_id || t.id || 0) + ',\'' + (t.name || '').replace(/'/g, "\\'") + '\',\'' + (t.subject || '').replace(/'/g, "\\'") + '\')">Subscribe</button></div>';
        });
        setHtml('tutorPopularList', popHtml);

        var newSorted = teachers.slice().sort(function(a, b) { return (a.experience || 0) - (b.experience || 0); });
        var newHtml = '';
        newSorted.slice(0, 3).forEach(function(t) {
            newHtml += '<div class="tt-top-card" onclick="viewTeacherProfile(' + (t.user_id || t.id || 0) + ',\'' + (t.name || '').replace(/'/g, "\\'") + '\',\'' + (t.subject || '').replace(/'/g, "\\'") + '\')">' +
                '<div class="tt-top-avatar" style="background:linear-gradient(135deg,#06B6D4,#22D3EE)">' + (t.name || 'T')[0] + '</div>' +
                '<h4>' + (t.name || '') + '</h4><p class="tt-top-subject">' + (t.subject || '') + '</p>' +
                '<div style="font-size:10px;color:#F59E0B">&#11088; ' + (t.rating || 0) + '</div></div>';
        });
        setHtml('tutorNewList', newHtml);

        // My Tutors - load from conversations (students only)
        if (USER_ROLE === 'student' && convos.length) {
            var myTutorsHtml = '';
            convos.slice(0, 5).forEach(function(c) {
                myTutorsHtml += '<div class="tt-my-tutor" onclick="openChat(' + c.user_id + ',\'' + (c.name || '').replace(/'/g, "\\'") + '\')">' +
                    '<div class="tt-my-tutor-avatar">' + (c.name || 'U')[0] + '</div>' +
                    '<div class="tt-my-tutor-info"><h4>' + (c.name || '') + '</h4><p>' + (c.last_message || 'Start a conversation') + '</p></div>' +
                    '<button class="tt-my-tutor-btn" onclick="event.stopPropagation();openChat(' + c.user_id + ',\'' + (c.name || '').replace(/'/g, "\\'") + '\')">Chat</button></div>';
            });
            setHtml('tutorMyTutorsList', myTutorsHtml);
        }

        // Learning Progress - load from student_progress (students only)
        if (USER_ROLE === 'student' && progress && progress.id) {
            var progHtml = '<div class="tt-progress-grid">' +
                '<div class="tt-progress-item"><i data-lucide="check-circle"></i><span class="tt-prog-val">' + (progress.total_classes || 42) + '</span><span class="tt-prog-lbl">Classes Attended</span></div>' +
                '<div class="tt-progress-item"><i data-lucide="clock"></i><span class="tt-prog-val">' + (progress.study_hours || 0) + 'h</span><span class="tt-prog-lbl">Study Hours</span></div>' +
                '<div class="tt-progress-item"><i data-lucide="file-text"></i><span class="tt-prog-val">' + (progress.books_read || 0) + '</span><span class="tt-prog-lbl">Books Read</span></div>' +
                '<div class="tt-progress-item"><i data-lucide="target"></i><span class="tt-prog-val">' + (progress.homework_score || 0) + '%</span><span class="tt-prog-lbl">Goal Progress</span></div>' +
                '</div>';
            var weeklyDone = Math.min(Math.round((progress.homework_score || 0) / 20), 7);
            progHtml += '<div class="tt-weekly-goal"><div class="tt-weekly-label"><span>Weekly Goal</span><p>' + weeklyDone + ' of 7 classes</p></div>' +
                '<div class="tt-progress-bar"><div class="tt-progress-fill" style="width:' + Math.min(weeklyDone / 7 * 100, 100) + '%"></div></div></div>';
            setHtml('tutorLearningProgress', progHtml);
        }

        // Next Class - load from live_schedule
        if (liveSchedule.length) {
            var nextClass = liveSchedule.find(function(c) { return c.status === 'scheduled' || c.status === 'live'; });
            if (nextClass) {
                var nextTitle = document.querySelector('.tt-next-class h3');
                if (nextTitle) nextTitle.textContent = (nextClass.subject || 'Class') + ' - ' + (nextClass.title || 'Upcoming');
                var nextTime = document.querySelector('.tt-next-time');
                if (nextTime) nextTime.innerHTML = '<i data-lucide="clock"></i> ' + (nextClass.time || nextClass.start_time || 'Soon');
            }
        }

        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

function toggleTutorVideo() {
    if (!_tutorVideoUrl) { showToast('No video link configured', 'info'); return; }
    var vid = '';
    var m = _tutorVideoUrl.match(/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
    if (m) vid = m[1];
    if (!vid) { m = _tutorVideoUrl.match(/embed\/([a-zA-Z0-9_-]+)/); if (m) vid = m[1]; }
    if (!vid) { vid = _tutorVideoUrl.split('/').pop().split('?')[0]; }
    if (!vid) { showToast('Invalid video URL', 'error'); return; }
    var embed = document.getElementById('videoEmbed');
    var poster = document.getElementById('videoPoster');
    var close = document.getElementById('videoClose');
    if (embed) {
        embed.innerHTML = '<iframe src="https://www.youtube.com/embed/' + vid + '?autoplay=1&rel=0" allow="autoplay; encrypted-media" allowfullscreen></iframe>';
        embed.style.display = 'block';
    }
    if (poster) poster.style.display = 'none';
    if (close) close.style.display = 'flex';
}

function closeTutorVideo() {
    var embed = document.getElementById('videoEmbed');
    var poster = document.getElementById('videoPoster');
    var close = document.getElementById('videoClose');
    if (embed) { embed.innerHTML = ''; embed.style.display = 'none'; }
    if (poster) poster.style.display = 'flex';
    if (close) close.style.display = 'none';
}

function filterTeachersBySubject(subject) {
    var chips = document.querySelectorAll('.tt-subject-chip');
    chips.forEach(function(c) { c.style.borderColor = ''; c.style.background = ''; });
    event.currentTarget.style.borderColor = 'var(--primary)';
    event.currentTarget.style.background = '#EEF2FF';
    var container = document.getElementById('teacherRankingsList');
    if (!container) return;
    api('teachers').then(function(teachers) {
        var filtered = (teachers || []).filter(function(t) {
            return (t.subject || '').toLowerCase().indexOf(subject.toLowerCase()) > -1;
        });
        if (!filtered.length) {
            container.innerHTML = '<p style="padding:12px;font-size:12px;color:var(--text3)">No teachers found for ' + subject + '</p>';
            return;
        }
        var html = '';
        filtered.forEach(function(t) {
            html += '<div class="tt-top-card" onclick="viewTeacherProfile(' + (t.user_id || t.id || 0) + ',\'' + (t.name || '').replace(/'/g, "\\'") + '\',\'' + (t.subject || '').replace(/'/g, "\\'") + '\')">' +
                '<div class="tt-top-avatar" style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">' + (t.name || 'T')[0] + '</div>' +
                '<h4>' + (t.name || '') + '</h4><p class="tt-top-subject">' + (t.subject || '') + '</p>' +
                '<div style="font-size:10px;color:#F59E0B">&#11088; ' + (t.rating || 0) + '</div></div>';
        });
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- TEACHER DASHBOARD STATS ---
function loadTeacherDashStats() {
    api('teacher_profile').then(function(data) {
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('teacherClassCountText', 'You have ' + (data.class_count || 0) + ' classes today');
        setText('teacherRatingVal', (data.avg_rating || 4.8));
        setText('teacherStudentProgress', (data.student_count || 0) + ' / 60 Students');
        var bar = document.getElementById('teacherStudentBar');
        if (bar) bar.style.width = Math.min(Math.round((data.student_count || 0) / 60 * 100), 100) + '%';
    });
}

// --- EXAM ANALYTICS ---
function loadExamAnalytics() {
    api('exam_analytics').then(function(data) {
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('eaAvgScore', (data.avg_score || 0) + '%');
        setText('eaPassRate', (data.pass_rate || 0) + '%');
        setText('eaTotalExams', data.total_exams || 0);
        setText('eaThisMonth', data.total_results || 0);
        var listHtml = '';
        (data.exams || []).slice(0, 4).forEach(function(e) {
            var resultsForExam = (data.results || []).filter(function(r) { return String(r.exam_id || r.id) === String(e.id); });
            var avg = resultsForExam.length > 0 ? Math.round(resultsForExam.reduce(function(s, r) { return s + (r.score || 0); }, 0) / resultsForExam.length) : 0;
            listHtml += '<div class="list-item"><div class="list-item-content"><h5>' + (e.title || '') + '</h5><p>Avg: ' + avg + '% - ' + resultsForExam.length + ' students</p></div>' +
                '<div style="font-size:14px;font-weight:700;color:' + (avg >= 80 ? '#10B981' : '#F59E0B') + '">' + avg + '%</div></div>';
        });
        setHtml('eaExamList', listHtml);
    });
}
function setHtml(eid, val) { var el = document.getElementById(eid); if (el) el.innerHTML = val; }

// --- HOMEWORK ANALYTICS ---
function loadHwAnalytics() {
    api('hw_analytics').then(function(data) {
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('haTotalAssigned', data.total_assigned || 0);
        setText('haSubmitted', data.submitted || 0);
        setText('haPending', data.pending || 0);
        setText('haSubmissionRate', (data.submission_rate || 0) + '%');
        var listHtml = '';
        var subjMap = {};
        (data.homework || []).forEach(function(h) {
            var sname = getSubjectName(h.subject_id);
            if (!subjMap[sname]) subjMap[sname] = { assigned: 0, submitted: 0 };
            subjMap[sname].assigned++;
        });
        (data.submissions || []).forEach(function(s) {
            var hw = (data.homework || []).find(function(h) { return String(h.id) === String(s.homework_id); });
            if (hw) {
                var sname = getSubjectName(hw.subject_id);
                if (subjMap[sname]) subjMap[sname].submitted++;
            }
        });
        Object.keys(subjMap).forEach(function(name) {
            var d = subjMap[name];
            var rate = d.assigned > 0 ? Math.round(d.submitted / d.assigned * 100) : 0;
            listHtml += '<div class="list-item"><div class="list-item-content"><h5>' + name + '</h5><p>' + d.assigned + ' assigned - ' + d.submitted + ' submitted</p></div>' +
                '<div style="font-size:14px;font-weight:700;color:' + (rate >= 80 ? '#10B981' : '#F59E0B') + '">' + rate + '%</div></div>';
        });
        setHtml('haHwList', listHtml);
    });
}

// --- REPORTS ---
function loadReports() {
    api('reports').then(function(reports) {
        var c = document.getElementById('reportsList');
        if (!c) return;
        var html = '';
        (reports || []).forEach(function(r) {
            var gradeColor = r.grade && r.grade.indexOf('A') >= 0 ? '#10B981' : r.grade && r.grade.indexOf('B') >= 0 ? '#4F46E5' : '#F59E0B';
            html += '<div class="hw-card" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px"><div><h5 style="font-size:13px">' + (r.subject || '') + '</h5><p style="font-size:10px;color:var(--text3)">' + (r.class || '') + '</p></div><div style="background:' + gradeColor + '20;color:' + gradeColor + ';padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700">' + (r.grade || '') + '</div></div>' +
                '<p style="font-size:11px;color:var(--text2);margin-bottom:4px">' + (r.comment || '') + '</p>' +
                '<div style="display:flex;justify-content:space-between;font-size:9px;color:var(--text3)"><span>Score: ' + (r.score || 0) + '%</span><span>' + (r.date || '') + '</span></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No reports found</p>';
        c.innerHTML = html;
        // Stats
        setText('reportsAplus', reports.filter(function(r) { return r.grade === 'A+'; }).length + ' reports');
        setText('reportsA', reports.filter(function(r) { return r.grade === 'A'; }).length + ' reports');
        setText('reportsBplus', reports.filter(function(r) { return r.grade === 'B+'; }).length + ' reports');
        setText('reportsOther', reports.filter(function(r) { return r.grade !== 'A+' && r.grade !== 'A' && r.grade !== 'B+'; }).length + ' reports');
    });
}
function setText(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; }

// --- CLASS SCHEDULE ---
function loadClassSchedule() {
    api('class_schedule').then(function(classes) {
        var c = document.getElementById('classScheduleList');
        if (!c) return;
        var html = '';
        (classes || []).forEach(function(cls) {
            var statusColor = cls.status === 'completed' ? '#10B981' : cls.status === 'ongoing' ? '#F59E0B' : '#4F46E5';
            html += '<div class="list-item"><div class="icon-box sm" style="background:' + statusColor + '20;color:' + statusColor + '"><i data-lucide="book-open" class="icon-sm"></i></div>' +
                '<div class="list-item-content"><h5>' + (cls.subject || '') + ' - ' + (cls.topic || '') + '</h5><p>' + (cls.class_name || '') + ' - ' + (cls.students_count || 0) + ' students</p></div>' +
                '<div style="text-align:right;font-size:11px;color:var(--text3)">' + (cls.time || '') + '<br><span style="color:' + statusColor + ';font-weight:600">' + (cls.status || '') + '</span></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No classes scheduled</p>';
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- CALENDAR ---
function loadCalendar() {
    api('calendar_events').then(function(events) {
        var now = new Date();
        var year = now.getFullYear();
        var month = now.getMonth();
        var today = now.getDate();
        var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('calMonth', monthNames[month] + ' ' + year);
        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var eventDates = {};
        (events || []).forEach(function(e) {
            var d = new Date(e.date);
            if (d.getMonth() === month && d.getFullYear() === year) {
                eventDates[d.getDate()] = e;
            }
        });
        var calGrid = document.getElementById('calGrid');
        if (calGrid) {
            var html = '<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center">';
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(d) {
                html += '<div style="font-size:9px;font-weight:600;color:var(--text3);padding:4px">' + d + '</div>';
            });
            for (var i = 0; i < firstDay; i++) html += '<div></div>';
            for (var day = 1; day <= daysInMonth; day++) {
                var isToday = day === today;
                var hasEvent = eventDates[day];
                var style = 'padding:6px 2px;border-radius:8px;font-size:11px;cursor:pointer;';
                if (isToday) style += 'background:var(--primary);color:white;font-weight:700;';
                else if (hasEvent) style += 'background:rgba(79,70,229,0.15);color:var(--primary);font-weight:600;';
                html += '<div style="' + style + '">' + day + (hasEvent ? '<div style="width:4px;height:4px;border-radius:50%;background:' + (hasEvent.color || '#4F46E5') + ';margin:2px auto 0"></div>' : '') + '</div>';
            }
            html += '</div>';
            calGrid.innerHTML = html;
        }
        // Upcoming events
        var evHtml = '';
        var upcoming = (events || []).filter(function(e) { return new Date(e.date) >= now; }).sort(function(a, b) { return new Date(a.date) - new Date(b.date); });
        upcoming.slice(0, 3).forEach(function(e) {
            var diff = Math.ceil((new Date(e.date) - now) / (1000 * 60 * 60 * 24));
            evHtml += '<div class="list-item"><div class="icon-box sm" style="background:' + (e.color || '#4F46E5') + '20;color:' + (e.color || '#4F46E5') + '"><i data-lucide="calendar" class="icon-sm"></i></div>' +
                '<div class="list-item-content"><h5>' + (e.title || '') + '</h5><p>' + e.date + '</p></div>' +
                '<div style="font-size:10px;color:var(--text3)">' + (diff <= 0 ? 'Today' : 'In ' + diff + ' days') + '</div></div>';
        });
        setHtml('calEvents', evHtml);
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- ADMIN DASHBOARD ---
function loadAdminDashboard() {
    api('admin_stats').then(function(data) {
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('adminStudents', (data.students || 0).toLocaleString());
        setText('adminTeachers', data.teachers || 0);
        setText('adminClasses', data.live_classes || 0);
        setText('adminHw', data.homework || 0);
        setText('adminExams', data.exams || 0);
    });
}

// --- ADMIN TEACHER RANKINGS ---
function loadAdminRankings() {
    api('teachers').then(function(teachers) {
        var c = document.getElementById('adminRankingsList');
        if (!c) return;
        var sorted = (teachers || []).sort(function(a, b) { return (b.rating || 0) - (a.rating || 0); });
        var html = '';
        sorted.forEach(function(t, i) {
            var medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '#' + (i + 1);
            html += '<div class="list-item"><div style="min-width:28px;text-align:center;font-size:14px">' + medal + '</div>' +
                '<div class="user-avatar" style="background:linear-gradient(135deg,#4F46E5,#7C3AED);width:36px;height:36px;font-size:14px">' + (t.name || 'T')[0] + '</div>' +
                '<div class="list-item-content"><h5>' + t.name + '</h5><p>' + (t.subject || '') + '</p></div>' +
                '<div style="text-align:right"><div style="font-size:13px;font-weight:700;color:#F59E0B">⭐ ' + (t.rating || 0) + '</div><div style="font-size:9px;color:var(--text3)">' + (t.total_students || 0) + ' students</div></div></div>';
        });
        c.innerHTML = html;
    });
}

// --- QUESTION BANK ---
function loadQuestionBankScreen() {
    api('question_bank').then(function(questions) {
        var c = document.getElementById('questionBankList');
        if (!c) return;
        var html = '';
        (questions || []).forEach(function(q) {
            var diffColor = q.difficulty === 'easy' ? '#10B981' : q.difficulty === 'medium' ? '#F59E0B' : '#EF4444';
            html += '<div class="hw-card" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px"><h5 style="font-size:12px;flex:1">' + (q.question || '') + '</h5>' +
                '<span style="font-size:9px;padding:2px 8px;border-radius:8px;background:' + diffColor + '20;color:' + diffColor + '">' + (q.difficulty || '') + '</span></div>' +
                '<p style="font-size:10px;color:var(--text3)">' + getSubjectName(q.subject_id) + ' · ' + (q.chapter || '') + ' · ' + (q.marks || 0) + ' marks</p></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No questions in bank</p>';
        c.innerHTML = html;
    });
}

// --- CHAPTER MANAGER ---
function loadChapterManager() {
    api('chapters&subject_id=1').then(function(chapters) {
        var c = document.getElementById('chapterManagerList');
        if (!c) return;
        var html = '';
        (chapters || []).forEach(function(ch) {
            var locked = ch.status === 'locked';
            html += '<div class="list-item" style="' + (locked ? 'opacity:0.5;' : '') + '">' +
                '<div class="icon-box sm" style="background:' + (locked ? '#9CA3AF' : '#4F46E5') + '20;color:' + (locked ? '#9CA3AF' : '#4F46E5') + '"><i data-lucide="' + (locked ? 'lock' : 'unlock') + '" class="icon-sm"></i></div>' +
                '<div class="list-item-content"><h5>' + ch.title + '</h5><p>' + ch.pages + ' pages · ' + ch.status.replace('_', ' ') + '</p></div></div>';
        });
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- LIBRARY MANAGEMENT ---
function loadLibraryMgmt() {
    api('library').then(function(items) {
        var c = document.getElementById('libraryMgmtList');
        if (!c) return;
        var html = '';
        (items || []).forEach(function(item) {
            var statusColor = item.is_active ? '#10B981' : '#9CA3AF';
            html += '<div class="list-item"><div class="list-item-content"><h5>' + (item.title || '') + '</h5><p>' + (item.type || '') + ' · ' + (item.class || '') + '</p></div>' +
                '<div style="display:flex;align-items:center;gap:8px"><span style="font-size:10px;padding:2px 8px;border-radius:8px;background:' + statusColor + '20;color:' + statusColor + '">' + (item.is_active ? 'Active' : 'Draft') + '</span>' +
                '<button onclick="deleteLibraryItem(' + item.id + ')" style="background:none;border:none;cursor:pointer;padding:4px" title="Delete"><i data-lucide="trash-2" style="width:14px;height:14px;color:#EF4444"></i></button></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No library items</p>';
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

function deleteLibraryItem(id) {
    if (!confirm('Are you sure you want to delete this library item?')) return;
    fetch('/api/index.php?action=library_delete&id=' + id, {
        method: 'GET',
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            showToast('Library item deleted', 'success');
            loadLibraryMgmt();
        } else {
            showToast(data.error || 'Failed to delete', 'error');
        }
    }).catch(function() { showToast('Network error', 'error'); });
}

// --- STUDENT EVALUATION ---
function loadStudentEvaluation() {
    api('student_evaluations').then(function(evals) {
        var c = document.getElementById('evalContent');
        if (!c) return;
        var ev = (evals || [])[0];
        if (!ev) { c.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No pending evaluations</p>'; return; }
        var setText = function(eid, val) { var el = document.getElementById(eid); if (el) el.textContent = val; };
        setText('evalStudentName', 'Student #' + ev.student_id);
        setText('evalSubject', getSubjectName(ev.subject_id));
        setText('evalTitle', ev.title || '');
        setText('evalSubmitted', 'Submitted: ' + (ev.submitted_at || ''));
        setText('evalAnswer', ev.submitted_answer || '');
        setText('evalScore', (ev.score || 0) + '%');
        setText('evalComment', ev.teacher_comment || '');
    });
}

// --- PRICING DYNAMIC ---
function loadPricingDynamic() {
    api('packages').then(function(packages) {
        var c = document.getElementById('pricingList');
        if (!c) return;
        var html = '';
        (packages || []).forEach(function(pkg) {
            var features = (pkg.features || '').split(',');
            var featHtml = features.map(function(f) { return '<li><i data-lucide="check-circle" style="width:14px;height:14px;color:#10B981;vertical-align:middle;margin-right:4px"></i> ' + f.trim() + '</li>'; }).join('');
            html += '<div class="hw-card" style="margin:0 0 12px;text-align:center">' +
                '<h4 style="font-size:16px;font-weight:800;margin-bottom:4px">' + (pkg.name || '') + '</h4>' +
                '<p style="font-size:11px;color:var(--text3);margin-bottom:12px">' + (pkg.description || '') + '</p>' +
                '<div style="font-size:28px;font-weight:800;color:var(--primary);margin-bottom:12px">৳' + (pkg.price || 0) + '<span style="font-size:12px;font-weight:400;color:var(--text3)">/' + (pkg.period || 'month') + '</span></div>' +
                '<ul style="text-align:left;font-size:12px;color:var(--text2);list-style:none;padding:0;margin:0 0 16px">' + featHtml + '</ul>' +
                '<button class="btn-primary" style="width:100%" onclick="showScreen(\'screen-payment\')">Choose Plan</button></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No plans available</p>';
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- GIVE REPORT STUDENT LIST ---
function loadGiveReportStudents() {
    api('my_students').then(function(students) {
        var c = document.getElementById('giveReportList');
        if (!c) return;
        var html = '';
        (students || []).forEach(function(s) {
            html += '<div class="list-item" style="cursor:pointer"><div class="user-avatar" style="background:linear-gradient(135deg,#4F46E5,#7C3AED);width:36px;height:36px;font-size:14px">' + (s.name || 'S')[0] + '</div>' +
                '<div class="list-item-content"><h5>' + s.name + '</h5><p>' + (s.class || '') + ' · Roll ' + (s.id || '') + '</p></div>' +
                '<i data-lucide="chevron-right" style="width:16px;height:16px;color:var(--text3)"></i></div>';
        });
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}

// --- AI CHAT GREETING ---
function loadAiChatGreeting() {
    var greeting = document.getElementById('aiGreeting');
    if (greeting) greeting.textContent = 'Hello ' + USER_NAME.split(' ')[0] + '! I\'m your AI Tutor. How can I help you today?';
}

// === PLATFORM SUBSCRIPTION ===
window._selectedPlatformPkg = null;

function selectPlatformPackage(pkgId, pkgName, price, duration) {
    window._selectedPlatformPkg = { id: pkgId, name: pkgName, price: price, duration: duration };
    showScreen('screen-payment');
    renderPaymentScreen();
}

function renderPaymentScreen() {
    var pkg = window._selectedPlatformPkg;
    if (!pkg) return;
    var fee = 10;
    var total = pkg.price + fee;
    var summary = document.getElementById('paymentSummary');
    if (summary) {
        summary.innerHTML = '<h4 style="font-size:13px;font-weight:600;margin-bottom:8px">Order Summary</h4>' +
            '<div class="payment-row"><span>' + pkg.name + '</span><span>৳' + pkg.price.toLocaleString() + '</span></div>' +
            '<div class="payment-row"><span>Processing fee</span><span>৳' + fee + '</span></div>' +
            '<div class="payment-row total"><span>Total</span><span>৳' + total.toLocaleString() + '</span></div>';
    }
    var payBtn = document.getElementById('paymentSubmitBtn');
    if (payBtn) {
        payBtn.textContent = 'Pay ৳' + total.toLocaleString();
        payBtn.onclick = function() { submitPlatformSubscription(); };
    }
    window._selectedPaymentMethod = 'bkash';
}

function selectPayment(el) {
    document.querySelectorAll('.payment-method').forEach(function(m) { m.classList.remove('selected'); });
    el.classList.add('selected');
    var name = el.querySelector('.payment-name h5');
    if (name) window._selectedPaymentMethod = name.textContent.toLowerCase().replace('pay with ', '');
}

function submitPlatformSubscription() {
    var pkg = window._selectedPlatformPkg;
    if (!pkg) { showToast('Please select a package', 'error'); return; }
    var txId = document.getElementById('paymentTxId');
    var txVal = txId ? txId.value.trim() : '';
    if (!txVal) { showToast('Please enter transaction ID', 'error'); return; }
    var fee = 10;
    var total = pkg.price + fee;
    var payBtn = document.getElementById('paymentSubmitBtn');
    if (payBtn) { payBtn.textContent = 'Submitting...'; payBtn.disabled = true; }
    api('subscribe_platform', {
        method: 'POST',
        body: JSON.stringify({
            package_id: pkg.id,
            amount: total,
            transaction_id: txVal,
            payment_method: window._selectedPaymentMethod || 'bkash'
        })
    }).then(function(res) {
        if (payBtn) { payBtn.textContent = 'Pay ৳' + total.toLocaleString(); payBtn.disabled = false; }
        window._paymentResult = {
            package_name: pkg.name,
            amount: total,
            tx_id: txVal,
            date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
            status: 'pending',
            duration: pkg.duration
        };
        showScreen('screen-payment-success');
        renderPaymentSuccess();
    }).catch(function(err) {
        if (payBtn) { payBtn.textContent = 'Pay ৳' + total.toLocaleString(); payBtn.disabled = false; }
        showToast(err.message || 'Failed to submit', 'error');
    });
}

function renderPaymentSuccess() {
    var data = window._paymentResult;
    if (!data) return;
    var el = document.getElementById('paymentSuccessContent');
    if (!el) return;
    el.innerHTML = '<div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#F59E0B,#F97316);display:flex;align-items:center;justify-content:center;margin:0 auto 16px"><i data-lucide="clock" style="width:40px;height:40px;color:white"></i></div>' +
        '<h2 style="font-size:22px;font-weight:800;margin-bottom:8px">Subscription Pending!</h2>' +
        '<p style="font-size:13px;color:var(--text3);margin-bottom:24px">Your payment of ৳' + data.amount.toLocaleString() + ' is being reviewed. Admin will approve within 24 hours.</p>' +
        '<div style="background:var(--card);border-radius:12px;padding:16px;margin-bottom:20px;text-align:left">' +
        '<div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:12px;color:var(--text3)">Plan</span><span style="font-size:12px;font-weight:600">' + data.package_name + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:12px;color:var(--text3)">Amount</span><span style="font-size:12px;font-weight:600">৳' + data.amount.toLocaleString() + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:12px;color:var(--text3)">Duration</span><span style="font-size:12px;font-weight:600">' + data.duration + ' days</span></div>' +
        '<div style="display:flex;justify-content:space-between"><span style="font-size:12px;color:var(--text3)">Transaction ID</span><span style="font-size:12px;font-weight:600">' + data.tx_id + '</span></div></div>' +
        '<button class="btn-primary" onclick="showScreen(\'screen-profile\')" style="width:100%">Go to Profile</button>';
    if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
}

function checkPlatformSubscription() {
    return api('my_platform_subscription').then(function(data) {
        return data;
    });
}

function showMyPlatformSubscription() {
    checkPlatformSubscription().then(function(data) {
        var c = document.getElementById('myPlatformSubContent');
        if (!c) return;
        if (!data.subscription && !data.is_premium) {
            c.innerHTML = '<div style="text-align:center;padding:30px"><i data-lucide="crown" style="width:48px;height:48px;color:var(--text3);margin-bottom:12px"></i><p style="font-size:14px;font-weight:600;margin-bottom:4px">No Active Subscription</p><p style="font-size:12px;color:var(--text3);margin-bottom:16px">Upgrade to access all premium features</p><button class="btn-primary" onclick="showScreen(\'screen-pricing\')">Upgrade Now</button></div>';
        } else {
            var sub = data.subscription;
            var statusColor = data.is_premium ? '#10B981' : (sub && sub.status === 'pending' ? '#F59E0B' : '#EF4444');
            var statusText = data.is_premium ? 'Active' : (sub ? sub.status.charAt(0).toUpperCase() + sub.status.slice(1) : 'Expired');
            c.innerHTML = '<div class="hw-card" style="margin-bottom:12px">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><h4>' + (sub ? sub.package_name : 'Premium') + '</h4><span style="padding:4px 10px;border-radius:8px;font-size:11px;font-weight:600;background:' + statusColor + '20;color:' + statusColor + '">' + statusText + '</span></div>' +
                (sub ? '<div style="font-size:12px;color:var(--text2);margin-bottom:4px">Amount: ৳' + sub.amount.toLocaleString() + '</div>' : '') +
                (sub ? '<div style="font-size:12px;color:var(--text2);margin-bottom:4px">Submitted: ' + sub.created_at + '</div>' : '') +
                (data.is_premium && data.expires_at ? '<div style="font-size:12px;color:var(--text2)">Expires: ' + data.expires_at + '</div>' : '') +
                (sub && sub.status === 'pending' ? '<div style="font-size:11px;color:#F59E0B;margin-top:8px">Admin will verify your payment shortly</div>' : '') +
                '</div>';
            if (!data.is_premium && sub && sub.status === 'pending') {
                c.innerHTML += '<button class="btn-primary" style="width:100%" onclick="showScreen(\'screen-pricing\')">Subscribe Now</button>';
            }
        }
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    });
}
