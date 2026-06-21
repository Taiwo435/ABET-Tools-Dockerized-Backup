# Central Admin Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the existing Symfony Central Admin Panel available to authorized administrators without exposing unfinished links.

**Architecture:** A focused Symfony controller owns the `/admin` endpoint and relies on the existing security role system. The existing Twig template is corrected to use the current assignments-and-grades route and to render User Management as disabled until Taiga task `#13` is implemented.

**Tech Stack:** PHP 8.3, Symfony 7.4, Twig, PHPUnit/WebTestCase

---

### Task 1: Add a failing admin-panel controller test

**Files:**
- Create: `src/abet_private/tests/Controller/AdminControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Controller;

use App\Entity\Permissions;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminControllerTest extends WebTestCase
{
    public function testAdminCanOpenCentralPanel(): void
    {
        $client = static::createClient();

        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setPasswordHash('test-password-hash');
        $admin->setPermission(Permissions::ROLE_ADMIN, true);

        $client->loginUser($admin);
        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Admin Panel');
        self::assertSelectorExists('a[href="/tool/assignmentsgrades"]');
        self::assertSelectorTextContains('[aria-disabled="true"]', 'Coming Soon');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
cd src/abet_private
./bin/phpunit tests/Controller/AdminControllerTest.php
```

Expected: failure because no Symfony route handles `/admin`.

- [ ] **Step 3: Commit the failing test**

```bash
git add src/abet_private/tests/Controller/AdminControllerTest.php
git commit -m "test: cover central admin panel"
```

### Task 2: Implement the protected controller and safe template

**Files:**
- Create: `src/abet_private/src/Controller/AdminController.php`
- Modify: `src/abet_private/templates/tools/admin_panel/home.html.twig`

- [ ] **Step 1: Add the minimal controller**

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin', name: 'app_admin_panel', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/admin_panel/home.html.twig');
    }
}
```

- [ ] **Step 2: Correct the working tool link**

Replace:

```twig
<a href="{{ path('app_admin_assignments_grades') }}" class="action-link">Config Tool 1 &rarr;</a>
```

with:

```twig
<a href="{{ path('app_assignments_grades') }}" class="action-link">Configure Tool 1 &rarr;</a>
```

- [ ] **Step 3: Disable the unfinished User Management action**

Replace the nonexistent `app_admin_users` link with:

```twig
<span class="action-link" aria-disabled="true">Coming Soon</span>
```

- [ ] **Step 4: Run the focused test**

Run:

```bash
cd src/abet_private
./bin/phpunit tests/Controller/AdminControllerTest.php
```

Expected: one passing test.

- [ ] **Step 5: Run the full Symfony test suite**

Run:

```bash
cd src/abet_private
./bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Run static repository checks**

Run:

```bash
git diff --check
git status --short
```

Expected: no whitespace errors; only the planned files are modified.

- [ ] **Step 7: Commit the implementation**

```bash
git add src/abet_private/src/Controller/AdminController.php \
  src/abet_private/templates/tools/admin_panel/home.html.twig
git commit -m "feat: expose protected central admin panel"
```
