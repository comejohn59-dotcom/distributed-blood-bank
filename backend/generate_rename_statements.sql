-- Generate RENAME TABLE statements to move tables from an old DB to `blood`.
-- Usage:
-- 1) Create the target DB first:
--    CREATE DATABASE IF NOT EXISTS `blood` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- 2) Run this query on the server (in mysql CLI or phpMyAdmin SQL tab) to produce RENAME statements.
SELECT CONCAT('RENAME TABLE `', TABLE_SCHEMA, '`.`', TABLE_NAME, '` TO `blood`.`', TABLE_NAME, '`;') AS rename_stmt
-- 3) Copy the output statements and run them.

SELECT CONCAT('RENAME TABLE `', TABLE_SCHEMA, '`.`', TABLE_NAME, '` TO `blood`.`', TABLE_NAME, '`;') AS rename_stmt
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'blood'
  AND TABLE_TYPE = 'BASE TABLE';

-- Note: RENAME TABLE requires appropriate privileges and will move table definitions and data.
