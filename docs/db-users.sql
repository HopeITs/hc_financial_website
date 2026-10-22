-- MySQL users for the web app (task T1.2).
-- Run LOCALLY against the test copy, as an administrator:
--     mysql -u root -p < docs/db-users.sql
-- Do NOT run it against production. Replace both passwords first and keep them
-- out of the repository: they go in .env as DB_PASSWORD (hc_web) and
-- LEGACY_DB_PASSWORD (hc_web_legacy), with DB_USERNAME=hc_web.
--
-- MySQL has no wildcards in table-level grants, so the app-owned tables are granted
-- one by one by the procedure below. Migrations are run with an administrator account
-- (set DB_USERNAME/DB_PASSWORD to it for that command), and then re-run this file
-- (it is idempotent) so hc_web is granted the newly created gl_* tables.

USE `hcsyria_financial_test`;

-- 1. Read-only user, used by the `legacy` connection.
CREATE USER IF NOT EXISTS 'hc_web_legacy'@'localhost' IDENTIFIED BY 'CHANGE_ME_LEGACY';
GRANT SELECT ON `hcsyria_financial_test`.* TO 'hc_web_legacy'@'localhost';

-- 2. Application user, used by the default connection.
CREATE USER IF NOT EXISTS 'hc_web'@'localhost' IDENTIFIED BY 'CHANGE_ME_APP';
GRANT SELECT ON `hcsyria_financial_test`.* TO 'hc_web'@'localhost';

-- The one legacy write: changing a password (FinancePassword::make) updates user.password only.
GRANT UPDATE (`password`) ON `hcsyria_financial_test`.`user` TO 'hc_web'@'localhost';

-- Tables the web app owns: gl_*, web_user_roles, sessions, cache*, jobs*, failed_jobs, migrations.
DROP PROCEDURE IF EXISTS grant_hc_web_owned_tables;
DELIMITER $$
CREATE PROCEDURE grant_hc_web_owned_tables()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE t VARCHAR(64);
    DECLARE cur CURSOR FOR
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = 'hcsyria_financial_test' AND table_type = 'BASE TABLE'
          AND (table_name LIKE 'gl\_%' OR table_name LIKE 'cache%' OR table_name LIKE 'jobs%'
               OR table_name IN ('sessions', 'failed_jobs', 'migrations', 'web_user_roles'));
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    OPEN cur;
    loop_tables: LOOP
        FETCH cur INTO t;
        IF done = 1 THEN LEAVE loop_tables; END IF;
        SET @sql = CONCAT('GRANT ALL PRIVILEGES ON `hcsyria_financial_test`.`', t, '` TO ''hc_web''@''localhost''');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;
CALL grant_hc_web_owned_tables();
DROP PROCEDURE grant_hc_web_owned_tables;

FLUSH PRIVILEGES;

-- Check:  SHOW GRANTS FOR 'hc_web'@'localhost';
