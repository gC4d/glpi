-- Terabras demo calendar events (fictitious) for the current week.
-- Idempotent: clears its own marked rows first.
DELETE FROM glpi_planningexternalevents WHERE text = 'terabras-demo';

INSERT INTO glpi_planningexternalevents
  (entities_id, date, users_id, name, text, begin, `end`, state, planningeventcategories_id, background)
VALUES
  (0,'2026-08-09 00:00:00',2,'Reunião de equipe — sprint','terabras-demo','2026-08-10 09:00:00','2026-08-10 10:00:00',0,0,0),
  (0,'2026-08-09 00:00:00',2,'Manutenção — servidor de e-mail','terabras-demo','2026-08-10 14:00:00','2026-08-10 15:30:00',1,0,0),
  (0,'2026-08-09 00:00:00',2,'Daily standup','terabras-demo','2026-08-11 08:30:00','2026-08-11 09:00:00',0,0,0),
  (0,'2026-08-09 00:00:00',2,'Atualização de firewall','terabras-demo','2026-08-11 11:00:00','2026-08-11 12:00:00',1,0,0),
  (0,'2026-08-09 00:00:00',2,'Onboarding — novo colaborador','terabras-demo','2026-08-11 15:00:00','2026-08-11 16:00:00',0,0,0),
  (0,'2026-08-09 00:00:00',2,'Revisão de backups','terabras-demo','2026-08-12 10:00:00','2026-08-12 11:00:00',1,0,0),
  (0,'2026-08-09 00:00:00',2,'Deploy — portal interno','terabras-demo','2026-08-12 16:00:00','2026-08-12 17:00:00',0,0,0),
  (0,'2026-08-09 00:00:00',2,'Auditoria de licenças','terabras-demo','2026-08-13 09:00:00','2026-08-13 10:30:00',1,0,0),
  (0,'2026-08-09 00:00:00',2,'Chamado crítico — VPN','terabras-demo','2026-08-13 14:00:00','2026-08-13 15:00:00',0,0,0),
  (0,'2026-08-09 00:00:00',2,'Retrospectiva','terabras-demo','2026-08-14 11:00:00','2026-08-14 12:00:00',0,0,0),
  (0,'2026-08-09 00:00:00',2,'Migração de estações','terabras-demo','2026-08-14 13:30:00','2026-08-14 14:30:00',1,0,0);
