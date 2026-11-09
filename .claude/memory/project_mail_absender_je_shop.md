---
name: project-mail-absender-je-shop
description: "ERP-Mails brauchen Absender + SMTP je Shop (bestellung@sockenwolle-online…), heute ein globaler Absender — bauen vor dem SOW-Go-Live"
metadata:
  node_type: memory
  type: project
  originSessionId: 75b00eaf-ba08-4cd0-87b9-ad6e86a4b5cf
  modified: 2026-11-09T19:03:42.000Z
---

Entscheidung Jacky 2026-11-09: Jeder Shop behält seine eigene Mailadresse passend zur Domain (bestellung@indra-design / bio-wolle / sockenwolle-online …). Nach außen geht die Mail unter der Marke raus, intern landet alles in einem Postfach (Weiterleitung oder Aliase).

**Lücke:** `src/core/Mailer.php` nutzt einen globalen Absender (`mail_from_address`/`mail_from_name`/`mail_smtp_*` aus system_einstellungen). Auftragsbestätigung, Rechnung, Versand, Zahlung und Widerruf eines SOW-Auftrags kämen also von der Hauptadresse. Das Logo je Shop kennt der Mailer schon (`ladeShopLogo($shopId)`).

**Plan:** Einstellungen → Kanäle je Shop: Absender-Name, Absender-Adresse, optional eigenes SMTP (Host/User/Pass/Port/Verschlüsselung). Der Mailer nimmt die Daten des Shops aus `auftraege.shop_id`, leer = globaler Standard. Kasse/Laden/Partner/Händler bleiben beim Hauptabsender. Aufwand klein (1 Migration, ca. 1 Sitzung).

**Wann:** spätestens vor dem SOW-Go-Live (für indra-design ist der Hauptabsender vorerst ok). Dazu je Domain SPF/DKIM/DMARC prüfen (mail-tester.com). Siehe [[project-soft-golive-sockenwolle]], [[project-1-0-check]].
