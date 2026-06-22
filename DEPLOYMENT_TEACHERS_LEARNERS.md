# Deployment & Verification Guide: Admin Teachers & Learners Management Pages

**Date:** 2026-02-22
**Feature:** Dedicated admin pages for managing Teachers (Instructors) and Learners (Students)

---

## Files to Upload

| # | File Path | Action | Description |
|---|-----------|--------|-------------|
| 1 | `admin/instructors/teachers.html` | **NEW** | Teacher management page |
| 2 | `admin/students/learners.html` | **NEW** | Learner management page |
| 3 | `js/admin-teachers.js` | **NEW** | Teacher CRUD logic |
| 4 | `js/admin-learners.js` | **NEW** | Learner CRUD logic |
| 5 | `admin/dashboard.html` | **MODIFIED** | Sidebar updated |
| 6 | `admin/users.html` | **MODIFIED** | Sidebar updated |
| 7 | `admin/courses.html` | **MODIFIED** | Sidebar updated |
| 8 | `admin/modules.html` | **MODIFIED** | Sidebar updated |
| 9 | `admin/lessons.html` | **MODIFIED** | Sidebar updated |
| 10 | `admin/quizzes.html` | **MODIFIED** | Sidebar updated |
| 11 | `admin/projects.html` | **MODIFIED** | Sidebar updated |
| 12 | `admin/analytics.html` | **MODIFIED** | Sidebar updated |

---

## Directory Structure Created

```
admin/
├── instructors/
│   └── teachers.html        ← NEW
├── students/
│   └── learners.html         ← NEW
├── dashboard.html             ← sidebar updated
├── users.html                 ← sidebar updated
├── courses.html               ← sidebar updated
├── modules.html               ← sidebar updated
├── lessons.html               ← sidebar updated
├── quizzes.html               ← sidebar updated
├── projects.html              ← sidebar updated
└── analytics.html             ← sidebar updated

js/
├── admin-teachers.js          ← NEW
└── admin-learners.js          ← NEW
```

---

## No Backend Changes Required

The existing API endpoints already support all functionality:

- `GET /api/users?role=teacher` — List teachers (with pagination, search, school scoping)
- `GET /api/users?role=student` — List learners (with pagination, search, school scoping)
- `POST /api/users` — Create user (role hardcoded in JS to `teacher` or `student`)
- `PUT /api/users/:id` — Update user
- `DELETE /api/users/:id` — Delete user
- `GET /api/organizations` — Populate organization dropdowns
- `GET /api/schools` — Populate school dropdowns and filters

---

## Verification Test Plan

### 1. Sidebar Navigation (All 10 Admin Pages)

| Test | Steps | Expected Result |
|------|-------|-----------------|
| Links visible | Visit any admin page, check sidebar | "Instructors" and "Students" appear between "Users" and "Courses" |
| Instructors link works | Click "Instructors" in sidebar | Navigates to `/admin/instructors/teachers.html` |
| Students link works | Click "Students" in sidebar | Navigates to `/admin/students/learners.html` |
| Active state — Teachers | Visit teachers page | "Instructors" nav item is highlighted |
| Active state — Learners | Visit learners page | "Students" nav item is highlighted |
| All 8 pages consistent | Visit each existing admin page | Both new links present on all 8 pages |

### 2. Teacher Management Page (`/admin/instructors/teachers.html`)

| Test | Steps | Expected Result |
|------|-------|-----------------|
| Page loads | Navigate to `/admin/instructors/teachers.html` | Page loads, shows teacher list (filtered to role=teacher automatically) |
| Auth gate | Visit while logged out | Redirects to login page |
| Search filter | Type a name in search box | Table filters after 500ms debounce |
| School filter | Select a school from dropdown | Table shows only that school's teachers |
| Status filter | Select "Active" or "Inactive" | Table filters by status |
| Clear filters | Click "Clear" button | All filters reset, full list shown |
| Add Teacher modal | Click "Add Teacher" button | Modal opens with empty form, password required |
| Create teacher | Fill form, submit | Teacher created with role=teacher, success alert, table refreshes |
| Edit teacher | Click edit icon on a row | Modal opens pre-filled, password optional |
| Update teacher | Modify fields, submit | Teacher updated, success alert, table refreshes |
| Delete teacher | Click trash icon on a row | Confirmation prompt, then deleted on confirm |
| Pagination | Have >20 teachers | Pagination controls appear, Previous/Next/page numbers work |

### 3. Learner Management Page (`/admin/students/learners.html`)

| Test | Steps | Expected Result |
|------|-------|-----------------|
| Page loads | Navigate to `/admin/students/learners.html` | Page loads, shows student list (filtered to role=student automatically) |
| Auth gate | Visit while logged out | Redirects to login page |
| Search filter | Type a name in search box | Table filters after 500ms debounce |
| School filter | Select a school from dropdown | Table shows only that school's learners |
| Grade filter | Select a grade | Grade filter dropdown is present (client-side, for future use) |
| Status filter | Select "Active" or "Inactive" | Table filters by status |
| Add Learner modal | Click "Add Learner" button | Modal opens with empty form, grade field present |
| Create learner | Fill form, submit | Learner created with role=student, success alert |
| Edit learner | Click edit icon on a row | Modal opens pre-filled |
| Delete learner | Click trash icon on a row | Confirmation prompt, then deleted on confirm |
| Pagination | Have >20 learners | Pagination controls appear, Previous/Next/page numbers work |

### 4. Role-Based Access Control

| Test | Login As | Page | Expected Result |
|------|----------|------|-----------------|
| SuperAdmin sees all | superadmin | Teachers | All teachers across all schools visible, Add/Edit/Delete enabled |
| SuperAdmin sees all | superadmin | Learners | All learners across all schools visible, Add/Edit/Delete enabled |
| OrgAdmin scoped | orgadmin | Teachers | Only their org's teachers visible, CRUD enabled |
| OrgAdmin scoped | orgadmin | Learners | Only their org's learners visible, CRUD enabled |
| SchoolAdmin scoped | schooladmin | Teachers | Only their school's teachers, org/school auto-filled & locked in modal |
| SchoolAdmin scoped | schooladmin | Learners | Only their school's learners, org/school auto-filled & locked in modal |
| Teacher read-only | teacher | Learners | Page loads, but "Add Learner" button hidden, no Edit/Delete icons in table |
| Teacher no access | teacher | Teachers | Redirected (auth requires superadmin/orgadmin/schooladmin only) |
| Student blocked | student | Both pages | Redirected to login/unauthorized |

### 5. Modal Form Validation

| Test | Page | Steps | Expected Result |
|------|------|-------|-----------------|
| Required fields | Teachers | Submit empty form | Browser validation prevents submission |
| Password required (create) | Teachers | Leave password blank on new teacher | Error: password is required |
| Password optional (edit) | Teachers | Edit teacher, leave password blank | Updates without changing password |
| Email format | Both | Enter invalid email | Browser validation prevents submission |
| Organization required | Both | Leave organization blank | Browser validation prevents submission |
| Required fields | Learners | Submit empty form | Browser validation prevents submission |

### 6. UI & Responsiveness

| Test | Steps | Expected Result |
|------|-------|-----------------|
| Table renders | Load page with data | Table shows Name, Email, School, Title/Grade, Status, Actions columns |
| Empty state | Load with no matching data | "No teachers/learners found." message displayed |
| Loading state | Load page | "Loading teachers/learners..." spinner shown initially |
| Modal close | Click Cancel or outside modal | Modal closes, form resets |
| Sidebar user info | Load page while logged in | Sidebar shows logged-in user's name and role |

---

## Rollback Plan

If issues are found, revert the sidebar changes by removing the two `<a>` tags for Instructors and Students from each of the 8 admin HTML files. The new files (`admin/instructors/`, `admin/students/`, `js/admin-teachers.js`, `js/admin-learners.js`) can simply be deleted as they are standalone additions with no backend dependencies.
