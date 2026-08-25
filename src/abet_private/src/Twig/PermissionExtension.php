<?php

namespace App\Twig;

use App\Entity\Permissions;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Converts role-name strings (e.g. from User::getRoles(),
 * getRequestedPermissionNames()) into human-readable labels, for the many
 * templates that display a user's permissions to someone other than a
 * developer. Unlike Permissions::label(), this works on the plain string
 * names those methods return, not Permissions instances directly.
 */
final class PermissionExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('permission_label', $this->label(...)),
            new TwigFilter('permission_labels', $this->labels(...)),
        ];
    }

    public function label(string $roleName): string
    {
        foreach (Permissions::cases() as $permission) {
            if ($permission->name === $roleName) {
                return $permission->label();
            }
        }

        return $roleName === 'ROLE_USER' ? 'User' : $roleName;
    }

    /**
     * Labels a list of role names, dropping the implicit ROLE_USER that
     * every account has — it's not a meaningful permission to display.
     *
     * @param list<string> $roleNames
     * @return list<string>
     */
    public function labels(array $roleNames): array
    {
        return array_values(array_map(
            $this->label(...),
            array_filter($roleNames, static fn (string $name): bool => $name !== 'ROLE_USER'),
        ));
    }
}
