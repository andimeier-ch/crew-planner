# Crew Planner

Web-App zur Einteilung von Personen (Staffs) für Events.

**Stack:** DDEV · PHP 8.3 · Symfony 7 · Doctrine ORM · MariaDB 11.8 · Vue 3 · Vite · TypeScript

---

## Setup

```bash
ddev start
ddev composer install
ddev exec php bin/console doctrine:migrations:migrate --no-interaction

# Frontend
cd frontend
ddev npm install
```

**JWT-Schlüssel generieren** (einmalig, falls nicht vorhanden):
```bash
ddev exec php bin/console lexik:jwt:generate-keypair
```

**Ersten Admin-Benutzer anlegen:**
```bash
ddev exec php bin/console app:create-admin admin@example.com geheimespasswort
```

---

## Entwicklung

| Dienst | URL |
|---|---|
| Symfony API | https://crew-planner.ddev.site |
| Vite Dev Server | http://localhost:5173 |
| Datenbank | `ddev mysql` |

Frontend starten:
```bash
cd frontend && ddev npm run dev
```

Der Vite Dev Server leitet `/api/*`-Anfragen automatisch an `https://crew-planner.ddev.site` weiter (kein CORS-Problem im Dev-Betrieb).

---

## Authentifizierung

Die API verwendet **JWT Bearer Tokens** (RSA-256, gültig 1 Stunde).

### Login

```http
POST /api/auth/login
Content-Type: application/json

{"username": "admin@example.com", "password": "geheimespasswort"}
```

Antwort:
```json
{"token": "<jwt>"}
```

### Geschützte Endpoints aufrufen

```http
GET /api/skill-types
Authorization: Bearer <jwt>
```

Ohne gültigen Token → `401 Unauthorized`.

### Staff-Umfragen (kein Login)

Staff-Umfragen sind öffentlich zugänglich über einen tokenbasierten Link:
```
GET  /api/survey/{token}   → Umfrage abrufen
POST /api/survey/{token}   → Antworten speichern
```

Der Token wird beim Erstellen einer Umfrage pro Staff automatisch generiert und ist im Survey-Response des Admins sichtbar.

---

## API-Routen (Übersicht)

Alle `/api/*`-Routen (ausser Login und Survey) erfordern `Authorization: Bearer <jwt>`.

### Skill-Typen
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/skill-types` | Alle Skill-Typen |
| POST | `/api/skill-types` | Neuen Skill-Typ erstellen |
| GET | `/api/skill-types/{id}` | Einzelner Skill-Typ |
| PUT | `/api/skill-types/{id}` | Skill-Typ aktualisieren |
| DELETE | `/api/skill-types/{id}` | Skill-Typ löschen |

### Skills
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/skills` | Alle Skills |
| POST | `/api/skills` | Neuen Skill erstellen |
| GET | `/api/skills/{id}` | Einzelner Skill |
| PUT | `/api/skills/{id}` | Skill aktualisieren |
| DELETE | `/api/skills/{id}` | Skill löschen |

### Staffs
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/staffs` | Alle Staffs |
| POST | `/api/staffs` | Neuen Staff erstellen |
| GET | `/api/staffs/{id}` | Einzelner Staff |
| PUT | `/api/staffs/{id}` | Staff aktualisieren |
| DELETE | `/api/staffs/{id}` | Staff löschen |

### Events
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/events` | Alle Events |
| POST | `/api/events` | Neues Event erstellen |
| GET | `/api/events/{id}` | Einzelnes Event |
| PUT | `/api/events/{id}` | Event aktualisieren |
| DELETE | `/api/events/{id}` | Event löschen |

### Umfragen
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/surveys` | Alle Umfragen (Zusammenfassung) |
| POST | `/api/surveys` | Neue Umfrage erstellen |
| GET | `/api/surveys/{id}` | Umfrage mit Teilnehmern und Tokens |
| DELETE | `/api/surveys/{id}` | Umfrage löschen |
| GET | `/api/surveys/{id}/matrix` | Matrix-Daten (Staffs, Events, Verfügbarkeiten, Einteilungen) |

**Umfrage erstellen – Request Body:**
```json
{
  "deadline": "2025-03-01T18:00:00",
  "staffIds": [1, 2, 3],
  "eventIds": [4, 5, 6]
}
```

### Einteilungen
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/assignments` | Alle Einteilungen (Filter: `?eventId=X&staffId=Y`) |
| POST | `/api/assignments` | Einteilung erstellen |
| DELETE | `/api/assignments/{id}` | Einteilung aufheben |

**Einteilung erstellen – Request Body:**
```json
{"staffId": 1, "eventId": 4, "skillId": 2}
```

### Staff-Umfrage (öffentlich)
| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/api/survey/{token}` | Umfrage für Staff abrufen |
| POST | `/api/survey/{token}` | Antworten und Bemerkung speichern |

**Antworten speichern – Request Body:**
```json
{
  "responses": {"4": true, "5": false, "6": true},
  "remark": "Bin am 5. möglicherweise etwas später."
}
```

Nach Ablauf der Deadline sind Antworten schreibgeschützt (`403 Forbidden`).

---

## Validierung

Fehlerhafte Eingaben werden mit `422 Unprocessable Entity` und einem `errors`-Objekt beantwortet:
```json
{"errors": {"name": "Diese Zeichenkette ist zu lang."}}
```