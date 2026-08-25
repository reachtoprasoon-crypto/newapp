# firebase_to_php

A PHP + jQuery/AJAX port of a Next.js/React school management system, built against the existing production database `vsecadlu_reportcard202667`.

Despite the name, the source app isn't actually Firebase-backed — it's a Next.js app (scaffolded via Firebase Studio) that talks directly to MySQL via `mysql2`. The name is leftover scaffolding naming, not a description of what's being migrated away from.

Source app: `/home/prasoon/Downloads/download/src` (Next.js/React/TypeScript, ~25,000 lines across 83 backend "flow" files and 67 UI components).

## Resuming work (session paused here — read this first)

This project was built interactively over one long session, then paused deliberately so work could continue from a different system. Everything needed to pick it back up is below and in this file; nothing is tracked only in chat history.

- **Where the code lives**: `/var/www/html/firebase_to_php` on this host, served by the Apache instance already running here (`http://localhost/firebase_to_php/`). If "a different system" means a different client machine hitting this same server, nothing changes. If it means an entirely different server, this whole directory (plus the `vsecadlu_reportcard202667` database) needs to move too — there's no separate deploy step, this *is* the deployment.
- **Database state right now**: live, real data (1869 students, 66 teachers). `Student_Login` (`controls` conid=17) is currently **disabled** by the school's own admin setting — this blocks the public student-facing kiosks (`attendance.php`, `collect.php`) and was deliberately left alone rather than flipped for testing. Check current value before assuming those flows work end-to-end.
- **What's done vs. not**: see "What's implemented" / "What's deferred" below — both are kept current after every round of work, not just at milestones.
- **Why the DB has schema changes beyond what's in `schema_only.sql`**: see "Known production database fixes" below — eight additive/corrective ALTERs were applied directly to the live DB during development (never destructive). `schema_only.sql` in this directory is a stale pre-migration snapshot; the live DB is the source of truth.
- **Deeper reasoning/history**: `/home/prasoon/.claude/projects/-var-www-html-firebase-to-php/memory/` holds a fuller per-phase narrative (why decisions were made, what was tried) if this README's summary isn't enough — but that directory is local to this machine's Claude Code installation and may not follow you to a different system. This README is the portable record; treat it as authoritative if the two ever disagree.
- **No automated tests**: verification was manual against live data each round (see "Testing approach" below). Re-running that discipline is the fastest way to confirm nothing regressed before continuing.

## Stack

- **Backend**: PHP 8.3, `mysqli` with prepared statements throughout
- **Frontend**: Bootstrap 5 + jQuery + DataTables + SweetAlert2, all via CDN — no build step
- **Database**: MySQL, `vsecadlu_reportcard202667` (host `localhost`, user `root`, see `config.php`)
- **Dependency management**: Composer installed locally as `composer.phar` in the project root (no system-wide install available on this host). `phpoffice/phpspreadsheet` is installed and used for real Excel-workbook generation (report cards); PHPWord/Dompdf not yet added — deferred until a feature needs them.

## Architecture

```
config.php              # mysqli connection + session_start()
lib/                     # shared PHP logic, one concern per file
  db.php                 # prepared-statement query helpers
  auth.php               # session guards (require_login_*, require_staff_role_*)
  permissions.php        # class-access control + the "is feeding allowed for
                          #   this class" roman-numeral/control-flag gate
  nav.php                # single source of truth for per-role dashboard tabs
  dates.php               # DOB format normalization (multiple legacy formats
                          #   coexist in the DB — VARCHAR vs DATE columns)
  reference.php, controls.php, students.php, teachers.php,
  attendance.php, daily_attendance.php, ht_wt.php, marks.php, grades.php,
  term_schedule.php, report_card.php, final_results.php, activity_log.php
                          # one file per feature area, each a straight port of
                          #   the matching *-flow.ts file(s) in the source app
assets/
  css/app.css
  js/app.js               # shared ajaxCall() convention (loading overlay,
                          #   toasts, unified {success,data|error} envelope)
  js/*.js                 # one file per feature area, paired with its partial
api/                      # one AJAX endpoint per backend "flow", grouped by
                          #   module folder (auth/, students/, marks/, ...)
partials/                 # HTML fragments AJAX-loaded into dashboard tabs;
  load.php                #   generic loader, role-gates slugs server-side
login.php / logout.php / index.php / dashboard.php / student_dashboard.php
attendance.php            # public daily-attendance kiosk (?sclass=X)
report_card.php           # print-friendly single-term report card
final_report_card.php     # print-friendly all-terms-combined report card
```

**Auth**: session-based. `$_SESSION['auth']` holds either a staff record (`tid`, `ttype`, `sclass`) or a student record (`sid`, `schno`, `sclass`). Teacher login is plaintext password comparison against `teachers.tpass` — preserved as-is from the source, not something this port changed.

**Roles** (`teachers.ttype`): `10` = Admin, `6` = Principal, `5` = Office, `1` = Class Teacher, anything else = plain subject Teacher. Each role's visible dashboard tabs are defined once in `lib/nav.php` and used both to render the nav and to gate direct API/partial access server-side.

**AJAX convention**: every `api/*.php` endpoint returns `{"success":true,"data":...}` or `{"success":false,"error":"..."}`. `assets/js/app.js`'s `ajaxCall()` wraps `$.ajax` around that envelope with automatic loading-overlay and toast handling.

**Class-level access control**: the source app only restricted which classes a teacher could act on via the UI (the class `<select>` only ever offered their own classes). This port enforces it server-side too — `lib/permissions.php::require_class_access_ajax()` / `require_class_access_page()` reject any staff member who isn't Admin/Office and isn't the class's own teacher (by homeroom or subject assignment).

## What's implemented

- **Auth & session**: unified teacher/student login, forgot/change password, role-gated dashboard shell
- **Student management**: list/search/add/edit/soft-delete (archives to class `"13Z"`), bulk roll-number editor, bulk photo upload (real multipart, matched by filename = Scholar Number)
- **Teacher management**: search/add/edit, class-teacher assignment
- **Attendance**: term-based (class/term/report grid) and a daily self-attendance kiosk (public QR-linked page, holiday/Sunday rules, monthly registry, admin link toggle)
- **Height/weight** entry
- **Marks & grades**: assessment-based marks entry (with `termhic` "highest total in class" recompute), graded-subject (Moral Science/SUPW) grade entry
- **Term schedule management**: per-class assessment max-marks editor, copy-schedule-to-other-classes
- **Report cards**: class logsheet (roster-style, one row per student) and single-student lookup; combined final report cards across all terms; **per-student formatted report cards** — a proper Excel workbook (one styled worksheet per selected student, via PhpSpreadsheet — school header, watermark image, senior/junior subject-grid layouts, HIC column, sidebar with percentage/legend/grade-subjects, signatures) plus a direct-print HTML equivalent, both driven by the same class/term/report/student-picker UI (`partials/report-cards.php`)
- **Final results**: final roster with cross-term averages and rank computation (40% eligibility thresholds), promotion status editor, HIC recompute
- **Activity log**: read-only viewer (Admin/Office)
- **Communications**: teacher/admin broadcasts to one or more classes (Notice/Homework/Worksheet/Other), with attachment support and ownership-enforced delete — required creating a `communication_recipients` join table the live DB was missing (the multi-class-recipients redesign in the source's own code predates what was actually deployed)
- **Student notes**: private per-teacher notes on a student, accessible via a "Notes" button in Student Management; delete is ownership-enforced server-side (the source lets *any* teacher delete *any* note — tightened here since "private" is the whole point of the feature)
- **Data collection**: admin/teacher dynamic form builder (Text/Number/Date/Select/Radio/Checkbox fields), a public self-service kiosk (`collect.php?form_id=X`, schno+DOB verified — and re-verified server-side at submit time rather than trusting a client-held student id from an earlier verify step, which is what the source did), and a responses viewer
- **Aptitude management** (Admin only): per-class aptitude marks entry, plus a combined logsheet (aptitude + Mathematics/Computer-Applications term averages, fuzzy subject-name matched) exported as a styled Excel workbook
- **Class roster**: marks + co-scholastic grades + attendance combined in one view/export per class/term/report, with failing-subject/failing-student highlighting (red/bold/underline) in the Excel export; also persists a HIC recompute as a side effect, matching the source
- **Final roster export** and **promotion export**: styled Excel versions of the on-screen final-results screens (2-row merged subject-group headers, alternating row shading, failing-mark highlighting)
- **Students Total**: school-wide grand-total leaderboard (Admin/Office), searchable, with Excel export

## What's deferred

Not built yet, in roughly ascending order of effort/risk:

- Excel export for **final report cards** and **data-collection responses** — the only two source screens with an Excel export not yet ported (everything else — term report cards, aptitude logsheet, class roster, final roster, students-total, promotion — is done)
- DB backup/reset tools, theme management, SMS/WhatsApp (stubs only in the source — nothing functional to port there)
- TC (Transfer Certificate) issuance — needs number-to-words conversion + DOCX generation
- Question paper / subjective paper authoring — needs webcam capture, a shared LaTeX-shorthand math parser, and DOCX export (PHPWord)
- Lesson planner + its AI-generation flow (needs a Gemini API key)
- **Timetable subsystem** — settings/load/constraints CRUD is straightforward, but the auto-generation algorithm (`generateTimetableData()` in the source) is a from-scratch class-scheduling solver and the single highest-risk item in the whole app

## Known production database fixes

While building and testing against the live DB, eight pre-existing schema defects were found and fixed — all were things that would have blocked core features in *any* implementation, not bugs introduced by this port. Each was verified by reproducing the failure first, then fixed with a minimal, additive change (adding a missing `AUTO_INCREMENT`/default/unique key, repointing a foreign key, or — once, for a table with zero rows — adding an entirely missing join table), never by deleting or altering existing data beyond deduplicating four `termhic` rows that a missing unique key had let accumulate.

1. `students.sid` / `teachers.tid` — `PRIMARY KEY` but not `AUTO_INCREMENT`; "Add Student"/"Add Teacher" could never have worked.
2. `teachers.sclass` — `NOT NULL` with no default; blocked adding a new teacher. Given `DEFAULT '-'`, matching the existing "unassigned" sentinel.
3. `termhic` — missing a unique key on `(sclass,termid,report)`, so the marks-save flow's `ON DUPLICATE KEY UPDATE` was silently inserting duplicates. Deduplicated and keyed.
4. `daily_attendance.sid` — foreign key pointed at `students_old` instead of `students`; broke daily self-attendance for any student not present in that stale snapshot (including every newly-added student).
5. `finaltotal.finaltot` / `finaltotal.per` — orphaned legacy columns (`NOT NULL`, no default) from a naming convention that predates `total_marks`/`percentage`. Given `DEFAULT 0`.
6. `finaltotal.rank` — `NOT NULL` with no default, but a student disqualified from ranking must store `rank = NULL` by design. Made nullable.
7. `student_notes.sid` — foreign key pointed at `students_old` instead of `students` (the same bug as #4, in a different table). Repointed.
8. `communications` — had a single inline `sclass NOT NULL` column with no `communication_recipients` join table at all; the source's current code assumes the multi-class-recipients design and would fail on any insert. The table had zero rows, so `communication_recipients` was created fresh and `communications.sclass` relaxed (nullable, unused going forward) rather than migrating anything.

See `/home/prasoon/.claude/projects/-var-www-html-firebase-to-php/memory/` for full session history and the reasoning behind each fix.

## Testing approach

There's no automated test suite. Every write endpoint was manually verified against the live production data using the same discipline: snapshot the real rows, resubmit unchanged data to prove a no-op round-trips identically, mutate one value and confirm it applied, then restore the original snapshot and diff to confirm an exact match. Where no safe real target existed (e.g. copying a term schedule), a disposable, non-existent class code was used instead so production data was never at risk.

## Migration plan

The original phased plan (Phases 1–4: scaffolding/auth, student & teacher management, attendance & marks, report cards) lives at `/home/prasoon/.claude/plans/misty-hopping-codd.md`. Everything beyond that (including the "Final Results" round) was scoped incrementally in conversation rather than pre-planned.
