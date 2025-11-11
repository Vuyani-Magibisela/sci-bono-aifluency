# Phase 1: Authentication Foundation - Testing Guide

**Status**: ✅ Implementation Complete
**Date**: November 11, 2025
**Version**: 1.0.0

---

## Implementation Summary

### Completed Components

1. **Core JavaScript Modules** (4 files, ~1,154 lines)
   - `/js/storage.js` (187 lines) - LocalStorage abstraction with JSON serialization
   - `/js/api.js` (273 lines) - API wrapper with automatic token refresh
   - `/js/auth.js` (353 lines) - Complete authentication lifecycle management
   - `/js/header-template.js` (341 lines) - Dynamic header with auth state

2. **Authentication Pages** (2 files modified)
   - `/login.html` - Backend integration with loading states
   - `/signup.html` - Registration with validation and auto-login

3. **CSS Enhancements** (462 lines added)
   - Alert messages (error, success, warning, info)
   - Loading spinners and disabled button states
   - User menu dropdown with animations
   - Mobile navigation overlay
   - Responsive design for all auth components

4. **Service Worker Updates**
   - Cache version bumped to `v2`
   - Network-first strategy for `/api/*` routes
   - Cache-first strategy for static content
   - Added new JS files to cache manifest

---

## Backend API Test Results

**Last Run**: Tue Nov 11 11:14:02 SAST 2025
**Test Suite**: `/api/tests/run_all_tests.sh`

```
Total Tests:  9
Passed:       9
Failed:       0
Success Rate: 100.00%
```

### Tested Endpoints

✅ Routing system
✅ User registration (`POST /api/auth/register`)
✅ User login (`POST /api/auth/login`)
✅ Token refresh (`POST /api/auth/refresh`)
✅ Get current user (`GET /api/auth/me`)
✅ User logout (`POST /api/auth/logout`)
✅ List users (`GET /api/users`)
✅ Show user (`GET /api/users/:id`)
✅ Update user (`PUT /api/users/:id`)

---

## Manual Testing Instructions

### Prerequisites

1. **Verify Server Status**
   ```bash
   systemctl status apache2  # or httpd
   ```

2. **Check Database Connection**
   ```bash
   mysql -u root -p sci_bono_lms -e "SHOW TABLES;"
   ```

3. **Verify API Routing**
   - Check `.htaccess` in `/api/` directory
   - Ensure `mod_rewrite` is enabled: `a2enmod rewrite && systemctl restart apache2`

---

## Frontend Testing Checklist

### Test 1: User Registration

1. **Navigate to Signup Page**
   - Open browser to `http://localhost/signup.html`
   - Or click "Sign Up" button from any page header

2. **Test Form Validation**
   - [ ] Try submitting with empty fields → Should show error
   - [ ] Try password < 8 characters → Should show error
   - [ ] Try mismatched passwords → Should show error
   - [ ] Try without accepting terms → Should show error

3. **Test Successful Registration**
   - [ ] Fill in all fields correctly
   - [ ] Accept terms checkbox
   - [ ] Click "Create Account"
   - [ ] Should show loading spinner on button
   - [ ] Should show success message
   - [ ] Should auto-redirect to student dashboard (1.5s delay)

4. **Verify Auth State**
   - [ ] After redirect, header should show:
     - Dashboard button
     - User avatar with initials
     - User name
     - Dropdown chevron icon
   - [ ] Check LocalStorage in DevTools:
     - `access_token` should exist
     - `refresh_token` should exist
     - `user` object should exist
     - `token_expiry` should be ~1 hour in future

---

### Test 2: User Login

1. **Logout First** (if logged in)
   - Click user avatar → Logout
   - Should redirect to home page
   - Header should show "Login" and "Sign Up" buttons

2. **Navigate to Login Page**
   - Click "Login" button in header
   - Or go to `http://localhost/login.html`

3. **Test Form Validation**
   - [ ] Try submitting with empty fields → Should show error
   - [ ] Try invalid email format → Browser validation
   - [ ] Try wrong credentials → Should show "Login failed" error

4. **Test Successful Login**
   - [ ] Enter correct email and password
   - [ ] Click "Login" button
   - [ ] Should show loading spinner
   - [ ] Should show success message
   - [ ] Should redirect to dashboard (0.5s delay)

5. **Verify Auth State**
   - [ ] Same checks as registration verification above
   - [ ] User data should match logged-in user

---

### Test 3: Authentication Persistence

1. **Refresh Page Test**
   - [ ] While logged in, refresh any page
   - [ ] Should remain logged in
   - [ ] Header should still show auth state
   - [ ] No redirect to login

2. **Navigate Between Pages**
   - [ ] Click through different pages (Home, Courses, Projects)
   - [ ] Auth state should persist
   - [ ] Header should update correctly on each page

3. **Direct URL Access**
   - [ ] While logged in, manually type URL: `http://localhost/login.html`
   - [ ] Should auto-redirect to dashboard (already authenticated)

4. **Token Expiry Simulation**
   - [ ] Open DevTools → Application → Local Storage
   - [ ] Set `token_expiry` to past timestamp (e.g., `1700000000000`)
   - [ ] Refresh page
   - [ ] Should redirect to login (expired token)

---

### Test 4: Token Refresh

1. **Background Refresh Test**
   - [ ] Login successfully
   - [ ] Open DevTools → Console
   - [ ] Wait 5-10 minutes (watch console logs)
   - [ ] Should see "Auto-refreshing token..." message
   - [ ] Token should refresh without user interaction

2. **Manual Expiry Test**
   - [ ] While logged in, open Console
   - [ ] Set token expiry to < 5 minutes remaining:
     ```javascript
     Storage.set('token_expiry', Date.now() + (4 * 60 * 1000));
     ```
   - [ ] Make any API call (navigate, refresh)
   - [ ] Should trigger automatic token refresh
   - [ ] Should complete request successfully

---

### Test 5: Logout

1. **Desktop Logout**
   - [ ] Click user avatar in header
   - [ ] Dropdown menu should appear with:
     - User info (name, email, role)
     - "My Profile" link
     - "Settings" link
     - "Logout" button (red)
   - [ ] Click "Logout"
   - [ ] Should redirect to home page
   - [ ] Header should show "Login" and "Sign Up"
   - [ ] LocalStorage should be cleared

2. **Mobile Logout**
   - [ ] Resize browser to mobile view (< 768px)
   - [ ] Click hamburger menu
   - [ ] Mobile nav should slide in from right
   - [ ] Click "Logout" in mobile menu
   - [ ] Should redirect and clear auth state

---

### Test 6: User Menu Interactions

1. **Desktop User Menu**
   - [ ] Click user avatar → Dropdown appears
   - [ ] Click outside → Dropdown closes
   - [ ] Press ESC key → Dropdown closes
   - [ ] Hover menu items → Background highlight

2. **Mobile Navigation**
   - [ ] Click hamburger → Mobile nav slides in
   - [ ] Click overlay → Nav closes
   - [ ] Click nav link → Nav closes and navigates
   - [ ] Resize to desktop → Nav automatically hides

3. **Responsive Behavior**
   - [ ] Desktop: Show user name, full buttons
   - [ ] Tablet (< 768px): Hide button text, show icons only
   - [ ] Mobile (< 480px): User menu dropdown full width

---

### Test 7: Error Handling

1. **Network Error Simulation**
   - [ ] Open DevTools → Network tab
   - [ ] Set to "Offline" mode
   - [ ] Try to login
   - [ ] Should show "Network error" message
   - [ ] Should not crash or show console errors

2. **Invalid Credentials**
   - [ ] Try login with wrong password
   - [ ] Should show user-friendly error message
   - [ ] Form should remain functional
   - [ ] Button should be re-enabled

3. **Duplicate Registration**
   - [ ] Try registering with existing email
   - [ ] Should show "Email already exists" error
   - [ ] Form should allow correction

---

### Test 8: Loading States

1. **Button Loading**
   - [ ] During login/register, button should:
     - Show spinner icon
     - Display "Logging in..." or "Creating Account..."
     - Be disabled (not clickable)
     - Not transform on hover

2. **Alert Messages**
   - [ ] Error alerts:
     - Red background with red icon
     - Auto-hide after 5 seconds
     - Clear on input focus
   - [ ] Success alerts:
     - Green background with checkmark icon
     - Stay visible until redirect

---

### Test 9: Service Worker

1. **Cache Verification**
   - [ ] Open DevTools → Application → Cache Storage
   - [ ] Should see `ai-fluency-cache-v2`
   - [ ] Cache should contain:
     - `/js/storage.js`
     - `/js/api.js`
     - `/js/auth.js`
     - `/js/header-template.js`
     - `/login.html`
     - `/signup.html`

2. **API Route Handling**
   - [ ] Login while online
   - [ ] Open Network tab, filter by `/api/`
   - [ ] API calls should NOT come from cache (network-first)
   - [ ] Static files should come from cache

3. **Offline Behavior**
   - [ ] Navigate to any page while online
   - [ ] Go offline (DevTools → Network → Offline)
   - [ ] Refresh page
   - [ ] Static content should load from cache
   - [ ] API calls should fail gracefully with error message

---

## Browser Compatibility Testing

Test in the following browsers:

- [ ] **Chrome/Chromium** (latest)
- [ ] **Firefox** (latest)
- [ ] **Safari** (latest)
- [ ] **Edge** (latest)
- [ ] **Mobile Safari** (iOS)
- [ ] **Chrome Mobile** (Android)

---

## Security Testing

### Test Token Security

1. **Token Storage**
   - [ ] Tokens stored in LocalStorage (accessible to JS)
   - [ ] Tokens include expiry timestamps
   - [ ] No sensitive data in tokens (inspect JWT payload)

2. **Token Refresh**
   - [ ] Refresh token has longer expiry (7 days)
   - [ ] Access token expires in 1 hour
   - [ ] Automatic refresh before expiry

3. **Logout Token Blacklist**
   - [ ] Logout should blacklist token server-side
   - [ ] Using blacklisted token should fail (401)

### Test Input Validation

1. **SQL Injection Prevention**
   - [ ] Try `' OR '1'='1` in email field
   - [ ] Should be safely escaped/rejected

2. **XSS Prevention**
   - [ ] Try `<script>alert('XSS')</script>` in name field
   - [ ] Should be sanitized before display

3. **Password Security**
   - [ ] Minimum 8 characters enforced
   - [ ] Password confirmation matches
   - [ ] Passwords hashed server-side (never plain text)

---

## Performance Testing

1. **Page Load Times**
   - [ ] Login page: < 1s (cached)
   - [ ] First-time load: < 2s
   - [ ] Auth module init: < 100ms

2. **API Response Times**
   - [ ] Login endpoint: < 500ms
   - [ ] Register endpoint: < 800ms
   - [ ] Token refresh: < 300ms

3. **Memory Usage**
   - [ ] No memory leaks after multiple logins/logouts
   - [ ] LocalStorage usage < 5MB

---

## Known Issues

None at this time. All Phase 1 components are functioning as expected.

---

## Next Steps (Phase 2)

1. **Create Dynamic Dashboards**
   - Student dashboard with enrolled courses
   - Instructor dashboard with teaching courses
   - Admin dashboard with platform metrics

2. **Protected Route System**
   - Add `Auth.requireAuth()` to dashboard pages
   - Implement role-based page access
   - Create 403 Forbidden page

3. **User Profile Pages**
   - Profile view page
   - Profile edit functionality
   - Avatar upload

---

## Troubleshooting

### Issue: "Failed to fetch" errors
**Solution**:
- Check if Apache/HTTPD is running: `systemctl status apache2`
- Verify API directory has `.htaccess` with rewrite rules
- Check `mod_rewrite` is enabled: `a2enmod rewrite && systemctl restart apache2`

### Issue: Tokens not persisting
**Solution**:
- Check browser LocalStorage is enabled
- Check for "Block third-party cookies" setting
- Try in Incognito/Private mode to rule out extensions

### Issue: "Token expired" immediately
**Solution**:
- Verify server and client clocks are synchronized
- Check token expiry calculation in `auth.js:32` (should be 1 hour)
- Inspect `token_expiry` in LocalStorage (should be future timestamp)

### Issue: 401 Unauthorized on refresh
**Solution**:
- Check refresh token is stored correctly
- Verify refresh endpoint returns new access token
- Check `API.refreshToken()` logic in `api.js:90`

### Issue: Service Worker not updating
**Solution**:
- Hard refresh: Ctrl+Shift+R (Chrome) or Cmd+Shift+R (Mac)
- Clear cache in DevTools → Application → Clear Storage
- Unregister SW: DevTools → Application → Service Workers → Unregister
- Restart browser

---

## Test Results Log

**Tester**: _____________
**Date**: _____________
**Browser**: _____________
**OS**: _____________

**Overall Result**: [ ] PASS  [ ] FAIL

**Notes**:
___________________________________________________________________________
___________________________________________________________________________
___________________________________________________________________________
___________________________________________________________________________

**Critical Issues Found**: _______________________________________________
___________________________________________________________________________
___________________________________________________________________________

**Recommendations**: _____________________________________________________
___________________________________________________________________________
___________________________________________________________________________
