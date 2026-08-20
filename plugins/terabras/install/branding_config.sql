-- Terabras white-label config defaults (idempotent). Applied on top of core config.
-- Sender display name shown in outgoing mail: "Terabras <no-reply@...>".
UPDATE glpi_configs SET value = 'Terabras'
  WHERE context='core' AND name='from_email_name' AND (value IS NULL OR value='' OR value='GLPI');
-- Replace GLPI's literal "SIGNATURE" placeholder with a brand signature (client may customize).
UPDATE glpi_configs SET value = 'Terabras'
  WHERE context='core' AND name='mailing_signature' AND value IN ('SIGNATURE','GLPI','');
