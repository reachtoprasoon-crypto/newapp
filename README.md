# firebase_to_php

A PHP + jQuery/AJAX port of a Next.js/React school management system, built against the existing production database `vsecadlu_reportcard202667`.

Despite the name, the source app isn't actually Firebase-backed — it's a Next.js app (scaffolded via Firebase Studio) that talks directly to MySQL via `mysql2`. The name is leftover scaffolding naming, not a description of what's being migrated away from.

Source app: on this (Windows) system, `C:\Users\p9839\Downloads\src` (Next.js/React/TypeScript, ~25,000 lines across 83 backend "flow" files and 67 UI components). Was `/home/prasoon/Downloads/download/src` on the Linux system this project started on — same source, different machine.

## Resuming work (session paused here — read this first)

This project was built interactively over one long session, then paused deliberately so work could continue from a different system. Everything needed to pick it back up is below and in this file; nothing is tracked only in chat history.

**Paused 2026-08-26, same system.** Question paper / subjective paper authoring (just finished) is the most recently completed item; **lesson planner + its AI-generation flow** is next up in "What's deferred" below (needs a Gemini API key — get one before starting). Local is fully built and backend-verified against real data (see "Testing approach"); not yet committed/pushed or SFTP'd to production — do that before starting the next feature so the two don't drift.

- **Local dev environment note**: on this round, the local MySQL data directory had no `vsecadlu_reportcard202667` database at all (likely lost in an XAMPP reinstall/data-dir change since the last session) and `root` had no password. Both are fixed: the database was recreated by importing `vsecadlu_reportcard202627.sql` (the repo's production dump — note its filename says `202627`, one digit off from the real DB name `202667`; that's just a stale filename, not a different database) and `root`'s password was set to `root` to match `config.php`. If a future session hits "Unknown database" or "Access denied for user root" again, redo both steps rather than editing `config.php`.

- **Where the code lives now**: this is a genuinely different system from where the project started (moved from a Linux host to Windows/XAMPP). Local dev is `c:\xampp1\htdocs\newapp` (`http://localhost/newapp/`), version-controlled at `https://github.com/reachtoprasoon-crypto/newapp`. Production is a separate cPanel host reached over SFTP (`vsecadlu@162.241.169.155`, path `/public_html/newapp`), live at `https://vsecavadhpuri.com/newapp/` — a real, unrelated shared-hosting deployment, not the same server the local database talks to. Local's `config.php` uses dev placeholder DB credentials (`root`/`root`, database `vsecadlu_reportcard202667`); production's `config.php` has its own real credentials and is deliberately **not** synced by git or SFTP pushes — check both independently before assuming a fix applied to one applies to the other.
- **Database state right now**: live, real data (1869 students, 66 teachers) — in the *production* database, not necessarily mirrored locally. `Student_Login` (`controls` conid=17) is currently **disabled** by the school's own admin setting — this blocks the public student-facing kiosks (`attendance.php`, `collect.php`) and was deliberately left alone rather than flipped for testing. Check current value before assuming those flows work end-to-end.
- **What's done vs. not**: see "What's implemented" / "What's deferred" below — both are kept current after every round of work, not just at milestones.
- **Why the DB has schema changes beyond what's in `schema_only.sql`**: see "Known production database fixes" below — eight additive/corrective ALTERs were applied directly to the live DB during development (never destructive). `schema_only.sql` in this directory is a stale pre-migration snapshot; the live DB is the source of truth.
- **Deploying a change**: there's no CI/deploy pipeline — after editing locally, `git commit`/push, then SFTP the changed files to `/public_html/newapp` on production directly (see the `school_website` entry in `~/.ssh/config` for the key-based login already set up). Production's shared host caps `max_execution_time` at 60s; anything that might run long (bulk Excel exports, especially) needs its own `set_time_limit()` call rather than relying on php.ini.
- **Deeper reasoning/history**: `/home/prasoon/.claude/projects/-var-www-html-firebase-to-php/memory/` holds a fuller per-phase narrative from the original Linux session (why decisions were made, what was tried) if this README's summary isn't enough — local to that machine, won't follow to others. This README is the portable record; treat it as authoritative if the two ever disagree.
- **No automated tests**: verification was manual against live production data each round (see "Testing approach" below), typically by hitting API endpoints directly with curl using a real logged-in session cookie. Re-running that discipline is the fastest way to confirm nothing regressed before continuing.

## Stack

- **Backend**: PHP 8.2 (both local XAMPP and production — see composer note below), `mysqli` with prepared statements throughout
- **Frontend**: Bootstrap 5 + jQuery + DataTables + SweetAlert2, all via CDN — no build step
- **Database**: MySQL, `vsecadlu_reportcard202667` (host `localhost`, user `root`, see `config.php`)
- **Dependency management**: Composer installed locally as `composer.phar` in the project root (no system-wide install available on this host). `phpoffice/phpspreadsheet` for Excel-workbook generation (report cards) and `phpoffice/phpword` for DOCX generation (TC certificates) are both installed; Dompdf not yet added — deferred until a feature needs it. `phpoffice/phpspreadsheet`'s lock entry was originally resolved on a machine running PHP ≥ 8.3, which silently pulled in a sub-dependency (`maennchen/zipstream-php`) requiring 8.3 — both local and production run PHP 8.2, so that caused every export to fatal with Composer's platform_check until re-resolved from this machine. Always run `composer update`/`require` from here (with `--ignore-platform-req=ext-gd` only, since that extension is present at runtime but absent from the CLI's php.ini) rather than copying a lock file resolved elsewhere.

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
- **Final results**: final roster with cross-term averages and rank computation (40% eligibility thresholds), promotion status editor, HIC recompute; **final (all-terms) report cards** — same per-student Excel workbook treatment as term report cards, but with TERM 1/TERM 2/AVG/HIC columns and a RANK/PROMOTION/REOPENS ON summary row from the persisted final totals
- **Activity log**: read-only viewer (Admin/Office)
- **Communications**: teacher/admin broadcasts to one or more classes (Notice/Homework/Worksheet/Other), with attachment support and ownership-enforced delete — required creating a `communication_recipients` join table the live DB was missing (the multi-class-recipients redesign in the source's own code predates what was actually deployed)
- **Student notes**: private per-teacher notes on a student, accessible via a "Notes" button in Student Management; delete is ownership-enforced server-side (the source lets *any* teacher delete *any* note — tightened here since "private" is the whole point of the feature)
- **Data collection**: admin/teacher dynamic form builder (Text/Number/Date/Select/Radio/Checkbox fields), a public self-service kiosk (`collect.php?form_id=X`, schno+DOB verified — and re-verified server-side at submit time rather than trusting a client-held student id from an earlier verify step, which is what the source did), a responses viewer, and Excel export of responses (one column per form field; done server-side via PhpSpreadsheet rather than the source's client-side SheetJS)
- **Aptitude management** (Admin only): per-class aptitude marks entry, plus a combined logsheet (aptitude + Mathematics/Computer-Applications term averages, fuzzy subject-name matched) exported as a styled Excel workbook
- **Class roster**: marks + co-scholastic grades + attendance combined in one view/export per class/term/report, with failing-subject/failing-student highlighting (red/bold/underline) in the Excel export; also persists a HIC recompute as a side effect, matching the source
- **Final roster export** and **promotion export**: styled Excel versions of the on-screen final-results screens (2-row merged subject-group headers, alternating row shading, failing-mark highlighting)
- **Students Total**: school-wide grand-total leaderboard (Admin/Office), searchable, with Excel export
- **Transfer Certificate (TC) issuance** (Admin only): archives a student's full snapshot into a new `tcissued` table and permanently deletes them from `students` (a real delete, not the class="13Z" soft-delete used elsewhere), then generates the printed certificate as a DOCX via PHPWord — server-side, unlike the source's client-side `docx` npm package. DOB-to-words for the form auto-fill is a client-side JS port (pure function, never touches the DB)
- **Question Papers (MCQ) & Subjective Papers** authoring: two related paper-builder tools ported from a pre-existing feature in the source app whose tables (`question_papers`/`questions`, `subjective_papers`) already held real historical data from before this port existed. A shared math/formatting shorthand (`$...$`/`$$...$$` LaTeX-subset math plus `**bold**`/`*italic*`/`__underline__`/`^^sup^^`/`,,sub,,`/list-prefix text shorthand — `lib/paper_shorthand.php` + `assets/js/paper-editor.js`, built once instead of the source's 3x-duplicated copies) powers a live KaTeX preview while authoring and a from-scratch OOXML (`<m:oMath>`) DOCX export — hand-built raw XML injected into PHPWord's output via a placeholder-token + zip-rewrite step, since the bundled `phpoffice/math` package can't render `\sqrt`/`^`/`_` on its own. Webcam capture (`getUserMedia` + canvas, shared modal) and plain file-upload both feed the same base64-in-DB convention used elsewhere (`students.photo`). MCQ papers are Admin/Principal (privileged, full edit/delete-any) or the owning teacher; subjective papers are stricter — only the creating teacher may ever create/edit/delete (Admin/Office/Principal can only view all and export), matching the source's own subjective-paper permission model exactly. MCQ delete ownership is tightened vs. the source (which lets any teacher delete any MCQ paper) to match the enforcement pattern already used for student notes.

## What's deferred

Not built yet, in roughly ascending order of effort/risk:

- DB backup/reset tools, theme management, SMS/WhatsApp (stubs only in the source — nothing functional to port there)
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

**Exception**: TC issuance (`api/tc/issue.php`) permanently deletes the chosen student from `students` with no undo — there's no safe way to round-trip-test it against a real record the way everything else here was verified. Everything around it (last-serial/history endpoints, auth gating, DOCX generation from a hand-built record, the UI) was checked; the actual issue-and-delete path was only code-reviewed against the source, never executed. Treat the first real use of this feature as its first real test.

**Question Papers / Subjective Papers verification note**: unlike every other feature above, this round's backend (list/get/upsert/delete/DOCX-export, role/ownership gating, math-to-OOXML rendering) was verified thoroughly via curl against the real local session/DB stack — including generating real `.docx` files from actual production formulas and confirming valid `<m:oMath>` XML by unzipping and parsing them — but the jQuery UI itself (toolbar buttons, live KaTeX preview, webcam modal, drag/reorder) was never clicked through in a real browser, since no browser/camera was available in that session. Treat the UI layer as unverified until someone actually opens it.

## Migration plan

The original phased plan (Phases 1–4: scaffolding/auth, student & teacher management, attendance & marks, report cards) lives at `/home/prasoon/.claude/plans/misty-hopping-codd.md`. Everything beyond that (including the "Final Results" round) was scoped incrementally in conversation rather than pre-planned.
