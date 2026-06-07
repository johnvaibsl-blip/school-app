# School App - Project Tracker

## Project Info
- **Location**: C:\Users\lucif\Downloads\School App\
- **Started**: June 2025
- **Tech**: HTML, CSS, PHP, JavaScript, Lucide Icons
- **Design**: Glassmorphism, Purple Gradient, Mobile-First (430px)

---

## ✅ COMPLETED

### Student Dashboard
- [x] Dashboard screen with quick actions, subjects, live classes
- [x] AI Tutor Chat with bot responses
- [x] Browse Tutors list
- [x] Teacher Profile (compact horizontal card + stats + subscription + reviews)
- [x] Student Profile (floating avatar, PRO badge, achievements)
- [x] Homework List & Detail
- [x] Exam List, Interface & Result
- [x] Subject Library
- [x] Book Reader
- [x] Live Class Lobby & Classroom
- [x] Notifications
- [x] Search
- [x] Pricing Plans
- [x] Payment Screen (bKash/Nagad/Card)
- [x] Settings

### Teacher Dashboard
- [x] Dashboard with 3x4 grid menu (12 items)
- [x] Create Homework
- [x] Create Exam (Manual + AI with MCQ/Written/CQ/Board + options 2-6)
- [x] Student Evaluation
- [x] Earnings
- [x] Upload Content
- [x] Content Library
- [x] My Students
- [x] Student Progress
- [x] Messages
- [x] Announcements (with Edit/Delete)
- [x] Create Announcement screen
- [x] Class Schedule
- [x] Calendar
- [x] Exam Analytics
- [x] Teacher Pro Membership Card (crown, PRO badge, 5 benefits)

### Admin Dashboard
- [x] Dashboard
- [x] Library Management
- [x] Chapter Manager
- [x] AI Settings
- [x] Packages Manager
- [x] Teacher Rankings
- [x] Question Bank

### Tutor Marketplace (NEW)
- [x] Featured Teacher Hero (carousel-ready, 2 cards, dots)
- [x] Quick Stats Row (4 stats: Active Tutors, Upcoming, Attended, Study Hrs)
- [x] Top Rated Teachers (horizontal scroll, rank badges)
- [x] My Tutors (compact cards with Profile CTA)
- [x] Next Class Highlight (premium gradient card, Join Now)
- [x] Upcoming Classes (time, subject, teacher, Join CTA)
- [x] Most Popular Teachers (marketplace cards, Subscribe)
- [x] New Teachers (horizontal scroll, NEW badge)
- [x] Popular Subjects (horizontal chips with icons)
- [x] Learning Progress (2x2 grid + weekly goal bar)
- [x] Scroll-snap + carousel dots JS

### Global
- [x] Glassmorphism UI (all components)
- [x] Lucide Icons only (FA removed)
- [x] Mobile-first (max-width 430px)
- [x] Purple gradient design system
- [x] All buttons wired/navigable
- [x] Quick Actions = 4 columns, 1 row
- [x] AI question generation with configurable options (2-6)
- [x] Bangladeshi Taka symbol on all prices
- [x] No emojis, no corrupted characters
- [x] UTF-8 no BOM

---

## BACKEND (NEW)
- [x] SQLite database with schema (users, teachers, subjects, homework, exams, live_classes, announcements, chapters, messages, packages)
- [x] Seed data (5 users, 2 teachers, 10 subjects, 4 homework, 3 exams, 3 live classes, 3 announcements, 3 packages)
- [x] Login page (email/password, demo accounts, role-based redirect)
- [x] Logout system
- [x] API endpoints (login, register, subjects, homework, exams, teachers, live_classes, announcements, stats, profile)
- [x] Admin Panel (dashboard, users CRUD, subjects CRUD, homework CRUD, exams CRUD, announcements CRUD, packages CRUD)
- [x] Student Portal (dashboard with stats, live classes, homework, exams, announcements, teachers)
- [x] Teacher Portal (dashboard, create homework, create exams, create announcements, view students)

## ⬜ NOT YET DONE

### Testing
- [ ] Test all navigation paths end-to-end
- [ ] Verify Lucide icons render on all screens
- [ ] Test AI question generation flow
- [ ] Test payment selection flow
- [ ] Test homework create/detail flow
- [ ] Test exam create/attempt/result flow

### Polish
- [ ] Review glassmorphism consistency across all screens
- [ ] Check spacing/alignment on small screens (320px)
- [ ] Verify all alerts produce proper feedback
- [ ] Add any missing transitions/animations

---

## FILE STRUCTURE

```
School App/
├── index.html              # Main frontend app (35+ screens)
├── index.php               # Root redirect to login
├── index_backup.html       # Frontend backup
├── login.php               # Login page (demo accounts)
├── logout.php              # Logout handler
├── config/
│   ├── database.php        # DB config, helpers, auth
│   └── schema.sql          # SQLite schema + seed data
├── api/
│   └── index.php           # REST API endpoints
├── admin/
│   └── index.php           # Admin Panel (full CRUD)
├── teacher/
│   └── index.php           # Teacher Portal
├── student/
│   └── index.php           # Student Portal
├── css/
│   └── app.css             # Full stylesheet (3500+ lines)
├── js/
│   └── app.js              # Frontend JS functions
├── data/
│   └── school.db           # SQLite database (auto-created)
└── images/                 # (empty)
```

## NAVIGATION MAP

### Student Flow
Home → AI Tutor Chat
Home → Browse Tutors → Teacher Profile → Subscribe
Home → Profile → Settings
Home → Homework → Homework Detail
Home → Exam → Exam Interface → Exam Result
Home → Subjects → Book Reader
Home → Live Classes → Live Class Lobby → Live Classroom
Home → Notifications
Home → Search
Home → Pricing → Payment

### Teacher Flow
Teacher Dashboard → Create Homework
Teacher Dashboard → Create Exam
Teacher Dashboard → Start Live Class
Teacher Dashboard → Upload Content → Content Library
Teacher Dashboard → My Students → Student Progress
Teacher Dashboard → Messages
Teacher Dashboard → Announcements → Create Announcement
Teacher Dashboard → Class Schedule → Calendar
Teacher Dashboard → Exam Analytics
Teacher Dashboard → Earnings

### Admin Flow
Admin Dashboard → Library Management → Chapter Manager
Admin Dashboard → AI Settings
Admin Dashboard → Packages Manager
Admin Dashboard → Teacher Rankings
Admin Dashboard → Question Bank

---

## KEY DESIGN DECISIONS
- Single HTML file, show/hide navigation
- Lucide Icons via unpkg CDN
- Glassmorphism via CSS classes (.glass, .glass-card, etc.)
- Teacher menu: 3x4 grid, 12 items, no section dividers
- Quick actions: 4 columns, 1 row
- Currency: Bangladeshi Taka (৳)
- Teacher Pro: Crown icon, PRO badge, purple gradient header
