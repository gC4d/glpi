-- Terabras — remove the fictitious demo data seeded by demo_data.sql.
-- Apply: docker compose exec -T db mariadb -uglpi -pglpi glpi < plugins/terabras/install/demo_data_cleanup.sql
SET FOREIGN_KEY_CHECKS=0;

-- Lookup/asset tables tagged with comment='TERABRAS_DEMO'
DELETE FROM glpi_manufacturers    WHERE comment='TERABRAS_DEMO';
DELETE FROM glpi_itilcategories   WHERE comment='TERABRAS_DEMO';
DELETE FROM glpi_users            WHERE comment='TERABRAS_DEMO';
DELETE FROM glpi_computers        WHERE comment='TERABRAS_DEMO';
DELETE FROM glpi_monitors         WHERE comment='TERABRAS_DEMO';
DELETE FROM glpi_softwares        WHERE comment='TERABRAS_DEMO';
DELETE FROM glpi_softwarelicenses WHERE comment='TERABRAS_DEMO';

-- ITIL objects seeded by id range (no comment column)
DELETE FROM glpi_tickets_users WHERE tickets_id BETWEEN 1 AND 200;
DELETE FROM glpi_tickets       WHERE id BETWEEN 1 AND 200;
DELETE FROM glpi_problems      WHERE id BETWEEN 1 AND 50;
DELETE FROM glpi_changes       WHERE id BETWEEN 1 AND 50;

SET FOREIGN_KEY_CHECKS=1;
