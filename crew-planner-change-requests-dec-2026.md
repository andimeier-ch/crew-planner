# Change requests for the Crew Planner app – December 2026

Terms used in this document:

- **Admin** – the person managing surveys in the back office.
- **Staff member** – a person in the staff list.
- **Participant** – a staff member who has been added to a specific survey.
- **Event** – a date the crew is planned for (e.g. "Gottesdienst, 6 Dec 2026").

---

## 1. Events belong to a survey

**Status:** ✅ Done

### Current behaviour

Events are managed independently. The admin first creates events, then creates a survey and selects existing events.
An event can be part of several surveys. Deleting a survey keeps its events.

### Requested behaviour

- The admin creates a survey first and adds events directly within that survey.
- Each event belongs to exactly one survey. The separate event list is no longer needed.
- A survey has a **title** (e.g. "Crew Advent 2026"), used in the list and in e-mails.
- Deleting a survey deletes everything that belongs to it: events, participants, responses and assignments.
  The admin must confirm this in a dialog that states what will be deleted.

### Migration of existing data

Existing surveys keep their events. If an event is shared by several surveys, it is copied into each of
them.

---

## 2. Surveys are closed manually

**Status:** ✅ Done

### Current behaviour

Each survey has a deadline. When the deadline passes, the survey locks automatically and participants can no longer
change their answers.

### Requested behaviour

- The deadline is informative only. It is shown to participants (on the survey page and in e-mails), but nothing
  locks when it passes.
- After the deadline, participants can still answer. The survey page shows a hint such as "The deadline has passed –
  please answer as soon as possible."
- The admin closes the survey manually once all the required feedback is in. After that, participants can view their
  answers but not change them.
- The admin can reopen a closed survey.

A survey therefore has the following states: **Draft → Open → Closed** (see also items 3 and 4).

---

## 3. Editing a survey after creation

**Status:** ✅ Done

### Current behaviour

After a survey has been created, it can no longer be edited: neither participants nor events nor the deadline can
be changed.

### Requested behaviour

- In every state (Draft, Open, Closed), the admin can:
  - add and remove participants,
  - add, edit and remove events,
  - change the title and the deadline.
- Removing a participant or an event that already has responses or assignments requires confirmation, because that
  data will be deleted.
- Participants who are added while the survey is open receive their invitation through the single-send action
  (see item 4).
- The admin is responsible for informing participants about other changes (e.g. new events).
  Participants who already answered see newly added events as unanswered when they reopen their link.

---

## 4. Invitation e-mails sent from the app

**Status:** ✅ Done

**Depends on:** item 7 (split staff name).

### Current behaviour

The admin copies each participant's personal survey link and sends it from their own e-mail client.

### Requested behaviour

- A new survey starts as a **Draft**. In this state, no e-mails are sent.
- The admin clicks **"Send invitations"**. All participants receive an e-mail and the survey becomes **Open**.
- Each survey has its own invitation text (subject and body), pre-filled from a default template. The admin can edit
  the text before sending.
- The following placeholders are available:

  | Placeholder      | Content                                |
  |------------------|----------------------------------------|
  | `{firstName}`    | Participant's first name               |
  | `{lastName}`     | Participant's last name                |
  | `{surveyTitle}`  | Title of the survey                    |
  | `{surveyLink}`   | Participant's personal survey link     |
  | `{deadline}`     | Deadline, formatted as e.g. 15.12.2026 |

- The participant list shows when each person was last e-mailed. The admin can (re)send the invitation to a single
  participant, e.g. a person added later or someone who did not receive it.
- Participants without an e-mail address are flagged. For them, the admin can still copy the link manually.

### Technical prerequisite

The production server needs a working e-mail setup (SMTP access and sender address) for outgoing e-mail.

---

## 5. Reminder e-mails

**Status:** ✅ Done

**Depends on:** item 4.

### Current behaviour

There is no reminder function.

### Requested behaviour

- Each survey has a separate reminder text (subject and body), independent of the invitation text. It supports the
  same placeholders.
- Reminders are only ever sent when the admin triggers them; nothing is sent on a schedule.
- The admin can send reminders:
  - **in bulk** to all participants who have not responded yet, or
  - **individually** to a single participant.
- "Not responded yet" means the participant has never submitted the survey form.
- Reminders can only be sent while the survey is **Open**.

---

## 6. Predefined event names

**Status:** ✅ Done

### Current behaviour

The admin has to type the event name every time, even for recurring names such as "Gottesdienst".

### Requested behaviour

- The admin manages a list of frequently used event names in a separate menu (add, edit, delete).
- When the admin creates an event, the title field suggests matching names from this list as they type.
- Free text is still allowed. Names that are not on the list are not added to it automatically.

---

## 7. Split staff name into first and last name

**Status:** ✅ Done

### Current behaviour

A staff member has a single name field.

### Requested behaviour

- A staff member has two required fields: **first name** and **last name**.
- Staff members are displayed as "First name Last name" and sorted by last name.
- E-mails can address participants by first name only via `{firstName}` (see item 4).

### Migration of existing data

Existing names are split at the first space: the part before it becomes the first name, the rest becomes the last
name. The admin then checks and corrects the result.

---

## 8. Assignment e-mails

**Status:** ⏳ Open

**Depends on:** item 4.

### Current behaviour

The admin assigns staff members to events in the survey's matrix. Assignments are not communicated by the app: the
admin has to inform the crew manually.

A staff member can have the **leader** flag, but there is no way to say who leads a specific event. An event can
have none, one or several leader-flagged people assigned.

### Requested behaviour

#### Leader per event

- In the matrix, the admin can mark one assigned person per event as the **leader** of that event.
- Each event has at most one leader. Marking another person moves the leader role to them.
- Only assigned staff members with the leader flag can be marked as leader. For everyone else, the option is not
  available.
- If the leader flag is removed from a staff member, they stay assigned to their events but are no longer the leader
  of any of them.
- If the leader's assignment is removed, the event no longer has a leader.

#### Sending the assignments

- When the assignments are complete, the admin clicks **"Send assignments"**. Every participant with at least one
  assignment receives an e-mail. Participants without any assignment receive nothing.
- Assignments can only be sent while the survey is **Closed**.
- Each survey has its own assignment text (subject and body), pre-filled from a default template and editable by the
  admin. It supports the same placeholders as the invitation (see item 4), plus:

  | Placeholder | Content                                                        |
  |-------------|----------------------------------------------------------------|
  | `{plan}`    | The participant's personal summary and the full plan (see below) |

- The participant list shows when each person last received the assignment e-mail. The admin can (re)send it to a
  single participant, e.g. after changing that person's assignments.
- If assignments change after sending, the app does not detect this. The admin sends the e-mail again, either to
  everyone or to the affected participants.
- Participants without an e-mail address are skipped and flagged. The admin informs them personally.

#### Content of the plan

The plan is sent as an HTML e-mail with a plain-text alternative. One block per event fits phone screens better
than a wide table.

1. **Personal summary** – the events the recipient is assigned to, sorted by date.
2. **Full plan** – all events of the survey, sorted by date. For each event:
   - weekday, date and title (e.g. "So 06.12.2026 · Gottesdienst"),
   - the event description, if one exists,
   - the leader, marked as "Leitung" and listed first,
   - the other assigned staff members, sorted by last name,
   - "Keine Einteilung" if nobody is assigned.
3. The recipient's own name is **highlighted** wherever it appears in the full plan.

Assigned skills are not shown.

Example (simplified, recipient Anna Muster):

```
Hallo Anna

Du bist an folgenden Daten eingeteilt:
  ▶ So 06.12.2026  Gottesdienst
  ▶ So 20.12.2026  Gottesdienst

── Gesamter Plan ──────────────────────

So 06.12.2026 · Gottesdienst
  Probe ab 08:30
  ★ Leitung: Peter Keller
    Marco Brunner
    Lisa Frei
    ┃ Anna Muster                ◀ du

So 13.12.2026 · Gottesdienst
  ★ Leitung: Sara Huber
    Marco Brunner

So 20.12.2026 · Gottesdienst
  ★ Leitung: Peter Keller
    ┃ Anna Muster                ◀ du
```

### Out of scope

- Showing the plan on the participant's personal survey page.
- Calendar files (.ics) for the participant's own assignments.
- Automatic notification when assignments change.
