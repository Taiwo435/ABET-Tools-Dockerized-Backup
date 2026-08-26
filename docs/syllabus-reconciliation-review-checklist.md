# Syllabus Reconciliation Review Checklist

- **Taiga:** #117
- **Target:** current `main` or the pull request branch under review
- **Browser coverage:** PR #219
- **Route and permission evidence:** PR #220

Use this checklist to reproduce the reconciled syllabus workflow from a clean
checkout. It covers stack startup, schema preparation, route resolution,
permissions, the admin create/publish browser path, and the evidence a reviewer
should retain.

## 1. Prepare the Checkout

- [ ] Fetch the latest remote branches.
- [ ] Check out `main` or the pull request branch under review.
- [ ] Record the exact commit SHA.
- [ ] Confirm the worktree does not contain unrelated changes.
- [ ] Create the ignored local environment file from the demo configuration.

```bash
git fetch origin --prune
git status --short --branch
git rev-parse HEAD
cp docker/demo.env docker/.env
printf '\nUID=%s\n' "$(id -u)" >> docker/.env
```

Do not place production credentials in the demo environment or commit
`docker/.env`.

## 2. Start and Verify the Stack

Confirm the Docker daemon is available before starting the project:

```bash
docker info
cd docker
docker compose --profile testing config --quiet
docker compose --profile testing up --build -d
docker compose --profile testing ps
cd ..
```

- [ ] `php_apache`, `mysql`, `redis`, and `selenium` are running.
- [ ] MySQL and Redis report healthy.
- [ ] `http://localhost:8080/login` returns the sign-in page.
- [ ] `http://localhost:4444/status` reports Selenium ready.

If the stack uses non-default host ports, update `BACKEND_URL` and
`SELENIUM_PORT` in the commands below. The browser still reaches the web service
by its Compose service name inside the Docker network.

## 3. Apply Migrations Before Browser Tests

```bash
docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console doctrine:migrations:migrate --no-interaction'

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console doctrine:migrations:status --no-interaction'
```

- [ ] Migration execution exits successfully.
- [ ] `Current` and `Latest` identify the same reviewed migration.
- [ ] No new migration failure is dismissed as an existing test issue.

The August 8 reference environment was current through
`Migrations\Version20260727000000`.

## 4. Verify Route Resolution

Run a focused match for every path rather than inferring route order from the
controller source:

```bash
docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console router:match /account/overview/ --method=GET'

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console router:match /admin/templates --method=GET'

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console router:match /admin/syllabus-templates/new --method=GET'

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console router:match /syllabus-templates/new --method=GET'

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/console router:match /admin/syllabus-template-reviews/history --method=GET'
```

- [ ] `/account/overview/` resolves to `app_account_overview`.
- [ ] `/admin/templates` resolves to `app_admin_templates`.
- [ ] `/admin/syllabus-templates/new` resolves to
  `app_admin_syllabus_templates_new`.
- [ ] `/syllabus-templates/new` resolves to
  `app_faculty_syllabus_templates_new`.
- [ ] The static review-history path resolves to
  `app_admin_syllabus_template_review_history`, not the dynamic `{id}` route.

Compare results with
`docs/syllabus-reconciliation-regression-evidence.md` from PR #220.

## 5. Verify Unauthenticated Permissions

Run each request without cookies:

```bash
for route_path in \
  /account/overview/ \
  /admin/templates \
  /admin/syllabus-templates \
  /syllabus-templates
do
  curl -sS -o /dev/null -D - "http://localhost:8080${route_path}" \
    | grep -Ei '^(HTTP/|location:)'
done
```

- [ ] Each request returns `302`.
- [ ] Each `Location` ends in `/login`.
- [ ] No protected route returns its application page to an anonymous request.

## 6. Run the Focused Browser Workflow

Install the pinned test dependencies in an isolated Python environment, then run
the task-specific test:

```bash
python3 -m venv .venv
. .venv/bin/activate
pip install -r src/test/requirements.txt
pip install ruff

BACKEND_URL=http://localhost:8080 \
  pytest -q src/test/test_admin_syllabus_workflow.py
```

Confirm that the test proves every acceptance step:

- [ ] A seeded non-admin is denied `/admin/syllabus-templates`.
- [ ] A seeded admin opens the shared syllabus workspace.
- [ ] The admin creates a draft with a program, unique course identity, delivery
  type, credits, coordinator, and credit categorization.
- [ ] The browser reaches the numeric edit route after draft creation.
- [ ] Publishing returns to the workspace and shows the unique course as
  `Published`.

The automated path is preferred because it creates isolated users and a unique
course number. For a visual walkthrough, open `http://localhost:8080/login`, use
a dedicated local active admin account, then navigate to
`/admin/syllabus-templates` and repeat the create/publish path. Do not use a
production account or production database for the demonstration.

## 7. Run Broader Checks

```bash
BACKEND_URL=http://localhost:8080 pytest -q src/test/

docker exec -i --env-file docker/.env --env APP_ENV=test php_apache \
  sh -c 'cd "$ABET_PRIVATE_DIR" && ./bin/phpunit'

ruff check src/test/test_admin_syllabus_workflow.py src/test/utils/seeder.py
git diff --check
```

- [ ] The full Python/Selenium suite passes.
- [ ] The full PHPUnit suite passes.
- [ ] Focused lint and whitespace checks pass.
- [ ] A failure is compared with `main` before being classified as pre-existing.

Reference results verified on August 8, 2026:

- focused browser test: one pass on three consecutive runs;
- full Python/Selenium suite: 15 passing tests;
- full PHPUnit suite: 149 passing tests with 875 assertions.

## 8. Capture Review Evidence

- [ ] Record the branch and commit SHA under review.
- [ ] Record the migration version and exact test totals.
- [ ] Link PR #219 for browser coverage and PR #220 for route evidence.
- [ ] Attach a screenshot of the published workspace row when visual evidence
  is requested.
- [ ] Distinguish local verification, CI verification, review approval, and
  merged-to-main status.
- [ ] Record any registry, image, credential, or environment limitation without
  converting it into a passing claim.
- [ ] Do not close the Taiga task or merge a pull request without the team
  workflow approval.

## 9. Troubleshooting Boundaries

- A missing Docker socket means the daemon is unavailable; start Docker Desktop
  and rerun `docker info`.
- A registry metadata timeout prevents a clean image rebuild. Retrying is the
  preferred response. Existing images may be used for a source-mounted local
  check only when that limitation is recorded; CI remains the clean-build
  evidence.
- A missing PHP Redis extension indicates a stale application image. Rebuild the
  current Dockerfile rather than treating the resulting HTTP 500 as an
  application regression.
- A missing syllabus table means migrations were not applied before Selenium.
- The Symfony debug toolbar can cover a viewport-edge control in development;
  the automated helper verifies that the target is displayed and enabled before
  dispatching its click.

## 10. Optional Shutdown

```bash
cd docker
docker compose --profile testing down
```

Leave the stack running only when another reviewer needs the same local
environment.
