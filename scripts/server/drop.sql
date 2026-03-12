SELECT GROUP_CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`' SEPARATOR '; ')
INTO @sql
FROM information_schema.tables
WHERE table_schema = 'osburn_abet_tools_dev'
--   AND table_name != 'table_to_keep';

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;