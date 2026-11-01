-- 221: Einrichtungs-Assistent (Onboarding) — siehe src/modules/einrichtung/OnboardingService.php
-- Bestätigungen liegen in system_einstellungen (onboarding_*). Bestehende Installationen, die schon
-- Firmendaten haben, gelten bei Superadmin und Steuer als eingerichtet, damit der Assistent sie nicht sperrt.
-- Eine frische 1.0-Installation hat keine Firmendaten → alle Pflicht-Schritte offen.

INSERT IGNORE INTO system_einstellungen (schluessel, wert)
SELECT k.schluessel, '1'
FROM (SELECT 'onboarding_superadmin' AS schluessel UNION ALL SELECT 'onboarding_steuer') k
WHERE EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'firmenname' AND wert <> '');
