# Central Admin Panel Design

## Scope

Implement Taiga task `#12 Central Admin Panel` on the existing Symfony application.
The change makes the existing admin-panel Twig template reachable and safe to use
without claiming Taiga task `#13` (the destination and user-management panels) is
complete.

## Architecture

- Add an `AdminController` with a `/admin` route named `app_admin_panel`.
- Require `ROLE_ADMIN` with Symfony's `IsGranted` attribute.
- Render the existing `templates/tools/admin_panel/home.html.twig` template.
- Correct Doctrine's entity mapping to the existing `src/Entity` directory so
  the Symfony container can compile.
- Link the Destination Canvas Shell card to the existing
  `app_assignments_grades` route.
- Display User Management as unavailable rather than linking to the nonexistent
  `app_admin_users` route.

## Behavior

- Anonymous and non-admin users cannot access `/admin`.
- Admin users can load `/admin`.
- The page offers a working link to the existing assignments-and-grades
  configuration tool.
- The unfinished User Management feature is visibly disabled.

## Testing

Add a focused test that verifies the controller's `/admin` route metadata,
`ROLE_ADMIN` access requirement, working template destination, disabled User
Management state, and valid Doctrine entity-directory mapping.

Because the repository's database-dependent kernel tests require a running MySQL
service, the focused test also verifies the route metadata, access requirement,
template destinations, and Doctrine entity-directory configuration without
requiring a database connection.

## Out of Scope

- Implementing the User Management screen.
- Changing destination-course configuration behavior.
- Porting the old `admin-user-management` branch.
- Refactoring unrelated authentication or layout code.
