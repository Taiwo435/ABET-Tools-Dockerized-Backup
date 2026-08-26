# Syllabus Reconciliation Route and Permission Regression Evidence

- **Taiga:** #116
- **Verified:** 2026-08-08, America/Phoenix
- **Base:** `origin/main` at `c9e6c23`
- **Related browser coverage:** PR #219

## Result

The post-reconciliation route and permission checks pass against the current
main branch. Symfony resolves the account, coordinator/admin, and faculty
syllabus paths to the intended routes. Protected routes redirect an
unauthenticated request to the current `/login` endpoint, and the focused
browser test verifies that a non-admin cannot enter the admin syllabus
workspace while an admin can create and publish a shared template.

## Route Resolution

Each result was produced with Symfony's `router:match` command using a `GET`
request.

| Request | Expected route | Result |
| --- | --- | --- |
| `/account/overview/` | `app_account_overview` | Pass |
| `/admin/templates` | `app_admin_templates` | Pass |
| `/admin/syllabus-templates/new` | `app_admin_syllabus_templates_new` | Pass |
| `/syllabus-templates/new` | `app_faculty_syllabus_templates_new` | Pass |
| `/admin/syllabus-template-reviews/history` | `app_admin_syllabus_template_review_history` | Pass |

The static review-history path resolves before the dynamic `{id}` review path,
and the static `/new` paths resolve before numeric syllabus-template routes.

## Permission Results

Unauthenticated requests produced the intended redirect:

| Request | Status | Location |
| --- | --- | --- |
| `/account/overview/` | `302` | `/login` |
| `/admin/templates` | `302` | `/login` |
| `/admin/syllabus-templates` | `302` | `/login` |
| `/syllabus-templates` | `302` | `/login` |

Authenticated role coverage is in PR #219 for Taiga #115. The test verifies
that:

- a user with a non-admin permission receives an access-denied response at
  `/admin/syllabus-templates`;
- an admin can load `/admin/syllabus-templates`;
- the admin can create a complete shared syllabus draft; and
- the admin can publish the draft and see its unique course row as published.

## Reproduction Commands

From the repository root after creating `docker/.env` from `docker/demo.env`:

```bash
cd docker
docker compose --profile testing up --build -d
cd ..

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console doctrine:migrations:migrate --no-interaction'

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console router:match /account/overview/ --method=GET'

BACKEND_URL=http://localhost:8080 pytest -q src/test/test_admin_syllabus_workflow.py
BACKEND_URL=http://localhost:8080 pytest -q src/test/

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/phpunit'
```

Repeat `router:match` for the other four paths in the route table. Use `curl`
without cookies to confirm each protected route returns `302` with a `Location`
ending in `/login`.

## Verified Results

- Doctrine current and latest version:
  `Migrations\Version20260727000000`.
- Focused admin syllabus browser workflow: one passing test on three consecutive
  runs.
- Full Python/Selenium suite: 15 passing tests in 46.22 seconds.
- Full PHPUnit suite: 149 passing tests with 875 assertions.
- Ruff, workflow YAML parsing, Docker Compose configuration, and
  `git diff --check`: passing.

## Evidence Boundary

The route and permission behavior above was checked on current application code.
The browser test is open for review in PR #219 and is not yet merged to `main`.
A fresh local image rebuild was blocked by a registry metadata timeout, so the
local run used existing project images with the current source mounted into the
containers. GitHub Actions on PR #219 is the clean-runner build and test record.
