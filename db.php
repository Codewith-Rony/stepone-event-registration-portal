<?php
// Prevent direct access to db.php
if (basename($_SERVER['PHP_SELF']) == 'db.php') {
    header('HTTP/1.0 403 Forbidden');
    exit('Forbidden');
}

// Redirect to installer if config is not present
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Auto-migration to support new fields
    try {
        // Add columns to teachers table if not exists
        $cols_to_add_teachers = [
            'school' => "VARCHAR(100) NULL AFTER name",
            'gender' => "VARCHAR(10) NULL AFTER parish",
            'married' => "VARCHAR(10) NULL AFTER gender",
            'dob' => "DATE NULL AFTER gender",
            'age' => "INT NULL AFTER dob",
            'address' => "TEXT NULL AFTER age",
            'prior_experience' => "VARCHAR(10) NULL AFTER address",
            'zone' => "VARCHAR(50) NULL AFTER prior_experience",
            'payment_type' => "VARCHAR(20) DEFAULT 'Full' AFTER address",
            'payment_screenshot' => "VARCHAR(255) NULL AFTER password",
            'payment_status' => "ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending' AFTER payment_screenshot"
        ];
        
        foreach ($cols_to_add_teachers as $col => $definition) {
            $check = $pdo->query("SHOW COLUMNS FROM `teachers` LIKE '$col'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `teachers` ADD `$col` $definition");
            }
        }
        
        // Make email in teachers table nullable
        $pdo->exec("ALTER TABLE `teachers` MODIFY `email` VARCHAR(100) NULL");

        // Add columns to participants table if not exists
        $cols_to_add_participants = [
            'guardian_name' => "VARCHAR(100) NULL AFTER full_name",
            'school' => "VARCHAR(100) NULL AFTER guardian_name",
            'zone' => "VARCHAR(50) NULL AFTER parish",
            'class' => "VARCHAR(10) NULL AFTER zone",
            'address' => "TEXT NULL AFTER class",
            'payment_type' => "VARCHAR(20) DEFAULT 'Full' AFTER address"
        ];
        
        foreach ($cols_to_add_participants as $col => $definition) {
            $check = $pdo->query("SHOW COLUMNS FROM `participants` LIKE '$col'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `participants` ADD `$col` $definition");
            }
        }
        
        // Make upi_transaction_id and payment_screenshot in participants table nullable
        $pdo->exec("ALTER TABLE `participants` MODIFY `upi_transaction_id` VARCHAR(100) NULL");
        $pdo->exec("ALTER TABLE `participants` MODIFY `payment_screenshot` VARCHAR(255) NULL");
        
        // Drop unique indexes in participants table that might prevent duplicates
        try {
            $pdo->exec("ALTER TABLE `participants` DROP INDEX `upi_transaction_id`");
        } catch (Exception $ex) {}
        try {
            $pdo->exec("ALTER TABLE `participants` DROP INDEX `phone`");
        } catch (Exception $ex) {}
        
        // Auto-migration for intercessions table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `intercessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `holy_mass` INT DEFAULT 0,
            `our_father` INT DEFAULT 0,
            `hail_mary` INT DEFAULT 0,
            `memorare` INT DEFAULT 0,
            `creed` INT DEFAULT 0,
            `divine_mercy` INT DEFAULT 0,
            `rosary` INT DEFAULT 0,
            `adoration` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Add role column to admins table if not exists
        $check_role = $pdo->query("SHOW COLUMNS FROM `admins` LIKE 'role'");
        if ($check_role->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `admins` ADD `role` VARCHAR(20) DEFAULT 'super_admin' AFTER `password`");
        }

        // Auto-create default media admin if no media admin exists
        $check_media = $pdo->query("SELECT COUNT(*) FROM `admins` WHERE `role` = 'media_admin'");
        if ($check_media->fetchColumn() == 0) {
            $hashed = password_hash('media123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO `admins` (`username`, `password`, `role`) VALUES ('media', '$hashed', 'media_admin')");
        }

        // Auto-migration for posters table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `posters` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-migration for videos table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `videos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `url` VARCHAR(255) NOT NULL,
            `platform` ENUM('youtube', 'instagram') NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
    } catch (Exception $migrate_e) {
        // Log migration error or continue silently
    }
} catch (PDOException $e) {
    // Elegant fallback page if the database is configured but offline
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Offline - Step One</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;600;800&family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #1d0933 0%, #3a1168 100%);
                color: #ffffff;
                font-family: 'Plus Jakarta Sans', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                text-align: center;
                padding: 1rem;
            }
            .card {
                background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 16px;
                padding: 2.5rem;
                max-width: 500px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            h1 {
                font-family: 'Outfit', sans-serif;
                color: #d4af37;
                margin-top: 0;
            }
            p {
                color: #bfaed6;
                line-height: 1.6;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(90deg, #7b3fe4, #5c24b3);
                color: #fff;
                padding: 0.75rem 1.5rem;
                text-decoration: none;
                border-radius: 8px;
                margin-top: 1.5rem;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(92, 36, 179, 0.3);
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Database Offline</h1>
            <p>We are experiencing database connectivity issues. This might be due to incorrect credentials or temporary server maintenance.</p>
            <p style="font-size: 0.85rem; color: #ff8591; background: rgba(220, 53, 69, 0.1); padding: 0.5rem; border-radius: 6px;">
                Error: <?= htmlspecialchars($e->getMessage()) ?>
            </p>
            <a href="install.php" class="btn">Run Setup Wizard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
