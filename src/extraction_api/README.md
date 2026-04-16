# Canvas Extraction API

The Extraction API is a **Python FastAPI service** responsible for pulling course data from the [Canvas LMS API](https://canvas.instructure.com/doc/api/), processing it for ABET accreditation needs, and uploading organized artifacts back into a designated Canvas course.

---

- [Canvas Extraction API](#canvas-extraction-api)
  - [Role Within the Application](#role-within-the-application)
  - [Architecture Diagram](#architecture-diagram)
  - [Technology Stack](#technology-stack)
  - [Directory Structure](#directory-structure)
  - [API Endpoints](#api-endpoints)
    - [`GET /verify-token`](#get-verify-token)
    - [`GET /verify-course/{course_id}`](#get-verify-coursecourse_id)
    - [`GET /canvas/courses`](#get-canvascourses)
    - [`POST /move-data-between-courses/{course_id_to_push}`](#post-move-data-between-coursescourse_id_to_push)
    - [`POST /generate-report-json/{course_id}`](#post-generate-report-jsoncourse_id)
    - [`GET /job-status/{job_id}`](#get-job-statusjob_id)
    - [`GET /jobs`](#get-jobs)
  - [Core Pipeline \& How Extraction Works](#core-pipeline--how-extraction-works)
    - [Phase 1: Data Gathering](#phase-1-data-gathering)
    - [Phase 2: Artifact Extraction \& Grade Reports](#phase-2-artifact-extraction--grade-reports)
    - [Phase 3: Canvas Upload](#phase-3-canvas-upload)
      - [How Upload Works](#how-upload-works)
      - [The Three Upload Sites](#the-three-upload-sites)
      - [Variable Construction Reference](#variable-construction-reference)
    - [Phase 4: ABET Outcome Analysis](#phase-4-abet-outcome-analysis)
    - [Phase 5: ABET Report Generation \& Upload](#phase-5-abet-report-generation--upload)
  - [Connection to the Broader Application](#connection-to-the-broader-application)
    - [Communication with the PHP Frontend (`api-proxy.php`)](#communication-with-the-php-frontend-api-proxyphp)
    - [Background Task Processing (Celery + Redis)](#background-task-processing-celery--redis)
    - [Handoff to Canvas Formatting API](#handoff-to-canvas-formatting-api)
    - [Database Interactions (MySQL)](#database-interactions-mysql)
  - [Key Modules](#key-modules)
    - [`assignment_extraction_api.py`](#assignment_extraction_apipy)
    - [`fetch_grades.py`](#fetch_gradespy)
    - [`csv_filter.py`](#csv_filterpy)
    - [`quiz_statistics.py`](#quiz_statisticspy)
    - [`update_database.py`](#update_databasepy)
    - [`upload_abet_reports.py`](#upload_abet_reportspy)
    - [`abetReportGenerator.py`](#abetreportgeneratorpy)
    - [`tasks.py`](#taskspy)
  - [Canvas Folder Structure](#canvas-folder-structure)
  - [Concurrency \& Rate Limiting](#concurrency--rate-limiting)
  - [Docker Configuration](#docker-configuration)
    - [Dockerfile (`docker/extraction_api/Dockerfile`)](#dockerfile-dockerextraction_apidockerfile)
    - [The Three Compose Environments](#the-three-compose-environments)
      - [1. Development — `docker-compose.yml`](#1-development--docker-composeyml)
      - [2. Staging — `docker-compose-staging.yml`](#2-staging--docker-compose-stagingyml)
      - [3. Production — `docker-compose-prod.yml`](#3-production--docker-compose-prodyml)
    - [Deployment Process](#deployment-process)
  - [Environment Variables](#environment-variables)
  - [Running Locally (Development)](#running-locally-development)

---

## Role Within the Application

The ABET-Tools-Dockerized project is a multi-container application that helps ASU faculty collect, organize, and report on course data for [ABET accreditation](https://www.abet.org/). The Extraction API is the service that communicates with Canvas to pull raw course data and push organized artifacts.

At a high level, the Extraction API:

1. **Validates** Canvas API tokens and course IDs on behalf of the PHP frontend.
2. **Extracts** assignments, submissions, syllabi, rubrics, and grade data from Canvas source courses.
3. **Organizes** these artifacts into a structured folder hierarchy in a designated Canvas destination course.
4. **Analyzes** ABET-tagged assignments to compute competency statistics, broken down by student major.
5. **Generates** ABET outcome reports (DOCX via embedded template + OpenAI feedback) and uploads them to Canvas.
6. **Stores** extracted ABET report data in the MySQL database for downstream use by the Report Generation API and PHP frontend.
7. **Triggers** the Canvas Formatting API to create structured Canvas modules from the uploaded files.

## Architecture Diagram

```
┌────────────────────────────────────────────────────────────────────────┐
│                          User (Browser)                                │
└────────────────┬───────────────────────────────────────────────────────┘
                 │ HTTPS
                 ▼
┌────────────────────────────────────────────────────────────────────────┐
│              PHP/Apache Frontend                                       │
│                                                                        │
│   ┌────────────────────────────────────────────────────────────────┐   │
│   │ api-proxy.php — proxies all extraction requests server-side   │   │
│   │   • Stores Canvas token in PHP session                        │   │
│   │   • Sends cURL requests to internal Extraction API            │   │
│   │   • Handles CSRF and authentication                           │   │
│   │     (this can be delegated to symfony                         │   │
│   │      if anyone wants to take a stab at it)                     │   │
│   └──────────────────────┬─────────────────────────────────────────┘   │
└──────────────────────────┼─────────────────────────────────────────────┘
                           │ HTTP
                           ▼
┌────────────────────────────────────────────────────────────────────────┐
│              Extraction API (extraction_api container)                 │
│              FastAPI on port 8000                                      │
│                                                                        │
│   • /verify-token              → Validates Canvas API token            │
│   • /verify-course/{id}        → Validates course & checks duplicates  │
│   • /canvas/courses            → Lists instructor's CSE courses        │
│   • /move-data-between-courses → Kicks off extraction pipeline         │
│   • /generate-report-json      → Returns ABET data as JSON             │
│   • /job-status/{job_id}       → Polls background task progress        │
│   • /jobs                      → Lists job history for a user          │
│                                                                        │
│   Long-running work is dispatched to Celery ──┐                        │
└───────────────────────────────────────────────┼────────────────────────┘
                           │                    │
                           │                    ▼
                           │   ┌──────────────────────────────────────┐
                           │   │  Redis (message broker + results)    │
                           │   └──────────────┬───────────────────────┘
                           │                  │
                           │                  ▼
                           │   ┌──────────────────────────────────────┐
                           │   │  Celery Worker (celery_worker)       │
                           │   │   • Runs extraction.run_pipeline     │
                           │   │   • Updates progress → Redis + MySQL │
                           │   │   • On completion, calls Formatting  │
                           │   └──────────────┬───────────────────────┘
                           │                  │
                           ▼                  ▼
              ┌────────────────────┐  ┌────────────────────────────────┐
              │      MySQL         │  │  Canvas Formatting API         │
              │  (courses table,   │  │  (creates Canvas modules       │
              │   job_history)     │  │   from uploaded files at dest) │
              └────────────────────┘  └────────────────────────────────┘
                                                    │
                                                    ▼
                                          ┌──────────────────┐
                                          │  Canvas LMS API  │
                                          │  (canvas.asu.edu)│
                                          └──────────────────┘
```

## Technology Stack

| Component         | Technology                        |
| ----------------- | --------------------------------- |
| Web Framework     | FastAPI                           |
| ASGI Server       | Uvicorn                           |
| Language          | Python 3.11                       |
| Task Queue        | Celery + Redis                    |
| Database          | MySQL                             |
| PDF Generation    | xhtml2pdf, PyPDF2                 |
| DOCX Generation   | python-docx                       |

## Directory Structure

```
src/extraction_api/
├── __init__.py
├── assignment_extraction_api.py   # FastAPI app + main pipeline logic + endpoints
├── fetch_grades.py                # CanvasGradesFetcher — Canvas API client (poorly aged name, ik) 
├── csv_filter.py                  # Student roster parser (CSV/XLS → major mapping)
├── quiz_statistics.py             # Fetches quiz stats from Canvas, renders as PDF
├── update_database.py             # DatabaseManager — MySQL connection pooling + writes
├── upload_abet_reports.py         # Generates ABET report DOCX files, uploads to Canvas
├── abetReportGenerator.py         # ABET DOCX report generation from template + OpenAI
├── tasks.py                       # Celery task definition (extraction.run_pipeline)
├── requirements.txt               # Python dependencies
├── pyproject.toml                 # Package metadata

src/shared/                        # Shared modules that can be used by all services
├── celery_app.py                  # Celery application configuration
├── base_task.py                   # TrackedTask — base class for lifecycle tracking
├── db.py                          # Database helpers (job_history CRUD)
├── locks.py                       # Redis-based extraction locks
└── requirements.txt               # 'Shared' Python dependencies
```

## API Endpoints

All endpoints are served by FastAPI. The Canvas access token is passed via the `canvas-access-token` HTTP header (never as a query parameter).

### `GET /verify-token`

Validates a Canvas API access token by hitting Canvas's `/users/self` endpoint.

| Aspect     | Detail                                       |
| ---------- | -------------------------------------------- |
| Headers    | `canvas-access-token` (required)             |
| Returns    | `{"valid": true}`                            |
| Errors     | `401` if token is invalid or expired         |

---

### `GET /verify-course/{course_id}`

Verifies a Canvas course ID is accessible with the provided token. Also checks if the course has already been **formatted** to a destination course (if provided). It checks if a module for that course exists, and if it does returns `duplicate_status: true`.

| Aspect         | Detail                                                                |
| -------------- | --------------------------------------------------------------------- |
| Path Params    | `course_id` — Canvas course ID                                       |
| Query Params   | `dest_course_id` (optional) — destination course to check duplicates  |
| Headers        | `canvas-access-token` (required)                                      |
| Returns        | Course name, code, term, teachers, and `duplicate_status: bool`       |

---

### `GET /canvas/courses`

Fetches courses from Canvas where the authenticated user has **teacher** enrollment. Filters results to only CSE-prefixed courses and a hardcoded set of allowed course IDs.

| Aspect         | Detail                                                                |
| -------------- | --------------------------------------------------------------------- |
| Headers        | `canvas-access-token` (required)                                      |
| Query Params   | `enrollment_type` (default: `teacher`)                                |
| Returns        | Array of Canvas course objects with term, teachers, total_students     |

> [!NOTE]
> **Course Filter:**
> The `ALLOWED_COURSE_IDS` set currently contains only `{240102}` (Testing ground, meaning it will show up in the UI if you are an instructor in testing ground). The CSE prefix filter is also hardcoded. However these can and should be changed. This filter is supposed to be dependent on the exact program you are doing the extraction for. For example, for the CSE curriculum at ASU, courses like PHY 101 are also included in the final report, and also in the ABET example course professor osburn provided. So the list of allowed courses should be governed by the curriculum for the program you are doing the extraction for.

---

### `POST /move-data-between-courses/{course_id_to_push}`

**Primary endpoint.** Initiates the full extraction pipeline as a background Celery task. Returns immediately with a `job_id` for polling.

| Aspect         | Detail                                                                |
| -------------- | --------------------------------------------------------------------- |
| Path Params    | `course_id_to_push` — destination Canvas course ID                    |
| Query Params   | `course_ids_to_pull` (required, list) — source course IDs to extract from |
|                | `course_name` (optional)                                               |
|                | `overwrite` (optional, default `false`)                                |
| Headers        | `canvas-access-token` (required)                                       |
|                | `submitted-by-user-id` (optional) — internal user ID(from mysql) for tracking      |
| Body           | `roster_file` — multipart file upload (CSV or XLS student roster)     |
| Returns        | `{"message": "Extraction started in background.", "job_id": "...", "status": "processing"}` |

**Concurrency guard:** Uses Redis-based locks to prevent duplicate extraction of the same source→destination course pair. Returns `409 Conflict` if a lock is already held. This is to ensure that if two users ever start extracting the same course to the same destination at the same time, it throws a 409 for the losing user (otherwise there could be all sorts of shenanigans with canvas uploads).

> [!NOTE]
> The 'overwrite' filter here is **NOT** for the extraction pipeline, but is passed onto the formatting pipeline. This is because the extraction pipeline by default will ALWAYS overwrite duplicate folders on the destination course, as in no case does it ever make sense to keep two copies of the same course data on the destination course. 

---

### `POST /generate-report-json/{course_id}`

Generates ABET outcome report data as a JSON response **without** uploading to Canvas or triggering background tasks. Useful for preview or debugging.

| Aspect         | Detail                                                                |
| -------------- | --------------------------------------------------------------------- |
| Path Params    | `course_id` — Canvas source course ID                                 |
| Headers        | `canvas-access-token` (required)                                       |
| Body           | `roster_file` — multipart file upload (CSV or XLS student roster)     |
| Returns        | JSON with `metadata` and `outcomes` arrays                            |

---

### `GET /job-status/{job_id}`

Polls the status of a background extraction job. Checks Redis first, then falls back to MySQL `job_history` table.

| Aspect         | Detail                                                                |
| -------------- | --------------------------------------------------------------------- |
| Path Params    | `job_id` — UUID returned from `/move-data-between-courses`            |
| Returns        | `status` (`processing`, `completed`, `failed`), `progress` (0-100), `message` |

---

### `GET /jobs`

Lists recent jobs, optionally filtered by user.

| Aspect         | Detail                                                                |
| -------------- | --------------------------------------------------------------------- |
| Headers        | `submitted-by-user-id` (optional) — filters to a specific user        |
| Query Params   | `limit` (default: 50)                                                 |
| Returns        | `{"success": true, "jobs": [...]}`                                     |

---

## Core Pipeline & How Extraction Works

The main extraction pipeline is executed by `run_pipeline_sync()` (called from the Celery task `extraction.run_pipeline`). It processes one source course and uploads results to a single destination course.

### Phase 1: Data Gathering

For each source course:

1. **Fetch assignment groups** — categories like "Quizzes", "Assignments", etc. These are dependent on how the professor of the source course has defined their modules
2. **Fetch course metadata** — name, code, term, teachers, syllabus body.
3. **Fetch all assignments** — including rubric data.
4. **Bulk-fetch all submissions** — uses Canvas's bulk endpoint (`/students/submissions`) to minimize API calls. Submissions are deduplicated by `(assignment_id, user_id)`.

All of this is bundled into a `CourseData` named tuple for use by later phases.

### Phase 2: Artifact Extraction & Grade Reports

For each assignment, run concurrently (up to `MAX_PARALLEL_ASSIGNMENTS = 2` threads):

1. **Extract representative submissions** — identify the highest, average, and lowest-scored graded submissions that have file attachments.
2. **Download submission files** — save to a temporary directory.
3. **Extract text** from PDFs and DOCX files for ABET report content.
4. **Save assignment description** and rubric as HTML/JSON files.
5. **Generate quiz statistics PDFs** for quiz-type assignments. (uses the quiz_statistics.py file)
6. **Generate per-assignment CSV grade reports.**

Also:
- **Extract and save syllabus** — HTML body → PDF conversion + download of any linked PDF files. However this does not work very well. Syllabus is also taken as explicit input from user on the UI before they can start extraction so that it is stored in a structured manner in our sql database.

### Phase 3: Canvas Upload

Upload all collected files to Canvas organized into a structured folder hierarchy on the **destination course**. This hierarchy is critical because the **Canvas Formatting API** reads it downstream to build Canvas modules. If folders are named or structured incorrectly, formatting will fail or produce incorrect modules.

#### How Upload Works

All uploads go through `CanvasGradesFetcher.upload_files(course_id, folder_path, file_paths)`, which uses Canvas's 3-step upload process:

1. **Init** — `POST /api/v1/courses/{id}/files` with `parent_folder_path` to tell Canvas where to place the file.
2. **Binary upload** — `POST` to the `upload_url` returned by step 1.
3. **Confirm** — `GET` the `location` URL returned by step 2.

Within a single folder, the **first file is always uploaded synchronously** before the rest are parallelized (up to `MAX_PARALLEL_UPLOADS = 4` threads). This is because Canvas creates folders implicitly when the first file is uploaded with a `parent_folder_path` that doesn't exist yet. If multiple files race to create the same folder, duplicates can result since Canvas doesn't offer `on_duplicate` behavior for folders (only for files, where we use `"overwrite"`).

> [!NOTE]
> I have tried optimizing the file upload, but canvas does not have a bulk upload endpoint, hence the single file upload above with some concurrency. This was the best balance I found between performance and not overwhelming the canvas api. 

#### The Three Upload Sites

There are three distinct places in the pipeline where `upload_files()` is called, each producing a different part of the folder tree:

**1. Syllabus Upload** 

```
folder_path = "{course_folder_name}/({term_display})/Syllabus"
```

Files: `syllabus_body.html`, `syllabus_body.pdf`, and any `syllabus_*.pdf` files linked in the syllabus HTML body.

**2. Per-Assignment Upload** (_process_single_assignment)

Every assignment produces **at least one** upload task, and ABET-tagged assignments produce **additional** upload tasks:

```python
# Primary upload — every assignment gets this:
canvas_folder = "{course_folder_name}/({term_display})/Test_Assignments/{assignment_type}/{sanitized_name}"

# ABET outcome uploads — only for assignments with ABET rubric criteria:
# The SAME files are uploaded AGAIN to each matching outcome folder:
abet_folder = "{course_folder_name}/({term_display})/Test_Assignments/Project Evaluations/Abet {outcome_num}"
```

Where:
- `assignment_type` = the Canvas assignment group name (e.g., `"Quizzes"`, `"Homework"`, `"Exams"`). Looked up from `assignment_groups[assignment.assignment_group_id]`, falling back to `"Uncategorized"`.
- `sanitized_name` = the assignment name with `<>:"/\|?*` characters stripped.
- `outcome_num` = extracted from the ABET rubric criterion title via regex `ABET\s*(\d+)` (e.g., `"CSE ABET 3 - Communication"` → `"3"`). If the regex doesn't match, the full title is used with spaces replaced by underscores.

Files in each assignment folder (all from `extract_and_save_artifacts()`):
- `description.html` — raw assignment description HTML (if present)
- Any files linked in the description (downloaded by Canvas file ID, kept with original filename)
- `rubric.json` — assignment rubric data (if present)
- `{Assignment_Name}_high_{score}.{ext}` — highest-scored student submission file
- `{Assignment_Name}_avg_{score}.{ext}` — closest-to-average student submission file
- `{Assignment_Name}_low_{score}.{ext}` — lowest-scored student submission file
- `grade_report_{assignment_id}.csv` — CSV with columns: `user_id, user_name, score, submitted_at, workflow_state`
- `{quiz_title}_statistics.pdf` — quiz statistics PDF (only for quiz-type assignments; replaces the high/avg/low submissions since quizzes don't have file attachments)

> [!IMPORTANT]
> **ABET assignments have their files uploaded twice.** Once to their normal `Test_Assignments/{group}/{name}/` folder, and again to `Test_Assignments/Project Evaluations/Abet {N}/`. This is because according to the requirements, we must have a project evaluations section in the formatted module for the course that is meant to contain the abet reports generated and the assignment files that contribute to a particular abet outcome. Not all assignments are tagged with abet outcomes.

**3. ABET Report DOCX Upload** (upload_abet_reports.py)
After the extraction pipeline builds outcome report data, DOCX reports are generated and uploaded:

```python
folder_path = "{course_folder_name}/({term_display})/Test_Assignments/Project Evaluations/Abet {outcome_num}"
```

Files: `{short_code}_ABET_{outcome_num}_{term_for_filename}.docx` (e.g., `CSE423_ABET_3_Fall_2023.docx`)

These go into the **same** `Abet {N}` folders as the duplicated assignment files from step 2, so each ABET outcome folder ends up containing both the raw student work and the generated DOCX report.

> [!NOTE]
> There is also a `generate_outcome_reports()` function that uploads `OUTCOME_{label}.json` files to `{course_folder_name}/_ABET_Outcome_Reports`, but this function is not called in the current `run_pipeline_sync()` pipeline. The JSON data is instead stored directly in MySQL via `db_manager.update_course_data()`. The function is legacy

#### Variable Construction Reference

The folder hierarchy depends on these variables, all derived from the **source course**:

| Variable | Built By | Example Value |
|---|---|---|
| `course_folder_name` | `_build_course_folder_name()` | `CSE 423 Systems capstone I [Jane Smith, John Doe]` |
| `term_display` | `get_term_display_name()` | `Fall 2023` |
| `assignment_type` | `assignment_groups[group_id]` | `Quizzes` |
| `sanitized_name` | `sanitize_filename(assignment["name"])` | `Homework 1` |
| `outcome_num` | regex on rubric criterion title | `3` |
| `short_code` | `_extract_short_course_code()` in `upload_abet_reports.py` | `CSE423` |

- `course_folder_name` is the Canvas course name (with invalid filename chars stripped) + teacher names in brackets. The term is **not** included in this root folder name as it appears as a subfolder.
- `term_display` converts Canvas term names like `"2023 Fall C"` to `"Fall 2023"`. It is always wrapped in parentheses when used as a folder: `(Fall 2023)`.
- `short_code` is extracted from the raw course code: `"2023Fall-T-CSE423-70483"` → `"CSE423"`.

### Phase 4: ABET Outcome Analysis

1. **Filter ABET assignments**: identifies assignments containing the keyword `"abet"` in their name or rubric descriptions.
2. **Group by ABET outcome**: uses `outcome_id` from Canvas rubric criteria.
3. **Compute competency statistics**:
   - For each student, compute a weighted average score across all ABET-tagged assignments for a given outcome.
   - A student is classified as "competent" if their weighted average ≥ 70%.
   - Statistics are broken down by major (CS vs CSE) using the uploaded roster file.
4. **Build structured report data**: includes outcome identification, competency percentages, sample sizes, and contributing assignment details.

### Phase 5: ABET Report Generation & Upload

1. **Generate ABET DOCX reports** from an embedded template. Uses OpenAI to:
   - Generate improvement feedback when an outcome is not met.
   - Format assessment instrument descriptions (Section 2 of the report).
2. **Upload reports** to outcome-specific Canvas folders (`/Project Evaluations/Abet N/`).
3. **Store report JSON** in the MySQL `courses` table via `DatabaseManager.update_course_data()`.

After the extraction pipeline completes, the Celery task automatically **triggers the Canvas Formatting API** to create Canvas modules from the uploaded files.

## Connection to the Broader Application

### Communication with the PHP Frontend (`api-proxy.php`)

The PHP frontend never makes direct calls to the Canvas API for extraction workflows. Instead, `api-proxy.php` acts as a server-side proxy:

```
Browser → POST api-proxy.php (action=verify-token) → cURL → Extraction API /verify-token → Canvas API
Browser → POST api-proxy.php (action=start-extraction) → cURL → Extraction API /move-data-between-courses
Browser → POST api-proxy.php (action=check-extraction-status) → cURL → Extraction API /job-status/{id}
```

**Key design decisions:**
- The Canvas access token is stored in the PHP session (server-side) and never exposed to the browser.
- Roster files are forwarded from the PHP upload to the Extraction API as multipart form data via cURL.
- CSRF protection is enforced on the PHP side before proxying requests.
- Session credentials expire after 30 minutes.



### Background Task Processing (Celery + Redis)

Long-running extraction jobs (which can take many minutes) are dispatched to a **Celery worker** via Redis so as to not leave the UI stuck on the same page:

1. The `/move-data-between-courses` endpoint creates a job record in MySQL (`job_history`), acquires Redis-based course locks, and dispatches a `extraction.run_pipeline` Celery task.
2. The Celery worker picks up the task and runs `run_pipeline_sync()`.
3. Progress is reported to both Redis (Celery result backend) and MySQL (`job_history`).
4. The frontend polls `/job-status/{job_id}` to display progress to the user.
5. On task completion, the worker calls the Canvas Formatting API internally.
6. On task completion/failure, course locks are released in a `finally` block.

The `TrackedTask` base class (in `src/shared/base_task.py`) automatically syncs Celery lifecycle events (`before_start`, `on_success`, `on_failure`, `on_retry`) to the `job_history` MySQL table.

### Handoff to Canvas Formatting API

After a successful extraction, the Celery task calls the **Canvas Formatting API** (`canvas_formatting_api` container) to organize uploaded files into Canvas modules:

```python
# From tasks.py
format_url = f"http://{formatting_host}:{formatting_port}/format-and-upload/{course_id_to_push}"
resp = requests.post(format_url, params=params, headers=headers, timeout=600)
```

The formatting API receives the `course_folder_name` and `term_display` as parameters, along with the Canvas access token, and handles module creation on the destination course.

> [!NOTE]
> The interaction between extraction and formatting is tightly coupled in the Celery task. If formatting fails, the exception message is "Extraction succeeded, but formatting failed". This coupling can be changed and decoupled if needed. For example, if the need arises to explicitly run formatting on a course separately from extraction. However, since formatting is dependent on the data extracted from extraction, the decision was made to couple them so one runs after the other.

### Database Interactions (MySQL)

The Extraction API interacts with MySQL in two ways:

1. **`job_history` table** (via `src/shared/db.py`) — Tracks background job lifecycle: status, progress, error messages, result metadata. Used for the job polling UI.
2. **`courses` table** (via `src/extraction_api/update_database.py`) — Stores the full ABET report JSON payload keyed by `course_id`. Uses `INSERT ... ON DUPLICATE KEY UPDATE` to upsert.

The `DatabaseManager` class uses a MySQL connection pool (size 5) with retry and exponential backoff logic.

Currently two different libraries are used for MySQL interactions. This is because two of us where working on similar things and did not consult with each other on what library to use. Feel free to merge them and use a single library

## Key Modules

### `assignment_extraction_api.py`

The main module. Contains:
- The FastAPI app instance and all endpoint definitions.
- The `run_pipeline_sync()` function.
- Helper functions for artifact extraction, grade report generation, ABET outcome analysis, and filename/folder naming.

### `fetch_grades.py`

The **Canvas API client** (`CanvasGradesFetcher`). Encapsulates all communication with the Canvas REST API:
- Thread-safe via thread-local `requests.Session` instances.
- Handles pagination using Canvas `Link` headers.
- Implements adaptive rate limiting based on `X-Rate-Limit-Remaining` headers.
- Implements retry-on-rate-limit with exponential backoff.
- Supports bulk submission fetching, file downloads, and multi-file uploads.

### `csv_filter.py`

Parses student roster files (CSV or PeopleSoft `.xls` HTML-table exports) and classifies students by major:
- **Computer Science (CS)** vs **Computer Systems Engineering (CSE)**.
- Produces a `RosterMap` data class with two dictionaries: `by_asurite` and `by_id`.
- This major classification is used during ABET outcome analysis to produce per-major competency breakdowns.

Currently only supports classification of CS and CSE.

### `quiz_statistics.py`

Fetches quiz statistics from Canvas's quiz statistics API and renders them as a styled PDF using xhtml2pdf. Includes:
- Summary statistics (average, high, low, standard deviation).
- Score distribution table.
- Per-question breakdown with answer-level statistics.
- 
The style is based on what I could replicate of the reference quiz_statistics.pdf files found in CSE ABET Fall 2021 (the reference course provided to us for what formatting should look like in the end).

### `update_database.py`

`DatabaseManager` — provides MySQL connection pooling and data persistence:
- Connection pool of 5 connections with retry logic (3 attempts, exponential backoff).
- `update_course_data()` — upserts ABET report JSON into the `courses` table.
- Used by the extraction pipeline to persist ABET outcome data for use by the Report Generation API and frontend.

### `upload_abet_reports.py`

Handles the generation and upload of ABET report DOCX files:
- Calls `abetReportGenerator.reportgen()` to produce DOCX files from the embedded template.
- Renames output files to a standard format: `CSE423_ABET_3_Fall_2023.docx`.
- Uploads each report to its corresponding outcome folder in Canvas.

### `abetReportGenerator.py`

The ABET DOCX report generator. Features:
- An **embedded DOCX template** encoded as Base64 (from a professor-provided template).
- Template text replacement while preserving formatting.
- OpenAI integration for two purposes:
  1. Generating feedback text when an outcome is not met.
  2. Formatting assessment instrument descriptions (Section 2).
- CSV summary generation alongside DOCX reports.
- Fetches the OpenAI API key from the `settings` database table (AES-encrypted), with fallback to the `OPENAI_API_KEY` environment variable.

Reach out to the reportgen team for any questions regarding the report generation process. Note that this report is different from the long report. This report is specifically for the per-course-per-abet-outcome reports (the ones titled something like Assessment Report Content and Format - CSE2_CS......)

### `tasks.py`

Celery task definitions. Contains `run_extraction_pipeline` — a `@shared_task` that:
1. Deserializes job parameters.
2. Calls `run_pipeline_sync()`.
3. On success, triggers the Canvas Formatting API.
4. In the `finally` block, releases all Redis course locks.

## Canvas Folder Structure

The complete folder tree created on the destination Canvas course is:

```
{Course Name} [{Teacher1, Teacher2}]/
│
├── ({Term Display})/                                          # e.g., (Fall 2023)
│   │
│   ├── Syllabus/                                              # UPLOAD SITE 1
│   │   ├── syllabus_body.html                                 # Raw Canvas syllabus HTML
│   │   ├── syllabus_body.pdf                                  # HTML → PDF conversion
│   │   └── syllabus_{original_name}.pdf                       # Any PDF files linked in syllabus body
│   │
│   └── Test_Assignments/
│       │
│       ├── {Assignment Group Name}/                           # UPLOAD SITE 2
│       │   │                                                  # e.g., "Quizzes", "Homework", "Exams"
│       │   │                                                  # Comes from Canvas assignment groups
│       │   │
│       │   └── {Assignment Name}/                             # Sanitized assignment name
│       │       ├── description.html                           # Assignment description (if any)
│       │       ├── {linked_file}.pdf                          # Files linked in assignment description
│       │       ├── {linked_file}.docx                         # (downloaded by Canvas file ID)
│       │       ├── rubric.json                                # Rubric criteria (if any)
│       │       ├── {Assignment_Name}_high_{score}.{ext}       # Highest-scored submission
│       │       ├── {Assignment_Name}_avg_{score}.{ext}        # Average-scored submission
│       │       ├── {Assignment_Name}_low_{score}.{ext}        # Lowest-scored submission
│       │       ├── grade_report_{assignment_id}.csv           # Full grade report for this assignment
│       │       └── {Quiz_Title}_statistics.pdf                # (quiz-type only, replaces high/avg/low)
│       │
│       ├── {Another Assignment Group}/                        # Repeats for each assignment group
│       │   └── ...
│       │
│       └── Project Evaluations/                               # UPLOAD SITE 2 (ABET) + UPLOAD SITE 3
│           │
│           ├── Abet {N}/                                      # One folder per ABET outcome number
│           │   │                                              # e.g., "Abet 1", "Abet 3", "Abet 6"
│           │   │
│           │   │                                              # From UPLOAD SITE 2 (duplicated files):
│           │   ├── description.html                           # Same files as the primary assignment
│           │   ├── rubric.json                                # folder, uploaded again here. These are
│           │   ├── {Assignment_Name}_high_{score}.{ext}       # the contributing assignments to that abet outcome
│           │   ├── {Assignment_Name}_avg_{score}.{ext}
│           │   ├── {Assignment_Name}_low_{score}.{ext}
│           │   ├── grade_report_{assignment_id}.csv
│           │   │
│           │   │                                              # From UPLOAD SITE 3 (generated DOCX):
│           │   └── {CSE423}_ABET_{N}_{Fall_2023}.docx         # Generated ABET report document
│           │
│           └── Abet {M}/                                      # Repeats for each outcome
│               └── ...

```

> [!WARNING]
> **This folder structure is the contract between the Extraction API and the Canvas Formatting API.** The formatting API traverses this hierarchy on the destination course to build Canvas modules. Any changes to folder naming, nesting depth, or the position of `Test_Assignments` / `Project Evaluations` / `Syllabus` will break formatting. If you modify the extraction hierarchy, you **must** update the formatting API to match.


## Concurrency & Rate Limiting

- **Assignment processing:** Up to `MAX_PARALLEL_ASSIGNMENTS = 2` assignments are processed concurrently via `ThreadPoolExecutor`.
- **File uploads:** Up to `MAX_PARALLEL_UPLOADS = 4` files are uploaded in parallel per folder, with the first file uploaded synchronously to avoid folder creation race conditions.
- **Canvas rate limiting:** `CanvasGradesFetcher` implements adaptive sleep based on the `X-Rate-Limit-Remaining` header:
  - < 50 remaining → 5s sleep
  - < 100 → 2s sleep
  - < 200 → 0.5s sleep
  - < 350 → 0.1s sleep
  - ≥ 350 → no sleep
- **Rate limit retries:** Up to 3 retries with a 10-second sleep on `403`/`429` responses containing "Rate Limit".
- **Celery concurrency:** Worker prefetch multiplier is 1 (one task at a time per process).

## Docker Configuration

There are **three** Docker Compose files, each targeting a different environment. All three share the same `docker/extraction_api/Dockerfile` — the differences are in compose-level configuration (networking, services, volumes, env overrides).

### Dockerfile (`docker/extraction_api/Dockerfile`)

The Extraction API Dockerfile is the same across all environments:

```dockerfile
FROM python:3.11-slim
WORKDIR /usr/src/app

ARG EXTRACTION_PORT=8000
ENV EXTRACTION_PORT=$EXTRACTION_PORT
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
ENV PYTHONPATH=/usr/src/app

# System deps for PDF/DOCX libraries (xhtml2pdf, python-docx, etc.)
RUN apt-get update && apt-get install -y \
    build-essential libjpeg-dev zlib1g-dev pkg-config libcairo2-dev \
    && rm -rf /var/lib/apt/lists/*

COPY src/shared/requirements.txt ./requirements-shared.txt
COPY src/extraction_api/requirements.txt ./requirements-extraction.txt
RUN pip install --no-cache-dir -r requirements-shared.txt -r requirements-extraction.txt

CMD uvicorn assignment_extraction_api:app --host 0.0.0.0 --port "$EXTRACTION_PORT"
```

Key points:
- `PYTHONPATH=/usr/src/app` — allows `import shared.celery_app` and `import assignment_extraction_api` to work from the same container.
- Uvicorn runs **without** `--reload` by default. You must restart the container to pick up code changes, or modify the CMD to add `--reload` for development.

### The Three Compose Environments

#### 1. Development — `docker-compose.yml`

Used for local development. Run with:

```bash
cd docker/
cp demo.env .env    # first time only
docker compose up --build
```

**Services included:** All services (php_apache, mysql, phpmyadmin, extraction_api, canvas_formatting_api, reportgen, redis, celery_worker, flower, selenium)

**Networking:** Uses a Docker bridge network (`app_network`). All containers communicate via Docker DNS hostnames (e.g., `extraction_api`, `mysql`, `redis`).

**Extraction API config:**
****
```yaml
extraction_api:
  build:
    context: ../
    dockerfile: docker/extraction_api/Dockerfile
    args:
      EXTRACTION_PORT: ${EXTRACTION_PORT}
  container_name: extraction_api
  hostname: ${EXTRACTION_HOSTNAME}
  env_file:
    - .env
  ports:
    - "${EXTRACTION_PORT}:${EXTRACTION_PORT}"
  volumes:
    - ../src/extraction_api:/usr/src/app
    - ../src/shared:/usr/src/app/shared
  networks:
    - app_network
  dns:
    - 8.8.8.8
    - 1.1.1.1
  depends_on:
    mysql:
      condition: service_healthy
```

**What's unique to dev:**
- MySQL container runs locally with `healthcheck` — extraction_api waits for it to be healthy before starting.
- MySQL data is **not** persisted by default (commented out volume mount). Each `docker compose down` wipes the database. uncomment to persist the data
- phpMyAdmin is available at port `8081` for database inspection.
- Flower (Celery monitoring dashboard) is included on port `5555`. You can monitor all celery tasks at `http://localhost:5555`.
- Selenium is available under the `testing` profile (not started by default, use `docker compose --profile testing up`).
- Celery worker connects to Redis via Docker hostname: `redis://redis:6379/0`.

---

#### 2. Staging — `docker-compose-staging.yml`

Simulates production on your local machine. I think this is still a work in progress but im not really sure.

#### 3. Production — `docker-compose-prod.yml`

Deployed to the GoDaddy VPS server. Run on the server only via the deploy script.

**Services included:** extraction_api, canvas_formatting_api, reportgen, redis, celery_worker 

**Why no PHP or MySQL?** In production, the PHP/Apache web server (`public_html/abet.asucapstonetools.com`) is served directly by the cPanel host's Apache, **not** from a Docker container. MySQL is provided by the host machine or a managed service, not a Docker container.

**Networking:** `network_mode: "host"` for all services. Redis binds to `127.0.0.1:6379` (not exposed externally).

**Extraction API config:**

```yaml
extraction_api:
  build:
    context: ../
    dockerfile: docker/extraction_api/Dockerfile
    args:
      EXTRACTION_PORT: ${EXTRACTION_PORT}
  container_name: extraction_api
  env_file:
    - .env
  environment:
    - OPENAI_API_KEY=${OPENAI_API_KEY}
    - CELERY_BROKER_URL=redis://127.0.0.1:6379/0
    - CELERY_RESULT_BACKEND=redis://127.0.0.1:6379/1
  volumes:
    - ../src/extraction_api:/usr/src/app
    - ../src/shared:/usr/src/app/shared
  network_mode: "host"
  dns:
    - 8.8.8.8
    - 1.1.1.1
```

**What's different from staging:**
- Celery/Redis URLs are explicitly overridden to `redis://127.0.0.1:6379`.
- `CANVAS_FORMATTING_HOSTNAME` is overridden to `127.0.0.1` in the celery_worker environment.
- No `hostname:` — not needed with host networking.
- No PHP/MySQL/phpMyAdmin containers — those are handled by the host.


> [!NOTE]
> The DNS override to `8.8.8.8` / `1.1.1.1` exists because the GoDaddy VPS's default DNS resolver uses tailscale causing issues. This was specifically debugged, refer to the google doc of the project at [dns issue](https://docs.google.com/document/d/1mHOwIYyIZtg7FO8jtxTz9lPIuB3W9JVeVAR240YsTQA/edit?tab=t.fr3omjk3cjzx#heading=h.q8ci7sjqkds3) for details

### Deployment Process

Refer to the deployment documentation

## Environment Variables

The following environment variables are relevant to the Extraction API (set via `docker/.env`):

| Variable                     | Description                                           | Dev Default        | Prod Override |
| ---------------------------- | ----------------------------------------------------- | ------------------ | ------------- |
| `EXTRACTION_PORT`            | Port the API listens on                               | `8000`             | same |
| `EXTRACTION_HOSTNAME`        | Docker hostname for internal service discovery         | `extraction_api`   | N/A (host networking) |
| `MYSQL_HOSTNAME`             | MySQL server hostname                                 | `mysql`            | `127.0.0.1` or managed DB host |
| `MYSQL_USER`                 | MySQL username                                        | `abet_user`        | (from prod .env) |
| `MYSQL_PASS`                 | MySQL password                                        | `notSecureChangeMe`| (from prod .env) |
| `MYSQL_DATABASE`             | MySQL database name                                   | `osburn_abet_tools_dev` | (from prod .env) |
| `MYSQL_PORT`                 | MySQL port                                            | `3306`             | same |
| `CELERY_BROKER_URL`          | Redis URL for Celery message broker                   | `redis://redis:6379/0` | `redis://127.0.0.1:6379/0` |
| `CELERY_RESULT_BACKEND`      | Redis URL for Celery result storage                   | `redis://redis:6379/1` | `redis://127.0.0.1:6379/1` |
| `OPENAI_API_KEY`             | OpenAI API key (fallback if not stored in DB)         | `NOT_SECURE_CHANGE_ME` | (from prod .env) |
| `CANVAS_FORMATTING_HOSTNAME` | Hostname of the Canvas Formatting API                 | `canvas_formatting` | `127.0.0.1` |
| `CANVAS_FORMATTING_PORT`     | Port of the Canvas Formatting API                     | `8001`             | same |


## Running Locally (Development)

From the project root:

```bash
cd docker/
cp demo.env .env    # if not already done
docker compose up --build
```

The Extraction API will be available at `http://localhost:8000`.

For testing individual endpoints you can either rawdog curl requests, or go to http://localhost:8000/docs to use the swagger UI. There you will get a nice ui and will be able to test all endpoints. Please be careful while testing the move-data-between-courses and any such endpoints that do actual uploads to canvas. Make sure you dont push data where you dont want to.
![alt text](/ABET-Tools-Dockerized/src/extraction_api/docs-images/docs-swagger-ui-image.png)
