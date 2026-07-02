# PR Review Checklist

Use this checklist before asking someone to review a branch. It keeps pull
requests easier to merge and lowers the chance of missing deployment-sensitive
details.

## Scope

- Link the issue or task that the PR addresses.
- Describe the user-facing behavior that changed.
- Call out any database, configuration, routing, or deployment impact.
- Keep unrelated cleanup out of the PR unless it directly supports the task.

## Local Verification

- Start the Docker environment when the change affects runtime behavior.
- Run the relevant Symfony, PHPUnit, Pest, or Python checks for the touched
  module.
- Add screenshots or short notes for UI changes.
- Note anything that could not be tested locally and why.

## Database and Environment

- Add a migration for schema changes instead of editing local database state
  only.
- Avoid committing real `.env` values, private keys, exported database files, or
  server-only credentials.
- Mention any new environment variables in the PR description.

## Reviewer Handoff

- List the files or screens reviewers should inspect first.
- Explain any legacy-router or Symfony-routing interaction.
- Keep the PR branch up to date with `main` before final review when possible.
