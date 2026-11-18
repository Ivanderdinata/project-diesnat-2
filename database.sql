-- ============================================================
--  Quiz Game Interaktif — Struktur Database + Data Contoh
--  Kompatibel: MySQL 5.7+ / MySQL 8.x / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `quiz_diesnat`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `quiz_diesnat`;

-- ------------------------------------------------------------
-- Admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Soal
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `questions` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question`       TEXT         NOT NULL,
    `option_a`       VARCHAR(255) NOT NULL,
    `option_b`       VARCHAR(255) NOT NULL,
    `option_c`       VARCHAR(255) NOT NULL,
    `option_d`       VARCHAR(255) NOT NULL,
    `correct_answer` ENUM('A','B','C','D') NOT NULL,
    `explanation`    TEXT         NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_questions_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pemain
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `players` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(50)  NOT NULL,
    `kelas`      VARCHAR(30)  NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_players_name_kelas` (`name`, `kelas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Sesi kuis
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_sessions` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`        INT UNSIGNED NOT NULL,
    `token`            CHAR(64)     NOT NULL,
    `question_order`   TEXT         NOT NULL,
    `current_index`    INT          NOT NULL DEFAULT 0,
    `total_score`      INT          NOT NULL DEFAULT 0,
    `correct_count`    INT          NOT NULL DEFAULT 0,
    `total_time_ms`    INT          NOT NULL DEFAULT 0,
    `question_sent_at` DATETIME(3)  NULL DEFAULT NULL,
    `finished_at`      DATETIME     NULL DEFAULT NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sessions_token` (`token`),
    KEY `idx_sessions_leaderboard` (`total_score` DESC, `total_time_ms` ASC),
    KEY `idx_sessions_player` (`player_id`),
    CONSTRAINT `fk_sessions_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jawaban per sesi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `session_answers` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quiz_session_id` INT UNSIGNED NOT NULL,
    `question_id`     INT UNSIGNED NOT NULL,
    `answer`          ENUM('A','B','C','D') NULL DEFAULT NULL,
    `is_correct`      TINYINT(1)   NOT NULL DEFAULT 0,
    `time_taken_ms`   INT          NOT NULL DEFAULT 0,
    `score`           INT          NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_answers_session_question` (`quiz_session_id`, `question_id`),
    KEY `idx_answers_question` (`question_id`),
    CONSTRAINT `fk_answers_session` FOREIGN KEY (`quiz_session_id`) REFERENCES `quiz_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pengaturan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(50)  NOT NULL,
    `setting_value` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Percobaan login (rate limit)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`  VARCHAR(45)  NOT NULL,
    `username`    VARCHAR(50)  NOT NULL DEFAULT '',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_ip_time` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Data awal
-- ------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('question_duration', '15'),
    ('questions_per_session', '10'),
    ('quiz_title', 'Quiz Dies Nat'),
    ('quiz_subtitle', 'Jawab cepat, jawab tepat, jadilah juara!')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Password default admin: admin123  (SEGERA GANTI setelah login)
INSERT INTO `admins` (`username`, `password_hash`) VALUES
    ('admin', '$2y$10$zZl.TziOXmULiuXATM6BP.uolxOpdmfYFijFX783c3kqAuNO51JJ.')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

INSERT INTO `questions` (`question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`) VALUES
    ('Apa ibu kota Indonesia?', 'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'A', 'Jakarta adalah ibu kota negara Indonesia.'),
    ('Hasil dari 12 × 8 adalah ...', '86', '96', '104', '92', 'B', '12 × 8 = 96.'),
    ('Planet terdekat dengan Matahari adalah ...', 'Venus', 'Bumi', 'Merkurius', 'Mars', 'C', 'Merkurius adalah planet terdekat dari Matahari.'),
    ('Siapa penemu lampu pijar yang dikenal dunia?', 'Nikola Tesla', 'Thomas Edison', 'Alexander Bell', 'Isaac Newton', 'B', 'Thomas Alva Edison mengembangkan lampu pijar yang praktis.'),
    ('Bahasa pemrograman yang berjalan di server dan banyak dipakai untuk web adalah ...', 'PHP', 'HTML', 'CSS', 'SQL', 'A', 'PHP adalah bahasa skrip sisi server yang populer untuk web.'),
    ('Organ tubuh manusia yang memompa darah adalah ...', 'Paru-paru', 'Hati', 'Ginjal', 'Jantung', 'D', 'Jantung memompa darah ke seluruh tubuh.'),
    ('Ibukota provinsi Jawa Barat adalah ...', 'Semarang', 'Bandung', 'Serang', 'Yogyakarta', 'B', 'Bandung adalah ibu kota Provinsi Jawa Barat.'),
    ('Nilai dari 15% × 200 adalah ...', '20', '25', '30', '35', 'C', '15% dari 200 = 0,15 × 200 = 30.'),
    ('Perangkat keras yang berfungsi sebagai otak komputer adalah ...', 'RAM', 'CPU', 'Harddisk', 'Monitor', 'B', 'CPU (Central Processing Unit) memproses instruksi program.'),
    ('Lagu "Indonesia Raya" diciptakan oleh ...', 'W.R. Supratman', 'Ismail Marzuki', 'C. Simanjuntak', 'Kusbini', 'A', 'Wage Rudolf Supratman adalah pencipta lagu Indonesia Raya.'),
    ('Benda langit yang mengelilingi planet disebut ...', 'Bintang', 'Komet', 'Satelit', 'Asteroid', 'C', 'Satelit alami seperti Bulan mengelilingi planet.'),
    ('Hasil dari 7² + 5 adalah ...', '54', '45', '49', '52', 'A', '7² = 49, lalu 49 + 5 = 54.');
