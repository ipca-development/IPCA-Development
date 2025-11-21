<?php
// includes/ai_scheduler/ai_migrations.php  (PHP 5.3)

if (!defined('AI_SCHED_MIGRATIONS')) define('AI_SCHED_MIGRATIONS', true);

function ai_col_exists($db, $table, $col) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `".$db->real_escape_string($table)."` LIKE ?");
    $stmt->bind_param('s', $col);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0);
}

function ai_table_exists($db, $table) {
    $stmt = $db->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0);
}

function ai_run_sql($db, $sql) {
    if (!$db->query($sql)) {
        // optional: log error; keep quiet in production
        // error_log("AI MIGRATION SQL ERROR: ".$db->error." in ".$sql);
    }
}

function ai_scheduler_run_migrations($db) {
    // 1) reservations.scenario_id (INT, nullable)
    if (ai_table_exists($db, 'reservations') && !ai_col_exists($db, 'reservations', 'scenario_id')) {
        ai_run_sql($db, "ALTER TABLE `reservations` ADD COLUMN `scenario_id` INT(11) NULL AFTER `mission_code`, ADD INDEX `idx_res_scn` (`scenario_id`)");
    }

    // 2) scenarios.sc_duration / sc_instructor_credentials / sc_device_credentials
    if (ai_table_exists($db, 'scenarios')) {
        if (!ai_col_exists($db, 'scenarios', 'sc_duration')) {
            ai_run_sql($db, "ALTER TABLE `scenarios` ADD COLUMN `sc_duration` INT(11) NULL COMMENT 'minutes' AFTER `mission_code`");
        }
        if (!ai_col_exists($db, 'scenarios', 'sc_instructor_credentials')) {
            ai_run_sql($db, "ALTER TABLE `scenarios` ADD COLUMN `sc_instructor_credentials` VARCHAR(255) NULL COMMENT 'CSV credential codes' AFTER `sc_duration`");
        }
        if (!ai_col_exists($db, 'scenarios', 'sc_device_credentials')) {
            ai_run_sql($db, "ALTER TABLE `scenarios` ADD COLUMN `sc_device_credentials` VARCHAR(255) NULL COMMENT 'CSV device capability codes' AFTER `sc_instructor_credentials`");
        }
    }

    // 3) users.credentials (CSV)
    if (ai_table_exists($db, 'users') && !ai_col_exists($db, 'users', 'credentials')) {
        ai_run_sql($db, "ALTER TABLE `users` ADD COLUMN `credentials` VARCHAR(255) NULL COMMENT 'CSV credential codes for instructors' AFTER `role`");
    }

    // 4) devices.device_credentials (CSV) — only if devices table exists
    if (ai_table_exists($db, 'devices') && !ai_col_exists($db, 'devices', 'device_credentials')) {
        ai_run_sql($db, "ALTER TABLE `devices` ADD COLUMN `device_credentials` VARCHAR(255) NULL COMMENT 'CSV device capability codes (e.g., SAB,FNPT,SE,ME,MCC,GND)'");
    }

    // 5) reservation_drafts.scenario_id (handy for drafts)
    if (ai_table_exists($db, 'reservation_drafts') && !ai_col_exists($db, 'reservation_drafts', 'scenario_id')) {
        ai_run_sql($db, "ALTER TABLE `reservation_drafts` ADD COLUMN `scenario_id` INT(11) NULL AFTER `mission_name`");
    }

    // 6) credential_types (master list)
    if (!ai_table_exists($db, 'credential_types')) {
        ai_run_sql($db, "CREATE TABLE IF NOT EXISTS `credential_types` (
            `code` VARCHAR(20) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        ai_run_sql($db, "INSERT IGNORE INTO `credential_types` (`code`,`name`,`description`) VALUES
            ('GND','Ground/Briefings','Only Ground/Theory/Briefings'),
            ('CFI','CFI','VFR/XP/Night'),
            ('CFII','CFII','Instrument'),
            ('SE','Single Engine','SE training'),
            ('ME','Multi Engine','ME training'),
            ('MCC','Multi Crew','MCC training')
        ");
    }

    // 7) user_credentials (normalized + reminder link)
    if (!ai_table_exists($db, 'user_credentials')) {
        ai_run_sql($db, "CREATE TABLE IF NOT EXISTS `user_credentials` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `credential_code` VARCHAR(20) NOT NULL,
            `reminder_id` INT(11) DEFAULT NULL,
            `valid_from` DATE DEFAULT NULL,
            `valid_to` DATE DEFAULT NULL,
            `status` ENUM('active','expired','suspended') DEFAULT 'active',
            PRIMARY KEY (`id`),
            KEY `idx_uc_user` (`user_id`),
            KEY `idx_uc_code` (`credential_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    }
}