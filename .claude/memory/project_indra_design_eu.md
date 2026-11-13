---
name: project-indra-design-eu
description: "Indra-Design wird Einzelunternehmen von Babsi (Gewerbe Kunsthandwerk, Kleinunternehmerregelung) — Fachmieter in der MEALANA KG, Verkauf im fremden Namen; Konfigurator muss fremde Produkte anlegen können"
metadata:
  node_type: memory
  type: project
  originSessionId: 349ed156-6550-4575-9112-f33373ee65da
  modified: 2026-11-13T08:40:11.864Z
---

**Entscheidung (Jacky, 2026-11-13), für später vorgemerkt — noch nichts gebaut:**
Aus gewerberechtlichen Gründen (Gewerbeschein „Kunsthandwerk“) wird Indra-Design ein **Einzelunternehmen (EU) von Babsi** mit **Kleinunternehmerregelung**.

- Die Indra-Produkte bleiben im ERP der MEALANA KG. Das EU ist dort **Fachmieter** und verkauft **im fremden Namen auf fremde Rechnung** — gleiches Muster wie das Partner-Modul (Verkaufsart `fremdrechnung`, nie Umsatz der KG, keine RKSV, Fachmiete), siehe [[project_partner_modul]].
- Großteil existiert schon (Partner-Stammdaten, Partner-Artikel XPnn, Kasse/Bon-Abschnitte, Partner-Konto, Export 3710). **Umbau nötig:** Konfigurator/Schilder so anpassen, dass Layouts und fertige Schilder als **„fremde“ Partner-Artikel** angelegt werden (FertigesSchildService legt bisher normale KG-Artikel an), und die Seite indra-design.at entsprechend adaptieren (Verkäufer/Impressum/Rechnung im Namen von Babsis EU, KU-Hinweis statt USt).

**Why:** Gewerbeschein Kunsthandwerk liegt bei Babsi persönlich, nicht bei der KG.
**How to apply:** Bevor am Konfigurator/indra-Shop weitergebaut wird, das mitdenken; Umsetzung erst besprechen (Steuerberatung wie beim Partner-Modul). Verwandt: [[project_konfigurator_modul]], [[project_indra_design_shop]], [[project_kleinunternehmer]], [[project_steuerprofil]].
