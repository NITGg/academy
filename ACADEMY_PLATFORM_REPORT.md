# Academy Platform — Comprehensive Capabilities Report

**Platform:** Moodle 3.11.8+ (Build: 20220729)  
**Stack:** PHP 7.4 · MariaDB 10.6 · Apache · Docker  
**Theme:** Edumy (Cocoon) — Full LMS Theme  
**URL:** http://localhost:8081  
**Report Date:** 2026-06-20

---

## 1. Platform Statistics

| Metric | Count |
|--------|-------|
| Total Users | 623 |
| Total Courses | 51 (50 active + 1 site) |
| Total Enrolments | 475 |
| Quiz Questions | 4,124 |
| Quiz Attempts | 3,690 |
| Quizzes | 199 |
| Assignments | 98 |
| Resources | 1,080 (resource + resource2) |
| Database Tables | 503 |

---

## 2. User Roles

| Role | Short Name | User Count | Description |
|------|-----------|------------|-------------|
| **Student** | `student` | 3,880 | Main learners, enrolled in courses |
| **Editing Teacher** | `editingteacher` | 64 | Can create/edit course content |
| **Parent** | `parent` | 28 | Custom role — monitors student progress |
| **Non-editing Teacher** | `teacher` | 26 | Can grade and view but not edit content |
| **Manager** | `manager` | 1 | Full site management capabilities |
| **Course Creator** | `coursecreator` | 0 | Can create new courses (unused) |
| **Authenticated User** | `user` | 0 | Default role (auto-assigned) |
| **Guest** | `guest` | 0 | Browse-only access |

### Role Hierarchy
```
Manager (1)
  └── Editing Teacher (64)
        └── Non-editing Teacher (26)
              └── Student (3,880)
  └── Parent (28) — Custom role
```

---

## 3. Course Categories & Structure

### Main Categories
```
Academy
├── المرحلة الثانوية (Secondary)
│   ├── Secondary01 — 14 courses (1st Year)
│   ├── Secondary02 — 11 courses (2nd Year)
│   └── Secondary03 — 9 courses (3rd Year)
├── المرحلة الاعدادية (Preparatory)
│   ├── Prep 01 — 4 courses
│   ├── Prep 02 — 4 courses
│   └── Prep 03 — 6 courses
└── Miscellaneous — 1 course
```

### Subjects Covered
| Subject | Language | Teachers |
|---------|----------|----------|
| Physics | EN/AR | Dr. Mohamed Mekhammer, Mr. Kamal Ahmed, Mrs. Marwa Ahmed |
| Chemistry | EN/AR | Mr. Raed Roshdy, Mr. Nader Mohamed, Dr. Nabil Hashmat |
| Mathematics (Algebra/Geometry/Trig/Calculus) | EN | Mr. Haytham Mansour |
| Arabic Language | AR | Mr. Farid Shawky, Mr. Mohamed Gaber |
| History | AR | Mr. Hossam Haroun |
| Social Studies | AR | Mr. Marwan Nagy |
| Science | AR | Mr. Raed Roshdy |

---

## 4. Installed Modules & Activity Types

### Activity Modules (in use)

| Module | Instances | Status |
|--------|-----------|--------|
| `resource2` (custom) | 993 | Primary content delivery |
| `quiz` | 199 | Assessments & exams |
| `assign` | 98 | Homework & assignments |
| `resource` | 87 | File resources |
| `testnew` (custom) | 50 | Custom test module |
| `forum` | 32 | Discussion forums |
| `url` | 2 | External links |
| `page` | 1 | Text pages |

### Available but Unused Modules

| Module | Purpose | Potential |
|--------|---------|-----------|
| `googlemeet` | Live video sessions | **Tables exist, 0 events — needs configuration** |
| `chat` | Real-time messaging | Can enable for teacher-student chat |
| `lesson` | Interactive lessons | Step-by-step learning paths |
| `book` | Multi-page resources | Structured study materials |
| `wiki` | Collaborative editing | Student collaboration |
| `workshop` | Peer assessment | Student peer review |
| `feedback` | Surveys & feedback | Course evaluation |
| `glossary` | Term dictionaries | Subject glossaries |
| `h5pactivity` | Interactive content | Rich interactive exercises |
| `scorm` | SCORM packages | External course packages |
| `lti` | External tool integration | 3rd party tool embedding |

---

## 5. Existing Custom APIs

### 5.1 Main API — `academy/academyApi/json.php`
**Auth:** Token-based via Moodle webservice  
**Format:** JSON responses

| Endpoint | Function | Description |
|----------|----------|-------------|
| `termsInfo` | Terms & conditions | Returns platform terms |
| `aboutInfo` | About page | Returns about information |
| `check` | Auth check | Validates user email/credentials |
| `forget_password` | Password reset | Sends reset email via PHPMailer |
| `sign_up` | Registration | Creates new student account |
| `sign_up_new` | Registration v2 | Updated registration flow |
| `signUpParent` | Parent registration | Creates parent account linked to student |
| `get_teacher_image` | Teacher photo | Returns teacher profile image |
| `get_teacher_years` | Teacher experience | Returns years of experience |
| `get_all_categories` | Category list | Lists all course categories |
| `get_all_news` | News feed | Returns platform announcements |
| `course_view_data` | Course details | Returns course info and metadata |
| `get_promo_video` | Promo video | Returns promotional video link |
| `course_content` | Course content | Returns sections and activities |
| `get_course_contents_data` | Detailed content | Full course content with modules |
| `check_quiz_reviews` | Quiz review | Returns quiz attempt review data |
| `get_enrolled_users_members` | Enrolled users | Lists course participants |
| `teachers` | Teacher list | Returns all teachers |

### 5.2 BigBlueButton API — `academy/academyApi/api.php` & `bbb.php`
| Endpoint | Description |
|----------|-------------|
| `create_bbb_table` | Create meeting record |
| `get_join_info` | Get join URL and meeting info |
| `end_room` | End a live meeting |
| `checkReservationClassRoom` | Check if teacher has active room |
| `check_course_has_bbb` | Verify BBB availability for course |
| `bbb_check_attend` | Check student attendance |
| `get_bbb_course_meetings` | List course meetings |
| `get_reports_bbb` | Meeting attendance reports |
| `get_available_sessions` | List available live sessions |
| `get_running_session` | Get currently active session |

### 5.3 Bundle API — `academy/academyApi/bundle.php`
| Endpoint | Description |
|----------|-------------|
| `add_bundle` | Create a course bundle (name, discount, courses) |
| `update_bundle` | Update existing bundle |
| `remove_bundle` | Delete a bundle |
| `add_new_user_bundle` | Assign bundle to user |

### 5.4 General APIs — `academygeneralapis/apis.php`
| Endpoint | Description |
|----------|-------------|
| `get_course_Codes` | Get activation codes for a course |
| `set_user_mobile` | Register user device info |
| `get_user_mobile` | Get user device data |
| `get_all_user_mobile` | List all registered devices |

### 5.5 Quiz API — `academy/academyApi/quiz.php`
- Quiz report generation with role-based access (teacher/admin)
- Detailed quiz analytics per student

### 5.6 Teacher API — `academy/signleteacher/`
- Teacher profile pages and API handlers

---

## 6. Enrolment & Authentication

### Enrolment Methods
| Method | Enrolments | Status |
|--------|------------|--------|
| `manual` | 475 | **Only method in use** |
| `self` | 0 | Available (self-enrolment) |
| `guest` | 0 | Available |
| `fee` | 0 | Available (paid enrolment) |
| `paypal` | 0 | Available (PayPal payments) |
| `cohort` | 0 | Available (group enrolment) |

### Authentication Methods
| Method | Status |
|--------|--------|
| `manual` | Active |
| `email` | Available |
| `oauth2` | Available (Google/Facebook login) |
| `webservice` | Active (API auth) |
| `ldap` | Available |

---

## 7. Theme & Frontend

**Theme: Edumy (Cocoon)**  
Premium LMS theme with 100+ custom blocks:

| Block Category | Examples |
|----------------|----------|
| Hero/Slider | 8 slider variants, parallax sections |
| Course Display | 8 grid layouts, category browsers, sliders |
| Teacher Profiles | Featured teacher, user sliders |
| Testimonials | 6 testimonial styles |
| Events | Event lists, sliders, featured events |
| Pricing | Price tables (light/dark) |
| Content | Accordions, tabs, FAQs, galleries |
| Marketing | Counters, features, services, partners |
| Forms | Contact forms, subscribe forms |
| Navigation | Course info, overview, instructor blocks |

---

## 8. Payment Infrastructure

### Currently Available
| Component | Status |
|-----------|--------|
| Moodle Payment API | Tables exist (`mdl_payments`, `mdl_payment_accounts`, `mdl_payment_gateways`) |
| PayPal Gateway | Plugin installed, not configured |
| Fee Enrolment | Plugin installed, not configured |
| Bundle System | Custom API exists (basic CRUD) |

### Missing
| Component | Priority |
|-----------|----------|
| Stripe/Paymob gateway | **High** — needed for local payment methods |
| Package/subscription management | **High** — session credit system |
| Invoice/receipt generation | Medium |
| Payment webhook handlers | **High** |

---

## 9. Live Sessions Infrastructure

### Currently Available
| Component | Status |
|-----------|--------|
| Google Meet Module | **Installed** — 4 DB tables exist, 0 events configured |
| BigBlueButton (BBB) | **Custom API exists** — full meeting lifecycle management |
| BBB Attendance Tracking | Working — check attend, reports |

### Missing
| Component | Priority |
|-----------|----------|
| Google Meet configuration | **High** — plugin installed but unconfigured |
| Zoom integration | Medium — no plugin installed |
| Session scheduling system | **High** |
| Session recording management | Medium |

---

## 10. Gap Analysis — Required Features

### 10.1 Electronic Payment System
**Status:** Not implemented  
**What exists:** PayPal plugin (unconfigured), Moodle payment tables  
**What's needed:**
- [ ] Payment gateway integration (Stripe / Paymob / Fawry for Egypt)
- [ ] Payment checkout flow API
- [ ] Webhook handlers for payment confirmation
- [ ] Transaction history and receipts
- [ ] Refund management

### 10.2 Student Packages (8 / 12 / 20 sessions)
**Status:** Basic bundle system exists  
**What exists:** `bundle.php` with add/update/remove/assign  
**What's needed:**
- [ ] New DB tables: `mdl_packages`, `mdl_package_purchases`, `mdl_session_credits`
- [ ] Package tiers: 8 sessions, 12 sessions, 20 sessions
- [ ] Session credit tracking (used/remaining)
- [ ] Package expiry management
- [ ] Package purchase linked to payment system

### 10.3 Booking System (Request → Approve/Reject/Reschedule)
**Status:** Not implemented  
**What exists:** Nothing  
**What's needed:**
- [ ] New DB tables: `mdl_bookings`, `mdl_booking_slots`, `mdl_teacher_availability`
- [ ] Student flow: search teacher/subject → view availability → request booking
- [ ] Teacher flow: view requests → approve / reject / suggest new time
- [ ] Notification system (email + push)
- [ ] Calendar integration
- [ ] Session credit deduction on confirmed booking

### 10.4 Live Sessions (Google Meet / Zoom)
**Status:** Google Meet plugin installed (unconfigured), BBB API exists  
**What's needed:**
- [ ] Configure Google Meet plugin with API credentials
- [ ] Auto-generate Meet/Zoom links on booking confirmation
- [ ] Session reminders (email + notification)
- [ ] Session recording storage and playback
- [ ] Attendance tracking per session

### 10.5 Search System
**Status:** Basic Moodle search available  
**What's needed:**
- [ ] API: Search teachers by name, subject, rating, availability
- [ ] API: Search courses/subjects by category, level, language
- [ ] API: Filter by price range, schedule, rating
- [ ] Teacher profile pages with ratings and reviews

---

## 11. Required New APIs for Frontend/Mobile (Omar)

### Package APIs
| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/packages` | GET | List all available packages (8/12/20) |
| `GET /api/packages/{id}` | GET | Get package details |
| `POST /api/packages/purchase` | POST | Purchase a package (triggers payment) |
| `GET /api/user/credits` | GET | Get user's remaining session credits |
| `GET /api/user/purchases` | GET | Get user's purchase history |

### Booking APIs
| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/teachers` | GET | List/search teachers |
| `GET /api/teachers/{id}/availability` | GET | Get teacher's available slots |
| `POST /api/bookings` | POST | Request a booking (deducts credit) |
| `GET /api/bookings` | GET | List user's bookings |
| `PUT /api/bookings/{id}/approve` | PUT | Teacher approves booking |
| `PUT /api/bookings/{id}/reject` | PUT | Teacher rejects booking |
| `PUT /api/bookings/{id}/reschedule` | PUT | Teacher suggests new time |
| `PUT /api/bookings/{id}/confirm` | PUT | Student confirms rescheduled time |
| `DELETE /api/bookings/{id}` | DELETE | Cancel booking (refund credit) |

### Payment APIs
| Endpoint | Method | Description |
|----------|--------|-------------|
| `POST /api/payment/checkout` | POST | Initiate payment for package |
| `POST /api/payment/webhook` | POST | Handle payment gateway callback |
| `GET /api/payment/status/{id}` | GET | Check payment status |
| `GET /api/payment/history` | GET | User's payment history |

### Session APIs
| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/sessions/{booking_id}` | GET | Get session link (Meet/Zoom) |
| `POST /api/sessions/{booking_id}/start` | POST | Teacher starts session |
| `POST /api/sessions/{booking_id}/end` | POST | End session |
| `GET /api/sessions/upcoming` | GET | List upcoming sessions |

### Search APIs
| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/search/teachers?q=&subject=&rating=` | GET | Search teachers |
| `GET /api/search/subjects?q=&category=&level=` | GET | Search subjects |

---

## 12. Recommended New Database Tables

```sql
-- Session packages (8, 12, 20)
CREATE TABLE mdl_packages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    session_count INT NOT NULL,        -- 8, 12, or 20
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EGP',
    duration_days INT DEFAULT 90,      -- package validity
    is_active TINYINT DEFAULT 1,
    created_at BIGINT,
    updated_at BIGINT
);

-- User package purchases
CREATE TABLE mdl_package_purchases (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    userid BIGINT NOT NULL,
    packageid BIGINT NOT NULL,
    payment_id VARCHAR(255),
    sessions_total INT NOT NULL,
    sessions_used INT DEFAULT 0,
    sessions_remaining INT NOT NULL,
    status ENUM('pending','active','expired','cancelled'),
    purchased_at BIGINT,
    expires_at BIGINT
);

-- Teacher availability slots
CREATE TABLE mdl_teacher_availability (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    teacherid BIGINT NOT NULL,
    day_of_week TINYINT,              -- 0=Sun, 6=Sat
    start_time TIME,
    end_time TIME,
    is_recurring TINYINT DEFAULT 1,
    specific_date DATE DEFAULT NULL,
    is_available TINYINT DEFAULT 1
);

-- Booking requests
CREATE TABLE mdl_bookings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    studentid BIGINT NOT NULL,
    teacherid BIGINT NOT NULL,
    purchaseid BIGINT NOT NULL,        -- links to package_purchases
    subject VARCHAR(255),
    requested_date DATE,
    requested_time TIME,
    duration_minutes INT DEFAULT 60,
    status ENUM('pending','approved','rejected','rescheduled','completed','cancelled'),
    meeting_link VARCHAR(500),
    meeting_provider ENUM('googlemeet','zoom','bbb'),
    teacher_notes TEXT,
    rescheduled_date DATE DEFAULT NULL,
    rescheduled_time TIME DEFAULT NULL,
    created_at BIGINT,
    updated_at BIGINT
);

-- Payment transactions
CREATE TABLE mdl_payment_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    userid BIGINT NOT NULL,
    purchaseid BIGINT,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EGP',
    gateway VARCHAR(50),               -- stripe, paymob, fawry
    gateway_transaction_id VARCHAR(255),
    status ENUM('pending','completed','failed','refunded'),
    metadata TEXT,
    created_at BIGINT,
    updated_at BIGINT
);
```

---

## 13. Implementation Priority & Roadmap

### Phase 1 — Foundation (Week 1-2)
1. Create new database tables
2. Build Package CRUD APIs
3. Build Search APIs (teachers + subjects)
4. Configure Google Meet plugin

### Phase 2 — Booking System (Week 2-3)
1. Teacher availability management
2. Booking request/approve/reject flow
3. Notification system (email)
4. Calendar integration

### Phase 3 — Payment Integration (Week 3-4)
1. Integrate payment gateway (Stripe or Paymob)
2. Package purchase flow
3. Webhook handlers
4. Transaction history

### Phase 4 — Live Sessions (Week 4-5)
1. Auto-generate meeting links on booking approval
2. Session reminders
3. Attendance tracking
4. Session history and recordings

---

*Generated from live platform analysis — Academy Moodle Instance*
