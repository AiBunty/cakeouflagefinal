SET @schema_name := DATABASE();

SELECT COUNT(*) INTO @department_label_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
	AND table_name = 'admins'
	AND column_name = 'department_label';

SET @sql := IF(
	@department_label_exists = 0,
	'ALTER TABLE admins ADD COLUMN department_label VARCHAR(40) NULL AFTER role',
	'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
