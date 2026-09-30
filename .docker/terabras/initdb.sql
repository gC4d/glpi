-- Terabras — database container initialisation.
--
-- Runs once, on an empty data directory.
--
-- The MariaDB image loads `mysql.time_zone*` itself (unless MARIADB_INITDB_SKIP_TZINFO
-- is set), but the application user has no privilege on the `mysql` schema, so GLPI's
-- `DbTimezones` requirement check — and, on MySQL, CONVERT_TZ() itself — fails without
-- this grant. That failure is what freezes `use_timezones = false` into config_db.php
-- at install time and produces "Timezone usage has not been activated" in the UI.
CREATE USER IF NOT EXISTS 'glpi'@'%' IDENTIFIED BY 'glpi';
GRANT SELECT ON `mysql`.`time_zone_name` TO 'glpi'@'%';
GRANT SELECT ON `mysql`.`time_zone` TO 'glpi'@'%';
GRANT SELECT ON `mysql`.`time_zone_transition` TO 'glpi'@'%';
GRANT SELECT ON `mysql`.`time_zone_transition_type` TO 'glpi'@'%';
FLUSH PRIVILEGES;
