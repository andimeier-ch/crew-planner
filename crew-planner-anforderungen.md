# Anforderungen: `crew-planner`

Eine Web-App zur Einteilung von Personen (Staffs) für Events. Das Wording ist bewusst generisch gehalten, damit die App später für verschiedene Zwecke angepasst werden kann.

---

## Projektstruktur

- **Projektname / Root-Ordner:** `crew-planner`
- **Stack:** DDEV, MySQL, Symfony, Doctrine ORM, Vue.js
- **Sprache:** Einsprachig (Deutsch)

---

## Rollen

Es gibt zwei Rollen:

- **Admin** – verwaltet die gesamte App (Login erforderlich)
- **Staff** – füllt Umfragen aus (Zugang über tokenbasierten Link, kein eigener Account)

---

## Skill-Types

- Der Admin kann Skill-Types erfassen, bearbeiten und löschen.
- Ein Skill-Type hat:
  - eine **Bezeichnung**
  - eine **Farbe** (wird in der Einteilungs-Matrix angezeigt)

---

## Skills

- Der Admin kann Skills erfassen, bearbeiten und löschen.
- Ein Skill hat:
  - einen **Namen**
  - eine Zuordnung zu einem **Skill-Type**

---

## Staff

- Der Admin kann Staffs erfassen, bearbeiten und löschen.
- Ein Staff hat:
  - einen **Namen**
  - eine oder mehrere Zuordnungen zu **Skills**
  - ein Flag **Leader** (boolean)

---

## Events

- Der Admin kann Events erfassen, bearbeiten und löschen.
- Ein Event hat:
  - einen **Titel**
  - ein **Datum**
  - eine optionale **Beschreibung**
- Alte Events können manuell gelöscht werden (kein Archiv, keine Automatik).

---

## Umfragen

### Umfrage erstellen

- Der Admin kann eine Umfrage erstellen und dabei auswählen:
  - welche **Staffs** die Umfrage erhalten
  - welche **Events** (Termine) die Umfrage enthält
  - eine **Deadline** (Datum/Uhrzeit), bis wann die Umfrage ausgefüllt werden kann

### Umfrage-Link

- Pro Staff und Umfrage wird ein **eindeutiger, tokenbasierter Link** generiert.
- Über diesen Link kann der Staff seine Antwort aufrufen und bearbeiten.
- Der Link wird dem Admin angezeigt, damit er ihn manuell weitergeben kann (z.B. per E-Mail oder Messenger).
- Kein automatischer E-Mail-Versand (vorerst).

### Umfrage ausfüllen (Staff-Ansicht)

- Der Staff öffnet seine persönliche Umfrage über den tokenbasierten Link.
- Der Staff kann für jedes Event ankreuzen, ob er **verfügbar** wäre.
- Der Staff kann eine optionale **Bemerkung** zur gesamten Umfrage hinterlassen.
- Der Staff kann seine Antworten **nachträglich ändern**, solange die Deadline nicht abgelaufen ist.
- Nach Ablauf der Deadline ist die Umfrage für den Staff **schreibgeschützt** (lesbar, aber nicht mehr änderbar).

### Umfrage löschen

- Alte Umfragen können manuell vom Admin gelöscht werden.

---

## Einteilung

### Matrix-Ansicht

- Der Admin sieht eine **Matrix** mit:
  - **Zeilen:** Staffs
  - **Spalten:** Events
- In jeder Zelle ist sichtbar:
  - ob der Staff das Event als **möglich** markiert hat (aus der Umfrage-Antwort)
  - ob der Staff für dieses Event **eingeteilt** ist (Admin-Entscheid)
- Die Matrix zeigt pro Staff:
  - den **Namen**
  - das **Leader-Flag** (visuell hervorgehoben)
  - die zugeordneten **Skills** mit der Farbe des jeweiligen Skill-Types

### Einteilung vornehmen

- Der Admin kann direkt in der Matrix auswählen, welche Staffs an welchem Event eingeteilt werden.
- Die Einteilung ist unabhängig von der Umfrage-Antwort (kein technischer Zwang, nur visuelle Hilfe).
- Ein Staff kann einem Event mit **einem oder mehreren Skills** zugeteilt werden (der Admin achtet selbst auf sinnvolle Kombinationen).
- Die Einteilung wird **nicht automatisch kommuniziert** (vorerst manuell durch den Admin).

---

## Design

- Schlicht und übersichtlich.
- Skill-Type-Farben werden in der Matrix als visuelle Orientierung genutzt.
- Die Matrix soll bei 15–20 Staffs (Zeilen) und 25–30 Events (Spalten) gut nutzbar sein (horizontales Scrollen ist akzeptabel).

---

## Nicht im Scope (vorerst)

- Kein automatischer E-Mail-Versand
- Kein Archiv für alte Daten
- Keine Slot-Anforderungen pro Event
- Keine Mehrsprachigkeit
- Keine automatische Kommunikation der Einteilung
- Kein technischer Zwang bei der Skill-Kombination pro Event
