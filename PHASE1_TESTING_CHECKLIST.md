# Phase 1: Authentication Foundation - Manual Testing Checklist

**Project**: Sci-Bono AI Fluency LMS
**Phase**: Phase 1 - Authentication Foundation
**Date Created**: November 11, 2025
**Version**: 1.0.0

---

## Test Information

**Tester Name**: ___________________________
**Test Date**: ___________________________
**Browser**: ___________________________
**Browser Version**: ___________________________
**Operating System**: ___________________________
**Screen Resolution**: ___________________________

---

## Prerequisites

Before starting tests, verify the following:

- [ ] Apache/HTTPD web server is running
- [ ] MySQL database is running
- [ ] Database `sci_bono_lms` exists and is populated
- [ ] Can access `http://localhost/` in browser
- [ ] Browser DevTools are accessible (F12)
- [ ] No browser extensions interfering (test in Incognito if needed)

**Server Status Check**:
```bash
systemctl status apache2  # or httpd
systemctl status mysql
```

---

## Test Scenarios

### Test 1: Header Template Integration ✅ CRITICAL

**Objective**: Verify dynamic header appears on all pages with correct content

**URL**: `http://localhost/index.html`

**Steps**:
1. [ ] Open browser to home page
2. [ ] Verify header contains:
   - [ ] Sci-Bono logo (blue circle with "AI")
   - [ ] "AI Fluency" title
   - [ ] Navigation links: Home, Courses, Projects, About
   - [ ] "Login" button (blue outlined)
   - [ ] "Sign Up" button (blue filled)
3. [ ] Navigate to `http://localhost/chapter1.html`
4. [ ] Verify same header structure
5. [ ] Navigate to `http://localhost/module1.html`
6. [ ] Verify same header structure
7. [ ] Navigate to `http://localhost/chapter2.html`
8. [ ] Verify same header structure

**Expected Results**:
- [ ] ✅ Header appears on all pages tested
- [ ] ✅ Header is visually consistent across pages
- [ ] ✅ All navigation links are present
- [ ] ✅ Login/Sign Up buttons are visible
- [ ] ✅ Header is responsive (test on mobile width)

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 2: User Registration ✅ CRITICAL

**Objective**: Create new user account and verify auto-login

**URL**: `http://localhost/signup.html`

**Test Data**:
- Full Name: `Test User`
- Email: `testuser@example.com` (use unique email)
- Password: `password123`
- Confirm Password: `password123`

**Steps**:
1. [ ] Click "Sign Up" button in header OR navigate directly to signup page
2. [ ] Fill in all form fields with test data above
3. [ ] Check "I agree to Terms & Conditions" checkbox
4. [ ] Click "Create Account" button
5. [ ] Observe button state during submission
6. [ ] Wait for success message
7. [ ] Wait for redirect

**Expected Results**:
- [ ] ✅ Form accepts all valid input
- [ ] ✅ Button shows spinner: "Creating Account..."
- [ ] ✅ Button is disabled during request
- [ ] ✅ Success message appears (green background)
- [ ] ✅ Message text: "Account created successfully! Redirecting..."
- [ ] ✅ Redirected to `/student-dashboard.html` after 1.5 seconds
- [ ] ✅ Header now shows user avatar and name instead of Login/Sign Up
- [ ] ✅ Avatar shows initials "TU"
- [ ] ✅ Username "Test User" is visible in header

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 3: Header User Menu (Desktop) ✅ CRITICAL

**Objective**: Verify user menu dropdown functionality

**Prerequisites**: Must be logged in (complete Test 2 first)

**URL**: Any page (e.g., `http://localhost/index.html`)

**Steps**:
1. [ ] Click on your avatar/name in header
2. [ ] Verify dropdown menu appears
3. [ ] Check dropdown contents
4. [ ] Click outside dropdown
5. [ ] Verify dropdown closes
6. [ ] Click avatar again to reopen
7. [ ] Press ESC key
8. [ ] Verify dropdown closes

**Expected Results - Dropdown Contents**:
- [ ] ✅ User's full name displayed
- [ ] ✅ User's email displayed
- [ ] ✅ User's role badge (purple "STUDENT" text)
- [ ] ✅ Horizontal divider line
- [ ] ✅ "My Profile" link with user icon
- [ ] ✅ "Logout" button with sign-out icon (red text)

**Expected Results - Interactions**:
- [ ] ✅ Dropdown opens on avatar click
- [ ] ✅ Dropdown closes on outside click
- [ ] ✅ Dropdown closes on ESC key
- [ ] ✅ Dropdown has smooth animation (fade in/out)
- [ ] ✅ Dropdown position is correct (below avatar, right-aligned)

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 4: Profile Page Access ✅ HIGH

**Objective**: Verify profile page displays user information correctly

**Prerequisites**: Must be logged in

**URL**: `http://localhost/profile.html`

**Steps**:
1. [ ] Click avatar → "My Profile" OR navigate directly to URL
2. [ ] Verify page loads without redirect
3. [ ] Check all displayed information
4. [ ] Verify avatar initials
5. [ ] Check buttons are present (even if disabled)

**Expected Results - Profile Information**:
- [ ] ✅ Page title: "My Profile"
- [ ] ✅ Large avatar with initials "TU"
- [ ] ✅ Full Name: "Test User"
- [ ] ✅ Email: "testuser@example.com"
- [ ] ✅ Role: "Student" (capitalized)
- [ ] ✅ Member Since: Current date (formatted: "November 11, 2025")

**Expected Results - UI Elements**:
- [ ] ✅ "Change Photo" button visible (disabled with note)
- [ ] ✅ "Edit Profile" button visible
- [ ] ✅ "Change Password" button visible
- [ ] ✅ Quick Stats section showing 0s
- [ ] ✅ Help text: "Statistics will load dynamically in Phase 2"

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 5: Student Dashboard Access ✅ CRITICAL

**Objective**: Verify student dashboard loads correctly for logged-in student

**Prerequisites**: Must be logged in as student

**URL**: `http://localhost/student-dashboard.html`

**Steps**:
1. [ ] Click "Dashboard" button in header OR navigate directly
2. [ ] Verify page loads successfully
3. [ ] Check welcome banner
4. [ ] Check stats cards
5. [ ] Check course section
6. [ ] Check quick links

**Expected Results - Welcome Banner**:
- [ ] ✅ "Welcome back, Test User!" heading
- [ ] ✅ "Continue your AI learning journey" subtext
- [ ] ✅ Graduation cap icon

**Expected Results - Stats Cards**:
- [ ] ✅ "Courses Enrolled" card showing 0
- [ ] ✅ "Overall Progress" card showing 0%
- [ ] ✅ "Certificates Earned" card showing 0
- [ ] ✅ "Quiz Average" card showing 0%

**Expected Results - Course Section**:
- [ ] ✅ "My Courses" heading
- [ ] ✅ "Enroll in More Courses" button
- [ ] ✅ Placeholder: "No courses yet"
- [ ] ✅ "Browse Courses" link

**Expected Results - Quick Links**:
- [ ] ✅ Module 1: AI Foundations link
- [ ] ✅ Module 2: Generative AI link
- [ ] ✅ Module 3: Advanced Search link
- [ ] ✅ Module 4: Responsible AI link

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 6: Role-Based Access Control - Instructor Dashboard ✅ CRITICAL

**Objective**: Verify students CANNOT access instructor dashboard (403 error)

**Prerequisites**: Must be logged in as student

**URL**: `http://localhost/instructor-dashboard.html`

**Steps**:
1. [ ] Navigate directly to instructor dashboard URL
2. [ ] Observe redirect behavior
3. [ ] Check 403 error page

**Expected Results**:
- [ ] ✅ Immediately redirected to `/403.html`
- [ ] ✅ NOT able to view instructor dashboard
- [ ] ✅ 403 page shows large "403" code
- [ ] ✅ 403 page shows "Access Forbidden" title
- [ ] ✅ 403 page shows ban icon (red)
- [ ] ✅ Error message: "You don't have permission to access this page"
- [ ] ✅ "Why am I seeing this?" section present
- [ ] ✅ "Go Back" button present
- [ ] ✅ "Go to Homepage" button present
- [ ] ✅ "Logout" button present

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 7: Role-Based Access Control - Admin Dashboard ✅ CRITICAL

**Objective**: Verify students CANNOT access admin dashboard (403 error)

**Prerequisites**: Must be logged in as student

**URL**: `http://localhost/admin-dashboard.html`

**Steps**:
1. [ ] Navigate directly to admin dashboard URL
2. [ ] Observe redirect behavior
3. [ ] Verify same 403 error as Test 6

**Expected Results**:
- [ ] ✅ Immediately redirected to `/403.html`
- [ ] ✅ NOT able to view admin dashboard
- [ ] ✅ Same 403 error page as Test 6

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 8: Logout Functionality ✅ CRITICAL

**Objective**: Verify logout clears session and updates UI

**Prerequisites**: Must be logged in

**URL**: Any page

**Steps**:
1. [ ] Click avatar in header
2. [ ] Click "Logout" button
3. [ ] Observe redirect
4. [ ] Check header state
5. [ ] Verify session cleared

**Expected Results**:
- [ ] ✅ Immediately redirected to `/index.html` (home page)
- [ ] ✅ Header now shows "Login" and "Sign Up" buttons
- [ ] ✅ User avatar and name are GONE from header
- [ ] ✅ User menu dropdown is no longer accessible

**Verification**:
1. [ ] Try to access `http://localhost/student-dashboard.html`
2. [ ] Expected: Redirected to `/login.html?return=/student-dashboard.html`

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 9: User Login ✅ CRITICAL

**Objective**: Verify existing user can log back in

**Prerequisites**: Must have registered account (Test 2) and be logged out (Test 8)

**URL**: `http://localhost/login.html`

**Test Data**:
- Email: `testuser@example.com`
- Password: `password123`

**Steps**:
1. [ ] Navigate to login page OR click "Login" in header
2. [ ] Enter email address
3. [ ] Enter password
4. [ ] Click "Login" button
5. [ ] Observe button state
6. [ ] Wait for success message
7. [ ] Wait for redirect

**Expected Results**:
- [ ] ✅ Form accepts input
- [ ] ✅ Button shows spinner: "Logging in..."
- [ ] ✅ Button is disabled during request
- [ ] ✅ Success message appears (green)
- [ ] ✅ Message text: "Login successful! Redirecting..."
- [ ] ✅ Redirected to `/student-dashboard.html` after 0.5 seconds
- [ ] ✅ Header shows user avatar and name again
- [ ] ✅ Username "Test User" visible in header

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 10: Return URL Redirect ✅ HIGH

**Objective**: Verify redirect to intended page after login

**Prerequisites**: Must be logged out

**URL**: `http://localhost/profile.html` (while logged out)

**Steps**:
1. [ ] Navigate directly to profile page while logged out
2. [ ] Observe redirect to login
3. [ ] Check URL in address bar
4. [ ] Login with credentials
5. [ ] Observe post-login redirect

**Expected Results**:
- [ ] ✅ Step 1: Redirected to `/login.html?return=/profile.html`
- [ ] ✅ URL contains `return=/profile.html` parameter
- [ ] ✅ After login: Redirected back to `/profile.html` (NOT dashboard)
- [ ] ✅ Profile page loads with user data

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 11: Token Persistence Across Pages ✅ HIGH

**Objective**: Verify authentication state persists when navigating between pages

**Prerequisites**: Must be logged in

**URLs**: Navigate between multiple pages

**Steps**:
1. [ ] Start at `http://localhost/index.html` (logged in)
2. [ ] Verify header shows your name
3. [ ] Navigate to `http://localhost/chapter1.html`
4. [ ] Verify header still shows your name
5. [ ] Navigate to `http://localhost/module1.html`
6. [ ] Verify header still shows your name
7. [ ] Navigate to `http://localhost/chapter2.html`
8. [ ] Verify header still shows your name
9. [ ] Navigate to `http://localhost/module2.html`
10. [ ] Verify header still shows your name

**Expected Results**:
- [ ] ✅ Auth state persists on ALL pages
- [ ] ✅ NO re-login required
- [ ] ✅ Header consistently shows user info
- [ ] ✅ No loading delays or flickers

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 12: Mobile Navigation ✅ HIGH

**Objective**: Verify mobile menu works correctly

**Prerequisites**: Must be logged in

**URL**: Any page

**Device Emulation**: Resize browser to < 768px width OR use DevTools device mode

**Steps**:
1. [ ] Resize browser to mobile width (e.g., iPhone width)
2. [ ] Verify hamburger icon appears in header
3. [ ] Verify "Login/Sign Up" buttons are hidden (showing icons only)
4. [ ] Click hamburger icon
5. [ ] Observe mobile menu
6. [ ] Check menu contents
7. [ ] Click outside menu (on overlay)
8. [ ] Verify menu closes

**Expected Results - Mobile Header**:
- [ ] ✅ Hamburger menu icon visible (3 horizontal lines)
- [ ] ✅ User avatar visible (smaller size)
- [ ] ✅ User name HIDDEN (only avatar shown)
- [ ] ✅ Button text hidden (icons only)

**Expected Results - Mobile Menu**:
- [ ] ✅ Menu slides in from right side
- [ ] ✅ Dark overlay appears behind menu
- [ ] ✅ Menu shows navigation links
- [ ] ✅ Menu shows user info at top
- [ ] ✅ Menu shows logout button at bottom
- [ ] ✅ Clicking overlay closes menu
- [ ] ✅ Menu closes with smooth animation

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 13: Error Handling - Wrong Password ✅ HIGH

**Objective**: Verify error messages display correctly

**Prerequisites**: Must be logged out

**URL**: `http://localhost/login.html`

**Test Data**:
- Email: `testuser@example.com`
- Password: `WRONGPASSWORD123` (intentionally wrong)

**Steps**:
1. [ ] Navigate to login page
2. [ ] Enter correct email
3. [ ] Enter WRONG password
4. [ ] Click "Login"
5. [ ] Observe error message
6. [ ] Click in email field
7. [ ] Observe error message behavior

**Expected Results**:
- [ ] ✅ Error message appears at top of form (red background)
- [ ] ✅ Error icon displayed (exclamation circle)
- [ ] ✅ Error text: "Login failed. Please check your credentials."
- [ ] ✅ Button re-enabled after error (can try again)
- [ ] ✅ Form remains functional
- [ ] ✅ Clicking in input field clears error message

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 14: Form Validation ✅ MEDIUM

**Objective**: Verify client-side form validation works

**URL**: `http://localhost/signup.html`

**Steps - Password Length**:
1. [ ] Enter password with only 5 characters: `pass1`
2. [ ] Try to submit
3. [ ] Expected: Error "Password must be at least 8 characters long"

**Steps - Password Mismatch**:
1. [ ] Password: `password123`
2. [ ] Confirm Password: `password456` (different)
3. [ ] Try to submit
4. [ ] Expected: Error "Passwords do not match"

**Steps - Terms Not Accepted**:
1. [ ] Fill all fields correctly
2. [ ] DO NOT check Terms & Conditions box
3. [ ] Try to submit
4. [ ] Expected: Error "Please accept the Terms & Conditions"

**Steps - Empty Fields**:
1. [ ] Leave all fields empty
2. [ ] Try to submit
3. [ ] Expected: Error "Please fill in all fields"

**Expected Results**:
- [ ] ✅ All validation rules enforced
- [ ] ✅ Clear error messages displayed
- [ ] ✅ Form prevents submission on validation errors
- [ ] ✅ Errors clear when corrected

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

### Test 15: Browser DevTools Verification ✅ MEDIUM

**Objective**: Verify tokens and data are stored correctly in browser

**Prerequisites**: Must be logged in

**URL**: Any page

**Steps - LocalStorage Inspection**:
1. [ ] Open DevTools (F12)
2. [ ] Go to "Application" tab (Chrome) or "Storage" tab (Firefox)
3. [ ] Click "Local Storage" → `http://localhost`
4. [ ] Verify stored items

**Expected LocalStorage Items**:
- [ ] ✅ `access_token` exists (long JWT string)
- [ ] ✅ `refresh_token` exists (long JWT string)
- [ ] ✅ `token_expiry` exists (Unix timestamp, ~1 hour in future)
- [ ] ✅ `user` exists (JSON object)
- [ ] ✅ User object contains: `name`, `email`, `role`, `id`

**Steps - Console Verification**:
1. [ ] Go to "Console" tab in DevTools
2. [ ] Type: `Auth.getUser()` and press Enter
3. [ ] Expected: Returns user object with your data
4. [ ] Type: `Auth.isAuthenticated()` and press Enter
5. [ ] Expected: Returns `true`
6. [ ] Type: `Auth.getUserRole()` and press Enter
7. [ ] Expected: Returns `"student"`

**Steps - Service Worker Verification**:
1. [ ] In "Application" tab, click "Service Workers"
2. [ ] Expected: See `service-worker.js` with status "activated and running"
3. [ ] Click "Cache Storage"
4. [ ] Expected: See `ai-fluency-cache-v3`
5. [ ] Expand cache
6. [ ] Expected: See cached files including:
   - [ ] `/js/storage.js`
   - [ ] `/js/api.js`
   - [ ] `/js/auth.js`
   - [ ] `/js/header-template.js`
   - [ ] `/student-dashboard.html`
   - [ ] `/profile.html`
   - [ ] `/403.html`

**Steps - Network Tab Verification** (logout and re-login):
1. [ ] Logout
2. [ ] Open "Network" tab in DevTools
3. [ ] Login again
4. [ ] Expected: See POST request to `/api/auth/login`
5. [ ] Click the request
6. [ ] Check "Response" tab
7. [ ] Expected: JSON response with `access_token`, `refresh_token`, `user`

**Status**: [ ] PASS  [ ] FAIL

**Notes**:
```
_________________________________________________________________
_________________________________________________________________
```

---

## Test Results Summary

| Test # | Test Name | Severity | Status | Notes |
|--------|-----------|----------|--------|-------|
| 1 | Header Template Integration | CRITICAL | [ ] P [ ] F | |
| 2 | User Registration | CRITICAL | [ ] P [ ] F | |
| 3 | Header User Menu | CRITICAL | [ ] P [ ] F | |
| 4 | Profile Page Access | HIGH | [ ] P [ ] F | |
| 5 | Student Dashboard | CRITICAL | [ ] P [ ] F | |
| 6 | RBAC - Instructor Dashboard | CRITICAL | [ ] P [ ] F | |
| 7 | RBAC - Admin Dashboard | CRITICAL | [ ] P [ ] F | |
| 8 | Logout Functionality | CRITICAL | [ ] P [ ] F | |
| 9 | User Login | CRITICAL | [ ] P [ ] F | |
| 10 | Return URL Redirect | HIGH | [ ] P [ ] F | |
| 11 | Token Persistence | HIGH | [ ] P [ ] F | |
| 12 | Mobile Navigation | HIGH | [ ] P [ ] F | |
| 13 | Error Handling | HIGH | [ ] P [ ] F | |
| 14 | Form Validation | MEDIUM | [ ] P [ ] F | |
| 15 | DevTools Verification | MEDIUM | [ ] P [ ] F | |

**Legend**: P = Pass, F = Fail

---

## Overall Test Results

**Total Tests**: 15
**Tests Passed**: _____
**Tests Failed**: _____
**Pass Rate**: ______%

**Critical Tests Passed**: _____ / 9
**High Priority Tests Passed**: _____ / 4
**Medium Priority Tests Passed**: _____ / 2

---

## Issues Found

| Issue # | Test # | Severity | Description | Status |
|---------|--------|----------|-------------|--------|
| 1 | | [ ] Critical [ ] High [ ] Medium [ ] Low | | [ ] Open [ ] Fixed |
| 2 | | [ ] Critical [ ] High [ ] Medium [ ] Low | | [ ] Open [ ] Fixed |
| 3 | | [ ] Critical [ ] High [ ] Medium [ ] Low | | [ ] Open [ ] Fixed |
| 4 | | [ ] Critical [ ] High [ ] Medium [ ] Low | | [ ] Open [ ] Fixed |
| 5 | | [ ] Critical [ ] High [ ] Medium [ ] Low | | [ ] Open [ ] Fixed |

**Additional Notes**:
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

---

## Browser Compatibility Testing

Test the same scenarios in multiple browsers:

| Browser | Version | OS | All Tests Pass? | Notes |
|---------|---------|----|--------------------|-------|
| Chrome | | | [ ] Yes [ ] No | |
| Firefox | | | [ ] Yes [ ] No | |
| Safari | | | [ ] Yes [ ] No | |
| Edge | | | [ ] Yes [ ] No | |
| Chrome Mobile | | Android | [ ] Yes [ ] No | |
| Safari Mobile | | iOS | [ ] Yes [ ] No | |

---

## Troubleshooting Guide

### Issue: Header doesn't appear
**Solution**:
- Check browser console for JavaScript errors
- Verify `/js/header-template.js` is loading (Network tab)
- Check if `<div id="header-placeholder"></div>` exists in HTML
- Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)

### Issue: "Failed to fetch" errors
**Solution**:
- Check Apache/HTTPD is running: `systemctl status apache2`
- Verify API is accessible: `curl http://localhost/api/auth/login`
- Check browser console for CORS errors
- Verify `.htaccess` file exists in `/api/` directory

### Issue: Tokens not persisting
**Solution**:
- Check LocalStorage is enabled in browser settings
- Disable "Block third-party cookies" in browser
- Try Incognito/Private mode to rule out extensions
- Check for JavaScript errors in console

### Issue: 404 errors on pages
**Solution**:
- Verify file exists in project root
- Check file permissions: `ls -la /var/www/html/sci-bono-aifluency/`
- Check Apache configuration allows access
- Clear browser cache and retry

### Issue: Service Worker not updating
**Solution**:
- Unregister old service worker: DevTools → Application → Service Workers → Unregister
- Hard refresh: Ctrl+Shift+R
- Clear cache: DevTools → Application → Clear Storage → Clear site data
- Restart browser

---

## Sign-Off

**Phase 1 Testing**: [ ] COMPLETE [ ] INCOMPLETE

**Recommended for Production**: [ ] YES [ ] NO [ ] WITH FIXES

**Tester Signature**: _____________________________

**Date**: _____________________________

**Additional Comments**:
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

---

## Next Steps

After completing Phase 1 testing:

- [ ] Fix any critical issues found
- [ ] Re-test failed scenarios
- [ ] Document all issues in GitHub/issue tracker
- [ ] Update PHASE1_SUMMARY.md with test results
- [ ] Get approval to proceed to Phase 2
- [ ] Begin Phase 2: Dynamic Dashboards implementation

---

**Document Version**: 1.0.0
**Last Updated**: November 11, 2025
**Created By**: Claude Code (Sonnet 4.5)
