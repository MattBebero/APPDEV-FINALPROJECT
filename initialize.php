<?php
declare(strict_types=1);

const CAMPUS_ONE_SCHEMA_VERSION = '2';

function databaseIsInitialized(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'schema_version'");
        $stmt->execute();
        return (string) $stmt->fetchColumn() === CAMPUS_ONE_SCHEMA_VERSION;
    } catch (PDOException) {
        // The settings table does not exist yet, so the first-run setup is needed.
        return false;
    }
}

function initializeDatabase(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('student','admin') NOT NULL,
            first_name VARCHAR(80) NOT NULL,
            last_name VARCHAR(80) NOT NULL,
            student_number VARCHAR(40) NULL UNIQUE,
            phone VARCHAR(30) NULL,
            address VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS balances (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL UNIQUE,
            tuition DECIMAL(12,2) NOT NULL DEFAULT 0,
            miscellaneous DECIMAL(12,2) NOT NULL DEFAULT 0,
            payments DECIMAL(12,2) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_balance_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS courses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(30) NOT NULL UNIQUE,
            title VARCHAR(150) NOT NULL,
            units DECIMAL(3,1) NOT NULL DEFAULT 3.0,
            description TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS sections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            section_code VARCHAR(30) NOT NULL,
            instructor VARCHAR(150) NOT NULL,
            room VARCHAR(50) NOT NULL,
            capacity INT UNSIGNED NOT NULL DEFAULT 30,
            school_year VARCHAR(20) NOT NULL,
            semester VARCHAR(30) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_section_term (course_id, section_code, school_year, semester),
            CONSTRAINT fk_section_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS section_schedules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            section_id INT UNSIGNED NOT NULL,
            day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            CONSTRAINT fk_schedule_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS enrollments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            section_id INT UNSIGNED NOT NULL,
            status ENUM('enrolled','dropped') NOT NULL DEFAULT 'enrolled',
            grade VARCHAR(10) NULL,
            grade_published TINYINT(1) NOT NULL DEFAULT 0,
            enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_section (student_id, section_id),
            CONSTRAINT fk_enrollment_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_enrollment_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            posted_by INT UNSIGNED NOT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_announcement_author FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    $pdo->beginTransaction();
    try {
        // Preserve the original demo account IDs and related records when
        // upgrading installations that used the old portal.test addresses.
        $migrateEmail = $pdo->prepare('UPDATE users legacy LEFT JOIN users current_account ON current_account.email = ? SET legacy.email = ? WHERE legacy.email = ? AND current_account.id IS NULL');
        $migrateEmail->execute(['student@campusone.edu.ph', 'student@campusone.edu.ph', 'student@portal.test']);
        $migrateEmail->execute(['admin@campusone.edu.ph', 'admin@campusone.edu.ph', 'admin@portal.test']);

        $insertUser = $pdo->prepare('INSERT IGNORE INTO users (email, password_hash, role, first_name, last_name, student_number, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insertUser->execute(['student@campusone.edu.ph', password_hash('student123', PASSWORD_DEFAULT), 'student', 'Demo', 'Student', '2026-0001', '09171234567', 'Manila, Philippines']);
        $insertUser->execute(['admin@campusone.edu.ph', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'Portal', 'Administrator', null, '09170000000', 'Campus One']);

        $studentId = (int) $pdo->query("SELECT id FROM users WHERE email = 'student@campusone.edu.ph'")->fetchColumn();
        $adminId = (int) $pdo->query("SELECT id FROM users WHERE email = 'admin@campusone.edu.ph'")->fetchColumn();

        $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
        $stmt->execute(['enrollment_open', '0']);
        $stmt->execute(['current_school_year', '2026-2027']);
        $stmt->execute(['current_semester', 'First Semester']);

        $stmt = $pdo->prepare('INSERT IGNORE INTO balances (student_id, tuition, miscellaneous, payments) VALUES (?, ?, ?, ?)');
        $stmt->execute([$studentId, 24000, 3500, 10000]);

        $courses = [
            ['CCS0043', 'Application Development and Emerging Technologies', 3.0],
            ['CCS0047', 'Web Design with Client Side Scripting', 3.0],
            ['GED0081', 'College Mathematics', 3.0],
            ['GED0009', 'Readings in Philippine History', 3.0]
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO courses (code, title, units) VALUES (?, ?, ?)');
        foreach ($courses as $course) {
            $stmt->execute($course);
        }

        $sectionCount = (int) $pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn();
        if ($sectionCount === 0) {
            $courseIds = [];
            foreach ($pdo->query('SELECT id, code FROM courses')->fetchAll() as $course) {
                $courseIds[$course['code']] = (int) $course['id'];
            }
            $insertSection = $pdo->prepare('INSERT INTO sections (course_id, section_code, instructor, room, capacity, school_year, semester) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insertSchedule = $pdo->prepare('INSERT INTO section_schedules (section_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
            $seedSections = [
                ['CCS0043', 'TN26', 'Prof. Maria Santos', 'F604', 35, [['Monday', '09:00', '10:30'], ['Thursday', '09:00', '10:30']]],
                ['CCS0047', 'TN26', 'Prof. Carlo Reyes', 'F602', 35, [['Tuesday', '10:30', '12:00'], ['Friday', '10:30', '12:00']]],
                ['GED0081', 'TN01', 'Prof. Elena Cruz', 'A303', 30, [['Monday', '13:00', '14:30'], ['Wednesday', '13:00', '14:30']]],
                ['GED0009', 'TN02', 'Prof. Jose Lim', 'A201', 30, [['Tuesday', '14:30', '16:00'], ['Thursday', '14:30', '16:00']]]
            ];
            foreach ($seedSections as $section) {
                $insertSection->execute([$courseIds[$section[0]], $section[1], $section[2], $section[3], $section[4], '2026-2027', 'First Semester']);
                $sectionId = (int) $pdo->lastInsertId();
                foreach ($section[5] as $meeting) {
                    $insertSchedule->execute([$sectionId, $meeting[0], $meeting[1], $meeting[2]]);
                }
            }
        }

        $stmt = $pdo->prepare('INSERT INTO announcements (title, body, posted_by) SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM announcements)');
        $stmt->execute(['Welcome to Campus One', 'Your student portal is ready. Check announcements regularly for enrollment and academic updates.', $adminId]);

        $version = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $version->execute([CAMPUS_ONE_SCHEMA_VERSION]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
