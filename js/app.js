// === ROLE-BASED SPA ===
var USER_ROLE = window.__USER_ROLE || '';
var USER_NAME = window.__USER_NAME || '';
var USER_CLASS = window.__USER_CLASS || 'Class 8';

var ROLE_SCREENS = {
    landing: ['screen-landing'],
    student: [
        'screen-home','screen-chat','screen-tutors','screen-teacher-profile',
        'screen-profile','screen-homework','screen-hw-detail','screen-exam',
        'screen-exam-interface','screen-exam-result','screen-subject',
        'screen-reader','screen-live-lobby','screen-live-class','screen-search',
        'screen-pricing','screen-payment','screen-payment-success','screen-settings','screen-notif',
        'screen-my-study','screen-library-subject'
    ],
    teacher: [
        'screen-teacher-dash','screen-upload-content','screen-content-library',
        'screen-my-students','screen-student-progress','screen-messages',
        'screen-announcements','screen-create-announcement','screen-class-schedule',
        'screen-calendar','screen-exam-analytics','screen-hw-analytics',
        'screen-create-hw','screen-create-exam','screen-evaluate','screen-earnings',
        'screen-reports','screen-give-report'
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
        {id:'screen-earnings',icon:'user',label:'Profile'}
    ],
    admin: []
};

// === INIT ===
document.addEventListener('DOMContentLoaded', function() {
    // Auth check: if logged in but no role, redirect to login
    if (!USER_ROLE && window.location.hash && window.location.hash !== '#screen-landing') {
        window.location.href = '/login.php';
        return;
    }
    applyRoleVisibility();
    buildBottomNav();
    handleHashRoute();
    bindEvents();
    if (USER_ROLE === 'student') loadLibraryGrid();
    if (typeof lucide !== 'undefined') lucide.createIcons();
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
    if (!nav || !USER_ROLE || !ROLE_NAV[USER_ROLE]) return;
    var items = ROLE_NAV[USER_ROLE];
    var html = '';
    items.forEach(function(item, i) {
        html += '<button class="nav-item' + (i === 0 ? ' active' : '') + '" data-screen="' + item.id + '" onclick="navTo(this,\'' + item.id + '\')">' +
            '<span class="nav-icon"><i data-lucide="' + item.icon + '"></i></span>' +
            '<span class="nav-label">' + item.label + '</span></button>';
    });
    nav.innerHTML = html;
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

// === SCREEN NAVIGATION ===
function showScreen(id) {
    // Role check: only allow screens for current role
    if (USER_ROLE && ROLE_SCREENS[USER_ROLE]) {
        var allowed = ROLE_SCREENS[USER_ROLE].concat(['screen-landing']);
        if (allowed.indexOf(id) === -1) return; // block cross-panel access
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
    if (id === 'screen-pricing') loadPackages();
    if (id === 'screen-tutors') loadTeacherRankings();
    if (id === 'screen-profile' && USER_ROLE === 'student') loadStudentProfile();
    if (id === 'screen-teacher-profile' && USER_ROLE === 'teacher') loadTeacherProfile();
    if (id === 'screen-my-students') loadMyStudents();
    if (id === 'screen-student-progress') loadStudentDetail();
    if (id === 'screen-live-schedule') loadLiveSchedule();
    if (id === 'screen-messages') loadMessages();
    if (id === 'screen-earnings') loadEarnings();
    if (id === 'screen-live-lobby') loadLiveSchedule();
}

function navTo(btn, screenId) {
    document.querySelectorAll('.nav-item').forEach(function(n) { n.classList.remove('active'); });
    btn.classList.add('active');
    showScreen(screenId);
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

function openSubjectFolder(subject) {
    var s = SUBJECTS[subject];
    if (!s) return;
    document.getElementById('libSubjectTitle').textContent = s.name;
    document.getElementById('libSubjectName').textContent = s.name;
    document.getElementById('libSubjectIcon').setAttribute('data-lucide', s.icon);
    var header = document.getElementById('libSubjectHeader');
    header.style.background = 'linear-gradient(135deg,' + s.color + ',' + s.color2 + ')';
    showFolderView();
    showScreen('screen-library-subject');
}

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
                html += '<div class="list-item card-enter" data-type="' + mappedType + '" onclick="showToast(\'Opening file...\',\'info\')">' +
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

// === FILTER MATERIALS BY CLASS ===
function filterMaterialsByClass() {
    var allowed = CLASS_SUBJECTS[USER_CLASS] || CLASS_SUBJECTS['Class 8'];
    var cards = document.querySelectorAll('#myMaterialsGrid .material-card');
    cards.forEach(function(card) {
        var subj = card.getAttribute('data-subject');
        if (allowed.indexOf(subj) > -1) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
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
            var subjectMap = {
                1: { key:'mathematics', name:'Mathematics', icon:'calculator', color:'#3B82F6' },
                2: { key:'english', name:'English', icon:'book-text', color:'#8B5CF6' },
                3: { key:'science', name:'Science', icon:'flask-conical', color:'#22C55E' },
                4: { key:'bangla', name:'Bangla', icon:'pen-tool', color:'#F59E0B' },
                5: { key:'physics', name:'Physics', icon:'atom', color:'#3B82F6' },
                6: { key:'chemistry', name:'Chemistry', icon:'test-tubes', color:'#06B6D4' },
                7: { key:'biology', name:'Biology', icon:'bug', color:'#10B981' },
                8: { key:'ict', name:'ICT', icon:'laptop', color:'#06B6D4' },
                9: { key:'geography', name:'Geography', icon:'globe', color:'#F97316' },
                10: { key:'religion', name:'Religion', icon:'landmark', color:'#EC4899' },
                11: { key:'accounting', name:'Accounting', icon:'calculator', color:'#F59E0B' },
                12: { key:'finance', name:'Finance', icon:'wallet', color:'#06B6D4' },
                13: { key:'history', name:'History', icon:'landmark', color:'#8B5CF6' }
            };
            var allowed = CLASS_SUBJECTS[USER_CLASS] || CLASS_SUBJECTS['Class 8'];
            Object.keys(subjects).forEach(function(sid) {
                var info = subjectMap[sid];
                if (!info) return;
                if (allowed.indexOf(info.key) === -1) return;
                var s = subjects[sid];
                var pct = Math.min(s.count * 25, 100);
                html += '<div class="material-card" onclick="openSubjectFolderDynamic(' + sid + ',\'' + info.key + '\')">' +
                    '<div class="material-icon ' + info.key + '"><i data-lucide="' + info.icon + '" style="width:18px;height:18px"></i></div>' +
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
    var items = document.querySelectorAll('#screen-messages .chat-item');
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
            var input = document.querySelector('.ai-hero-input, .chat-input');
            if (input && input.value.trim()) {
                var msg = input.value.trim();
                input.value = '';
                showToast('AI: ' + getAIResponse(msg), 'info');
            }
            return;
        }

        // Upload chips (home screen AI hero)
        if (t.closest('.upload-chip')) {
            showToast('File picker coming soon!', 'info');
            return;
        }

        // Chat send
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

        // Pricing toggle
        if (t.closest('.toggle-btn')) {
            togglePricing(t.closest('.toggle-btn'));
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
        if (e.key === 'Enter' && e.target.classList.contains('chat-input')) {
            e.preventDefault();
            var sendBtn = e.target.closest('.chat-input-wrap') ? e.target.closest('.chat-input-wrap').querySelector('.chat-send') : document.querySelector('.chat-send');
            if (sendBtn) sendBtn.click();
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
    var msg = document.createElement('div');
    msg.className = 'msg ' + type;
    msg.innerHTML = '<div class="msg-avatar">' + (type === 'ai' ? '' : 'A') + '</div><div class="msg-bubble">' + text + '</div>';
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

function sendPrompt(btn) {
    var text = btn.textContent;
    addChatMessage(text, 'user');
    setTimeout(function() { addChatMessage(getAIResponse(text), 'ai'); }, 800);
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
        if (total <= 0) { clearInterval(examTimerInterval); showToast('Time up! Submitting...', 'error'); showScreen('screen-exam-result'); return; }
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

function togglePricing(btn) {
    btn.parentElement.querySelectorAll('.toggle-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
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

// Helper: get subject name by id
function getSubjectName(id) {
    var map = {1:'Mathematics',2:'English',3:'Science',4:'Bangla',5:'Physics',6:'Chemistry',7:'Biology',8:'ICT',9:'Geography',10:'Religion',11:'Accounting',12:'Finance',13:'History'};
    return map[id] || 'General';
}

// Helper: get subject icon by id
function getSubjectIcon(id) {
    var map = {1:'calculator',2:'book-text',3:'flask-conical',4:'pen-tool',5:'atom',6:'test-tubes',7:'bug',8:'laptop',9:'globe',10:'landmark',11:'calculator',12:'wallet',13:'landmark'};
    return map[id] || 'book-open';
}

// Helper: get subject color by id
function getSubjectColor(id) {
    var map = {1:'#3B82F6',2:'#8B5CF6',3:'#22C55E',4:'#F59E0B',5:'#3B82F6',6:'#06B6D4',7:'#10B981',8:'#06B6D4',9:'#F97316',10:'#EC4899',11:'#F59E0B',12:'#06B6D4',13:'#8B5CF6'};
    return map[id] || '#6366F1';
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

    // Load progress
    api('student_progress').then(function(prog) {
        if (prog && prog.id) {
            var streakEl = document.getElementById('homeStreak');
            var levelEl = document.getElementById('homeLevel');
            var xpEl = document.getElementById('homeXP');
            var xpBar = document.getElementById('homeXPBar');
            var booksEl = document.getElementById('homeBooks');
            var hwScoreEl = document.getElementById('homeHWScore');
            var examScoreEl = document.getElementById('homeExamScore');
            var streakBadge = document.getElementById('homeStreakBadge');
            if (streakEl) streakEl.textContent = prog.streak || 0;
            if (streakBadge) streakBadge.textContent = prog.streak || 0;
            if (levelEl) levelEl.textContent = 'Level ' + Math.floor((prog.books_read || 0) / 2 + 1) + ' Learner';
            if (xpEl) xpEl.textContent = ((prog.study_hours || 0) * 50) + ' / 3,000 XP';
            if (xpBar) xpBar.style.width = Math.min(((prog.study_hours || 0) * 50 / 3000) * 100, 100) + '%';
            if (booksEl) booksEl.textContent = (prog.books_read || 0) + ' / 30';
            if (hwScoreEl) hwScoreEl.textContent = (prog.homework_score || 0) + '%';
            if (examScoreEl) examScoreEl.textContent = (prog.exam_score || 0) + '%';
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
    api('live_classes').then(function(classes) {
        var next = (classes || []).find(function(c) { return c.status === 'live' || c.status === 'scheduled'; });
        var el = document.getElementById('homeLiveClass');
        if (el && next) {
            var subjName = getSubjectName(next.subject_id);
            el.textContent = subjName + ' - ' + (next.start_time || '');
        } else if (el) {
            el.textContent = 'No upcoming class';
        }
    }).catch(function(){});

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
            html += '<div class="glass-card" style="padding:14px;cursor:pointer" onclick="showScreen(\'screen-hw-detail\')">' +
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
            html += '<div class="glass-card" style="padding:14px;cursor:pointer" onclick="showScreen(\'screen-exam-interface\')">' +
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
                '<button class="btn-primary glass-btn" style="width:100%" onclick="showScreen(\'screen-payment\')">Choose Plan</button></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No packages available</p>';
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
    }).catch(function() {
        container.innerHTML = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">Failed to load</p>';
    });
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
            html += '<div class="list-item" style="cursor:pointer" onclick="showScreen(\'screen-teacher-profile\')">' +
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
    api('student_profile').then(function(data) {
        var u = data.user || {};
        var p = data.progress || {};
        var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setEl('profileUserName', u.name || USER_NAME);
        setEl('profileUserEmail', u.email || USER_EMAIL);
        setEl('profileUserClass', (u.class || USER_CLASS) + ' - Premium Student');
        var avatarEl = document.getElementById('profileUserAvatar');
        if (avatarEl) avatarEl.textContent = (u.name || USER_NAME || 'S')[0].toUpperCase();
        setEl('profileStat1', p.books_read || 0);
        setEl('profileStat2', (p.homework_score || 0) + '%');
        setEl('profileStat3', p.streak || 0);
        setEl('profileStat4', p.badges_count || 0);
        // Badges
        var badgeContainer = document.getElementById('profileBadges');
        if (badgeContainer && data.badges && data.badges.length > 0) {
            var bhtml = '';
            data.badges.forEach(function(b) {
                bhtml += '<div class="sp-badge-item"><div class="sp-badge-icon"><i data-lucide="' + (b.icon || 'award') + '"></i></div><span>' + b.name + '</span></div>';
            });
            badgeContainer.innerHTML = bhtml;
            if (typeof lucide !== 'undefined') setTimeout(function() { lucide.createIcons(); }, 50);
        }
    });
}

// --- TEACHER PROFILE ---
function loadTeacherProfile() {
    api('teacher_profile').then(function(data) {
        var u = data.user || {};
        var t = data.teacher || {};
        var setEl = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setEl('tpName', u.name || USER_NAME);
        setEl('tpSubject', t.subject || 'Teacher');
        setEl('tpExp', (t.experience || 0) + ' years experience');
        setEl('tpRating', t.rating || data.avg_rating || 0);
        setEl('tpStudents', t.total_students || data.student_count || 0);
        setEl('tpLessons', data.hw_count || 0);
        var avatarEl = document.getElementById('tpAvatar');
        if (avatarEl) avatarEl.textContent = (u.name || 'T')[0].toUpperCase();
        var descEl = document.getElementById('tpBio');
        if (descEl) descEl.textContent = (t.bio || u.name || 'Teacher') + ' is a passionate teacher.';
        // Reviews
        var reviewContainer = document.getElementById('tpReviews');
        if (reviewContainer) {
            var rhtml = '<p style="font-size:12px;color:var(--text3);margin-bottom:8px">' + data.review_count + ' reviews</p>';
            reviewContainer.innerHTML = rhtml;
        }
    });
}

// --- MY STUDENTS (Teacher) ---
function loadMyStudents() {
    var container = document.getElementById('myStudentsList');
    var statEl = document.getElementById('myStudentsStat');
    if (!container) return;
    api('my_students').then(function(students) {
        if (statEl) {
            statEl.innerHTML = '<div style="text-align:center"><div style="font-size:20px;font-weight:800;color:#4F46E5">' + students.length + '</div><div style="font-size:10px;color:var(--text3)">Total</div></div>' +
                '<div style="text-align:center"><div style="font-size:20px;font-weight:800;color:#10B981">' + students.filter(function(s){ return s.avg_score > 70; }).length + '</div><div style="font-size:10px;color:var(--text3)">Active</div></div>' +
                '<div style="text-align:center"><div style="font-size:20px;font-weight:800;color:#F59E0B">' + students.filter(function(s){ return s.avg_score <= 70; }).length + '</div><div style="font-size:10px;color:var(--text3)">Needs Help</div></div>';
        }
        var html = '';
        students.forEach(function(s) {
            var color = s.avg_score >= 80 ? '#10B981' : s.avg_score >= 60 ? '#F59E0B' : '#EF4444';
            html += '<div class="list-item" style="cursor:pointer" onclick="openStudentDetail(' + s.id + ')">' +
                '<div class="user-avatar" style="background:linear-gradient(135deg,' + color + ',' + color + '80);width:36px;height:36px;font-size:14px">' + (s.name || 'S')[0] + '</div>' +
                '<div class="list-item-content"><h5>' + (s.name || '') + '</h5><p>' + (s.class || '') + ' · Streak: ' + (s.streak || 0) + 'd</p></div>' +
                '<div style="text-align:right"><div style="font-size:14px;font-weight:700;color:' + color + '">' + (s.avg_score || 0) + '%</div></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No students found</p>';
        container.innerHTML = html;
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
    var container = document.getElementById('liveScheduleList');
    if (!container) return;
    api('live_schedule').then(function(classes) {
        var html = '';
        (classes || []).forEach(function(c) {
            var timeStr = c.time || '10:00 AM';
            html += '<div class="list-item">' +
                '<div class="list-item-content"><h5>' + (c.subject || '') + '</h5><p>' + (c.topic || '') + ' · ' + (c.class_name || '') + '</p></div>' +
                '<div style="text-align:right;font-size:11px;color:var(--text3)">' + timeStr + '<br><span style="color:#10B981;font-weight:600">' + (c.status || 'upcoming') + '</span></div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No upcoming classes</p>';
        container.innerHTML = html;
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
    api('conversations').then(function(convos) {
        var unreadTotal = convos.reduce(function(sum, c) { return sum + (c.unread || 0); }, 0);
        if (unreadEl) unreadEl.textContent = unreadTotal;
        if (totalEl) totalEl.textContent = convos.length;
        var html = '';
        convos.forEach(function(c) {
            html += '<div class="list-item" style="cursor:pointer">' +
                '<div class="user-avatar" style="background:linear-gradient(135deg,#4F46E5,#7C3AED);width:36px;height:36px;font-size:14px">' + (c.name || 'U')[0] + '</div>' +
                '<div class="list-item-content"><h5>' + (c.name || '') + '</h5><p style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px">' + (c.last_message || '') + '</p></div>' +
                '<div style="text-align:right"><div style="font-size:9px;color:var(--text3)">' + (c.time || '') + '</div>' +
                (c.unread ? '<div style="background:#4F46E5;color:white;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;margin-top:4px">' + c.unread + '</div>' : '') +
                '</div></div>';
        });
        if (!html) html = '<p style="text-align:center;padding:20px;font-size:12px;color:var(--text3)">No conversations</p>';
        container.innerHTML = html;
    });
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
