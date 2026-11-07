-- ============================================================
-- RATTIL — rattil_db.sql
-- MySQL 8.0+ | utf8mb4_unicode_ci
-- نسخة مضمونة 100% لـ phpMyAdmin
-- ============================================================

SET NAMES utf8mb4;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ① تعطيل FK قبل كل عملية
SET FOREIGN_KEY_CHECKS=0;

-- ② إنشاء/اختيار قاعدة البيانات
CREATE DATABASE IF NOT EXISTS `rattil_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `rattil_db`;

-- ③ حذف الجداول (بترتيب آمن — FK معطّل)
DROP TABLE IF EXISTS `memorization_plan`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `student_garden`;
DROP TABLE IF EXISTS `student_badges`;
DROP TABLE IF EXISTS `student_points`;
DROP TABLE IF EXISTS `quran_tracking`;
DROP TABLE IF EXISTS `student_quran_marks`;
DROP TABLE IF EXISTS `recitation_log`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `halqa_students`;
DROP TABLE IF EXISTS `halaqat`;
DROP TABLE IF EXISTS `badges`;
DROP TABLE IF EXISTS `users`;

-- ④ إعادة تشغيل FK بعد الحذف
SET FOREIGN_KEY_CHECKS=1;

-- ============================================================
-- إنشاء الجداول بدون FOREIGN KEY أولاً
-- ============================================================

CREATE TABLE `users` (
    `id`         INT           NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)  NOT NULL,
    `email`      VARCHAR(150)  NOT NULL,
    `password`   VARCHAR(255)  NOT NULL,
    `role`       ENUM('admin','teacher','student') NOT NULL,
    `avatar`     VARCHAR(255)  DEFAULT NULL,
    `phone`      VARCHAR(20)   DEFAULT NULL,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `badges` (
    `id`              INT          NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(100) NOT NULL,
    `description`     TEXT,
    `icon`            VARCHAR(50)  DEFAULT NULL,
    `type`            ENUM('points_based','teacher_granted') NOT NULL,
    `points_required` INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `halaqat` (
    `id`            INT           NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)  NOT NULL,
    `teacher_id`    INT           DEFAULT NULL,
    `schedule_days` VARCHAR(200)  DEFAULT NULL,
    `schedule_time` TIME          DEFAULT NULL,
    `location`      VARCHAR(200)  DEFAULT NULL,
    `max_students`  INT           NOT NULL DEFAULT 20,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_teacher` (`teacher_id`),
    INDEX `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `halqa_students` (
    `id`         INT       NOT NULL AUTO_INCREMENT,
    `halqa_id`   INT       NOT NULL,
    `student_id` INT       NOT NULL,
    `joined_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_halqa_student` (`halqa_id`,`student_id`),
    INDEX `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance` (
    `id`         INT       NOT NULL AUTO_INCREMENT,
    `halqa_id`   INT       NOT NULL,
    `student_id` INT       NOT NULL,
    `teacher_id` INT       NOT NULL,
    `date`       DATE      NOT NULL,
    `status`     ENUM('present','absent','late','excused') NOT NULL,
    `note`       TEXT      DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_att` (`halqa_id`,`student_id`,`date`),
    INDEX `idx_att_student` (`student_id`),
    INDEX `idx_att_date`    (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recitation_log` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `student_id`     INT          NOT NULL,
    `teacher_id`     INT          NOT NULL,
    `halqa_id`       INT          DEFAULT NULL,
    `surah_number`   INT          NOT NULL,
    `surah_name`     VARCHAR(100) NOT NULL,
    `ayah_from`      INT          NOT NULL,
    `ayah_to`        INT          NOT NULL,
    `grade`          ENUM('ممتاز','جيد جداً','جيد','مقبول','يحتاج مراجعة') NOT NULL,
    `notes`          TEXT         DEFAULT NULL,
    `points_awarded` INT          NOT NULL DEFAULT 0,
    `recorded_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rl_student`  (`student_id`),
    INDEX `idx_rl_teacher`  (`teacher_id`),
    INDEX `idx_rl_recorded` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_quran_marks` (
    `id`           INT       NOT NULL AUTO_INCREMENT,
    `student_id`   INT       NOT NULL,
    `surah_number` INT       NOT NULL,
    `ayah_number`  INT       NOT NULL,
    `status`       ENUM('memorized','last_stop','reviewing') NOT NULL DEFAULT 'memorized',
    `marked_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_mark` (`student_id`,`surah_number`,`ayah_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quran_tracking` (
    `id`           INT       NOT NULL AUTO_INCREMENT,
    `student_id`   INT       NOT NULL,
    `surah_number` INT       NOT NULL,
    `ayah_number`  INT       NOT NULL,
    `status`       ENUM('memorized','reviewing','last_stop') NOT NULL,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ayah` (`student_id`,`surah_number`,`ayah_number`),
    INDEX `idx_qt_student_surah` (`student_id`,`surah_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_points` (
    `id`           INT       NOT NULL AUTO_INCREMENT,
    `student_id`   INT       NOT NULL,
    `points`       INT       NOT NULL DEFAULT 0,
    `total_earned` INT       NOT NULL DEFAULT 0,
    `level`        INT       NOT NULL DEFAULT 1,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_badges` (
    `id`         INT       NOT NULL AUTO_INCREMENT,
    `student_id` INT       NOT NULL,
    `badge_id`   INT       NOT NULL,
    `granted_by` INT       DEFAULT NULL,
    `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_student_badge` (`student_id`,`badge_id`),
    INDEX `idx_badge` (`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_garden` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `student_id`   INT          NOT NULL,
    `tree_type`    ENUM('shrub','palm','oak') NOT NULL,
    `surah_name`   VARCHAR(100) NOT NULL,
    `surah_number` INT          NOT NULL DEFAULT 0,
    `planted_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_garden` (`student_id`,`surah_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `user_id`    INT          NOT NULL,
    `type`       VARCHAR(50)  NOT NULL,
    `title`      VARCHAR(200) NOT NULL,
    `body`       TEXT         DEFAULT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user`    (`user_id`),
    INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
    `id`          INT       NOT NULL AUTO_INCREMENT,
    `sender_id`   INT       NOT NULL,
    `receiver_id` INT       NOT NULL,
    `content`     TEXT      NOT NULL,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    `sent_at`     TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sender`   (`sender_id`),
    INDEX `idx_receiver` (`receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memorization_plan` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `student_id`   INT          NOT NULL,
    `halqa_id`     INT          DEFAULT NULL,
    `surah_number` INT          NOT NULL,
    `surah_name`   VARCHAR(100) NOT NULL DEFAULT '',
    `ayah_from`    INT          NOT NULL,
    `ayah_to`      INT          NOT NULL,
    `target_date`  DATE         DEFAULT NULL,
    `status`       ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_mp_student` (`student_id`),
    INDEX `idx_mp_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- إضافة Foreign Keys بعد إنشاء كل الجداول
-- ============================================================

ALTER TABLE `halaqat`
    ADD CONSTRAINT `fk_halaqat_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `halqa_students`
    ADD CONSTRAINT `fk_hs_halqa`
    FOREIGN KEY (`halqa_id`) REFERENCES `halaqat`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_hs_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `attendance`
    ADD CONSTRAINT `fk_att_halqa`
    FOREIGN KEY (`halqa_id`) REFERENCES `halaqat`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_att_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_att_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `recitation_log`
    ADD CONSTRAINT `fk_rl_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_rl_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_rl_halqa`
    FOREIGN KEY (`halqa_id`) REFERENCES `halaqat`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `student_quran_marks`
    ADD CONSTRAINT `fk_sqm_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `quran_tracking`
    ADD CONSTRAINT `fk_qt_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `student_points`
    ADD CONSTRAINT `fk_sp_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `student_badges`
    ADD CONSTRAINT `fk_sb_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_sb_badge`
    FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_sb_granter`
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `student_garden`
    ADD CONSTRAINT `fk_sg_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `notifications`
    ADD CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `messages`
    ADD CONSTRAINT `fk_msg_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_msg_receiver`
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `memorization_plan`
    ADD CONSTRAINT `fk_mp_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_mp_halqa`
    FOREIGN KEY (`halqa_id`) REFERENCES `halaqat`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================
-- البيانات الافتراضية
-- ============================================================

INSERT INTO `badges` (`name`,`description`,`icon`,`type`,`points_required`) VALUES
('نجم مبتدئ',    'وصلت إلى 100 نقطة',   '⭐', 'points_based',    100),
('حافظ متميز',   'وصلت إلى 500 نقطة',   '🌟', 'points_based',    500),
('قارئ ألفي',    'وصلت إلى 1000 نقطة',  '🏆', 'points_based',   1000),
('عطاء مضاعف',   'وصلت إلى 2000 نقطة',  '💎', 'points_based',   2000),
('أفضل طالب',    'منحها المعلم',          '🥇', 'teacher_granted',   0),
('الأكثر تسميعاً','منحها المعلم',         '📖', 'teacher_granted',   0),
('المواظب',       'منحها المعلم',         '✅', 'teacher_granted',   0);

-- كلمات المرور: Admin@123 | Teacher@123 | Student@123
INSERT INTO `users` (`name`,`email`,`password`,`role`,`phone`) VALUES
('المدير',      'admin@rattil.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   '0599000001'),
('الشيخ أحمد', 'teacher@rattil.com',  '$2y$10$TKh8H1.PdZBRCFBFcVAXPO5Rs0UjhbL4pCEzKCGZVkVwYWI9P6M5i','teacher', '0599000002'),
('محمد علي',   'student@rattil.com',  '$2y$10$MkFhP1sTSCLxBLaLJRIPmu6BKcmhtnXpRN6kp.tq1PL7v3wniVo0y','student', '0599000003'),
('أحمد محمود', 'ahmed@rattil.com',    '$2y$10$MkFhP1sTSCLxBLaLJRIPmu6BKcmhtnXpRN6kp.tq1PL7v3wniVo0y','student', '0599000004'),
('يوسف إبراهيم','yousuf@rattil.com',  '$2y$10$MkFhP1sTSCLxBLaLJRIPmu6BKcmhtnXpRN6kp.tq1PL7v3wniVo0y','student', '0599000005'),
('عمر خالد',   'omar@rattil.com',     '$2y$10$MkFhP1sTSCLxBLaLJRIPmu6BKcmhtnXpRN6kp.tq1PL7v3wniVo0y','student', '0599000006'),
('الشيخة فاطمة','teacher2@rattil.com','$2y$10$TKh8H1.PdZBRCFBFcVAXPO5Rs0UjhbL4pCEzKCGZVkVwYWI9P6M5i','teacher', '0599000007');

INSERT INTO `halaqat` (`name`,`teacher_id`,`schedule_days`,`schedule_time`,`location`,`max_students`,`status`) VALUES
('حلقة الفجر',  2, 'السبت,الاثنين,الأربعاء', '07:00:00', 'المسجد الكبير — القاعة أ',    20, 'active'),
('حلقة العصر',  2, 'الأحد,الثلاثاء,الخميس',  '16:00:00', 'المسجد الكبير — القاعة ب',    15, 'active'),
('حلقة المساء', 7, 'الاثنين,الأربعاء,الجمعة', '19:00:00', 'مركز رتِّل — الطابق الثاني', 20, 'active');

INSERT INTO `halqa_students` (`halqa_id`,`student_id`) VALUES
(1,3),(1,4),(1,5),(2,6),(3,3),(3,4);

INSERT INTO `student_points` (`student_id`,`points`,`total_earned`,`level`) VALUES
(3,250,250,1),(4,120,120,1),(5,80,80,1),(6,50,50,1);

INSERT INTO `recitation_log`
    (`student_id`,`teacher_id`,`halqa_id`,`surah_number`,`surah_name`,`ayah_from`,`ayah_to`,`grade`,`notes`,`points_awarded`) VALUES
(3,2,1,114,'الناس',  1,6,'ممتاز',   'تلاوة ممتازة',20),
(3,2,1,113,'الفلق',  1,5,'ممتاز',   'أداء رائع',   20),
(3,2,1,112,'الإخلاص',1,4,'جيد جداً','يحسّن المخارج',15),
(4,2,1,114,'الناس',  1,6,'جيد',     'يراجع التجويد',10),
(5,2,1,114,'الناس',  1,3,'مقبول',   'بداية جيدة',  5);

INSERT INTO `attendance` (`halqa_id`,`student_id`,`teacher_id`,`date`,`status`,`note`) VALUES
(1,3,2,CURDATE(),'present',NULL),
(1,4,2,CURDATE(),'present',NULL),
(1,5,2,CURDATE(),'absent', 'لم يحضر'),
(1,3,2,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'present',NULL),
(1,4,2,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'late',   'تأخر 10 دقائق'),
(1,5,2,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'excused','مرض');

INSERT INTO `student_quran_marks` (`student_id`,`surah_number`,`ayah_number`,`status`) VALUES
(3,114,1,'memorized'),(3,114,2,'memorized'),(3,114,3,'memorized'),
(3,114,4,'memorized'),(3,114,5,'memorized'),(3,114,6,'memorized'),
(3,113,1,'memorized'),(3,113,2,'memorized'),(3,113,3,'memorized'),
(3,113,4,'memorized'),(3,113,5,'memorized'),
(3,112,1,'memorized'),(3,112,2,'memorized'),
(3,112,3,'reviewing'),(3,112,4,'last_stop');

INSERT INTO `quran_tracking` (`student_id`,`surah_number`,`ayah_number`,`status`) VALUES
(3,114,1,'memorized'),(3,114,2,'memorized'),(3,114,3,'memorized'),
(3,114,4,'memorized'),(3,114,5,'memorized'),(3,114,6,'memorized'),
(3,113,1,'memorized'),(3,113,2,'memorized'),(3,113,3,'memorized'),
(3,113,4,'memorized'),(3,113,5,'memorized'),
(3,112,1,'memorized'),(3,112,2,'memorized'),
(3,112,3,'reviewing'),(3,112,4,'last_stop');

INSERT INTO `student_garden` (`student_id`,`tree_type`,`surah_name`,`surah_number`) VALUES
(3,'shrub','الناس',114),(3,'shrub','الفلق',113);

INSERT INTO `student_badges` (`student_id`,`badge_id`,`granted_by`) VALUES (3,1,NULL);

INSERT INTO `memorization_plan`
    (`student_id`,`halqa_id`,`surah_number`,`surah_name`,`ayah_from`,`ayah_to`,`target_date`,`status`) VALUES
(3,1,111,'المسد',    1,5,DATE_ADD(CURDATE(),INTERVAL  7 DAY),'pending'),
(3,1,110,'النصر',    1,3,DATE_ADD(CURDATE(),INTERVAL 14 DAY),'pending'),
(3,1,109,'الكافرون', 1,6,DATE_ADD(CURDATE(),INTERVAL 21 DAY),'pending'),
(4,1,114,'الناس',    1,6,DATE_ADD(CURDATE(),INTERVAL  7 DAY),'in_progress');

INSERT INTO `notifications` (`user_id`,`type`,`title`,`body`,`is_read`) VALUES
(3,'badge',      'شارة جديدة! 🏅','حصلت على شارة: نجم مبتدئ — وصلت إلى 100 نقطة',0),
(3,'recitation', 'تسميع جديد 📖', 'رصد المعلم الشيخ أحمد تسميع سورة الناس — الدرجة: ممتاز',0),
(4,'attendance', 'تسجيل غياب',    'سجّل الشيخ أحمد غيابك بتاريخ اليوم',0);

-- ============================================================
-- END OF FILE
-- ============================================================
