---
name: project-sammelabholung-auftraege
description: "Geplantes Feature: mehrere Online-Bestellungen eines Kunden auf einem gemeinsamen Abhol-Bon/Beleg zusammenfassen"
metadata:
  node_type: memory
  type: project
  originSessionId: 3c350eb2-8eb3-43e3-bac5-de17c4ce7718
---

## Bedarf bestätigt (2026-07-10)

Barbara sind ad hoc 3 Kunden eingefallen, die mehrere Online-Bestellungen mit Abholung machen und dann alle gemeinsam abholen — genau der Sammelabholungs-Fall. Damit ist der Bedarf laut [[feedback_scope_ohne_bedarf]] validiert, kein rein spekulatives Feature.

**Bedeutet für uns:** Wird gebaut — mehrere Aufträge sollen auf einem gemeinsamen Bon/Beleg abgeholt werden können, statt pro Bestellung ein eigener Beleg.

**Zeitplan:** Bewusst zurückgestellt bis wir mit dem Entwurf der Online-Shop-Anbindung beginnen (die Bestellungen kommen von dort, macht als eigenständiges Kassen-Feature vorher wenig Sinn). Siehe auch [[project_paperless_rechnung_modul]] und [[project_kundenanzeige_modul]] — beide ebenfalls an den Start der Online-Shop-Anbindung gekoppelt.

**Noch offen:** Konkretes Design (wie werden mehrere Aufträge an der Kasse zu einem Bon zusammengeführt — RKSV-Belegnummer, Zahlungsstatus je Einzelauftrag, Teilabholung eines der mehreren Aufträge?) — noch nicht entworfen, erst beim eigentlichen Start dran.
