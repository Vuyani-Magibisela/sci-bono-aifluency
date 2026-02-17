-- =====================================================
-- AI FLUENCY LMS - Production Database Schema
-- =====================================================
-- Exported from development database: 2026-02-17
-- Target database: vuyanjcb_scibono_ai_discoveryHub
-- Includes: All tables, views, triggers from migrations 001-027
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: ai_fluency_lms
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `achievement_categories`
--

DROP TABLE IF EXISTS `achievement_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `achievement_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Font Awesome icon class',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hex color code',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `achievements`
--

DROP TABLE IF EXISTS `achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `achievements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `badge_icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Font Awesome icon or image filename',
  `badge_color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#4B6EFB' COMMENT 'Hex color code',
  `tier` enum('bronze','silver','gold','platinum') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bronze',
  `points` int NOT NULL DEFAULT '10' COMMENT 'Points awarded for earning this achievement',
  `unlock_criteria` json NOT NULL COMMENT 'Criteria for unlocking (quiz scores, completion, etc)',
  `is_secret` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Hidden until unlocked',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tier` (`tier`,`is_active`),
  KEY `idx_category` (`category_id`),
  CONSTRAINT `achievements_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `achievement_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookmarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_lesson` (`user_id`,`lesson_id`),
  KEY `idx_user_bookmarks` (`user_id`,`created_at` DESC),
  KEY `idx_lesson_bookmarks` (`lesson_id`),
  KEY `idx_bookmark_lookup` (`user_id`,`lesson_id`),
  CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookmarks_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificate_templates`
--

DROP TABLE IF EXISTS `certificate_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `template_type` enum('course_completion','module_completion','quiz_achievement','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'course_completion',
  `template_data` json NOT NULL COMMENT 'Template design, layout, colors, fonts in JSON',
  `requirements` json NOT NULL COMMENT 'Completion requirements (min score, modules, etc)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_template_type` (`template_type`,`is_active`),
  CONSTRAINT `certificate_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificate_verification_log`
--

DROP TABLE IF EXISTS `certificate_verification_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_verification_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `certificate_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `verification_result` enum('valid','invalid','revoked') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_verification_attempts` (`certificate_number`,`verified_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `course_id` int NOT NULL,
  `certificate_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issued_date` date NOT NULL,
  `certificate_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_number` (`certificate_number`),
  UNIQUE KEY `verification_code` (`verification_code`),
  UNIQUE KEY `unique_certificate` (`user_id`,`course_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_verification_code` (`verification_code`),
  KEY `idx_certificate_number` (`certificate_number`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructor_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `difficulty_level` enum('beginner','intermediate','advanced') COLLATE utf8mb4_unicode_ci DEFAULT 'intermediate',
  `duration_hours` int DEFAULT '0',
  `thumbnail_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_slug` (`slug`),
  UNIQUE KEY `idx_courses_slug` (`slug`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_courses_instructor` (`instructor_id`),
  CONSTRAINT `fk_courses_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `course_id` int NOT NULL,
  `status` enum('active','completed','dropped') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `progress_percentage` decimal(5,2) DEFAULT '0.00',
  `enrolled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `last_accessed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`user_id`,`course_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_status` (`status`),
  KEY `idx_completed_at` (`completed_at`),
  KEY `idx_last_accessed_at` (`last_accessed_at`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_progress`
--

DROP TABLE IF EXISTS `lesson_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_progress` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `status` enum('not_started','in_progress','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'not_started',
  `time_spent_minutes` int DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_progress` (`user_id`,`lesson_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_status` (`status`),
  KEY `idx_completed_at_with_user` (`user_id`,`completed_at`),
  CONSTRAINT `lesson_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lessons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `order_index` int NOT NULL,
  `duration_minutes` int DEFAULT '15',
  `is_published` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_lesson_slug_module` (`module_id`,`slug`),
  KEY `idx_module_id` (`module_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_order` (`module_id`,`order_index`),
  KEY `idx_is_published` (`is_published`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` text COLLATE utf8mb4_unicode_ci,
  `objectives` text COLLATE utf8mb4_unicode_ci,
  `duration_hours` int DEFAULT '0',
  `is_published` tinyint(1) DEFAULT '0',
  `order_index` int NOT NULL,
  `thumbnail_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_slug_course` (`course_id`,`slug`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_order` (`course_id`,`order_index`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_duration` (`duration_hours`),
  CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'South Africa',
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_views`
--

DROP TABLE IF EXISTS `profile_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `viewer_user_id` int NOT NULL COMMENT 'User who viewed the profile',
  `viewed_user_id` int NOT NULL COMMENT 'User whose profile was viewed',
  `viewed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address for analytics',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'Browser user agent string',
  PRIMARY KEY (`id`),
  KEY `idx_viewed_user` (`viewed_user_id`,`viewed_at` DESC),
  KEY `idx_viewer_user` (`viewer_user_id`,`viewed_at` DESC),
  KEY `idx_viewed_at` (`viewed_at`),
  CONSTRAINT `fk_profile_views_viewed` FOREIGN KEY (`viewed_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_views_viewer` FOREIGN KEY (`viewer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_submissions`
--

DROP TABLE IF EXISTS `project_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `project_id` int NOT NULL,
  `submission_text` text COLLATE utf8mb4_unicode_ci,
  `submission_file_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_file_id` int DEFAULT NULL,
  `status` enum('submitted','graded','returned') COLLATE utf8mb4_unicode_ci DEFAULT 'submitted',
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text COLLATE utf8mb4_unicode_ci,
  `graded_by` int DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `graded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `graded_by` (`graded_by`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_status` (`status`),
  KEY `idx_uploaded_file_id` (`uploaded_file_id`),
  KEY `idx_graded_at` (`graded_at`),
  CONSTRAINT `fk_project_submissions_uploaded_file` FOREIGN KEY (`uploaded_file_id`) REFERENCES `uploaded_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_submissions_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_submissions_ibfk_3` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `module_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` int DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `requirements` text COLLATE utf8mb4_unicode_ci,
  `max_score` int DEFAULT '100',
  `due_date` date DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_slug_per_course` (`course_id`,`slug`),
  KEY `idx_module_id` (`module_id`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_course_order` (`course_id`,`order`),
  CONSTRAINT `fk_projects_course_id` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quiz_attempt_answers`
--

DROP TABLE IF EXISTS `quiz_attempt_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_attempt_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attempt_id` int NOT NULL,
  `question_id` int NOT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Snapshot of question at time of attempt',
  `user_answer` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Student answer (could be text, multiple choice index, etc)',
  `correct_answer` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Correct answer at time of attempt',
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `points_awarded` decimal(5,2) NOT NULL DEFAULT '0.00',
  `points_possible` decimal(5,2) NOT NULL DEFAULT '1.00',
  `time_spent_seconds` int DEFAULT NULL COMMENT 'Time spent on this question',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempt_answers` (`attempt_id`),
  KEY `idx_question_performance` (`question_id`,`is_correct`),
  CONSTRAINT `quiz_attempt_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quiz_attempts`
--

DROP TABLE IF EXISTS `quiz_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `quiz_id` int NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `total_questions` int NOT NULL,
  `correct_answers` int NOT NULL,
  `answers` json DEFAULT NULL,
  `passed` tinyint(1) DEFAULT '0',
  `time_taken_minutes` int DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `attempt_number` int NOT NULL DEFAULT '1' COMMENT 'Sequential attempt number for this user/quiz',
  `time_started` timestamp NULL DEFAULT NULL COMMENT 'When the attempt began',
  `time_completed` timestamp NULL DEFAULT NULL COMMENT 'When the attempt was submitted',
  `time_spent_seconds` int DEFAULT NULL COMMENT 'Total time spent in seconds',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address for academic integrity',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'Browser user agent',
  `instructor_score` decimal(5,2) DEFAULT NULL COMMENT 'Override score set by instructor',
  `instructor_feedback` text COLLATE utf8mb4_unicode_ci COMMENT 'Instructor feedback on attempt',
  `graded_by` int DEFAULT NULL COMMENT 'Instructor who graded/reviewed',
  `graded_at` timestamp NULL DEFAULT NULL COMMENT 'When instructor grading occurred',
  `status` enum('in_progress','submitted','graded','reviewed') COLLATE utf8mb4_unicode_ci DEFAULT 'submitted' COMMENT 'Attempt status',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_passed` (`passed`),
  KEY `idx_attempt_status` (`status`),
  KEY `idx_graded_by` (`graded_by`),
  KEY `idx_user_quiz_attempts` (`user_id`,`quiz_id`,`attempt_number`),
  KEY `idx_completion_time` (`time_completed`),
  KEY `idx_quiz_time` (`quiz_id`,`time_completed`),
  CONSTRAINT `fk_graded_by` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quiz_questions`
--

DROP TABLE IF EXISTS `quiz_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_id` int NOT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` json NOT NULL,
  `correct_option` int NOT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci,
  `points` int DEFAULT '1',
  `order_index` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_order` (`quiz_id`,`order_index`),
  CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_correct_option` CHECK (((`correct_option` >= 0) and (`correct_option` <= 10)))
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quizzes`
--

DROP TABLE IF EXISTS `quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quizzes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `lesson_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` text COLLATE utf8mb4_unicode_ci,
  `passing_score` int DEFAULT '70',
  `time_limit_minutes` int DEFAULT NULL,
  `max_attempts` int DEFAULT '3',
  `is_published` tinyint(1) DEFAULT '0',
  `order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_quiz_slug` (`module_id`,`slug`),
  KEY `idx_module_id` (`module_id`),
  KEY `idx_quiz_published` (`is_published`),
  KEY `idx_quiz_lesson` (`lesson_id`),
  KEY `idx_quiz_order` (`module_id`,`order`),
  CONSTRAINT `fk_quizzes_lesson_id` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `executed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schools` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emis_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `school_type` enum('primary','secondary','combined','other') COLLATE utf8mb4_unicode_ci DEFAULT 'combined',
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'South Africa',
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `principal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `principal_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `principal_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_students` int DEFAULT '0',
  `total_teachers` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_slug` (`organization_id`,`slug`),
  KEY `idx_organization` (`organization_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_emis_number` (`emis_number`),
  CONSTRAINT `schools_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2811 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_notes`
--

DROP TABLE IF EXISTS `student_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `note_content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_notes` (`user_id`,`lesson_id`),
  KEY `idx_lesson_notes` (`lesson_id`),
  KEY `idx_user_created` (`user_id`,`created_at` DESC),
  CONSTRAINT `student_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_notes_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `token_blacklist`
--

DROP TABLE IF EXISTS `token_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `token_blacklist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 hash of JWT token',
  `user_id` int NOT NULL COMMENT 'User who owns this token',
  `expires_at` timestamp NOT NULL COMMENT 'When token expires (no need to check after this)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When token was blacklisted',
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `token_blacklist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores blacklisted JWT tokens for logout functionality';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `uploaded_files`
--

DROP TABLE IF EXISTS `uploaded_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `uploaded_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('avatar','project','document') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`,`file_type`),
  KEY `idx_created` (`created_at` DESC),
  CONSTRAINT `uploaded_files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_achievement_points`
--

DROP TABLE IF EXISTS `user_achievement_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_achievement_points` (
  `user_id` int NOT NULL,
  `total_points` int NOT NULL DEFAULT '0',
  `achievements_count` int NOT NULL DEFAULT '0',
  `bronze_count` int NOT NULL DEFAULT '0',
  `silver_count` int NOT NULL DEFAULT '0',
  `gold_count` int NOT NULL DEFAULT '0',
  `platinum_count` int NOT NULL DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_leaderboard` (`total_points` DESC,`achievements_count` DESC),
  CONSTRAINT `user_achievement_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_achievements`
--

DROP TABLE IF EXISTS `user_achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_achievements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `achievement_id` int NOT NULL,
  `unlocked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `progress_data` json DEFAULT NULL COMMENT 'Progress towards achievement if partially complete',
  `notification_sent` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_achievement` (`user_id`,`achievement_id`),
  KEY `achievement_id` (`achievement_id`),
  KEY `idx_user_achievements` (`user_id`,`unlocked_at` DESC),
  KEY `idx_recent_achievements` (`unlocked_at` DESC),
  CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`vuksDev`@`localhost`*/ /*!50003 TRIGGER `after_user_achievement_insert` AFTER INSERT ON `user_achievements` FOR EACH ROW BEGIN
    DECLARE achievement_points INT;
    DECLARE achievement_tier VARCHAR(20);

    
    SELECT points, tier INTO achievement_points, achievement_tier
    FROM achievements
    WHERE id = NEW.achievement_id;

    
    INSERT INTO user_achievement_points (user_id, total_points, achievements_count, bronze_count, silver_count, gold_count, platinum_count)
    VALUES (
        NEW.user_id,
        achievement_points,
        1,
        CASE WHEN achievement_tier = 'bronze' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'silver' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'gold' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'platinum' THEN 1 ELSE 0 END
    )
    ON DUPLICATE KEY UPDATE
        total_points = total_points + achievement_points,
        achievements_count = achievements_count + 1,
        bronze_count = bronze_count + CASE WHEN achievement_tier = 'bronze' THEN 1 ELSE 0 END,
        silver_count = silver_count + CASE WHEN achievement_tier = 'silver' THEN 1 ELSE 0 END,
        gold_count = gold_count + CASE WHEN achievement_tier = 'gold' THEN 1 ELSE 0 END,
        platinum_count = platinum_count + CASE WHEN achievement_tier = 'platinum' THEN 1 ELSE 0 END;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_organizations`
--

DROP TABLE IF EXISTS `user_organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_organizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `organization_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `role_in_org` enum('student','teacher','schooladmin','orgadmin','superadmin') COLLATE utf8mb4_unicode_ci DEFAULT 'student',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_org` (`user_id`,`organization_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_organization` (`organization_id`),
  KEY `idx_is_primary` (`is_primary`),
  CONSTRAINT `user_organizations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_organizations_ibfk_2` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_schools`
--

DROP TABLE IF EXISTS `user_schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_schools` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `school_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `role_in_school` enum('student','teacher','schooladmin') COLLATE utf8mb4_unicode_ci DEFAULT 'student',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_school` (`user_id`,`school_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_is_primary` (`is_primary`),
  CONSTRAINT `user_schools_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_schools_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','prefer_not_to_say') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `profile_picture_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('student','teacher','schooladmin','orgadmin','superadmin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `primary_organization_id` int DEFAULT NULL,
  `primary_school_id` int DEFAULT NULL,
  `organizational_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_verified` tinyint(1) DEFAULT '0',
  `verification_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci COMMENT 'User biography/introduction (max 5000 chars)',
  `headline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Professional headline (max 255 chars)',
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'City, Country',
  `website_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Personal website URL',
  `github_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GitHub profile URL',
  `linkedin_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'LinkedIn profile URL',
  `twitter_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Twitter/X profile URL',
  `is_public_profile` tinyint(1) DEFAULT '1' COMMENT 'Profile visibility toggle',
  `show_email` tinyint(1) DEFAULT '0' COMMENT 'Email visibility in public profile',
  `show_achievements` tinyint(1) DEFAULT '1' COMMENT 'Achievements visibility toggle',
  `show_certificates` tinyint(1) DEFAULT '1' COMMENT 'Certificates visibility toggle',
  `profile_views_count` int DEFAULT '0' COMMENT 'Total profile view count',
  `last_profile_updated` timestamp NULL DEFAULT NULL COMMENT 'Last profile edit timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_is_public_profile` (`is_public_profile`),
  KEY `idx_last_profile_updated` (`last_profile_updated` DESC),
  KEY `idx_profile_views_count` (`profile_views_count` DESC),
  KEY `idx_last_login_at` (`last_login_at`),
  KEY `idx_primary_organization` (`primary_organization_id`),
  KEY `idx_primary_school` (`primary_school_id`),
  KEY `idx_role` (`role`),
  CONSTRAINT `fk_users_organization` FOREIGN KEY (`primary_organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_school` FOREIGN KEY (`primary_school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `v_achievement_distribution`
--

DROP TABLE IF EXISTS `v_achievement_distribution`;
/*!50001 DROP VIEW IF EXISTS `v_achievement_distribution`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_achievement_distribution` AS SELECT 
 1 AS `achievement_id`,
 1 AS `achievement_title`,
 1 AS `category_id`,
 1 AS `category_name`,
 1 AS `tier`,
 1 AS `points`,
 1 AS `unlock_count`,
 1 AS `first_unlock_date`,
 1 AS `last_unlock_date`,
 1 AS `active_months`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_at_risk_students`
--

DROP TABLE IF EXISTS `v_at_risk_students`;
/*!50001 DROP VIEW IF EXISTS `v_at_risk_students`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_at_risk_students` AS SELECT 
 1 AS `user_id`,
 1 AS `student_name`,
 1 AS `email`,
 1 AS `course_id`,
 1 AS `course_title`,
 1 AS `progress_percentage`,
 1 AS `enrolled_at`,
 1 AS `days_since_last_access`,
 1 AS `last_accessed_at`,
 1 AS `avg_quiz_score`,
 1 AS `failed_quiz_count`,
 1 AS `risk_score`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_certificate_trends`
--

DROP TABLE IF EXISTS `v_certificate_trends`;
/*!50001 DROP VIEW IF EXISTS `v_certificate_trends`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_certificate_trends` AS SELECT 
 1 AS `issue_date_day`,
 1 AS `issue_month`,
 1 AS `course_id`,
 1 AS `course_title`,
 1 AS `certificates_issued`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_course_popularity`
--

DROP TABLE IF EXISTS `v_course_popularity`;
/*!50001 DROP VIEW IF EXISTS `v_course_popularity`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_course_popularity` AS SELECT 
 1 AS `course_id`,
 1 AS `course_title`,
 1 AS `description`,
 1 AS `is_published`,
 1 AS `total_enrollments`,
 1 AS `active_enrollments`,
 1 AS `completions`,
 1 AS `avg_progress_percentage`,
 1 AS `completion_rate`,
 1 AS `last_enrollment_date`,
 1 AS `active_quiz_takers`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_enrollment_trends`
--

DROP TABLE IF EXISTS `v_enrollment_trends`;
/*!50001 DROP VIEW IF EXISTS `v_enrollment_trends`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_enrollment_trends` AS SELECT 
 1 AS `enrollment_date`,
 1 AS `enrollment_month`,
 1 AS `course_id`,
 1 AS `course_title`,
 1 AS `enrollments_count`,
 1 AS `active_count`,
 1 AS `completed_count`,
 1 AS `dropped_count`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_lesson_completion_heatmap`
--

DROP TABLE IF EXISTS `v_lesson_completion_heatmap`;
/*!50001 DROP VIEW IF EXISTS `v_lesson_completion_heatmap`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_lesson_completion_heatmap` AS SELECT 
 1 AS `user_id`,
 1 AS `lesson_id`,
 1 AS `lesson_title`,
 1 AS `module_id`,
 1 AS `module_title`,
 1 AS `completion_date`,
 1 AS `day_of_week`,
 1 AS `hour_of_day`,
 1 AS `time_spent_minutes`,
 1 AS `status`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_quiz_performance`
--

DROP TABLE IF EXISTS `v_quiz_performance`;
/*!50001 DROP VIEW IF EXISTS `v_quiz_performance`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_quiz_performance` AS SELECT 
 1 AS `quiz_id`,
 1 AS `quiz_title`,
 1 AS `module_id`,
 1 AS `module_title`,
 1 AS `unique_students`,
 1 AS `total_attempts`,
 1 AS `average_score`,
 1 AS `min_score`,
 1 AS `max_score`,
 1 AS `passed_count`,
 1 AS `failed_count`,
 1 AS `avg_time_seconds`,
 1 AS `first_attempt_date`,
 1 AS `last_attempt_date`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_student_engagement`
--

DROP TABLE IF EXISTS `v_student_engagement`;
/*!50001 DROP VIEW IF EXISTS `v_student_engagement`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_student_engagement` AS SELECT 
 1 AS `user_id`,
 1 AS `course_id`,
 1 AS `student_name`,
 1 AS `email`,
 1 AS `lessons_accessed`,
 1 AS `total_time_minutes`,
 1 AS `lessons_completed`,
 1 AS `notes_created`,
 1 AS `bookmarks_created`,
 1 AS `last_lesson_activity`,
 1 AS `enrolled_at`,
 1 AS `progress_percentage`,
 1 AS `enrollment_status`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_user_acquisition`
--

DROP TABLE IF EXISTS `v_user_acquisition`;
/*!50001 DROP VIEW IF EXISTS `v_user_acquisition`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_user_acquisition` AS SELECT 
 1 AS `signup_date`,
 1 AS `signup_month`,
 1 AS `role`,
 1 AS `new_users_count`,
 1 AS `active_users_count`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'ai_fluency_lms'
--

--
-- Final view structure for view `v_achievement_distribution`
--

/*!50001 DROP VIEW IF EXISTS `v_achievement_distribution`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_achievement_distribution` AS select `a`.`id` AS `achievement_id`,`a`.`name` AS `achievement_title`,`a`.`category_id` AS `category_id`,`ac`.`name` AS `category_name`,`a`.`tier` AS `tier`,`a`.`points` AS `points`,count(`ua`.`id`) AS `unlock_count`,min(`ua`.`unlocked_at`) AS `first_unlock_date`,max(`ua`.`unlocked_at`) AS `last_unlock_date`,count(distinct date_format(`ua`.`unlocked_at`,'%Y-%m')) AS `active_months` from ((`achievements` `a` left join `user_achievements` `ua` on((`a`.`id` = `ua`.`achievement_id`))) join `achievement_categories` `ac` on((`a`.`category_id` = `ac`.`id`))) group by `a`.`id`,`a`.`name`,`a`.`category_id`,`ac`.`name`,`a`.`tier`,`a`.`points` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_at_risk_students`
--

/*!50001 DROP VIEW IF EXISTS `v_at_risk_students`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_at_risk_students` AS select `e`.`user_id` AS `user_id`,`u`.`name` AS `student_name`,`u`.`email` AS `email`,`e`.`course_id` AS `course_id`,`c`.`title` AS `course_title`,`e`.`progress_percentage` AS `progress_percentage`,`e`.`enrolled_at` AS `enrolled_at`,(to_days(now()) - to_days(`e`.`last_accessed_at`)) AS `days_since_last_access`,`e`.`last_accessed_at` AS `last_accessed_at`,(select avg(`qa`.`score`) from (`quiz_attempts` `qa` join `quizzes` `q` on((`qa`.`quiz_id` = `q`.`id`))) where ((`qa`.`user_id` = `e`.`user_id`) and `q`.`module_id` in (select `modules`.`id` from `modules` where (`modules`.`course_id` = `e`.`course_id`)))) AS `avg_quiz_score`,(select count(0) from (`quiz_attempts` `qa` join `quizzes` `q` on((`qa`.`quiz_id` = `q`.`id`))) where ((`qa`.`user_id` = `e`.`user_id`) and `q`.`module_id` in (select `modules`.`id` from `modules` where (`modules`.`course_id` = `e`.`course_id`)) and (`qa`.`passed` = 0))) AS `failed_quiz_count`,(case when ((`e`.`progress_percentage` < 10) and ((to_days(now()) - to_days(`e`.`enrolled_at`)) > 30)) then 90 when ((`e`.`progress_percentage` < 25) and ((to_days(now()) - to_days(`e`.`last_accessed_at`)) > 14)) then 75 when ((`e`.`progress_percentage` < 50) and ((to_days(now()) - to_days(`e`.`last_accessed_at`)) > 7)) then 60 when ((to_days(now()) - to_days(`e`.`last_accessed_at`)) > 21) then 80 when ((select avg(`qa`.`score`) from (`quiz_attempts` `qa` join `quizzes` `q` on((`qa`.`quiz_id` = `q`.`id`))) where ((`qa`.`user_id` = `e`.`user_id`) and `q`.`module_id` in (select `modules`.`id` from `modules` where (`modules`.`course_id` = `e`.`course_id`)))) < 50) then 70 else 30 end) AS `risk_score` from ((`enrollments` `e` join `users` `u` on((`e`.`user_id` = `u`.`id`))) join `courses` `c` on((`e`.`course_id` = `c`.`id`))) where (`e`.`status` = 'active') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_certificate_trends`
--

/*!50001 DROP VIEW IF EXISTS `v_certificate_trends`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_certificate_trends` AS select cast(`cert`.`issued_date` as date) AS `issue_date_day`,date_format(`cert`.`issued_date`,'%Y-%m') AS `issue_month`,`cert`.`course_id` AS `course_id`,`c`.`title` AS `course_title`,count(0) AS `certificates_issued` from (`certificates` `cert` join `courses` `c` on((`cert`.`course_id` = `c`.`id`))) group by cast(`cert`.`issued_date` as date),date_format(`cert`.`issued_date`,'%Y-%m'),`cert`.`course_id`,`c`.`title` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_course_popularity`
--

/*!50001 DROP VIEW IF EXISTS `v_course_popularity`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_course_popularity` AS select `c`.`id` AS `course_id`,`c`.`title` AS `course_title`,`c`.`description` AS `description`,`c`.`is_published` AS `is_published`,count(`e`.`id`) AS `total_enrollments`,sum((case when (`e`.`status` = 'active') then 1 else 0 end)) AS `active_enrollments`,sum((case when (`e`.`status` = 'completed') then 1 else 0 end)) AS `completions`,avg(`e`.`progress_percentage`) AS `avg_progress_percentage`,((sum((case when (`e`.`status` = 'completed') then 1 else 0 end)) / nullif(count(`e`.`id`),0)) * 100) AS `completion_rate`,max(`e`.`enrolled_at`) AS `last_enrollment_date`,(select count(distinct `qa`.`user_id`) from ((`quiz_attempts` `qa` join `quizzes` `q` on((`qa`.`quiz_id` = `q`.`id`))) join `modules` `m` on((`q`.`module_id` = `m`.`id`))) where (`m`.`course_id` = `c`.`id`)) AS `active_quiz_takers` from (`courses` `c` left join `enrollments` `e` on((`c`.`id` = `e`.`course_id`))) group by `c`.`id`,`c`.`title`,`c`.`description`,`c`.`is_published` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_enrollment_trends`
--

/*!50001 DROP VIEW IF EXISTS `v_enrollment_trends`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_enrollment_trends` AS select cast(`e`.`enrolled_at` as date) AS `enrollment_date`,date_format(`e`.`enrolled_at`,'%Y-%m') AS `enrollment_month`,`e`.`course_id` AS `course_id`,`c`.`title` AS `course_title`,count(0) AS `enrollments_count`,sum((case when (`e`.`status` = 'active') then 1 else 0 end)) AS `active_count`,sum((case when (`e`.`status` = 'completed') then 1 else 0 end)) AS `completed_count`,sum((case when (`e`.`status` = 'dropped') then 1 else 0 end)) AS `dropped_count` from (`enrollments` `e` join `courses` `c` on((`e`.`course_id` = `c`.`id`))) group by cast(`e`.`enrolled_at` as date),date_format(`e`.`enrolled_at`,'%Y-%m'),`e`.`course_id`,`c`.`title` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_lesson_completion_heatmap`
--

/*!50001 DROP VIEW IF EXISTS `v_lesson_completion_heatmap`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_lesson_completion_heatmap` AS select `lp`.`user_id` AS `user_id`,`lp`.`lesson_id` AS `lesson_id`,`l`.`title` AS `lesson_title`,`l`.`module_id` AS `module_id`,`m`.`title` AS `module_title`,cast(`lp`.`completed_at` as date) AS `completion_date`,dayofweek(`lp`.`completed_at`) AS `day_of_week`,hour(`lp`.`completed_at`) AS `hour_of_day`,`lp`.`time_spent_minutes` AS `time_spent_minutes`,`lp`.`status` AS `status` from ((`lesson_progress` `lp` join `lessons` `l` on((`lp`.`lesson_id` = `l`.`id`))) join `modules` `m` on((`l`.`module_id` = `m`.`id`))) where (`lp`.`status` = 'completed') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_quiz_performance`
--

/*!50001 DROP VIEW IF EXISTS `v_quiz_performance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_quiz_performance` AS select `qa`.`quiz_id` AS `quiz_id`,`q`.`title` AS `quiz_title`,`q`.`module_id` AS `module_id`,`m`.`title` AS `module_title`,count(distinct `qa`.`user_id`) AS `unique_students`,count(`qa`.`id`) AS `total_attempts`,avg(`qa`.`score`) AS `average_score`,min(`qa`.`score`) AS `min_score`,max(`qa`.`score`) AS `max_score`,sum((case when (`qa`.`passed` = 1) then 1 else 0 end)) AS `passed_count`,sum((case when (`qa`.`passed` = 0) then 1 else 0 end)) AS `failed_count`,avg(`qa`.`time_spent_seconds`) AS `avg_time_seconds`,min(`qa`.`time_completed`) AS `first_attempt_date`,max(`qa`.`time_completed`) AS `last_attempt_date` from ((`quiz_attempts` `qa` join `quizzes` `q` on((`qa`.`quiz_id` = `q`.`id`))) join `modules` `m` on((`q`.`module_id` = `m`.`id`))) where ((`qa`.`status` = 'submitted') or (`qa`.`status` = 'graded')) group by `qa`.`quiz_id`,`q`.`title`,`q`.`module_id`,`m`.`title` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_student_engagement`
--

/*!50001 DROP VIEW IF EXISTS `v_student_engagement`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_student_engagement` AS select `e`.`user_id` AS `user_id`,`e`.`course_id` AS `course_id`,`u`.`name` AS `student_name`,`u`.`email` AS `email`,count(distinct `lp`.`lesson_id`) AS `lessons_accessed`,sum(ifnull(`lp`.`time_spent_minutes`,0)) AS `total_time_minutes`,count(distinct (case when (`lp`.`status` = 'completed') then `lp`.`lesson_id` end)) AS `lessons_completed`,count(distinct `sn`.`id`) AS `notes_created`,count(distinct `b`.`id`) AS `bookmarks_created`,max(`lp`.`updated_at`) AS `last_lesson_activity`,`e`.`enrolled_at` AS `enrolled_at`,`e`.`progress_percentage` AS `progress_percentage`,`e`.`status` AS `enrollment_status` from ((((`enrollments` `e` join `users` `u` on((`e`.`user_id` = `u`.`id`))) left join `lesson_progress` `lp` on((`e`.`user_id` = `lp`.`user_id`))) left join `student_notes` `sn` on((`e`.`user_id` = `sn`.`user_id`))) left join `bookmarks` `b` on((`e`.`user_id` = `b`.`user_id`))) group by `e`.`user_id`,`e`.`course_id`,`u`.`name`,`u`.`email`,`e`.`enrolled_at`,`e`.`progress_percentage`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_user_acquisition`
--

/*!50001 DROP VIEW IF EXISTS `v_user_acquisition`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`ai_fluency_user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_user_acquisition` AS select cast(`users`.`created_at` as date) AS `signup_date`,date_format(`users`.`created_at`,'%Y-%m') AS `signup_month`,`users`.`role` AS `role`,count(0) AS `new_users_count`,sum((case when (`users`.`is_active` = 1) then 1 else 0 end)) AS `active_users_count` from `users` group by cast(`users`.`created_at` as date),date_format(`users`.`created_at`,'%Y-%m'),`users`.`role` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-17 15:54:03

SET FOREIGN_KEY_CHECKS = 1;

-- Schema import complete
