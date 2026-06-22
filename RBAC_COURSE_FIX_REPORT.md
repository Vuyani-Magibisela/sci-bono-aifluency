# Report: Role-Based Access Control (RBAC) and Course Persistence Issues

This report investigates why the "Enroll" button is failing to transition to "Continue Learning" following the implementation of hierarchical Role-Based Access Control (RBAC).

## 1. The Core Issue: Missing User Context in Course Queries
The transition from a flat role system to a hierarchical one (SuperAdmin, OrgAdmin, SchoolAdmin, Teacher, Student) has impacted how the system identifies the "Current User" during course data retrieval.

### Symptom
-   The API returns course data but omits the `is_enrolled` and `completion_percentage` fields.
-   The frontend, receiving no enrollment data, defaults to showing the **"Enroll Now"** button.
-   In some cases, the API crashes (500 error) or returns an empty response ("Unexpected end of JSON data").

## 2. Root Causes Identified

### A. Hierarchical Permission Check "Deadlocks"
In `BaseController.php`, the `getCurrentUser()` method now performs an additional database query to fetch `primary_organization_id` and `primary_school_id`.
If this query fails (e.g., due to a missing column or an inactive user), it calls `Response::unauthorized()`, which executes `exit;`. 
*   **Result:** The `CourseController` stops executing before it can attach the user's enrollment status to the course object.

### B. Conditional Enrichment Failure
In `CourseController::index`, the logic to show enrollment status is wrapped in a check for `$currentUser`:
```php
if ($currentUser) {
    $enrollment = $this->enrollmentModel->getUserEnrollment($currentUser->id, $course->id);
    $course->is_enrolled = $enrollment !== null;
    // ...
}
```
If `JWTHandler::getCurrentUser()` fails to parse the token (perhaps due to a mismatch between the token's payload and the new role hierarchy), `$currentUser` becomes `null`, and the system treats the user as a "Guest," even if they are logged in.

### C. Placeholder Logic in Dashboard
The "Continue Learning" functionality on the main student dashboard is currently a placeholder:
```javascript
// js/dashboard.js
continueCourse(courseId) {
    alert(`Course navigation will be implemented in Phase 5.`);
}
```
This prevents users from actually navigating back to their last lesson from the dashboard view.

## 3. Findings on Instructor/Admin "School-Only" Visibility
The user requested that Instructors and SchoolAdmins only see users/stats within their own school. The current implementation in `UserController.php` handles this:
*   **SchoolAdmin:** Scoped to `primary_school_id`.
*   **OrgAdmin:** Scoped to `primary_organization_id`.
*   **SuperAdmin:** No scoping (system-wide).
*   **Teacher/Student:** Forbidden from listing users.

This logic is sound, but if a Teacher or Student tries to access a restricted list, the API returns a 403 Forbidden and exits, which might be interpreted by the frontend as a data failure if not handled gracefully.

## 4. Required Fixes

1.  **Stable Context Loading:** Ensure `BaseController::getCurrentUser()` handles missing database columns gracefully to prevent script termination during course listing.
2.  **Implementation of Dashboard Navigation:** Update `js/dashboard.js` to replace the `alert` with a redirect to `/student/course-view.html?course_id=${courseId}`.
3.  **Frontend Graceful Handling:** Update `js/student-courses.js` to handle 403/401 errors without crashing the entire course grid.
4.  **Database Sync:** Verify that the `users` table contains the `primary_organization_id` and `primary_school_id` columns introduced in Phase 12.

## 5. Summary
The "forgetting" behavior is a **visibility issue**, not a data loss issue. The data exists in the database, but the API's new, stricter security checks are preventing that data from being "seen" by the frontend unless the user context is perfectly loaded.
