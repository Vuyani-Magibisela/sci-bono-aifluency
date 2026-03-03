# Course Enrollment and Progress Tracking Report

This report analyzes the architectural flow of course enrollment, progress tracking, and the "resume learning" functionality in the Sci-Bono AI Fluency LMS.

## 1. Course Enrollment Flow

The enrollment process establishes a formal relationship between a user and a course.

### Process Flow:
1.  **Trigger:** User clicks "Enroll" or "Start Course" on a course details page.
2.  **API Call:** The frontend sends a `POST` request to `/api/enrollments` with the `course_id`.
3.  **Database Action:** 
    -   The `EnrollmentController` validates the request.
    -   The `Enrollment` model checks if a record already exists (to handle re-enrollment).
    -   A new record is created in the `enrollments` table with:
        -   `status`: 'active'
        -   `progress_percentage`: 0
        -   `enrolled_at`: Current timestamp
4.  **Response:** The user is now authorized to access the restricted course content (modules and lessons).

## 2. Progress Tracking Mechanism

Progress is tracked at two levels: granular (lessons) and aggregate (courses).

### A. Lesson Level Tracking
-   **Start Lesson:** When a user opens a lesson, the frontend calls `POST /api/lessons/:id/start`. This creates/updates a record in the `lesson_progress` table with `status = 'in_progress'`.
-   **Complete Lesson:** When a user finishes a lesson, the frontend calls `POST /api/lessons/:id/complete`. This updates the `lesson_progress` record to `status = 'completed'` and sets the `completed_at` timestamp.

### B. Course Level Aggregation
-   Every time a lesson is completed, the backend triggers `EnrollmentModel::calculateProgress()`.
-   **Calculation:** It counts the total number of published lessons in the course and compares it against the number of `lesson_progress` records marked as 'completed' for that user.
-   **Updates:** The `enrollments` table is updated with the new `progress_percentage` and the `last_accessed_at` timestamp.

## 3. How the System "Remembers" and Resumes

The system utilizes several data points to ensure a seamless "return to learning" experience.

### Dashboard Memory
-   The **Student Dashboard** (`js/dashboard.js`) fetches all 'active' enrollments via `GET /api/courses/enrolled`.
-   It displays the **Progress Percentage** for each course, allowing the user to see exactly how much is left.
-   The "Continue Learning" button navigates the user back to the specific course page.

### Course & Module Resumption
-   When a user returns to a course page (`js/course-view.js`), the system fetches the course structure along with the user's specific progress.
-   **Module Gating:** The system "remembers" which modules are unlocked based on the completion of previous modules. A module only unlocks if:
    1.  It is the first module.
    2.  OR the previous module's **Quiz is passed** AND the **Project is submitted**.
-   This logic is handled in `CourseController::show`, which attaches `quiz_passed` and `project_submitted` flags to each module in the response.

### Resuming Lessons
-   Inside a module, the `ContentLoader` (`js/content-loader.js`) identifies which lessons have been completed by checking the `progress` object returned for each lesson.
-   This allows the UI to visually distinguish between lessons the user has already seen and those they haven't started.

## 4. Completion & Certification
-   **Automatic Completion:** When `progress_percentage` reaches 100%, the enrollment status automatically changes to `completed`.
-   **Certification:** Upon reaching 100%, the system triggers the `Certificate` model to automatically generate a unique certificate for the user, which is then displayed on their dashboard.

## 5. Summary Flow Diagram
`User` -> `Enrollment` -> `Lessons (Start/Complete)` -> `Progress Calculation` -> `Enrollment Update` -> `Dashboard UI Update` -> `Certificate Generation`
