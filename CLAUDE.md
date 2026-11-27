# CLAUDE.md: TUA Research Repository System

Build guide for Claude Code. Put this file in the root of the repository. Claude Code reads it at the start of every session.

**System:** Web-Based Research Repository System with Data Analytics and Visualization Using TF-IDF and K-Means Clustering, for Trinity University of Asia (TUA). It is the enhanced platform of the TUA Centralized Repository for the Terminal Degree Papers, administered by the University Research and Development Center (URDC).

**Sources of truth, in this order:**
1. The revised manuscript, 01.17 Revised Manuscript, Chapters 1–2 (Statement of the Problem, Scope and Delimitation, Chapter 2 Methodology). If this file and the manuscript disagree, the manuscript wins. Stop and ask the developer.
2. This file.
3. The clickable prototype `prototype/index.html`, together with its README and screenshots. It is the reference for screens, wording, layout and colors. Its data is sample data only.

Some items in this file are not in the manuscript yet: the landing page CMS, the theme color, and the recovery email or mobile. They are marked **[Not in manuscript]**. Build them, but keep them easy to switch off with a config flag until the manuscript includes them.

---

## 1. Non-negotiable rules

These come straight from the manuscript. Do not change them without the developer's approval.

1. Only `@tua.edu.ph` addresses can register and sign in. The one exception is temporary evaluator accounts, and those exist only in the separate evaluation copy (section 17).
2. A new account stays inactive until the user opens a one-time activation link sent to the TUA address. The link expires after 48 hours. Activation and reset tokens are stored only as hashes.
3. Self-registration gives only the Student or Faculty adviser role. Only the System administrator can assign Research coordinator, URDC administrator or System administrator.
4. There are five roles: Student, Faculty adviser, Research coordinator, URDC administrator and System administrator. Guests (not signed in) see only the public catalog of approved titles and abstracts.
5. The workflow has four steps: Student submits → Adviser endorses → Research coordinator reviews similarity flags → URDC approves and publishes. Any return or rejection needs a written reason. The reason is logged and the submitter is emailed.
6. Similarity screening never rejects a study automatically. Only an identical file (same SHA-256 hash) is stopped automatically, as an exact duplicate.
7. The LLM only suggests. It never changes a record without a user's click. It receives only the title, abstract, keywords or cluster terms, never full papers, files or personal data. If it is down, fall back to the top TF-IDF terms.
8. The core algorithms are TF-IDF, cosine similarity and K-Means (scikit-learn). Do not swap them for embeddings or another model.
9. Research files live outside the public web root. Every download is checked against permissions and logged.
10. Every important action goes to the activity log (section 13).
11. The system must fit the ICT Office server: Ubuntu on Intel, 4 GB RAM, 70 GB disk. Clustering runs as a scheduled background job, never during a web request.
12. Text analytics cover English-language records only.
13. Out of scope (do not build): LLM training or fine-tuning, question answering over the repository (RAG), automatic summarization of full papers, full-text plagiarism checks against the internet, and trend projections used for decisions about individuals.

---

## 2. Tech stack

The manuscript names Laravel 12, PHP 8.2+, MySQL, Python scikit-learn, Apache ECharts, the Laravel HTTP client for the LLM, and SMTP.

| Layer | Choice |
|---|---|
| Core app | Laravel 12, PHP 8.2+ (use 8.3 if the ICT server has it), Composer |
| UI | Blade + Livewire 3 + Alpine.js, Tailwind CSS (tokens from section 15), Vite |
| Charts | Apache ECharts 5 |
| Database | MySQL 8 (InnoDB, utf8mb4). The ICT Office also supports PostgreSQL, but keep MySQL to match the manuscript |
| Search | MySQL FULLTEXT through Laravel Scout's database driver, plus TF-IDF "related studies" from the analytics service |
| Auth | Laravel Fortify (email verification, password reset, rate limiting) and `spatie/laravel-permission` |
| Audit | `spatie/laravel-activitylog`, plus the custom tables `search_logs` and `download_logs` |
| Backup | `spatie/laravel-backup` (encrypted zip) |
| Import/export | `maatwebsite/excel`; PDF reports with `barryvdh/laravel-dompdf` |
| Background work | Laravel queues (database driver) with Supervisor; scheduler through cron |
| Analytics service | Python 3.11+, FastAPI, Uvicorn, scikit-learn, NLTK (stop words, lemmatizer), joblib, numpy |
| LLM | External LLM API over HTTPS through the Laravel HTTP client. Wrap the provider in an `LlmClient` interface so it can be swapped. The service must exclude submitted data from training, and the Data Privacy Officer must approve its use |
| Mail | SMTP with a TUA sender address. Use the University mail server or a transactional service the ICT Office approves |
| SMS **[Not in manuscript]** | A Philippine SMS gateway behind an `SmsClient` interface, used only for recovery codes. Turn it off with `RECOVERY_SMS_ENABLED=false` |
| Tests | Pest (Laravel), pytest (Python), Playwright (end to end and mobile viewports), OWASP ZAP baseline scan |
| Server | ICT Office server: Ubuntu, Intel, 4 GB RAM, 70 GB. Nginx, PHP-FPM, MySQL, Supervisor, a tua.edu.ph subdomain with SSL, UFW |

---

## 3. Architecture

This is the three-tier architecture from Figure 6 of the manuscript, plus the analytics service and the external LLM.

```
Browser (desktop / phone, installable PWA)
   │ HTTPS/TLS, session cookie, CSRF
Laravel 12 core app (M1–M9) ──── MySQL (least-privilege account)
   │  │                           private storage/app/research (outside web root)
   │  └─ HTTP 127.0.0.1:8001, HMAC-signed ──► Python analytics service (FastAPI)
   └──── HTTPS ──► External LLM service (suggestions only)
   └──── SMTP  ──► Mail      └── SMS gateway (recovery only, optional)
Queue workers (Supervisor) + scheduler (cron)
```

- **Analytics service:** listens only on `127.0.0.1`. It accepts a request only if the `X-Signature` header holds an HMAC-SHA256 of the body with a shared secret, plus a timestamp that is less than 60 seconds old. It receives only text fields and returns only scores, cluster assignments and top terms. It has no database access.
- **LLM calls:** all go through a queue job or a short request with a timeout. The default timeout is 8 seconds, after which the fallback is used. They are rate-limited (default 60 per minute). The reply is treated as untrusted text: validate it against a JSON schema, escape it, and never save it without the user's confirmation.
- **Database:** the app account has only DML rights on its own schema. Migrations run with a separate account. Use parameterized queries only (Eloquent or the query builder).
- **Every exchange between components is logged** (section 13), including the service called, the purpose, the fields sent, the result and the duration.

### Repository layout

```
/                       Laravel app
  app/Domain/{Accounts,Repository,Submissions,Search,Screening,Clustering,Analytics,Audit,Admin,Site}
  app/Services/{AnalyticsClient,LlmClient,SmsClient}
  resources/views/{site,guest,student,adviser,coordinator,urdc,sysadmin,components}
  database/{migrations,seeders,factories}
  tests/{Feature,Unit,Browser}
analytics/              Python service
  app/main.py  app/preprocess.py  app/screen.py  app/cluster.py  app/security.py
  tests/
deploy/                 nginx, supervisor, cron, deploy.sh, backup scripts
docs/                   this spec, prototype screenshots, ER diagram
```

---

## 4. Roles and access

| Function | Guest | Student | Adviser | Coordinator | URDC | Sys admin |
|---|---|---|---|---|---|---|
| Landing page, public catalog (titles and abstracts) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Search with all facets, related studies | — | ✓ | ✓ | ✓ | ✓ | ✓ |
| Full paper | — | Request; URDC approves | ✓ | ✓ | ✓ | — |
| Submit, revise, resubmit a study | — | ✓ | — | — | — | — |
| Endorse or return an advisee's submission | — | — | ✓ (own advisees) | — | — | — |
| Decide on similarity flags | — | — | — | ✓ (own cluster) | ✓ | — |
| Approve, return, reject, publish | — | — | — | — | ✓ | — |
| Approve full-paper requests | — | — | — | — | ✓ | — |
| Records, metadata, bulk import | — | — | — | — | ✓ | — |
| Run clustering | — | — | — | — | ✓ | — |
| Accept, edit or rate theme labels | — | — | — | ✓ | ✓ | — |
| Dashboards | — | Summary | Supervised studies | Cluster and colleges | University-wide, AI performance | — |
| Activity log | — | Own security events | — | — | ✓ | ✓ |
| Landing page CMS **[Not in manuscript]** | — | — | — | — | ✓ | ✓ |
| Users, roles, settings, services, backups, evaluator accounts | — | — | — | — | — | ✓ |
| Own account, recovery contacts, theme | — | ✓ | ✓ | ✓ | ✓ | ✓ |

Use policies (`app/Policies`) for every model. Test every cell of this table in a Pest dataset test.

---

## 5. Reference data

**Colleges (academic units):**

| Code | Name | Example programs |
|---|---|---|
| CAHS | College of Allied Health Sciences | BS Physical Therapy, BS Pharmacy, BS Radiologic Technology |
| CASE | College of Arts, Sciences and Education | BS Psychology, BSEd English, AB Communication, BS Biology |
| IBAM | Institute of Business and Management | BSBA Marketing, BS Accountancy, BS Hospitality Management |
| CEIS | College of Engineering and Information Sciences (pilot college) | BS IT, BS CS, BS Computer Engineering, BS Civil Engineering |
| CMT | College of Medical Technology | BS Medical Technology |
| SLCN | St. Luke's College of Nursing | BS Nursing, MA Nursing |

Programs are seed data that the URDC can edit. Get the real list from the URDC.

**URDC research clusters** (names from the manuscript, Research Locale). Which colleges belong to each one is an assumption until the URDC confirms it, so store it as editable data:

| # | Cluster | Colleges (assumed) |
|---|---|---|
| 1 | Business and Technology | IBAM, CEIS |
| 2 | Health: Diagnostic and Pharmaceutical | CMT, CAHS |
| 3 | Health: Patient Care | SLCN |
| 4 | Social Science, Humanities, and Science | CASE |

**Other reference data:**
- **Research types:** Undergraduate thesis, Capstone project, Graduate thesis, Faculty research.
- **Degree levels:** Undergraduate, Graduate, Faculty.
- **SDGs:** all 17 UN SDGs, each with its number and short name.

---

## 6. Data model

Use Laravel migrations. Main tables and columns (add timestamps everywhere and soft deletes where noted):

```
users(id, name, email UNIQUE, password, email_verified_at, academic_unit_id NULL, program_id NULL,
      user_type ENUM(student,faculty,staff,evaluator), is_active, deactivated_at,
      recovery_email NULL, recovery_email_verified_at NULL,            -- [Not in manuscript]
      recovery_mobile NULL, recovery_mobile_verified_at NULL,          -- [Not in manuscript]
      theme_color CHAR(7) NULL, theme_mode ENUM(system,light,dark) DEFAULT system, -- [Not in manuscript]
      expires_at NULL, last_login_at, password_changed_at)
roles / permissions / model_has_roles / role_has_permissions        (spatie)
password_reset_tokens(email, token_hash, created_at)
account_activations(user_id, token_hash, expires_at, used_at)
recovery_codes(id, user_id, channel ENUM(tua_email,recovery_email,sms), code_hash, expires_at, attempts, used_at) -- [Not in manuscript]
research_clusters(id, number, name, coordinator_user_id NULL)
academic_units(id, code, name, research_cluster_id)
programs(id, academic_unit_id, name, degree_level)
research_records(id, public_id e.g. TUA-2024-0123, title, abstract, year, research_type, degree_level,
      academic_unit_id, program_id, adviser_user_id NULL, adviser_name, status ENUM(draft,published,withdrawn),
      published_at, views, downloads, source ENUM(submission,google_form_migration,legacy_import),
      is_complete, FULLTEXT(title, abstract)) softDeletes
record_authors(record_id, name, user_id NULL, position)
keywords(id, term UNIQUE) ; record_keyword(record_id, keyword_id, source ENUM(user,llm_accepted,llm_edited,import))
sdgs(id 1..17, name) ; record_sdg(record_id, sdg_id, source)
record_files(id, record_id NULL, submission_id NULL, slot ENUM(abstract,full_paper,imrad,toa,references),
      path, original_name, mime, size, sha256 INDEX, uploaded_by)
submissions(id, public_id e.g. SUB-0142, record_id NULL, student_user_id, adviser_user_id, research_cluster_id,
      title, abstract, research_type, program_id, year, status ENUM(draft,submitted,endorsed,reviewed,published,returned,rejected),
      current_step TINYINT, max_similarity DECIMAL(4,3), flag ENUM(original,similar,probable_duplicate), submitted_at)
submission_members(submission_id, name, user_id NULL)
submission_reviews(id, submission_id, step, reviewer_user_id, decision ENUM(endorse,return,clear,revise,reject,approve), reason, created_at)
similarity_checks(id, submission_id, run_at, vectorizer_version, top_score, flag)
similarity_matches(check_id, record_id, score DECIMAL(4,3), rank, shared_terms JSON)
screening_decisions(id, submission_id, coordinator_user_id, decision, reason, label_for_calibration ENUM(duplicate,distinct) NULL)
llm_suggestions(id, kind ENUM(keyword,sdg,research_type,theme_label), subject_type, subject_id, suggested, final NULL,
      action ENUM(pending,accepted,edited,rejected), rating TINYINT NULL, provider, latency_ms, fallback_used BOOL, acted_by, acted_at)
cluster_runs(id, k, silhouette, davies_bouldin, n_records, params JSON, triggered_by, state ENUM(queued,running,done,failed,current,archived), finished_at)
clusters(id, cluster_run_id, number, top_terms JSON, label, label_status ENUM(pending,confirmed), size, x, y)
record_cluster(record_id, cluster_id, x, y)          -- x,y = 2-D projection for the theme map
access_requests(id, public_id, user_id, record_id, purpose, status ENUM(pending,approved,declined), decided_by, reason, expires_at)
notifications (Laravel database notifications) + mail_log(id, to, subject, status, attempts, last_error)
activity_log (spatie) ; search_logs(id, user_id NULL, query, filters JSON, results_count, created_at) ; download_logs(...)
kpi_snapshots(id, scope_type, scope_id, metric, period, value, computed_at)
settings(key, value JSON)       -- thresholds, schedule, file limits, LLM, session, recovery, site
site_contents(id, version, data JSON, status ENUM(draft,published), published_by, published_at) -- landing CMS [Not in manuscript]
evaluator_accounts(...) only in the evaluation copy
```

Seeders: reference data, one demo user per role, and 1,419 sample records. The prototype's generator logic shows realistic distributions. Never seed real student names in the evaluation copy.

---

## 7. Modules (M1–M9)

Each module states its Statement of the Problem (SOP) item, its features and its acceptance criteria. The prototype screen names are given in quotes.

### M1. Authentication and role-based access control (SOP 1.4)

**Features**
- **"Create account":** name, TUA email, I am a (Student or Faculty), college, program, password, recovery email or mobile **[Not in manuscript]**, and a Privacy Notice checkbox (RA 10173).
  - The email domain must be exactly `tua.edu.ph`, matched case-insensitively.
  - The password needs at least 10 characters, with at least one letter and one number. Check it against a list of common passwords.
- **Activation:** a one-time link with a 48-hour expiry, sent by email. "Send a new link" makes the old link invalid. The account stays inactive until activated.
- **"Sign in":** rate limited. After 5 failed attempts, pause for 1 minute per email and IP. Sessions end after 30 minutes idle (configurable). Regenerate the session on login.
- **Forgot password and account recovery [recovery contacts not in manuscript]:**
  1. The user enters the TUA email. The answer is the same whether or not the account exists.
  2. The user chooses a channel, with only part of each contact shown: TUA email, verified recovery email, or verified recovery mobile (SMS).
  3. A 6-digit code is sent. It expires in 10 minutes, works once, and stops working after 5 wrong tries. It is stored hashed.
  4. The user sets a new password. All other sessions are signed out. A notice goes to the TUA email and the recovery email.
- **Assisted recovery:** if the user can use none of the contacts, the URDC or System administrator checks a school or government ID. They then set a new recovery contact and send a one-time reset link to it. Staff never see or set the password. Every step is logged.
- **"Account and recovery" page (every role):**
  - Add, change or remove the recovery email (must not be @tua.edu.ph) and the PH mobile number (normalized to `+63 9XX XXX XXXX`). Each one is verified with a code before it can be used.
  - Show recent security events.
  - Notify the TUA email when a recovery contact changes.
- **Role assignment:** only the System administrator can do it, and each change is logged with the old and new role. Deactivated users cannot sign in.

**Acceptance**
- A Gmail address is refused at registration.
- An expired link shows "Send a new link".
- A used token cannot be reused.
- The coordinator, URDC and sysadmin roles cannot be self-assigned.
- An unverified recovery contact cannot receive a code.
- A Feature test covers every rule above.

### M2. Repository and metadata management (SOP 1.1)

**Features**
- Every record carries title, abstract, authors, adviser, college, program, degree level, research type, year, 3–5 keywords, SDG tags and status.
- **Five typed file slots** (from the Centralized Repository protocol):
  1. Abstract in the URDC format (Word: `.doc`, `.docx`)
  2. Full paper (PDF)
  3. IMRAD version (PDF, where applicable; the URDC can mark it not applicable)
  4. Signed Transfer of Ownership Agreement R-12-01 (PDF, required before upload)
  5. List of references (Word)
- Format checks use the real MIME type and the extension. Default maximum size is 20 MB, set in Settings.
- A SHA-256 hash is stored for every file.
- **LLM metadata suggestions:** see section 10.
- **"Records and metadata" (URDC):**
  - Search, filter by college, and filter by missing data: no keywords, no SDG, or incomplete files.
  - Edit metadata. Every edit is logged with old and new values.
  - Withdraw a record (soft delete).
- **"Bulk import" (URDC):** CSV or XLSX template for migrating the Google Form submissions and older outputs, with a preview, row-level errors, duplicate detection by hash and title, and a dry-run mode.

**Acceptance**
- A record cannot be published without the required slots.
- Fewer than 3 or more than 5 keywords is refused.
- The abstract word limit (setting, default 250) is enforced.

### M3. Submission and approval workflow (SOP 1.2)

**State machine:**
```
draft → submitted (step 1) → endorsed (step 2) → reviewed (step 3) → published (step 4)
          ↘ returned (by adviser, coordinator or URDC, reason required) → resubmitted → submitted (back to adviser)
          ↘ rejected (coordinator or URDC, reason required; final)
```

**On submit, in order:**
1. Metadata and abstract word-limit checks.
2. All required file slots are present.
3. Exact-file check: if the SHA-256 matches a stored file, stop and tell the user which record it matches.
4. Similarity screening (M5) runs as a queue job, so the student sees "Screening…" and then the result.
5. Status becomes `submitted`. The adviser is emailed.

**Review steps:**
- **Adviser** ("Endorsement queue", "Review submission"): endorse, or return with a reason.
- **Coordinator** ("Review queue", "Similarity review"):
  - Sees the side-by-side comparison with the closest match and shared terms highlighted.
  - Decides: clear (reviewed), return for revision, or reject. A reason is required except for a clear on an original.
  - The decision also records a calibration label (duplicate or distinct) for M5.
- **URDC** ("Approve and publish"): approve and publish, return, or reject, with a reason. Publishing does three things:
  1. It creates or updates the `research_record`.
  2. It indexes the record for search.
  3. It queues the record for the next clustering run.
- **Student** ("Submission status"):
  - A stepper with the four steps, the history with names, times and reasons, and the screening result with the top 5 matches.
  - "Revise and resubmit" for a returned study. A new file version gets a new SHA-256, and the older versions are kept.
  - **Assumption:** a resubmitted study goes back to the adviser. Confirm with the URDC.
- **Email at every step** (section 12), plus an in-app "Notifications" page per role.

**Acceptance**
- Each transition is allowed only for the right role and the right owner (a policy test for every transition).
- Every return or reject needs a reason.
- Every transition writes a `submission_reviews` row and an activity-log entry, and sends an email.

### M4. Search, recommendation and public catalog (SOP 1.3)

**Features**
- **"Public catalog" (guest):** titles, abstracts and metadata of published records only. No files. Shows "Sign in" and "Create account".
- **"Search" (signed in):**
  - Full-text search over title, abstract and keywords.
  - Facets: college, program, year, research type, theme (current cluster label), SDG and adviser.
  - Sort by relevance, newest or most viewed. Paginate 20 per page.
- **"Study page":** metadata, abstract, keywords, SDG chips, theme, and "Related studies" (top 5 by cosine similarity from the stored vectors).
  - The full paper is shown according to role (section 4). Every view and download is logged.
- **"Full-paper requests":** a student asks with a stated purpose. The URDC approves (with an expiry date) or declines (with a reason). The student is emailed.
- **"Research summary" (student):** a simple summary of the student's college research.
- Every search is logged to `search_logs`, including searches with zero results (needed for a Table 7 visual).

### M5. Similarity screening (SOP 1.5, 3.1)

**Preprocessing** (the same for stored records and new submissions; implement it once, in Python):
1. Combine the title, the abstract and the keywords into one text. Repeat the keywords so they carry more weight. In the prototype the title also appears twice.
2. Lowercase, and remove punctuation, numbers and English stop words (NLTK list plus a domain list: study, research, thesis, system, etc.).
3. Lemmatize (WordNet lemmatizer).

**TF-IDF:** `TfidfVectorizer(sublinear_tf=False, norm='l2', min_df=1, ngram_range=(1,2))`, with weight `w(t,d) = tf × log(N/df)`.
- Store the fitted vectorizer with joblib, versioned.
- Refit it after each clustering run or after every 50 new records.

**Cosine similarity:** compare the submission with all published records and return the top 5 with their scores and shared terms.

**Flags** (thresholds are in Settings and their changes are logged):
- `≥ 0.80`: probable duplicate.
- `0.60–0.79`: similar, needs review.
- `< 0.60`: original. The matches are shown to the student as related studies.

**Rules**
- The coordinator always makes the decision. Each decision is added to the labeled data used for calibration.
- **"AI performance" page (URDC):** a confusion matrix on the labeled test set of at least 100 submission–record pairs (about half duplicates, labeled by the majority judgment of 3 coordinators). It shows accuracy, precision, recall and F1, with a threshold slider that shows how the metrics change.

**Acceptance**
- pytest checks that the same text gives a score of 1.0 and that unrelated text scores below 0.2.
- The Laravel test checks the flag at each threshold edge.

### M6. Thematic clustering (SOP 1.6, 3.2)

**Clustering run** ("Theme clustering", URDC):
- K-Means on the TF-IDF vectors of the published records, with `init='k-means++'`, `n_init=10` and a fixed `random_state` for repeatability.
- It tests k = 2 to 15 and shows an elbow chart (inertia), the average silhouette and the Davies–Bouldin index. It suggests the k with the best silhouette, and the URDC can override it.

**Outputs saved to `cluster_runs` and `clusters`:**
- The cluster for each record.
- The top 10 terms by centroid weight.
- A 2-D projection (TruncatedSVD to 2 components) for the theme map.

**LLM label suggestions:** after each run, the LLM suggests a label of at most 5 words for each cluster (section 10).
- Coordinators ("Theme labels") accept, edit or reject each label and rate it from 1 to 4.
- The fallback is the top 3 terms.

**Schedule:** each semester by default (Settings), or after N new records, plus a manual "Run now". The run is a queue job, and memory must stay under about 1.5 GB (see section 14).

A run becomes `current` only after the URDC confirms it. Earlier runs are archived and kept.

### M7. Analytics and visualization (SOP 2, 3.3)

Build the 13 visual analytics in Table 7 of the manuscript with ECharts. Every chart has filters for college, program, year, theme and SDG. **Clicking any chart segment opens the list of records behind it**, following the visual information-seeking mantra.

1. Summary KPI cards: total outputs, outputs this year, growth rate, active users, downloads.
2. Research output trend per year: a line chart with a linear trend line labeled "descriptive only".
3. Outputs by college and program: a stacked bar chart.
4. Distribution by research type: a donut chart.
5. Research theme map: a cluster scatter plot (2-D projection), plus theme distribution as a bar chart.
6. Themes by college: a heat map.
7. Top and emerging keywords: a bar chart. A word cloud is optional.
8. SDG alignment: a bar chart.
9. Adviser load and productivity: a bar chart and a table.
10. Similarity screening summary: screened, flagged and confirmed duplicates, as KPI cards and a bar chart.
11. Submission compliance per unit: KPI cards and a table.
12. Repository usage: views, downloads, searches and most-accessed studies, as line and bar charts.
13. Searches with no or few results: a table.

**Scopes**
- URDC: university-wide ("Analytics dashboard").
- Coordinator: own cluster and its colleges ("Cluster analytics").
- Adviser: supervised studies.
- Student: a summary only.

**Speed:** a nightly job fills `kpi_snapshots` so dashboards load fast. Live filters read from the snapshots.

**Exports:** CSV and PDF of any dashboard, for accreditation reports.

**Dashboard task mode** (for the evaluation, SOP 3.3): a URDC setting that records the time and completion of 5 set tasks per evaluator.

### M8. Activity monitoring and audit (SOP 1.7)

- "Activity log": timestamped and filterable by event type, user and date, with CSV export. The events are listed in section 13.
- Logs are append-only. No UI can edit or delete them. Retention is set in Settings.

### M9. Administration and backup (SOP 1.8)

- **"System health":**
  - Disk use against 70 GB, memory, the queue length and failed jobs.
  - The last backup, mail failures with retry, and the analytics and LLM service status.
- **"Services and security":** the status of each link between components (section 3), the last calls, and buttons that test each service.
- **"Users and roles":** search, assign a role (logged), deactivate or reactivate, resend activation, and assisted recovery (M1).
- **"System settings":**
  - Similarity thresholds (the review threshold must be lower than the duplicate threshold).
  - Clustering schedule, file limits, session timeout, sign-in attempts.
  - LLM on or off, timeout, rate limit, and the fields sent (read-only list).
  - Recovery channels on or off **[Not in manuscript]**.
- **"Backups":** an encrypted daily database and file backup, kept separately from the server (off-server copy), alongside the ICT Office's twice-daily manual backups.
  - Show size and status.
  - Restore to a staging database first, then promote. Run a monthly restore test.
- **"Evaluator accounts":** only in the evaluation copy (section 17).

---

## 8. Public landing page and CMS **[Not in manuscript]**

This is the public home page (`/`) for guests and signed-in users. The prototype screens are "Home page" and URDC › Website › "Landing page content".

**Sections, in their default order (the CMS can reorder, show or hide them):**
- **Site header:** logo, links to About, Studies, Clusters, Submit, News and Contact, plus "Sign in" and "Browse the catalog".
- **Announcement bar:** optional, with a label, a message and a link.
- **Hero:**
  - Small heading, headline, introduction, and a search box that opens the catalog with the query.
  - Popular searches as chips, and two buttons.
  - A "Latest approved studies" card (the 4 newest published records, filled automatically).
- **Key figures:** 4 numbers with labels. The default is the 1,419 papers on record from Table 1.
- **About the repository:** text plus feature cards.
- **Featured studies:** chosen by the URDC, or the most viewed or most recent automatically; 3, 6 or 9 cards.
- **Research clusters:** 4 cards with their colleges and the live study counts.
- **How submission works:** 4 steps plus a note on the required files.
- **Explore by SDG:** chips with live counts that open the filtered catalog.
- **News and announcements:** items with a date, a label, a headline and text, each with its own show/hide switch.
- **FAQ**, then **Contact**: office, address, email, phone, hours, and an **Account help** text that links to account recovery.
- **Footer:** text, a Privacy Notice link and a Sign in link.

**CMS (URDC and System administrator)**
- A form for each section, plus "Theme and branding": the default theme color, "let users pick their own color", the site name and tagline, and the footer.
- **Draft and publish:** edits autosave to a draft (`site_contents.status=draft`).
  - "Preview" shows the draft with a banner.
  - "Publish" makes it live and saves a new version.
  - "Discard changes" and "Load default content" are also available.
- Publishing history shows who published, when, and which sections changed. Each publish is written to the activity log as event `cms`.
- Content is plain text. Escape all of it on output and do not allow raw HTML.
- Records shown on the page are always live data. The CMS stores only their IDs.

---

## 9. Theme color and appearance **[Not in manuscript]**

- The "Theme" button in the top bar opens a popover with 8 swatches:
  - Navy `#1e3a6b` (default), Maroon `#7a1f2b`, Forest `#1f5f3f`, Teal `#0f5e66`
  - Violet `#4b3a8c`, Rust `#8a3b12`, Slate `#334155`, Magenta `#9c2a6b`
- It also has a custom color picker and an appearance control: System, Light or Dark.
- The choice is saved to the user (`users.theme_color`, `users.theme_mode`). Guests keep it in localStorage.
- The site default comes from the CMS. If the URDC turns off "let users pick", everyone sees the default color, but light and dark mode can still be chosen.
- **How a theme is built:** from the chosen hue, compute these CSS variables:
  - Light mode: `--accent` (lightness capped at 36% so white text passes WCAG AA), `--accent-soft` (93% lightness), `--bar` and the chart color `--c1`.
  - Dark mode: accent at 74% lightness, soft at 20%, bar at 14%.
- Gold `#b5862a` stays the second brand color. Status colors (ok, warn, bad) never change with the theme.
- Charts read the CSS variables and redraw when the theme changes.

---

## 10. LLM integration (suggestions only)

**`LlmClient` interface:**
- `suggestMetadata(title, abstract): {keywords[3..5], sdgs[int], research_type}`
- `suggestThemeLabel(topTerms[10], nearestTitles[5]): {label ≤ 5 words}`

**Prompts:** fixed and versioned, with a system instruction to return JSON only. Validate the reply against a JSON schema:
- Keywords must be lowercase, 1–4 words, with no duplicates.
- SDGs must be numbers from 1 to 17.
- The research type must be one of the four types.
- The label must be 5 words or fewer and contain no punctuation other than "and".

Anything invalid is dropped, and the fallback is used.

**Fallback:**
- Metadata: the top 5 TF-IDF terms of the text, from the analytics service. There are no SDG or type suggestions.
- Labels: the top 3 terms of the cluster.

The UI says "Suggestions unavailable, showing top terms".

**UI rules** (see the prototype's "Submit a study"):
- Suggestions appear in a dashed gold box marked **LLM suggestion**.
- Each item has Accept, Edit and Remove. Nothing is added without a click.

**Logging:** every suggestion and the user's action (accepted, edited or rejected) go to `llm_suggestions` and the activity log. The acceptance rates and the coordinators' 1–4 label ratings feed the "AI performance" page (SOP 3.4).

**Privacy:** send only the title, abstract, keywords and cluster terms, never names, emails, files or full text.
- The key lives in `.env` on the server and is never sent to the browser.
- Use the provider's setting that excludes data from training.
- The Data Privacy Officer's approval date appears in "Services and security".

---

## 11. Python analytics service API

All endpoints are POST with JSON, signed with HMAC, and reachable only on 127.0.0.1.

| Endpoint | Request | Response |
|---|---|---|
| `/fit` | `{records:[{id,text}]}` | `{vectorizer_version, n_docs, n_terms}` (stores the vectorizer and matrix) |
| `/screen` | `{text, k:5}` | `{matches:[{id,score,shared_terms[]}], top_terms[]}` |
| `/related` | `{id, k:5}` | `{matches:[{id,score}]}` |
| `/cluster` | `{k_min:2,k_max:15,k:null}` | `{scores:[{k,inertia,silhouette,db}], chosen_k, assignments:[{id,cluster,x,y}], clusters:[{n,top_terms[],size}]}` |
| `/top-terms` | `{text, n:5}` | `{terms[]}` (LLM fallback) |
| `/health` | `{}` | `{ok, vectorizer_version, n_docs, mem_mb}` |

Errors return `{error, detail}` with HTTP 4xx or 5xx. Laravel retries 3 times with backoff and logs the failure. Run the service with 1 Uvicorn worker under Supervisor, with memory capped by `MemoryMax` in systemd.

---

## 12. Email (and SMS) notifications

All email goes out through queue jobs and is written to `mail_log`. A failed send retries 3 times, and failures show in System health. Every email comes from a TUA sender, follows a simple branded template, and includes a plain-text version.

| Trigger | To |
|---|---|
| Activation link | New user |
| Password reset or recovery code | User (TUA email or recovery email); SMS when chosen **[Not in manuscript]** |
| Recovery contact changed, password changed | User's TUA email (and the old recovery email) |
| Submission received, with the screening result | Student |
| New submission to endorse | Adviser |
| Endorsed; or flagged and needs review | Student; coordinator of the cluster |
| Reviewed and ready for approval | URDC |
| Returned or rejected, with the reason | Student (and the adviser) |
| Published | Student, adviser |
| Full-paper request; approved or declined | URDC; student |
| Clustering run finished, labels waiting | URDC, coordinators |
| Backup failed, service down | System administrator |

---

## 13. Activity log events

Event types: `login`, `logout`, `login_failed`, `register`, `activate`, `recovery`, `password_reset`, `search`, `view`, `download`, `upload`, `submit`, `endorsed`, `reviewed`, `published`, `returned`, `rejected`, `request`, `llm`, `service`, `notify`, `role`, `setting`, `cms`, `theme`, `import`, `backup`, `cluster_run`, `metadata_edit`.

Each entry stores: time (Asia/Manila), user, event, details, subject, IP address, user agent, and old and new values for edits.

---

## 14. Non-functional requirements (ISO/IEC 25010:2023)

The evaluation (SOP 4) rates these characteristics, so build for them.

- **Functional suitability:** every SOP 1.1–1.8 item works end to end. Use the acceptance tests in section 7.
- **Performance efficiency:** on the 4 GB server, aim for these page times at the 95th percentile:
  - Catalog and search pages: under 1.5 s.
  - Dashboards: under 2 s (from snapshots).
  - Screening result: shown within 10 s of submit.
  - Clustering of about 1,500–5,000 records: under 5 minutes, with memory under 1.5 GB. Use sparse matrices, `float32`, and `MiniBatchKMeans` if the number of records grows past 10,000.
- **Compatibility:** latest Chrome, Edge, Firefox and Safari, plus Android Chrome and iOS Safari. Exports open in Excel.
- **Interaction capability:** follow WCAG 2.2 AA:
  - Labels on every input, focus rings, keyboard navigation, color contrast of at least 4.5:1.
  - Errors written next to the field.
  - Plain English in the UI, using the prototype's wording.
- **Reliability:** queue retries, a daily backup with a monthly restore test, the LLM fallback, and clear "service unavailable" messages.
- **Security:**
  - HTTPS with an HSTS header, a CSP header, secure HttpOnly SameSite=Lax cookies, CSRF protection.
  - bcrypt passwords, input validation, upload MIME sniffing, and private file storage with signed temporary download URLs.
  - Rate limits on sign-in, recovery, search and the LLM.
  - An OWASP ZAP baseline scan with no high findings before release.
- **Maintainability:** domain folders, service interfaces, PSR-12 coding style, Larastan level 6, and Pest coverage of at least 70% on the Domain folders. The README explains how to run everything.
- **Flexibility:** the LLM, SMS and mail providers can be swapped. Thresholds, the schedule and limits are in Settings. The design is modular so it can later be connected to other University systems (Smart Campus).
- **Safety:** no automatic rejection. Every AI output can be undone. Trend lines are labeled descriptive. Nothing is decided about a person from the analytics.
- **Data privacy (RA 10173):** a privacy notice at registration, minimal personal data, export and delete on request (handled by the URDC), and DPO approval for the LLM.

---

## 15. UI and design system (match the prototype)

**Layout:** a top bar (logo, title "TUA Research Repository / University Research and Development Center", the Theme button, and the user menu), a left navigation grouped by role, and a scrolling main area with a maximum width of 1180 px. Logged-out pages use the landing layout.

**Fonts:** IBM Plex Sans for text, IBM Plex Serif 600 for page titles, IBM Plex Mono for IDs and scores (self-host them).

**Color tokens (light / dark):**

| Token | Light | Dark |
|---|---|---|
| `--bg` | `#f3f5f8` | `#0e1420` |
| `--surface` | `#ffffff` | `#161e2d` |
| `--surface-2` | `#eef2f7` | `#1c2638` |
| `--ink` | `#162033` | `#e5eaf2` |
| `--muted` | `#5a6579` | `#9aa6ba` |
| `--line` | `#dbe1ea` | `#2a3549` |
| `--accent` | `#1e3a6b` | `#8eb0ea` |
| `--accent-soft` | `#e3eaf6` | `#1f2d48` |
| `--gold` | `#b5862a` | `#e0b45a` |
| `--gold-soft` | `#f6ecd6` | `#3a2f17` |
| `--ok` | `#23744a` | `#62c18b` |
| `--ok-soft` | `#e2f2e8` | `#173326` |
| `--warn` | `#a66a10` | `#e8a94a` |
| `--warn-soft` | `#fbefd9` | `#3a2b12` |
| `--bad` | `#b0281c` | `#f08a7e` |
| `--bad-soft` | `#fbe4e1` | `#3d1c19` |

Chart series colors c1–c7, light: `#1e3a6b #b5862a #3f8f86 #a4486a #6a5fb0 #5f8a3a #c2622d`.

**Components** (build them as Blade components): button (default, primary, ok, bad, small), pill (ok, warn, bad, info, gold), chip, panel, KPI card, table, stepper, score bar, callout (info, ok, bad, gold), drawer, toast, LLM suggestion box with an `ai-tag`, file slot, empty state.

**Wording:**
- Short, plain sentences.
- Buttons say what they do ("Endorse", "Return with reason", "Approve and publish").
- Statuses are Submitted, Endorsed by adviser, Reviewed by coordinator, Published, Returned, Rejected.
- Flags are "Probable duplicate", "Similar, needs review" and "Original".

---

## 16. Mobile (web and mobile)

The manuscript defines the system as web-based, used "through a web browser on a computer or mobile device". So mobile means a **responsive web app that can be installed as a PWA**, not a separate native app. A native Android or iOS app would need a change to the manuscript's scope first. If the developer wants one later, add a token-authenticated JSON API (Laravel Sanctum) under `/api/v1`, shaped to match the screens below.

**Responsive rules (test at 390 × 844 and 768 × 1024)**
- At 820 px and below:
  - The left navigation becomes a horizontal scrolling tab bar under the top bar.
  - Grids collapse to one column, and tables scroll sideways inside their container.
  - The page never scrolls sideways (`scrollWidth` equals the viewport width).
- Touch targets are at least 44 px, form inputs are at least 16 px (so iOS does not zoom), and there is a 16 px side gutter. Respect `env(safe-area-inset-*)`.
- Charts resize with the container and use a taller aspect ratio on phones. Their legends move below the chart.
- **Landing page on phones:**
  - The site links hide, and the "Sign in" and "Browse" buttons stay.
  - The hero stacks, and the search box stacks its button.
  - Section grids become one column.

**Mobile-first screens** (the prototype has phone screenshots):
- Student: submit with LLM suggestions, status, notifications, account and recovery.
- Adviser: endorsement queue.
- Coordinator: similarity review, with the side-by-side comparison stacked.
- URDC: approvals, full-paper requests, and the analytics KPIs.

**PWA**
- `manifest.webmanifest`: name "TUA Research Repository", short name "TUA Repository", theme color = the current accent, icons at 192 and 512 px, `display: standalone`.
- **Service worker** (Workbox or plain):
  - Cache the app shell and static assets only.
  - Show an "You are offline" page.
  - Never cache research files, personal pages or API responses that contain personal data.
- File uploads on phones accept the camera or document picker for PDFs. Show upload progress, and allow a retry on a weak connection.

**Mobile tests:** Playwright projects for "Pixel 7" and "iPhone 14". Run the critical paths on both: register, activate, sign in, submit, endorse, review, approve, search, account recovery, and theme change.

---

## 17. Evaluation copy and data

- A separate deployment (for example `eval-repository.tua.edu.ph`) with **sample or anonymized records only**.
- Temporary evaluator accounts for external IT experts are made by the System administrator. They expire automatically (default: the end of the evaluation period), every action is logged, and they cannot exist in production (guarded by `APP_EVALUATION_COPY=true`).
- A seeder loads the labeled screening test set (at least 100 pairs) and the dashboard task sheet.
- The questionnaires (ISO/IEC 25010 sections A–F) are run outside the system. The system only supplies the measured metrics on the "AI performance" page.

---

## 18. Development method (Agile Scrum, as in the manuscript)

**Roles:** the researcher is the Developer, the thesis adviser is the Scrum Master, and a URDC representative is the Product Owner.

**Sprints:** two weeks each, ending with a sprint review (URDC, coordinators, advisers, students) and a retrospective. CEIS is the pilot college.

**Suggested backlog order:**

| Sprint | Goal |
|---|---|
| 0 | Repo, CI (Pest, pytest, Larastan, Pint), server setup, design tokens and components, seed data |
| 1 | M1: register, activate, sign in, roles, recovery and account page; M8 logging foundation |
| 2 | M2: records, metadata, file slots, bulk import (Google Form migration) |
| 3 | M3: submission and approval workflow, notifications, status page |
| 4 | Python service: preprocessing, `/fit`, `/screen`, `/related`; M5 screening and coordinator review |
| 5 | M4: search, facets, study page, related studies, public catalog, full-paper requests |
| 6 | M6: clustering runs and theme map; LLM metadata and label suggestions with fallback |
| 7 | M7: the 13 dashboards, drill-down, exports, KPI snapshots |
| 8 | Landing page and CMS, theme color, PWA and mobile polish |
| 9 | M9: health, services, settings, backups, evaluator accounts; security hardening, ZAP scan |
| 10 | Evaluation copy, labeled test set, AI performance page, pilot deployment |

**Definition of done for every story:**
1. It meets the acceptance criteria.
2. Tests pass (desktop and mobile Playwright for UI stories).
3. Actions are logged.
4. Text is escaped.
5. Policies are checked.
6. It works at 390 px.
7. Dark mode is readable.
8. The README is updated.
9. It has been demoed in the sprint review.

---

## 19. Deployment (ICT Office server)

**Server setup**
- Ubuntu with Nginx, PHP-FPM, MySQL 8 and Python in a venv.
- Supervisor runs `queue:work` (2 processes) and Uvicorn (1 worker).
- Cron runs `schedule:run` every minute.
- A tua.edu.ph subdomain with SSL. UFW allows only ports 80 and 443 and SSH from the ICT network.

**`.env` secrets:** `APP_KEY`, the DB credentials, `ANALYTICS_HMAC_SECRET`, `LLM_API_KEY`, the SMTP settings, `SMS_API_KEY` (optional), and `BACKUP_ARCHIVE_PASSWORD`. Never commit them.

**`deploy.sh`:**
1. `git pull`
2. `composer install --no-dev`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. `config:cache`, `route:cache`, `view:cache`
6. `queue:restart`
7. Restart the analytics service.

**Storage budget (70 GB):** about 5 GB of files for 1,500 papers. Keep 7 daily, 4 weekly and 3 monthly backups off-server. An alert fires at 80% disk use.

Follow the University's Smart Campus initiative and the ICT Office's backup schedule.

---

## 20. Open items to confirm before or during development

1. Which colleges belong to each of the four URDC clusters, and who the coordinators are.
2. The abstract word limit and any other URDC format rules.
3. Where a resubmitted study re-enters the workflow (assumed: the adviser).
4. The official URDC contact email, phone and office for the landing page and for assisted recovery.
5. **[Not in manuscript]** Whether to add the landing page CMS, theme color and recovery email or mobile (with an SMS gateway) to the manuscript's M1 and M9 and to Figure 7. If they are not added, keep them switched off in the version that is evaluated.
6. The LLM provider and the Data Privacy Officer's approval. The SMTP route approved by the ICT Office (the ICT reply was unclear about email).
7. Whether the ICT server will run PHP 8.2 or 8.3, and MySQL 8.
