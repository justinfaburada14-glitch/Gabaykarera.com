<?php
// config/database.php

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    define('DB_NAME', getenv('DB_NAME') ?: 'gabaykarera_db');
}

if (!defined('BASE_URL')) {
    $reqUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($reqUri, '/GabayKarera') !== false) {
        define('BASE_URL', '/GabayKarera');
    } else {
        define('BASE_URL', '');
    }
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            // First connect without database selected to ensure it exists
            $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $tempPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Now connect directly to the database
            $dbDsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dbDsn, DB_USER, DB_PASS, $options);

            // Verify if tables exist, if not auto-run setup
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() === 0) {
                require_once __DIR__ . '/setup.php';
                runDatabaseSetup($this->pdo);
            } else {
                // Ensure required schema columns exist
                $this->ensureSchemaIntegrity();
            }
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage() . "<br><small>Make sure MySQL is running in your XAMPP control panel.</small>");
        }
    }

    private function ensureSchemaIntegrity() {
        try {
            // Check resume_file in applicant_profiles
            $chkCol = $this->pdo->query("SHOW COLUMNS FROM `applicant_profiles` LIKE 'resume_file'");
            if ($chkCol->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE `applicant_profiles` ADD COLUMN `resume_file` VARCHAR(255) NULL AFTER `raw_resume_text`");
            }

            // Check candidate background & career detail columns
            $colsToAdd = [
                'work_history' => "TEXT NULL",
                'certifications' => "TEXT NULL",
                'portfolio_url' => "VARCHAR(255) NULL",
                'linkedin_url' => "VARCHAR(255) NULL",
                'github_url' => "VARCHAR(255) NULL",
                'career_goals' => "TEXT NULL",
                'work_preference' => "VARCHAR(100) NULL",
                'academic_honors' => "VARCHAR(255) NULL"
            ];

            foreach ($colsToAdd as $colName => $colDef) {
                $chk = $this->pdo->query("SHOW COLUMNS FROM `applicant_profiles` LIKE '{$colName}'");
                if ($chk->rowCount() === 0) {
                    $this->pdo->exec("ALTER TABLE `applicant_profiles` ADD COLUMN `{$colName}` {$colDef}");
                }
            }
        } catch (Exception $e) {}

        try {
            // Check avatar in users
            $chkAvatar = $this->pdo->query("SHOW COLUMNS FROM `users` LIKE 'avatar'");
            if ($chkAvatar->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(255) DEFAULT 'default_avatar.png' AFTER `company_name`");
            }

            // Check HR company verification columns in users
            $hrCols = [
                'company_reg_number' => "VARCHAR(100) NULL",
                'company_tin' => "VARCHAR(50) NULL",
                'company_website' => "VARCHAR(255) NULL",
                'company_address' => "TEXT NULL",
                'company_industry' => "VARCHAR(100) NULL DEFAULT 'Information Technology & Services'",
                'company_size' => "VARCHAR(50) NULL DEFAULT '50-200 Employees'",
                'hr_designation' => "VARCHAR(100) NULL DEFAULT 'HR & Talent Acquisition Lead'",
                'company_verification_status' => "ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending'",
                'company_verification_notes' => "TEXT NULL"
            ];

            foreach ($hrCols as $colName => $colDef) {
                $chk = $this->pdo->query("SHOW COLUMNS FROM `users` LIKE '{$colName}'");
                if ($chk->rowCount() === 0) {
                    $this->pdo->exec("ALTER TABLE `users` ADD COLUMN `{$colName}` {$colDef}");
                }
            }
        } catch (Exception $e) {}

        try {
            // Check application_messages table
            $chkMsg = $this->pdo->query("SHOW TABLES LIKE 'application_messages'");
            if ($chkMsg->rowCount() === 0) {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS `application_messages` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `application_id` INT NOT NULL,
                        `sender_id` INT NOT NULL,
                        `receiver_id` INT NOT NULL,
                        `message_type` ENUM('Status Update', 'Message', 'Interview Invitation', 'Job Offer', 'Decision Notice') DEFAULT 'Message',
                        `subject` VARCHAR(255) NOT NULL,
                        `body` TEXT NOT NULL,
                        `is_read` TINYINT(1) DEFAULT 0,
                        `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
                        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                        FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
        } catch (Exception $e) {}

        try {
            // Check system_documents table
            $chkDocs = $this->pdo->query("SHOW TABLES LIKE 'system_documents'");
            if ($chkDocs->rowCount() === 0) {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS `system_documents` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `title` VARCHAR(255) NOT NULL,
                        `category` VARCHAR(100) NOT NULL DEFAULT 'Policy & Compliance',
                        `target_audience` ENUM('all', 'applicant', 'hr') NOT NULL DEFAULT 'all',
                        `file_name` VARCHAR(255) NOT NULL,
                        `file_size` VARCHAR(50) NULL,
                        `file_type` VARCHAR(50) NULL,
                        `description` TEXT NULL,
                        `uploaded_by` INT NOT NULL,
                        `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
        } catch (Exception $e) {}

        try {
            // Check document_verifications table
            $chkVer = $this->pdo->query("SHOW TABLES LIKE 'document_verifications'");
            if ($chkVer->rowCount() === 0) {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS `document_verifications` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `document_type` VARCHAR(100) NOT NULL DEFAULT 'Resume Document',
                        `document_title` VARCHAR(255) NOT NULL,
                        `file_path` VARCHAR(255) NOT NULL,
                        `verification_status` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
                        `admin_notes` TEXT NULL,
                        `verified_by` INT NULL,
                        `verified_at` DATETIME NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
        } catch (Exception $e) {}
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}
?>
