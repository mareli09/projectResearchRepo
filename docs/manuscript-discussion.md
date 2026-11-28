# TUA Research Repository System: Notes for the Manuscript

**System:** Web-Based Research Repository System with Data Analytics and Visualization Using TF-IDF and K-Means Clustering, for Trinity University of Asia (TUA), administered by the University Research and Development Center (URDC).

**Status as of November 28, 2026:** every module (M1–M9) and the evaluation tools are built and tested on the development machine. The gaps found while writing the first version of these notes have since been closed (see §15). The system is not yet on the ICT Office server and has not yet been evaluated by users.

**How to use this file.** It explains what was built, how it works and why, in the order of the Statement of the Problem (SOP). Use it when writing:
- the system description and design (architecture, modules, algorithms);
- the discussion of how each SOP item was met;
- the scope, limitations and deviations from the plan;
- the preparation for Chapter 4 (what still has to be measured).

> **Important for Chapter 4.** All figures in this file come from the development copy. Its 1,419 records are **sample data** made by the prototype's generator, not real TUA studies. They show that the system works. They are **not** evaluation results. The results for SOP 3 and SOP 4 must come from the evaluation copy, with real or anonymized records, real coordinator labels and real evaluators.

---

## Contents

1. [Summary: SOP items and how they were met](#1-summary-sop-items-and-how-they-were-met)
2. [Development method](#2-development-method)
3. [System architecture](#3-system-architecture)
4. [Users, roles and access](#4-users-roles-and-access)
5. [Modules (SOP 1.1 to 1.8)](#5-modules-sop-11-to-18)
6. [The text-analytics algorithms](#6-the-text-analytics-algorithms)
7. [LLM integration (suggestions only)](#7-llm-integration-suggestions-only)
8. [Data analytics and visualization (SOP 2)](#8-data-analytics-and-visualization-sop-2)
9. [Evaluation tools built into the system (SOP 3)](#9-evaluation-tools-built-into-the-system-sop-3)
10. [Software quality: ISO/IEC 25010 (SOP 4)](#10-software-quality-isoiec-25010-sop-4)
11. [Data privacy and ethics](#11-data-privacy-and-ethics)
12. [Verification done during development](#12-verification-done-during-development)
13. [Decisions and deviations to state in the manuscript](#13-decisions-and-deviations-to-state-in-the-manuscript)
14. [Features not in the manuscript](#14-features-not-in-the-manuscript)
15. [Known gaps](#15-known-gaps)
16. [Limitations](#16-limitations)
17. [What Chapter 4 still needs](#17-what-chapter-4-still-needs)
18. [Appendix: default settings and logged events](#18-appendix-default-settings-and-logged-events)

---

## 1. Summary: SOP items and how they were met

| SOP | Requirement | Module | How the system meets it | Status |
|---|---|---|---|---|
| 1.1 | Repository and metadata management | M2 | Complete metadata per record, five typed file slots, SHA-256 per file, metadata editing with history, bulk import from the Google Form and older outputs | Built |
| 1.2 | Submission and approval workflow | M3 | Four steps: student submits, adviser endorses, coordinator reviews similarity, URDC approves and publishes. Every return or rejection needs a written reason | Built |
| 1.3 | Search, recommendation and public catalog | M4 | Public catalog of titles and abstracts, MySQL FULLTEXT search with seven facets, related studies by cosine similarity, full-paper requests | Built |
| 1.4 | Authentication and role-based access | M1 | TUA email only, activation link, five roles, rate-limited sign-in, account recovery | Built |
| 1.5 | Similarity screening | M5 | TF-IDF and cosine similarity against every published record. Flags only; the coordinator decides | Built |
| 1.6 | Thematic clustering | M6 | K-Means on TF-IDF vectors, k from 2 to 15, chosen by silhouette, with a 2-D theme map and theme labels | Built |
| 1.7 | Activity monitoring and audit | M8 | Append-only activity log of 29 event types, filterable, with CSV export and a retention period in Settings | Built |
| 1.8 | Administration and backup | M9 | System health, service tests, users and roles, settings, encrypted backups with restore testing | Built (off-server copy needs the ICT share) |
| 2 | Data analytics and visualization | M7 | The 13 visuals of Table 7, with filters for college, program, year, theme and SDG, drill-down to the records, and exports | Built |
| 3.1 | Accuracy of similarity screening | M5 | Labeled test set screens, confusion matrix, accuracy, precision, recall, F1, threshold slider | Tools built; labels not yet collected |
| 3.2 | Quality of clustering | M6 | Silhouette and Davies–Bouldin per run, coordinators' label decisions | Tools built; measured on sample data only |
| 3.3 | Usefulness of the dashboards | M7 | Dashboard task mode: 5 set tasks, time and completion per evaluator | Tools built; not yet run with evaluators |
| 3.4 | Acceptance of LLM suggestions | §10 | Accepted, edited and rejected counts; 1–4 label ratings | Tools built; no real usage yet |
| 4 | ISO/IEC 25010 software quality | All | Built for each characteristic (see §10). Questionnaires are run outside the system | Ready for evaluation |

---

## 2. Development method

The system was built with **Agile Scrum**, as planned in Chapter 2. Each sprint delivered working, tested screens. The order followed the planned backlog, with some sprints merged.

| Sprint (plan) | What was delivered |
|---|---|
| 0 | Project setup, database, design tokens and components, seed data (1,419 sample records), automated checks (CI) |
| 1 | M1 accounts and access; M8 logging foundation |
| 2 | M2 records, metadata, five file slots, bulk import |
| Design stage | Every prototype screen built at its final address and role, so the panel could see the whole system early |
| 3 | M3 submission and approval workflow, email and in-app notifications |
| 4 | Python analytics service; M5 similarity screening |
| 5 | M4 search logging, view logging, full-paper requests |
| 6 | M6 K-Means clustering and theme labels; LLM suggestions with fallback |
| 7 | M7 the 13 visuals (built in the design stage on real records), then exports and nightly snapshots |
| 8 | Landing page and CMS, theme color, installable web app (PWA), mobile fixes |
| 9 | M9 health, services, settings, backups, evaluator accounts; security headers; OWASP ZAP scan |
| 10 | Evaluation tools: labeled test set, AI performance page, dashboard task mode. **Pilot deployment still to do** |

**Definition of done** for each feature: it meets its acceptance criteria, its tests pass (including phone-size browser tests for screens), its actions are logged, text is escaped, permissions are checked, it works at 390 px wide, and dark mode is readable.

**Tools used in development:** Pest (PHP tests), pytest (Python tests), Playwright (browser tests on desktop, Pixel 7 and iPhone 14), Larastan level 6 (static analysis), Pint (PSR-12 code style), OWASP ZAP (security scan), GitHub Actions (runs the checks on every push).

---

## 3. System architecture

The system follows the three-tier architecture of Figure 6, with a separate analytics service and an external LLM.

```
Browser (computer or phone; can be installed as an app)
   │  HTTPS, session cookie, CSRF token
   ▼
Laravel 12 web application (modules M1–M9) ─────── MySQL database
   │   │                                             private file storage
   │   │                                             (outside the public web folder)
   │   └── HTTP on 127.0.0.1 only, HMAC-signed ──► Python analytics service
   │                                               (TF-IDF, cosine similarity, K-Means)
   ├────── HTTPS ──► External LLM (suggestions only)
   └────── SMTP  ──► University mail
Background workers: queue (emails, screening), long queue (backups, clustering), scheduler
```

**Presentation tier.** Web pages built with Blade, Livewire and Alpine.js, styled with Tailwind CSS. Charts use Apache ECharts 5. The same pages work on computers and phones.

**Application tier.** Laravel 12 on PHP 8.2. The code is grouped by domain: Accounts, Repository, Submissions, Search, Screening, Clustering, Analytics, Audit, Admin and Site. Every model has an access policy.

**Data tier.** MySQL 8 (MariaDB locally). Research files are stored outside the public web folder and are only served through a permission check.

**Analytics service.** A separate Python program (FastAPI with scikit-learn and NLTK). It does all text processing, so the algorithms are written once.
- It listens only on the server itself (127.0.0.1). It cannot be reached from the network.
- Every request must be signed with HMAC-SHA256 using a shared secret, and be less than 60 seconds old.
- It receives only text (title, abstract, keywords) and returns only scores, cluster numbers and terms. It has no access to the database.

**Why a separate service.** PHP has no mature equivalent of scikit-learn. Keeping Python separate means the web pages stay fast, the algorithms can be tested on their own, and clustering can run in the background without slowing the site.

**Background work.** Long tasks never run while a user waits for a page:
- Similarity screening runs as a queue job right after submission (the student sees "Screening…").
- Emails are sent by queue jobs and retried 3 times.
- Clustering, backups and restores run on a separate "long" queue, so a 10-minute job cannot block emails.
- A scheduler runs the nightly jobs (see §18).

**Server target.** The ICT Office server: Ubuntu, Intel, 4 GB RAM, 70 GB disk. Nginx, PHP-FPM, MySQL, Supervisor for the workers, and cron for the scheduler. The deployment files are in `deploy/`.

---

## 4. Users, roles and access

There are five roles plus guests.

| Role | Main tasks |
|---|---|
| Guest (not signed in) | Landing page; public catalog of approved titles and abstracts |
| Student | Search; submit, revise and resubmit a study; request a full paper; own research summary |
| Faculty adviser | Endorse or return advisees' submissions; supervised-studies dashboard |
| Research coordinator | Decide on similarity flags for their cluster; label themes; cluster dashboard |
| URDC administrator | Approve and publish; full-paper requests; records and import; clustering; university-wide dashboards; AI performance |
| System administrator | Users and roles, settings, health, services, backups, evaluator accounts |

**Key access rules:**
- Self-registration gives only the Student or Faculty adviser role. Only the System administrator assigns Coordinator, URDC or System administrator, and each change is logged with the old and new role.
- Students cannot open full papers directly. They request access, and the URDC approves it with an expiry date.
- The System administrator manages the system but cannot open research files. This separates technical control from access to the research.
- The signed Transfer of Ownership Agreement is visible to the URDC only.
- Every cell of the access table in the build guide is covered by an automated test.

---

## 5. Modules (SOP 1.1 to 1.8)

### 5.1 M1: Authentication and role-based access (SOP 1.4)

**What it does**
- **Registration** accepts only `@tua.edu.ph` addresses, in any letter case. A Gmail address is refused.
- **Passwords** need at least 10 characters, with a letter and a number, and must not be on a list of common passwords. They are stored with bcrypt.
- **Activation:** a new account stays inactive until the user opens a one-time link sent to the TUA email. The link expires after 48 hours. Sending a new link cancels the old one. Only a hash of the token is stored.
- **Sign-in** pauses for 1 minute after 5 failed attempts per email and IP address. The session is renewed at sign-in and ends after 30 minutes of inactivity.
- **Account recovery:** the user enters the TUA email, chooses a channel, receives a 6-digit code (valid 10 minutes, one use, 5 tries), and sets a new password. All other sessions are signed out and a notice is sent. The answer is the same whether or not the account exists, so the form cannot be used to find accounts.
- **Assisted recovery:** if the user can reach none of their contacts, the URDC checks an ID, sets a new recovery email and sends a one-time reset link to it. Staff never see or set the password.
- **Account and recovery page:** recovery contacts (each verified with a code), recent security events, theme.

**Design decisions to discuss**
- The activation link opens a page with an "Activate" button instead of activating at once. Some mail systems open links automatically to scan them, which would use up a one-time link before the user sees it.
- Laravel Fortify is used only for sign-in, sign-out and password change. Its own registration and reset features are switched off, because the manuscript's activation and recovery rules are stricter.

### 5.2 M2: Repository and metadata management (SOP 1.1)

**What it does**
- Every record has a title, abstract, authors, adviser, college, program, degree level, research type, year, 3 to 5 keywords, SDG tags and a status (draft, published or withdrawn).
- **Five typed file slots**, from the Centralized Repository protocol:
  1. Abstract in the URDC format (Word)
  2. Full paper (PDF)
  3. IMRAD version (PDF; the URDC can mark it not applicable)
  4. Signed Transfer of Ownership Agreement R-12-01 (PDF)
  5. List of references (Word)
- **File checks:** both the extension and the real file content are checked, so a renamed file is refused. The size limit is 20 MB by default (a setting).
- **SHA-256 fingerprint** for every file. An identical file already stored for another record is refused, and the message names that record.
- A record cannot be published if a required file is missing, it has fewer than 3 or more than 5 keywords, or the abstract is over 250 words (a setting).
- **Records and metadata page** (URDC): search, filter by college or by missing data (no keywords, no SDG, incomplete files), edit metadata, withdraw. Every edit is logged with old and new values.
- **Bulk import** (URDC): a CSV or Excel template, with an optional ZIP of files, for migrating the Google Form submissions and older outputs. A dry run shows errors row by row and duplicates by title and by file hash, before anything is saved.

**Why this matters.** The current Centralized Repository relies on a Google Form. The import lets the URDC move those records without retyping, and the dry run prevents duplicates and incomplete records from entering the new system.

### 5.3 M3: Submission and approval workflow (SOP 1.2)

```
Draft ──► Submitted ──► Endorsed by adviser ──► Reviewed by coordinator ──► Published
              │                │                         │                       ▲
              └──── Returned (reason required) ◄─────────┴─── by any reviewer   │
                        │                                                      URDC
                        └──► Revise and resubmit ──► back to the adviser
            Rejected (coordinator or URDC, reason required, final)
```

**On submit, in this order:**
1. Metadata and abstract word-limit checks.
2. All required files present.
3. Exact-file check: an identical file (same SHA-256) is stopped, naming the matching record.
4. Similarity screening runs in the background (M5).
5. The status becomes Submitted and the adviser is emailed.

**Each step**
- **Adviser:** endorse, or return with a reason.
- **Coordinator:** sees the submission beside its closest match, with shared terms highlighted. Decides: clear, return for revision, or reject. A reason is required except when clearing an original. The decision also records whether the pair is a duplicate or distinct, which feeds the accuracy measures (SOP 3.1).
- **URDC:** approve and publish, return, or reject, with a reason. Publishing creates the repository record, moves the five files to it, makes it searchable and queues it for the next clustering run.
- **Student:** a four-step progress bar, the full history with names, times and reasons, and the top 5 screening matches. A returned study can be revised and resubmitted. New files get new fingerprints, and older versions are kept.
- **Save draft:** a student can save an unfinished submission with its files. Only the student sees it. Nothing is sent to anyone until it is submitted.

**Every transition** is allowed only for the right role and owner, writes a review row and an activity-log entry, and sends an email and an in-app notification.

### 5.4 M4: Search, recommendation and public catalog (SOP 1.3)

- **Public catalog (guests):** titles, abstracts and metadata of published records only. No files.
- **Search (signed in):** full-text search over title, abstract and keywords. Facets: college, program, year, research type, theme, SDG and adviser. When a college is chosen, the program list shows only that college's programs. Sort by relevance, newest or most viewed. 20 results per page. How the text search works is explained in §6.5.
- **Study page:** metadata, abstract, keywords, SDGs, theme, and **Related studies**: the 5 records with the highest cosine similarity to this one (from the analytics service; shared keywords are used if the service is down).
- **Full-paper requests:** the student states a purpose; the URDC approves with an expiry date (default 14 days) or declines with a reason; the student is emailed either way. Access ends automatically on the expiry date.
- **Downloads** use links that expire after 10 minutes. Each download is checked against the user's permission and logged.
- **Logging for analytics:** every search is logged, including searches with no results (used by Table 7 visual 13). Every study view is counted.

### 5.5 M5: Similarity screening (SOP 1.5)

The method is explained in §6. In short:
- Every new submission is compared with every published record using TF-IDF and cosine similarity.
- The top 5 matches are shown with their scores and the terms they share.
- Flags: **Probable duplicate** (score 0.80 or higher), **Similar, needs review** (0.60 to 0.79), **Original** (below 0.60). The thresholds are settings.

**The system never rejects a study by itself.** Only an identical file is stopped automatically, because that is a fact, not a judgment. A high similarity score can have innocent causes, for example a continuation study, a replication or a common topic. The coordinator always decides, and the decision is logged with a reason.

### 5.6 M6: Thematic clustering (SOP 1.6)

The method is explained in §6. In short:
- K-Means groups the published records into themes, using their TF-IDF vectors.
- The URDC starts a run (or the schedule does). The system tries k = 2 to 15 and suggests the k with the best silhouette score. The URDC can choose another k.
- Each theme gets its top 10 terms, a 2-D position for the theme map and a suggested label.
- A run becomes **current** only when the URDC confirms it. Older runs are archived and kept, so changes in the themes over time can be compared.
- **Schedule:** each semester by default (150 days after the last run), monthly, or after a set number of new records, plus "Run clustering now".
- **Theme labels:** the LLM suggests a label of at most 5 words; coordinators accept, edit or reject it and rate it from 1 to 4. Without the LLM, the label is the top 3 terms.

### 5.7 M8: Activity monitoring and audit (SOP 1.7)

- Every important action is logged with the time (Asia/Manila), user, event type, details, the record it concerns, IP address, browser, and old and new values for edits. There are 29 event types (listed in §18).
- The Activity log page filters by event, user and date, and exports to CSV.
- **The log is append-only:** the application blocks every update and delete of a log entry, and no screen can change it.
- **Retention:** the System administrator sets how long entries are kept, from 1 to 10 years (default 5). Every night at 3:00 AM, entries older than that are removed in one batch job, and the removal itself is logged with the cut-off date and the number removed. This is the only way entries leave the log. Removed entries stay in the backups until those expire (3 months at most). The Activity log page states the current retention period.
- Exchanges between components (analytics service, LLM, mail) are also logged, with the purpose, the result and the duration.

### 5.8 M9: Administration and backup (SOP 1.8)

- **System health:** disk use against the 70 GB, database and file sizes, memory, queue length, failed jobs and emails (with a retry button), the last backup, and the status of the analytics service and the LLM.
- **Services and security:** a test button for the analytics service, the LLM (it sends a fixed sample text, never repository data) and email, plus recent calls between components and the Data Privacy Officer's approval date.
- **Users and roles:** search, assign roles (logged), deactivate or reactivate, resend activation, assisted recovery.
- **System settings:** similarity thresholds (the review threshold must be lower than the duplicate threshold), clustering schedule, file and abstract limits, session timeout, sign-in attempts, the LLM switch, timeout and rate limit. Every change is logged with old and new values, and applies at once.
- **Backups:**
  - An encrypted (AES-256) daily backup of the database and research files at 2:00 AM.
  - A copy written to a folder outside the server, once the ICT Office provides one.
  - Kept: 7 daily, 4 weekly and 3 monthly copies.
  - **Restore to staging:** a backup is loaded into a separate database and 8 key tables are compared with the live data. A restore test runs on its own on the 1st of every month.
  - Restoring to the live system is possible only from the command line, only for a backup that passed the staging check, and it takes a safety backup first.
  - Failures are emailed to the System administrators.
- **Evaluator accounts** (evaluation copy only): see §9.5.

---

## 6. The text-analytics algorithms

All text processing is in the Python analytics service, so stored records and new submissions are always processed the same way.

### 6.1 Preprocessing

For each record or submission:
1. **Combine the fields** into one text: the title **twice**, the abstract once, and the keywords **three times**. Repeating the title and keywords gives them more weight, because they are written by the author to describe the study.
2. **Clean:** lowercase; remove punctuation and numbers; remove English stop words (the NLTK list) and a domain list of words that appear in almost every study and say nothing about its topic: *study, research, thesis, system, paper, capstone, project, result, method, respondent, using, based, among, toward, objective, conclusion, findings* (and their plurals).
3. **Lemmatize** each word with the WordNet lemmatizer, so that *students* and *student* count as the same term.

Only English-language records are analysed (delimitation).

### 6.2 TF-IDF

Each text becomes a vector of term weights. Terms are single words and two-word phrases (unigrams and bigrams).

$$w(t,d) = \mathrm{tf}(t,d) \times \mathrm{idf}(t), \qquad \mathrm{idf}(t) = \ln\frac{N}{\mathrm{df}(t)} + 1$$

where tf(t, d) is how many times term t appears in document d, N is the number of published records, and df(t) is the number of records that contain t. Each vector is then scaled to length 1 (L2 normalization).

> **Note for the manuscript.** The manuscript gives $w = tf \times \log(N/df)$. scikit-learn's `TfidfVectorizer` (with `smooth_idf=False`) adds **+1** to the idf. Without it, a term found in every record would get weight 0 and disappear. The ranking of terms is otherwise the same. Mention this in the methodology so the formula matches the implementation.

Settings used: `TfidfVectorizer(sublinear_tf=False, norm='l2', min_df=1, ngram_range=(1, 2), smooth_idf=False)`, stored as 32-bit floats in a sparse matrix to save memory. The fitted vectorizer is saved and versioned, and refitted after every 50 new published records and before every clustering run.

### 6.3 Cosine similarity (screening and related studies)

$$\cos(\mathbf{q}, \mathbf{d}) = \frac{\mathbf{q}\cdot\mathbf{d}}{\lVert\mathbf{q}\rVert\,\lVert\mathbf{d}\rVert}$$

Because every vector has length 1, the cosine is simply the dot product. The submission's vector is compared with all published records in one matrix multiplication, which takes well under a second for a few thousand records. The score runs from 0 (no shared terms) to 1 (the same terms in the same proportions).

**Shared terms** shown to the coordinator are the terms present in both texts, ranked by the product of their weights in each.

**Flags:**

| Score | Flag | Meaning |
|---|---|---|
| ≥ 0.80 | Probable duplicate | The coordinator must look closely |
| 0.60 – 0.79 | Similar, needs review | Overlapping topic or wording |
| < 0.60 | Original | Matches are shown as related studies |

**Checks:** automated tests confirm that the same text scores 1.0 and unrelated text scores below 0.2, and that the flag changes exactly at each threshold.

### 6.4 K-Means clustering

- **Input:** the TF-IDF vectors of all published records.
- **Algorithm:** scikit-learn `KMeans` with `init='k-means++'`, `n_init=10` (10 starts, the best is kept) and a fixed `random_state=42`, so the same data always gives the same result. Above 10,000 records the system switches to `MiniBatchKMeans` to stay within the server's memory.
- **Choosing k:** every k from 2 to 15 is tried. For each, the system records:
  - **Inertia** (within-cluster sum of squares) for the elbow chart.
  - **Average silhouette**, using cosine distance (on a sample of up to 3,000 records):
    $$s(i) = \frac{b(i) - a(i)}{\max\{a(i),\, b(i)\}}$$
    where a(i) is the mean distance from record i to the others in its cluster and b(i) the mean distance to the nearest other cluster. It runs from −1 to 1; higher is better.
  - **Davies–Bouldin index** (lower is better):
    $$DB = \frac{1}{k}\sum_{i=1}^{k}\max_{j\neq i}\frac{\sigma_i + \sigma_j}{d(c_i, c_j)}$$
    where σ is the average distance of a cluster's records to its centre and d(c_i, c_j) the distance between centres. It is computed on a 100-dimension TruncatedSVD reduction of the vectors, because it needs dense data.
  - The system **suggests the k with the highest silhouette**. The URDC may override it, for example to match the number of URDC clusters.
- **Outputs per theme:** the top 10 terms by centroid weight, the size, and the 5 records nearest the centre (used for the label suggestion).
- **Theme map:** each record is placed on a 2-D scatter plot using the first two components of the TruncatedSVD. Records that are close on the map have similar wording.

**Development run** (sample data, for illustration only): 1,420 records, k = 8 chosen by silhouette, about 11 seconds on the development laptop. The target on the server is under 5 minutes and under 1.5 GB of memory for 1,500 to 5,000 records.

### 6.5 Keyword search (MySQL FULLTEXT)

Search and the public catalog use the database, not the analytics service, so they stay fast and work even when the service is down. The study page's **Related studies** use cosine similarity (§6.3).

- **Index:** a MySQL FULLTEXT index on the title and abstract.
- **Matching:** every word of 3 or more letters in the query must appear in the record, in any order, as a whole word or the start of a word. For example, "invent canteen" finds "Inventory System for a Canteen". This uses FULLTEXT boolean mode (`+invent* +canteen*`). Common words such as *the*, *for* and *with* are skipped.
- **Substring match as well:** the title, abstract and keywords are also searched for the query as typed. This finds short terms that FULLTEXT ignores, such as "AI" or "IT", and exact phrases.
- **Relevance order:** first the records whose title contains the whole query, then the FULLTEXT relevance score (natural-language mode), then the most viewed.
- **Safety:** FULLTEXT operators typed by the user are removed, and `%` and `_` are treated as plain characters, so a query cannot change the meaning of the search.
- **Implementation note:** the build guide names Laravel Scout's database driver. It was not used, because that driver orders results by record number, not by relevance. Laravel's query builder sends the same FULLTEXT SQL directly.

---

## 7. LLM integration (suggestions only)

**What the LLM does**
- On "Submit a study": suggests 3 to 5 keywords, SDG tags and the research type from the **title and abstract only**.
- After a clustering run: suggests a theme label of at most 5 words from the theme's **top terms and the 5 nearest titles only**.

**Safeguards**
- **It only suggests.** Each suggestion appears in a gold dashed box marked "LLM suggestion", with Accept, Edit and Remove. Nothing is saved without the user's click.
- **Minimal data.** It never receives names, emails, files or full papers.
- **Validated replies.** The reply must be JSON and pass a schema check: lowercase keywords of 1 to 4 words with no duplicates, SDG numbers from 1 to 17, one of the four research types, a label of 5 words or fewer. Anything invalid is dropped.
- **Fallback.** If the LLM is off, slow (more than 8 seconds) or invalid, the system shows "Suggestions unavailable, showing top terms" and uses the top 5 TF-IDF terms (metadata) or the top 3 cluster terms (labels). The system works fully without the LLM.
- **Limits.** At most 60 calls a minute. The System administrator can switch it off.
- **Logged.** Every suggestion and what the user did with it (accepted, edited, rejected) is stored, with the provider, the time taken and whether the fallback was used. These records feed SOP 3.4.

**Provider.** The code talks to the LLM through an `LlmClient` interface, so the provider can be changed without changing the rest of the system. The current implementation uses the OpenAI API. The API key is kept on the server and is never sent to the browser. **The Data Privacy Officer must approve the provider before it is switched on**, and the approval date is shown on the Services and security page.

---

## 8. Data analytics and visualization (SOP 2)

### 8.1 The 13 visuals of Table 7

| # | Visual | Chart | Data source |
|---|---|---|---|
| 1 | Summary KPIs: total outputs, outputs this year, growth rate, active users, downloads | KPI cards | Nightly snapshot |
| 2 | Research output trend per year | Line chart with a linear trend line labeled "descriptive only" | Records |
| 3 | Outputs by college and program | Stacked bar chart: colleges by research type; with one college or program chosen, that college's programs by research type | Records |
| 4 | Distribution by research type | Donut chart | Records |
| 5 | Research theme map and theme distribution | Scatter plot (2-D projection) and bar chart | Current clustering run |
| 6 | Themes by college | Heat map | Records and clusters |
| 7 | Top and emerging keywords | Two bar charts: the 10 most used keywords of the last 3 years, and the 10 keywords whose share grew most | Keywords |
| 8 | SDG alignment | Bar chart | SDG tags |
| 9 | Adviser load and productivity | Bar chart and table | Records |
| 10 | Similarity screening summary: screened, flagged, confirmed duplicates | KPI cards and bar chart (per month, by flag) | Screening checks and coordinator decisions |
| 11 | Submission compliance per unit | Table with completion rates | File slots |
| 12 | Repository usage: views, downloads, searches, most-accessed studies | Line and bar charts (per week) | Activity, download and search logs |
| 13 | Searches with no or few results | Table | Search log (3 or fewer results, last 90 days) |

**Drill-down.** Following the visual information-seeking mantra ("overview first, zoom and filter, then details on demand"), clicking any bar, slice or point opens the list of records behind it in Search.

**Filters.** College, program, year, theme and SDG. The program list shows only the programs of the colleges in view, and a program from outside the user's scope is ignored.

**Emerging keywords (visual 7).** The latest two years are the recent period, and the three years before them are the earlier period. For each keyword used on at least 3 recent studies:

$$\text{growth}(k) = \frac{n_{\text{recent}}(k)}{N_{\text{recent}}}\times 100 \;-\; \frac{n_{\text{earlier}}(k)}{N_{\text{earlier}}}\times 100$$

where n(k) is the number of studies with keyword k in the period and N is the number of studies in the period. The result is in percentage points. The 10 keywords with the largest positive growth are shown. Comparing shares rather than counts keeps a year with more studies from looking like a rise in every topic. The chart is labeled "descriptive only", like the trend line.

**Scopes.**
- URDC: the whole university.
- Coordinator: their own cluster and its colleges. A coordinator cannot see another cluster's figures, even by editing the page address.
- Adviser: their supervised studies.
- Student: a summary of their college's research.

**Speed.** A nightly job (2:15 AM) counts the summary figures into a snapshot table, so dashboards load fast. The page says "Summary figures as of …". With a theme or SDG filter the cards are counted live, and the page says so.

**Exports** for accreditation reports: Excel (one sheet per panel), CSV and an A4 PDF report, all using the filters on the page. Cells that start with `=`, `+`, `-` or `@` are prefixed so a spreadsheet never runs them as formulas. Each export is logged.

### 8.2 Responsible use of the analytics

- The trend line is labeled **descriptive only**. It describes past output; it is not a forecast.
- Nothing is decided about an individual from the analytics. The adviser chart shows workload, not performance ratings.
- Charts follow the theme colors and dark mode, and their legends move below the chart on phones.

---

## 9. Evaluation tools built into the system (SOP 3)

The system does not produce the evaluation results by itself. It provides the tools to **collect and measure** them.

### 9.1 SOP 3.1: Accuracy of similarity screening

**Building the labeled test set** (URDC › Labeled test set):
- **Pairs from the repository:** half are each sampled study's most similar record (the hard cases), half are random pairs (the easy distinct cases).
- **Imported pairs (CSV):** prepared near-duplicates, for example a stored abstract that was paraphrased or partly copied. The repository rarely contains true duplicates, so importing prepared ones is how the set reaches about half duplicates, as Chapter 2 requires.
- Each pair is scored once with the same TF-IDF and cosine similarity used in screening.

**Labeling** (Coordinator › Label test pairs):
- Coordinators see one pair at a time, side by side, **without the score**, so the number cannot influence their judgment. They choose Duplicate or Distinct.
- The **reference label** is the one given by at least 2 of the 3 coordinators.
- **Note for the manuscript:** once two coordinators agree, the pair is not shown to the third, because the third label cannot change the majority. The third coordinator is asked only when the first two disagree. This saves the coordinators' time without changing any result. Describe it this way in the methodology.
- The system never generates labels. They come only from coordinators.

**Measures** (URDC › AI performance). A pair is predicted "duplicate" when its score is at or above the duplicate threshold (0.80 by default):

| | Labeled duplicate | Labeled distinct |
|---|---|---|
| **Score ≥ threshold** | True positive (TP) | False positive (FP) |
| **Score < threshold** | False negative (FN) | True negative (TN) |

$$\text{Accuracy} = \frac{TP+TN}{TP+FP+FN+TN} \qquad \text{Precision} = \frac{TP}{TP+FP} \qquad \text{Recall} = \frac{TP}{TP+FN} \qquad F_1 = \frac{2 \cdot P \cdot R}{P+R}$$

A **threshold slider** recalculates all four at any other threshold without changing the live setting, so the panel can see the trade-off between catching duplicates (recall) and avoiding false alarms (precision). This can support a recommendation on the best threshold. The page also offers the everyday coordinator decisions as a second data set. The CSV export states which set was measured.

**Development check only** (not a result): on sample data, pairs of a study and its most similar record scored about 0.73 to 0.86, while random pairs scored about 0.02 to 0.45.

### 9.2 SOP 3.2: Quality of clustering

- Silhouette and Davies–Bouldin for every k and every run, with the elbow chart.
- How many theme labels coordinators accepted, edited or rejected, and their 1–4 ratings of the labels.
- Archived runs allow comparison over time.

### 9.3 SOP 3.3: Usefulness of the dashboards (dashboard task mode)

- The URDC writes 5 set tasks (for example "How many CEIS studies are aligned with SDG 4?") and switches task mode on.
- Participants can be evaluator accounts only, or evaluators plus coordinators and URDC staff.
- Each participant sees the current task at the top of every page: **Start**, then **Done** (with an optional answer) or **I could not do it**. Tasks go in order.
- **Times are measured on the server**, from Start to Done, so they do not depend on the evaluator's device.
- The results page shows each task's completion rate and mean time, and each evaluator's time per task. Results export to CSV.

### 9.4 SOP 3.4: Acceptance of LLM suggestions

- For keywords, SDGs, research type and theme labels: the number accepted, edited, rejected and pending, the acceptance rate, how often the fallback was used, and the mean 1–4 rating of theme labels.

### 9.5 The evaluation copy

- A separate installation (for example `eval-repository.tua.edu.ph`) with sample or anonymized records only.
- **Evaluator accounts** for external IT experts are created by the System administrator. They are the only accounts allowed outside `@tua.edu.ph`. They act as Student, Faculty adviser or URDC administrator (never System administrator), expire automatically (at most 120 days), and set their own password. Every page they open is logged.
- They cannot exist in the production system: the feature works only when the installation is marked as the evaluation copy.
- The ISO/IEC 25010 questionnaires (sections A–F) are run outside the system.

---

## 10. Software quality: ISO/IEC 25010 (SOP 4)

What was done for each characteristic the evaluators will rate:

| Characteristic | What the system does | Evidence available now |
|---|---|---|
| **Functional suitability** | Every SOP 1.1–1.8 item works end to end | 367 automated tests; browser tests of the full workflow |
| **Performance efficiency** | Snapshots for dashboards; screening in the background; sparse 32-bit matrices; MiniBatchKMeans above 10,000 records | Clustering of 1,420 records in about 11 s (development). Page times on the ICT server **not yet measured** |
| **Compatibility** | Chrome, Edge, Firefox, Safari, Android Chrome, iOS Safari; exports open in Excel | Browser tests on desktop Chrome, Pixel 7 and iPhone 14 (WebKit) |
| **Interaction capability (usability)** | WCAG 2.2 AA: labels on every input, focus rings, keyboard navigation, contrast of at least 4.5:1, errors beside the field, plain wording from the prototype | Theme colors are computed to keep white text readable (lightness capped at 36%) |
| **Reliability** | Queue retries (3), daily backup with monthly restore test, LLM fallback, clear "service unavailable" messages | A real encrypted backup and staging restore were tested; 8 tables matched |
| **Security** | See below | OWASP ZAP baseline scan: **no High findings** |
| **Maintainability** | Code grouped by domain, service interfaces, PSR-12 style, static analysis | Larastan level 6 with no errors; Pint clean; checks run on every push; line coverage of the Domain folders measured in CI (target at least 70%) |
| **Flexibility** | LLM, SMS and mail providers can be swapped; thresholds, schedule and limits are settings; modular design for future links to Smart Campus | — |
| **Safety** | No automatic rejection; every AI output can be undone; descriptive trend lines; no decisions about individuals | — |

**Security measures**
- HTTPS with HSTS; a Content Security Policy (scripts only from the site, with a new nonce per page); other protective headers; secure, HttpOnly, SameSite=Lax cookies; CSRF protection on every form.
- bcrypt passwords; validation of every input; real-content checks on uploads; private file storage with expiring signed download links.
- Rate limits on sign-in, recovery, search and the LLM.
- The database account has only the rights it needs; all queries are parameterized.
- The analytics service is reachable only from the server and only with a valid signature.
- **ZAP findings and how each was handled** (from `deploy/zap/README.md`):

| Risk | Finding | Handling |
|---|---|---|
| Medium | CSP allows `'unsafe-eval'` | Accepted: Alpine.js (part of Livewire 3) needs it. Inline scripts are still blocked |
| Medium | CSP allows inline styles | Accepted: charts and progress bars set widths. Styles cannot run code |
| Medium | No anti-CSRF token on a register-page form | False positive: it is the Privacy Notice's Close button, which sends nothing |
| Low | Cookie without HttpOnly | Fixed: the script-readable cookie is no longer sent |
| Low | `X-Powered-By` header | Fixed in the app and in the server configuration |
| Low | Missing header on `robots.txt` | Handled by the Nginx configuration on the server |

The scan must be repeated on the evaluation copy over HTTPS before release.

**Mobile.** The manuscript defines the system as web-based, used "through a web browser on a computer or mobile device". It is therefore a **responsive web app that can be installed on a phone** (a PWA), not a separate native app.
- At 820 px and below, the side menu becomes a scrolling tab bar and grids become one column. Pages never scroll sideways.
- Touch targets are at least 44 px and inputs at least 16 px, so iPhones do not zoom.
- It can be installed from the browser. An offline page is shown without a connection. Research files and personal pages are never stored on the phone.
- File uploads show progress and can be retried on a weak connection.

---

## 11. Data privacy and ethics

In line with the Data Privacy Act of 2012 (RA 10173):
- A Privacy Notice must be accepted at registration. **Its final text must come from the Data Privacy Officer**; a placeholder is used now.
- Only the personal data needed is collected: name, TUA email, college, program, and optional recovery contacts.
- Research files are private. Students get full papers only by approved, time-limited request. Every view and download is logged.
- The LLM receives only the title, abstract, keywords and cluster terms. It must be a service that excludes submitted data from training, approved by the DPO.
- The evaluation copy uses sample or anonymized records only; no real student names.
- Export and deletion of personal data on request are handled by the URDC.
- Out of scope, by design: training or fine-tuning an LLM, question answering over the repository (RAG), automatic summaries of full papers, internet plagiarism checks, and any use of trends to make decisions about individuals.

---

## 12. Verification done during development

| Check | Result |
|---|---|
| Pest tests (Laravel): unit, feature, and FULLTEXT integration tests on committed data | 367 tests, 1,836 assertions, all passing |
| pytest (Python analytics service) | 16 tests passing, including: same text scores 1.0; unrelated text scores below 0.2 |
| Playwright browser tests | 7 tests on each of desktop Chrome, Pixel 7 and iPhone 14, all passing (one timing race in the recovery test on iPhone was found and fixed in the test). They cover registration (Gmail refused), activation, sign-in, recovery, the full four-step workflow, search, dashboards and export, and theme. Every page is also checked for sideways scrolling and script errors |
| Larastan level 6 (static analysis) | No errors |
| Pint (PSR-12 style) | Clean |
| OWASP ZAP baseline (150 URLs) | No High findings |
| Backup and restore | Encrypted backup made and restored to staging; 8 key tables matched |
| Clustering | 1,420 sample records, k = 8 by silhouette, about 11 s |

**Bugs found by the browser tests and fixed.** These show the value of testing on real phone browsers (useful for the discussion of the testing method):
- Uploads with a progress bar were sent to the wrong address after "Save draft" was added.
- A theme color chosen by the user never actually changed the colors.
- On iPhone 14 (390 px wide), several pages could be scrolled sideways.
- On phones there was no way to open the theme menu.

**Other defects found in testing and fixed:** a coordinator could see another cluster's dashboard totals by editing the page address; on MariaDB, finishing a dashboard task reset its start time; long jobs (backups, clustering) could be started twice by the queue.

---

## 13. Decisions and deviations to state in the manuscript

These are choices made during development. Each should be stated in the methodology or confirmed with the adviser and the URDC.

| # | Topic | What the system does | Why | Action |
|---|---|---|---|---|
| 1 | TF-IDF formula | idf = ln(N/df) + 1 (scikit-learn) | Keeps terms found in every record from getting weight 0 | Update the formula in Chapter 2 or add a note |
| 2 | Field weighting | Title ×2, abstract ×1, keywords ×3 | Title and keywords describe the topic best | State in the preprocessing description |
| 3 | Domain stop words | 23 words such as *study, research, system, respondents* | They appear in almost every study | List them in the methodology |
| 4 | Bigrams | Single words and two-word phrases | Captures phrases such as "machine learning" | State in the methodology |
| 5 | Silhouette and Davies–Bouldin | Silhouette with cosine distance on up to 3,000 records; Davies–Bouldin on a 100-dimension SVD | Both need distance calculations that are too heavy on the full sparse matrix | State in the methodology |
| 6 | Theme map | First two TruncatedSVD components | Works directly on sparse TF-IDF data | Matches Chapter 2 |
| 7 | Majority labeling | The third coordinator labels only when the first two disagree | Saves time; the result is the same | Describe in the evaluation procedure |
| 8 | Test-set composition | Repository neighbours, random pairs and imported prepared near-duplicates | Real duplicates are rare | Describe how the near-duplicates were prepared |
| 9 | Resubmission | A revised study goes back to the adviser (step 1) | Assumption in the build guide | **Confirm with the URDC** |
| 10 | Cluster–college mapping | Business and Technology: IBAM, CEIS; Diagnostic and Pharmaceutical: CMT, CAHS; Patient Care: SLCN; Social Science, Humanities and Science: CASE | Assumed; stored as editable data | **Confirm with the URDC** |
| 11 | Abstract limit | 250 words (a setting) | Default in the build guide | **Confirm with the URDC** |
| 12 | Clustering schedule | "Every semester" = 150 days after the last run | Approximates one semester | State, or set to the academic calendar |
| 13 | Activation | The link opens a page with an "Activate" button | Mail scanners would otherwise use up the link | Optional mention |
| 14 | Drafts | One draft per student, visible only to that student | Keeps queues clean; nobody is emailed about drafts | Optional mention |
| 15 | Themes before the first run | Records get the prototype theme matching their keywords until the first clustering run is confirmed | So dashboards have themes from day one | Disappears after the first confirmed run |
| 16 | Default color | TUA Green `#1b4a42` instead of Navy `#1e3a6b` | Developer's choice to match the landing page design | Update if the manuscript or prototype names Navy |
| 17 | Database | MariaDB used locally; MySQL 8 on the server | XAMPP ships MariaDB | No change to the manuscript |
| 18 | LLM provider | OpenAI API through a swappable interface | A provider had to be chosen to build and test | **Needs DPO approval**; name the provider in the manuscript |
| 19 | Evaluator roles | Student, Faculty adviser or URDC administrator | The System administrator role is too powerful for external accounts | Check against the evaluation plan, which may need a coordinator role |
| 20 | Keyword search | MySQL FULLTEXT (all words, any order, word starts) plus a substring match; Laravel's query builder instead of Scout | Scout's database driver does not rank by relevance; FULLTEXT skips words under 3 letters | State in the M4 description (§6.5) |
| 21 | Emerging keywords | Growth in share of studies, latest 2 years against the 3 years before, keywords on at least 3 recent studies | Shares are fair across years with different numbers of studies | State the definition under visual 7 |
| 22 | Visual 3 | Colleges by research type; one college's programs by research type when a college or program is chosen | Programs differ per college, so one chart of every program would be unreadable | State under visual 3 |
| 23 | Log retention | 5 years by default, 1 to 10 in Settings; older entries removed nightly, and the removal logged | RA 10173 asks that personal data be kept no longer than needed | **Confirm the period with the URDC and the DPO** |

---

## 14. Features not in the manuscript

These were built for the URDC's needs but are **not in the revised manuscript**. Each can be switched off in the server settings (`.env`).

| Feature | Switch | Default |
|---|---|---|
| Landing page CMS (URDC edits the home page: draft, preview, publish, version history) | `FEATURE_LANDING_CMS` | On |
| User theme color and light/dark mode | `FEATURE_THEME_PICKER` | On |
| Recovery email | `RECOVERY_EMAIL_ENABLED` | On |
| Recovery by SMS | `RECOVERY_SMS_ENABLED` | Off (no SMS gateway chosen yet) |

**Decision needed with the adviser:** either add these to the manuscript (M1 for recovery contacts, M9 for the CMS and theme, and Figure 7), or switch them off in the version that is evaluated. Evaluators should rate only what the manuscript describes.

---

## 15. Known gaps

**Closed since the first version of these notes:**

| Gap | What was built |
|---|---|
| Search matched text with a simple substring search, not MySQL FULLTEXT as planned | FULLTEXT search with relevance ranking (§6.5) |
| No program filter in Search or the dashboards | A program facet in Search and the catalog; a program filter on every dashboard and its exports; visual 3 shows a college's programs (§8.1) |
| Visual 7 had no *emerging* keywords | An Emerging keywords chart, also in the exports (§8.1) |
| No setting for how long the activity log is kept | A retention setting (1 to 10 years) and a nightly cleanup that logs itself (§5.7) |
| Test coverage never measured | GitHub Actions now measures the line coverage of `app/Domain` on every push and shows it in the job summary. Locally, `composer test:coverage` does the same on a PHP with PCOV or Xdebug |

**Still open:**

1. **Coverage figure.** The first figure appears in GitHub Actions after the next push. This development PHP (XAMPP) has no coverage driver, so it could not be measured locally. The CI step shows a warning, without failing the build, if the figure is below the 70% target.
2. **Off-server backup copy.** The code is ready; the ICT Office must provide the external folder.
3. **SMS gateway.** The code and tests exist, but no Philippine gateway has been chosen.
4. **Word cloud** for visual 7 is optional in the build guide and was not built; the two bar charts cover the same information.

---

## 16. Limitations

Suitable for the Scope and Delimitation, or the limitations in the final chapter:
- **English only.** Filipino or mixed-language abstracts are not analysed correctly by the English stop-word list and lemmatizer.
- **Metadata only.** Screening compares the title, abstract and keywords, not the full paper. Two studies with different abstracts but copied chapters would not be detected. Full-text and internet plagiarism checks are out of scope.
- **Bag of words.** TF-IDF matches words, not meaning. A heavily paraphrased abstract with different vocabulary can score low; two different studies on the same narrow topic can score high. This is why the coordinator always decides.
- **K-Means assumptions.** Each record belongs to exactly one theme, and K-Means favours clusters of similar size. A study that spans two themes is placed in only one.
- **Labels depend on the LLM or on top terms.** The theme labels are suggestions; their quality is measured by the coordinators' ratings.
- **Server size.** The 4 GB server limits the size of clustering runs. MiniBatchKMeans is used above 10,000 records, which is slightly less precise.
- **Sample data in development.** The system's behaviour was verified on generated records. Results on the real repository may differ.
- **Trends are descriptive.** The trend line is not a forecast and must not be used for decisions about individuals.

---

## 17. What Chapter 4 still needs

**Data to collect on the evaluation copy**
1. **SOP 3.1:** at least 100 labeled pairs, about half duplicates, each labeled by the coordinators. Report the confusion matrix, accuracy, precision, recall and F1 at the chosen threshold, and the effect of other thresholds (from the slider).
2. **SOP 3.2:** the clustering run on the real records: the chosen k, the silhouette and Davies–Bouldin scores, the elbow chart, the theme map, the top terms per theme, and the coordinators' decisions and 1–4 ratings of the labels.
3. **SOP 3.3:** the dashboard task results: completion rate and mean time for each of the 5 tasks.
4. **SOP 3.4:** LLM acceptance rates per kind of suggestion and how often the fallback was used (only if the LLM is approved and switched on).
5. **SOP 4:** the ISO/IEC 25010 questionnaire results (sections A–F), collected outside the system.
6. **Performance:** page times on the ICT server (catalog and search under 1.5 s, dashboards under 2 s, screening result within 10 s, clustering under 5 minutes).
7. **Security:** the ZAP scan repeated on the evaluation copy over HTTPS.
8. **Maintainability:** the line coverage of the Domain folders, from the GitHub Actions job summary.

**To confirm with TUA before then**
- Cluster–college mapping, the coordinators, and the program list (URDC).
- The abstract word limit and other format rules (URDC).
- Where a resubmitted study goes (URDC).
- The official URDC contact details for the landing page and assisted recovery.
- The SMTP route (ICT Office) and the LLM provider (Data Privacy Officer).
- The Privacy Notice text and the activity-log retention period (Data Privacy Officer).
- Whether the features in §14 go into the manuscript.
- PHP 8.2 or 8.3, and MySQL 8, on the ICT server.

**Server set-up (ICT Office)**
- A tua.edu.ph subdomain with SSL; Nginx, PHP-FPM, MySQL 8, Python; Supervisor and cron.
- The backup password, kept off the server, and the off-server backup folder.
- The deployment steps are in `deploy/README.md`.

---

## 18. Appendix: default settings and logged events

### Default settings (all changeable by the System administrator, and each change is logged)

| Setting | Default |
|---|---|
| Probable-duplicate threshold | 0.80 |
| Review threshold | 0.60 |
| Clustering schedule | Every semester (150 days), or after a set number of new records |
| Refit the TF-IDF index | After every 50 new published records, and before each clustering run |
| Maximum file size | 20 MB |
| Abstract word limit | 250 words |
| Session idle timeout | 30 minutes |
| Sign-in attempts before a 1-minute pause | 5 |
| LLM timeout / rate limit | 8 seconds / 60 calls per minute |
| Activation link | 48 hours, one use |
| Recovery code | 6 digits, 10 minutes, one use, 5 tries |
| Download link | 10 minutes |
| Full-paper access | 14 days by default, at most 1 year |
| Activity log retention | 5 years (1 to 10) |

### Scheduled jobs (Asia/Manila time)

| Time | Job |
|---|---|
| 2:00 AM daily | Encrypted backup, then cleanup to 7 daily, 4 weekly, 3 monthly |
| 2:15 AM daily | Dashboard summary snapshot |
| 2:30 AM daily | Check whether a clustering run is due, and queue it |
| 3:00 AM daily | Remove activity-log entries past the retention period, and log the removal |
| 7:00 AM daily | Backup health check |
| 4:00 AM, 1st of each month | Restore test on the staging database |

### Activity-log event types (29)

`login`, `logout`, `login_failed`, `register`, `activate`, `recovery`, `password_reset`, `search`, `view`, `download`, `upload`, `submit`, `endorsed`, `reviewed`, `published`, `returned`, `rejected`, `request`, `llm`, `service`, `notify`, `role`, `setting`, `cms`, `theme`, `import`, `backup`, `cluster_run`, `metadata_edit`.

Each entry stores the time, user, event, details, subject, IP address, browser, and old and new values for edits.
