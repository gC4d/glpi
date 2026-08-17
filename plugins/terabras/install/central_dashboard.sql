-- Terabras — curated "Central" dashboard (manager overview).
--
-- Reorganizes GLPI's seeded Central dashboard (glpi_dashboards_dashboards.key='central',
-- id assumed 1) from 26 scattered widgets into a clean, grouped layout on the 26-col grid:
--   · Band 1 (y0) — Chamados KPIs      · Band 2 (y2) — Ativos KPIs
--   · Band 3 (y4) — Status + Categorias · Band 4 (y10) — Requerentes + Fabricantes
-- Columns align at 0 / 7 / 14 / 20 so the two KPI rows read as one grid. Clutter count
-- tiles (Rack, Printer, Phone, User, Group, Entity, Profile, …) are removed for a manager
-- view. Idempotent: keys off card_id, preserves each widget's card_options (colors/type).
--
-- Apply:  docker compose exec -T db mariadb -uglpi -pglpi glpi < plugins/terabras/install/central_dashboard.sql

SET @d := (SELECT id FROM glpi_dashboards_dashboards WHERE `key`='central' AND `context`='core' LIMIT 1);

-- Remove clutter widgets (asset/admin counts not relevant to a manager overview)
DELETE FROM glpi_dashboards_items
WHERE dashboards_dashboards_id=@d AND card_id IN (
  'bn_count_NetworkEquipment','bn_count_Phone','bn_count_Rack','bn_count_Printer',
  'bn_count_User','bn_count_Group','bn_count_Supplier','bn_count_Document',
  'bn_count_Entity','bn_count_Profile','bn_count_KnowbaseItem','bn_count_Project',
  'count_Monitor_MonitorModel','count_NetworkEquipment_State'
);

-- Band 1 — Chamados KPIs (y=0, h=2)
UPDATE glpi_dashboards_items SET x=0,  y=0, width=7, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_Ticket';
UPDATE glpi_dashboards_items SET x=7,  y=0, width=7, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_tickets_late';
UPDATE glpi_dashboards_items SET x=14, y=0, width=6, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_Problem';
UPDATE glpi_dashboards_items SET x=20, y=0, width=6, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_Change';

-- Band 2 — Ativos KPIs (y=2, h=2) — columns aligned with Band 1
UPDATE glpi_dashboards_items SET x=0,  y=2, width=7, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_Computer';
UPDATE glpi_dashboards_items SET x=7,  y=2, width=7, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_Software';
UPDATE glpi_dashboards_items SET x=14, y=2, width=6, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_SoftwareLicense';
UPDATE glpi_dashboards_items SET x=20, y=2, width=6, height=2 WHERE dashboards_dashboards_id=@d AND card_id='bn_count_Monitor';

-- Band 3 — Status dos chamados + Top categorias (y=4, h=6)
UPDATE glpi_dashboards_items SET x=0,  y=4, width=14, height=6 WHERE dashboards_dashboards_id=@d AND card_id='ticket_status';
UPDATE glpi_dashboards_items SET x=14, y=4, width=12, height=6 WHERE dashboards_dashboards_id=@d AND card_id='top_ticket_ITILCategory';

-- Band 4 — Top requerentes + Computadores por fabricante (y=10, h=5)
UPDATE glpi_dashboards_items SET x=0,  y=10, width=14, height=5 WHERE dashboards_dashboards_id=@d AND card_id='top_ticket_user_requester';
UPDATE glpi_dashboards_items SET x=14, y=10, width=12, height=5 WHERE dashboards_dashboards_id=@d AND card_id='count_Computer_Manufacturer';

-- Brand the single-series chart base colors (navy family). GLPI derives each chart's
-- gradient from card_options.color; the multi-color ticket_status chart is left alone
-- (its colors are semantic per status). Orange stays reserved as the UI accent.
UPDATE glpi_dashboards_items SET card_options=JSON_SET(card_options,'$.color','#140078') WHERE dashboards_dashboards_id=@d AND card_id='count_Computer_Manufacturer';
UPDATE glpi_dashboards_items SET card_options=JSON_SET(card_options,'$.color','#2417a0') WHERE dashboards_dashboards_id=@d AND card_id='top_ticket_user_requester';
UPDATE glpi_dashboards_items SET card_options=JSON_SET(card_options,'$.color','#2f1d93') WHERE dashboards_dashboards_id=@d AND card_id='top_ticket_ITILCategory';
