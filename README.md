# TUA Research Repository

Web-Based Research Repository System with Data Analytics and Visualization Using TF-IDF and K-Means Clustering, for Trinity University of Asia. It is administered by the University Research and Development Center (URDC).

- Build guide: [CLAUDE.md](CLAUDE.md). The revised manuscript takes priority over it.
- Clickable prototype (screens, wording, colors; sample data only): [prototype/index.html](prototype/index.html)

## Status

- **Sprint 0 (setup):** done, except the items left open in its checklist below.
- **Sprint 1 (M1 accounts and access, M8 logging foundation):** done. See "Sprint 1" below.
- **Sprint 2 (M2 repository and metadata):** done. See "Sprint 2" below.
- **Sprint 3 (M3 submission and approval workflow):** done, except draft saving. See "Sprint 3" below.
- **Sprint 4 (Python service, M5 similarity screening):** done. See "Sprint 4" below.
- **Sprint 5 (M4 full-paper requests, search and view logging):** done. See "Sprint 5" below.
- **Sprint 6 (M6 clustering, LLM suggestions):** done. See "Sprint 6" below.
- **System settings, theme saving, clustering schedule (parts of Sprints 8 and 9):** done. See "Settings, theme and schedule" below.
- **AI performance (SOP 3) and the landing page CMS:** done. See "AI performance and CMS" below.
- **System health, Services and security; one green theme for the whole site:** done. See "Health, services and theme" below.

## Stack

Laravel 12 (PHP 8.2+), Livewire 3 + Alpine.js, Tailwind CSS 4, Vite, Apache ECharts 5, MySQL 8 (MariaDB 10.4+ for local development), Laravel Fortify, spatie/laravel-permission, spatie/laravel-activitylog, Pest, Larastan. Analytics service: Python 3.11, FastAPI, scikit-learn, NLTK.

## Local setup (Windows + XAMPP)

Requirements: PHP 8.2+, Composer, Node 20+, Python 3.11, and MySQL or MariaDB running (XAMPP is fine).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the databases (XAMPP's root user has no password by default):

```bash
C:/xampp/mysql/bin/mysql -uroot -e "CREATE DATABASE tua_repository CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE tua_repository_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Set a shared secret in `.env` for `ANALYTICS_HMAC_SECRET` (for example the output of `php -r "echo bin2hex(random_bytes(32));"`), then:

```bash
php artisan migrate:fresh --seed
npm run build        # or: npm run dev
php artisan serve    # http://127.0.0.1:8000
php artisan queue:work   # in a second terminal: sends the queued emails
```

Locally `MAIL_MAILER=log`, so emails (activation links, recovery codes) are written to `storage/logs/laravel.log` instead of being sent. Every sent email is also recorded in the `mail_log` table.

### Analytics service

```bash
cd analytics
py -3.11 -m venv .venv
.venv/Scripts/python -m pip install -r requirements-dev.txt
.venv/Scripts/python -c "import nltk; [nltk.download(p, download_dir='nltk_data') for p in ('stopwords','wordnet','omw-1.4')]"
# Use the same secret as Laravel's .env
set ANALYTICS_HMAC_SECRET=...   # PowerShell: $env:ANALYTICS_HMAC_SECRET="..."
.venv/Scripts/uvicorn app.main:app --host 127.0.0.1 --port 8001 --workers 1
```

Or, from the project root, `php artisan analytics:serve` (also `composer analytics`) starts it with the secret from Laravel's `.env`. `composer dev` starts the web server, queue worker, Vite and the analytics service together. After seeding, build the TF-IDF index once with `php artisan repository:index`.

### OpenAI key for LLM suggestions (optional)

Without a key, suggestions use the fallback (top TF-IDF terms) and say so. To try the real LLM, edit `.env` yourself. Never paste the key into chat, an issue or a commit:

```
LLM_ENABLED=true
LLM_PROVIDER=openai
LLM_MODEL=gpt-4o-mini
LLM_API_KEY=sk-...
```

Then run `php artisan config:clear`. Only the title and abstract (metadata) or the cluster terms and nearest titles (labels) are sent.

## Demo accounts (local and evaluation copy only)

The password for every demo account is `Password123`.

| Role | Email |
|---|---|
| Student | jasmine.villanueva@tua.edu.ph |
| Faculty adviser | ramon.castillo@tua.edu.ph |
| Research coordinator (Cluster 1) | liza.ferrer@tua.edu.ph |
| URDC administrator | urdc.admin@tua.edu.ph |
| System administrator | paolo.reyes@tua.edu.ph |

## Checks

```bash
composer test        # Pest (uses the tua_repository_test database)
composer lint        # Pint
composer analyse     # Larastan level 6
cd analytics && .venv/Scripts/python -m pytest -q
```

GitHub Actions runs all four on every push ([.github/workflows/ci.yml](.github/workflows/ci.yml)).

## Layout

```
app/Domain/{Accounts,Repository,Submissions,Search,Screening,Clustering,Analytics,Audit,Admin,Site}
app/Services/{AnalyticsClient,LlmClient,SmsClient}   swappable providers (interfaces + current implementation)
config/tua.php                                        project settings and [Not in manuscript] feature switches
resources/views/components                            Blade components (button, pill, panel, callout, kpi-card, ...)
analytics/                                            Python service (FastAPI, HMAC-signed, 127.0.0.1 only)
deploy/                                               Supervisor, cron, deploy.sh
prototype/                                            clickable prototype (reference only)
```

## Feature switches for items not in the manuscript

These are set in `.env` and default to on, except SMS. Switch them off for the evaluated version unless the manuscript adds them.

| Switch | Feature |
|---|---|
| `FEATURE_LANDING_CMS` | Landing page CMS |
| `FEATURE_THEME_PICKER` | User theme color |
| `RECOVERY_EMAIL_ENABLED` | Recovery email |
| `RECOVERY_SMS_ENABLED` | Recovery by SMS |

## Sprint 0 checklist

- [x] Laravel 12 app, Composer and npm packages
- [x] MariaDB/MySQL databases, `.env` settings, `config/tua.php`
- [x] Private `research` disk outside the web root
- [x] Domain folders and service interfaces (analytics, LLM, SMS)
- [x] Reference data, roles, settings and demo users (seeders)
- [x] Design tokens (light/dark), self-hosted IBM Plex, first Blade components
- [x] Analytics service skeleton: HMAC check, preprocessing, `/health`
- [x] Pest, pytest, Pint, Larastan, CI workflow
- [ ] Remaining components: table, stepper, score bar, drawer, toast (file slot added in Sprint 2)
- [x] 1,419 sample records (added in Sprint 2)
- [ ] Server setup on the ICT Office server

## Sprint 1: accounts and access (M1), logging foundation (M8)

| Screen | URL | Who |
|---|---|---|
| Create account | `/register` | Guest |
| Activate account | `/activate/{token}` (link in the email) | Guest |
| Sign in / Sign out | `/login`, `POST /logout` | Guest / signed in |
| Forgot password (email → channel → code) | `/forgot-password` | Guest |
| Assisted recovery link | `/reset-password/{token}` | Guest |
| Home | `/dashboard` | Every role |
| Account and recovery | `/account` | Every role |
| Users and roles (Account help for the URDC) | `/admin/users` | System administrator; URDC for assisted recovery |
| Activity log, CSV export | `/activity` | URDC, System administrator |

How the M1 rules are met:
- Only `@tua.edu.ph` can register or sign in, matched exactly and in any letter case. Self-registration gives only Student or Faculty adviser.
- A new account stays inactive until its activation link is used. The link works once and expires after 48 hours. Sending a new link cancels the old one. Only the SHA-256 hash of the token is stored. Opening the link shows a button, and pressing it activates the account, so mail scanners that open links automatically cannot use it up.
- Passwords need at least 10 characters, a letter and a number, and must not be on `resources/data/common-passwords.txt`.
- Sign-in pauses for 1 minute after 5 failed attempts per email and IP. The session is renewed at sign-in, ends after 30 minutes idle, and ends at once if the account is deactivated.
- Recovery codes have 6 digits, are stored hashed, expire in 10 minutes, work once and stop after 5 wrong tries. A code is never sent to an unverified contact. A reset signs out every other session and notifies the TUA email and the recovery email.
- In assisted recovery, staff confirm an ID check and set a new recovery email, and a one-time reset link is sent to it. Staff never see the password.
- Every step goes to the activity log through `App\Domain\Audit\Audit::record()`. The log cannot be edited or deleted from the app: the model blocks updates and deletes.

Decisions made in Sprint 1 (confirm with the adviser if needed):
- Fortify provides only sign-in, sign-out and password change. Its registration, link-based reset, two-factor and passkey features are off, because the spec uses its own activation link and recovery codes. The two-factor and passkey migrations were removed.
- `recovery_codes` has a `purpose` column (`password_reset` or `verify_contact`), so one table holds both kinds of code.
- Recovery by SMS is off (`RECOVERY_SMS_ENABLED=false`) until a gateway is chosen. The code paths and tests exist.
- The Privacy Notice text on the register page is a placeholder until the Data Privacy Officer provides the final text.

## Sprint 2: repository and metadata (M2)

| Screen | URL | Who |
|---|---|---|
| Records and metadata | `/urdc/records` (filters: search, college, status, no keywords, no SDG, incomplete files) | URDC |
| Edit a record: metadata, five file slots, publish, withdraw | `/urdc/records/{id}` | URDC |
| Bulk import: template, dry run, import | `/urdc/import` | URDC |
| File download (signed link, valid 10 minutes) | `/files/{id}` | Depends on role, see below |

How the M2 rules are met:
- Every record has a title, abstract, authors, adviser, college, program, degree level, research type, year, 3 to 5 keywords, SDG tags and a status (draft, published or withdrawn).
- There are five typed file slots: abstract (.doc or .docx), full paper (.pdf), IMRAD version (.pdf, which the URDC can mark not applicable), the signed Transfer of Ownership Agreement (.pdf) and the list of references (.doc or .docx). Each file's extension is checked, and its real content type is checked with `finfo`. A `.docx` must also be a real Word package, not any renamed ZIP.
  - The size limit comes from Settings (`files.max_mb`, default 20 MB).
  - Every file's SHA-256 is stored.
  - An identical file that is already stored for another record is refused, and the message names that record.
  - Older versions are kept.
- A record cannot be published while a required file is missing, it has fewer than 3 or more than 5 keywords, or its abstract is over the word limit (Settings `repository.abstract_word_limit`, default 250).
- Every metadata edit is logged with old and new values (`metadata_edit`). Withdrawing needs a reason and is a soft delete.
- Bulk import reads a CSV or XLSX file and an optional ZIP of files. The dry run shows row-level errors and duplicates by title (ignoring case and punctuation) and by file hash, against the repository and against earlier rows. Only ready rows are imported. The import is logged (`import`).
- Files live on the private `research` disk, outside the web root. Every download needs a signed link that expires, passes a permission check (`app/Policies/RecordFilePolicy.php`), and is logged in `download_logs` and the activity log.
  - URDC: every file.
  - Advisers and coordinators: the paper files of published records.
  - Students: by approved request (Sprint 5).
  - System administrator: no files.
  - The signed Transfer of Ownership Agreement: URDC only.
- Sample data: `SampleRecordSeeder` loads 1,419 records with the prototype's generator: the same `mulberry32` random generator and seed, the same weights, and the 5 planted near-duplicates for the screening demo. About 82% have all files. The files are shared placeholder documents under `storage/app/research/_samples`. It never runs in production.

Local notes:
- The Excel import needs PHP's `gd` extension. It was turned on in `C:\xampp\php\php.ini`, and the original is backed up as `php.ini.bak-before-gd`.
- XAMPP limits uploads to 40 MB (`upload_max_filesize`, `post_max_size`), so an import ZIP must be smaller than that. Raise both values if larger migration ZIPs are needed.
- Tests write their logs to the `null` channel, so `storage/logs/laravel.log` only shows the app's own entries.

Decisions made in Sprint 2:
- `research_records` has an `imrad_not_applicable` column, and `record_files` has an `is_current` column so older versions are kept. `record_files.submission_id` gets its foreign key in Sprint 3.
- A record's adviser is linked to a user account only when exactly one user has that name. Otherwise only the name is stored.
- Publishing a record directly (for imported legacy records) is allowed here. The four-step submission workflow comes in Sprint 3.

## Design stage: every screen built (before the remaining functions)

Every screen of the clickable prototype now exists in the app, at its final URL, behind its role, using the shared design system. The functions are then built into these screens sprint by sprint.

| Role | Screens |
|---|---|
| Public | Landing page `/`, Public catalog `/catalog`, Study abstract `/catalog/{id}` |
| Every signed-in user | Search `/search`, Study page `/studies/{id}`, Notifications `/notifications`, Account and recovery `/account` |
| Student | Home `/student`, Submit a study `/student/submit`, Submission status `/student/status`, Research summary `/student/summary` |
| Faculty adviser | Endorsement queue `/adviser`, Review submission `/adviser/review`, Supervised studies `/adviser/supervised` |
| Research coordinator | Review queue `/coordinator`, Similarity review `/coordinator/screening`, Theme labels `/coordinator/themes`, Cluster analytics `/coordinator/analytics` |
| URDC | Analytics dashboard `/urdc/analytics`, Approve and publish `/urdc/approvals`, Full-paper requests `/urdc/requests`, Records and metadata, Bulk import, Theme clustering `/urdc/clustering`, AI performance `/urdc/ai-performance`, Activity log, Account help, Landing page content `/site-content` |
| System administrator | System health `/admin/health`, Services and security `/admin/services`, Users and roles, System settings `/admin/settings`, Backups `/admin/backups`, Evaluator accounts `/admin/evaluators`, Activity log, Landing page content |

How the design screens work:
- **Real data wherever it exists.**
  - The landing page, catalog, search (facets: college, year, type, theme, SDG, adviser; sort), study pages, related studies, the analytics dashboards (Table 7 charts and compliance), the supervised-studies and research-summary charts, and the settings values all read the 1,419 records and the settings table.
  - Downloads on the study page use the real permission check.
- **Sample data where the module is not built yet**, from `app/Domain/Site/DesignData.php`, and the page says "sample". This covers submissions, notifications, full-paper requests, clustering runs, AI performance figures, backups and jobs. Each module replaces its part of that class in its sprint.
- **Buttons that do not work yet** use `<x-button preview="Sprint N">`. They never submit; a toast says which sprint builds them. Every such page shows a "Design preview" note.
- **Themes before clustering:** until the first K-Means run (Sprint 6), `app/Domain/Site/Corpus.php` gives each record the prototype theme that best matches its keywords. This drives the theme map, theme filter and heat map.
- **Theme picker [Not in manuscript]:** the Theme button in the top bar has 8 swatches, a custom color, and System, Light or Dark. The accent colors are computed from the chosen hue (lightness capped at 36% in light mode for WCAG AA). Guests keep the choice in the browser. For signed-in users it is saved to the account.
- **Charts:** `<x-chart>` holds an ECharts option. Colors are written as `var(--token)` so charts follow the theme and redraw when it changes. On phones the legend moves below the chart. Clicking a segment opens the matching search: the records behind it.
- **Styles:** component styles are ported from the prototype into `resources/css/design.css` (stepper, score bar, KPI card, tables, landing page, CMS, theme popover, responsive rules at 1000, 820 and 520 px).
- **Navigation:** the menu for each role is in `app/Support/Navigation.php`. `/dashboard` sends each role to its home page. The Sprint 0 component page moved to `/styleguide` (local only).

## Landing page theme, full-width app shell, complete database

**Landing page (`/`)** follows the developer's reference design: deep green and lime on cream, the Urbanist font (self-hosted), and rounded "bento" cards.
- **Sections:** top contact bar, sticky header, announcement strip, hero with search and floating cards (latest approved studies, published total, studies by cluster), key figures, an About bento, dark Featured studies, research clusters as college labels on a dotted map, a dark "How submission works" step card with the five required files, SDG chips, news cards, an FAQ accordion, contact with a call to action, and the footer.
- **Content and data:** every text comes from the landing page content in the database. Studies, counts and SDGs are live.
- **Styles:** in `resources/css/landing.css`, scoped to `.lp`. The landing page has a fixed palette and does not follow the user theme color or dark mode; the app screens still do.
- **Phones:** links move into a menu button, the hero and grids stack, the map labels become a list, the search button goes full width, and the page never scrolls sideways.
- **Photos:** illustrations stand in for photos until the URDC supplies real ones.

**App shell (signed in)** is now a full-width dashboard instead of a centered column:
- A top bar across the page: brand, search, Theme, notifications bell with an unread count, and the user menu (account, public page, sign out).
- A full-height dark sidebar with icons and group labels. Its color is the theme color, darkened.
- Fluid content that fills the rest of the screen.
- At 820 px and below the sidebar becomes a horizontal tab bar, as CLAUDE.md §16 requires.
- KPI cards can show a soft round icon (`<x-kpi icon="chart" icon-tone="ok">`).

**Database** now has every table of the CLAUDE.md data model (§6), 46 tables in all:
- Workflow: `submissions`, `submission_members`, `submission_reviews`.
- Screening: `similarity_checks`, `similarity_matches`, `screening_decisions`.
- LLM and clustering: `llm_suggestions`, `cluster_runs`, `clusters`, `record_cluster`.
- Access and usage: `access_requests`, `search_logs`, `kpi_snapshots`, `notifications`.
- Landing page and evaluation copy: `site_contents`, `evaluator_accounts`.

Models are in `app/Models`. Seeding (`php artisan migrate:fresh --seed`):
- `SiteContentSeeder` (runs everywhere): the landing page content as published version 1. The landing page always shows the latest published version, never a draft.
- `WorkflowSampleSeeder` (local and evaluation copy only) loads the sample data the design screens show:
  - 8 submissions, with their members, reviews, screening results and decisions
  - 4 full-paper requests
  - 3 clustering runs, the current one with 7 clusters and all 1,419 records assigned
  - theme-label suggestions, in-app notifications and search logs
- The screens still read `DesignData` until each module is wired in its sprint. The rows are already in place for that.

Extra sample accounts created by `WorkflowSampleSeeder` (password `Password123`, fictional names from the prototype):
- Students: `enzo.navarro`, `francine.garcia`, `gabriel.ilagan`, `isaac.tolentino`, `patricia.soriano`, `nicole.hernandez`, `carlo.mendoza`, `leah.santos`, `mika.pascual`
- Advisers: `evelyn.sarmiento`, `marites.lualhati`, `dennis.galang`

All of these use the `@tua.edu.ph` domain.

## Sprint 3: submission and approval workflow (M3)

The four-step workflow now works on the database (`app/Domain/Submissions/Workflow.php`, permissions in `app/Policies/SubmissionPolicy.php`):

| Step | Who | Screen | Actions |
|---|---|---|---|
| 1 Submit | Student | `/student/submit` | Metadata and word-limit checks, all required files (IMRAD can be marked not applicable), exact-file check against every stored record and submission |
| 2 Endorse | The assigned adviser | `/adviser/review` | Endorse, or return with a reason |
| 3 Review | The coordinator of the submission's cluster | `/coordinator/screening` | Clear, return for revision, or reject. The reason is required except when clearing an original. Saves a calibration label (distinct or duplicate) |
| 4 Approve | URDC | `/urdc/approvals` | Approve and publish, return, or reject (reason required) |
| Revise | The student | `/student/status` | Upload corrected files (older versions kept) and resubmit to the adviser |

- **Every transition:**
  - writes a `submission_reviews` row and an activity-log entry (`submit`, `endorsed`, `reviewed`, `published`, `returned`, `rejected`)
  - sends `WorkflowNotice` by email and as an in-app copy on `/notifications`, with an unread badge on the bell
- **Publishing** creates the research record from the submission, moves its five files to the record, checks that the record is complete, and publishes it to the catalog.
- **Submission files** can be downloaded only by the people in the workflow (student, assigned adviser, cluster coordinator, URDC), through the same signed and logged link.
- **Run the queue worker locally** (`php artisan queue:work`): emails and in-app notices are queued, as CLAUDE.md §12 requires.
- **Not in this sprint:**
  - Similarity screening scores (Sprint 4: the Python `/screen` endpoint). Until then a new submission shows "Screening is queued", and the coordinator decides with a reason.
  - LLM suggestions (Sprint 6).
  - "Save draft", which is still a preview.
- **Sample workflow data:** the `WorkflowSampleSeeder` submissions have placeholder files, so the URDC can approve the sample SUB-0133 and SUB-0131 in a demo.

## Sprint 4: analytics service and similarity screening (M5)

- **Python service** (`analytics/app`): `/fit`, `/screen`, `/related`, `/cluster`, `/top-terms` and `/health`. It is HMAC-signed and listens on 127.0.0.1 only.
  - The TF-IDF settings follow CLAUDE.md §7 M5.
  - The fitted index is saved with joblib in `analytics/models/`.
- **On submit,** `ScreenSubmission` runs as a queue job:
  - It stores the top 5 matches with their shared terms, the highest score and the flag. The flag uses the thresholds in Settings: 0.80 or higher is a probable duplicate, 0.60 to 0.79 is similar and needs review.
  - It never rejects a study by itself.
  - If the index is missing, it refits and tries again.
- **Refitting:** the index is refit after every 50 new published records and before every clustering run.
- **Related studies** on the study page come from `/related`. If the service is down, they fall back to shared keywords.
- **Tests:** pytest checks that the same text scores 1.0 and unrelated text scores below 0.2. Pest checks the flag at each threshold edge.

## Sprint 6: thematic clustering (M6) and LLM suggestions (§10)

**Clustering** (`/urdc/clustering`, URDC):
- **Run clustering now** queues `RunClustering`; it never runs inside the web request.
  - The job refits the index and runs K-Means for k = 2 to 15 (k-means++, n_init 10, random_state 42).
  - It saves the elbow (inertia), silhouette and Davies–Bouldin scores, each record's cluster and 2-D position, and the top 10 terms of each cluster.
  - The URDC can force a k.
- **Confirm as current** makes a finished run the one used by search, the theme map and the dashboards. The previous run is archived and kept.
- **Live run on the 1,420 local records:** k = 8 chosen by silhouette, about 11 seconds.

**Theme labels** (`/coordinator/themes` for coordinators, `/themes` for the URDC):
- Each cluster gets an LLM label of at most 5 words. If the LLM is unavailable or its reply fails validation, the label is the top 3 terms.
- **Accept**, **Save my edit** or **Reject, use top terms**. Coordinators must also rate the suggestion from 1 to 4.
- Each decision is written to `llm_suggestions` and the activity log, with the old and new label. The menu badge counts labels still waiting.

**Metadata suggestions** (`/student/submit`):
- **Suggest keywords and SDGs** sends only the title and abstract.
- **The reply is validated:**
  - 3 to 5 lowercase keywords of 1 to 4 words
  - SDGs from 1 to 17
  - one of the four research types
- Each item has Accept, Edit and Remove. Nothing is added without a click.
- What the student did with each item is saved with the submission.
- **Fallback:** without a key, or on any failure, the form shows "Suggestions unavailable, showing top terms".
- **Provider:** `app/Services/LlmClient/OpenAiLlmClient.php`, behind the `LlmClient` interface.
  - Timeout 8 s and a limit of 60 calls a minute.
  - The System administrator's "LLM on or off" setting switches it off.

## Sprint 5: full-paper requests and usage logging (M4)

**Full-paper requests:**
- **Student:** on a study page, a student writes the purpose and sends a request.
  - Only one request per paper can be open at a time.
  - The URDC gets an email and an in-app notice.
- **URDC** (`/urdc/requests`, filter by status):
  - **Approve** with an access-until date (default 14 days, at most a year).
  - **Decline** with a reason. The student sees the reason on the study page and can ask again.
  - The student is emailed either way. Each step is written to the activity log as `request`.
- **Downloads:** with an approved, unexpired request, `RecordFilePolicy` lets the student download that record's full paper and IMRAD version through the signed, logged link. Access ends on the expiry date. The Transfer of Ownership stays with the URDC.

**Usage logging:**
- **Searches:** every search in the catalog and in Search is written to `search_logs`, including searches with no results. A search is a query or a facet on the first page. Signed-in searches are also written to the activity log as `search`.
- **"Searches with no or few results"** (Table 7 visual 13) now reads `search_logs`: terms with 3 or fewer results in the last 90 days, most searched first.
- **Views:** every study view (guest or signed in) adds to the record's view count and writes a `view` entry.

## Settings, theme and schedule

**System settings** (`/admin/settings`, System administrator):
- **Six forms:** similarity thresholds, clustering schedule, LLM, files and abstracts, sessions and sign-in, and recovery channels.
- **Validation:** for example, the review threshold must be lower than the duplicate threshold. Errors show next to the field.
- **Logging:** each saved change goes to the activity log as `setting`, with the old and new values. A save that changes nothing is not logged.
- **Changes apply at once:**
  - Screening uses the new thresholds.
  - Uploads use the new size and word limits.
  - The idle session timeout is set before the session starts (`ApplyRuntimeSettings`).
  - Fortify's sign-in limiter uses the number of attempts (`SettingsLoginRateLimiter`).
  - The LLM switch, timeout and rate apply to the OpenAI client.
  - A recovery channel that is switched off is no longer offered for codes.

**Theme [Not in manuscript]:** signed-in users' color and appearance are saved to `users.theme_color` and `theme_mode` (`POST /account/theme`) and follow them to every device. Each change is logged as `theme`.

**Clustering schedule:**
- `php artisan clustering:scheduled` runs every night at 2:30 AM. Production needs the cron entry for `schedule:run`.
- It queues a run when one is due:
  - when there is no run yet
  - for "Every semester", 150 days after the last run
  - for "Every month", 28 days after the last run
  - after the set number of new published records
- The URDC still confirms each run. Use `--dry-run` to check without queuing.

## AI performance and CMS

**AI performance** (`/urdc/ai-performance`) uses only real data:
- **Duplicate screening:** every coordinator decision with a calibration label (duplicate or distinct) is a labeled pair, scored with its similarity from the latest screening.
  - The page shows the confusion matrix, accuracy, precision, recall and F1 at the threshold in use.
  - A slider recomputes them at any other threshold without changing the setting.
  - The evaluation's test set of at least 100 pairs must be labeled by three coordinators. It is not generated, because generated labels would make up the results.
- **Clustering quality:** silhouette and Davies–Bouldin for each run, and how many labels the coordinators kept.
- **LLM suggestions:** accepted, edited, rejected and pending counts for each kind, fallback use, and the mean 1–4 label rating.
- **Export report (CSV):** the metrics and every labeled pair, ready for Excel.

**Landing page CMS** (`/site-content`, URDC and System administrator):
- **Draft:** each tab's form saves to one draft row in `site_contents`. Visitors keep seeing the published version.
  - Lists (figures, feature cards, clusters, steps, news, FAQ) can be added to, removed and reordered.
  - Sections can be shown, hidden and moved.
  - Featured studies can be chosen from the published records.
  - The site's default theme color and the "let users pick" switch are set here.
- **Actions:**
  - **Preview** (`/?preview=1`, CMS users only) shows the draft with a banner.
  - **Publish** saves it as the next version. The publishing history records who published, when and which sections changed, and the activity log gets a `cms` entry.
  - **Discard changes** drops the draft.
  - **Load default content** puts the defaults into the draft.
- **Plain text only:** tags are stripped on save and everything is escaped on output.

## Health, services and theme

**System health** (`/admin/health`) shows live figures:
- **Disk:** disk use, uploaded files and database size.
- **Memory:** read from `/proc/meminfo` on the Ubuntu server.
- **Services:** analytics service status and response time, and whether the LLM is on.
- **Jobs:** the queue length, waiting and failed jobs, and failed emails.
- **Retry failed jobs** puts failed jobs back on the queue. Each retry is logged.

**Services and security** (`/admin/services`):
- **Tests:**
  - **Test connection** for the analytics service.
  - **Test connection** for the LLM. It sends a fixed sample text, never repository data.
  - **Send a test email to me.**
  - Each test is logged as `service`.
- **Recent calls between components** come from the activity log.
- **DPO approval:** the date comes from `LLM_DPO_APPROVED_ON` in `.env`.

**Theme:**
- The signed-in pages use the landing page palette:
  - deep green accent `#1b4a42` (the new default, "TUA Green")
  - cream background, green-tinted neutrals and lines
  - a dark green sidebar
  - lime for the current page and the menu counts
- Dark mode uses deep green surfaces.
- Users can still pick another swatch. Navy is one of them.
- A migration moves stored landing content from the old navy default to green. A color the URDC chose on purpose is kept.
- **Unchanged:** gold is still the LLM suggestion color, and the status colors (ok, warn, bad) are fixed.
- **CLAUDE.md:** this replaces its Navy default (§9, §15) at the developer's request.
