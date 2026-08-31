<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\Table(name: "users")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column(name:"password_hash", type: "string", length:255)]
    private ?string $password = null;

    /**
     * @var int The user permissions that define user roles
     * bitmask based on Permissions enum
     */
    #[ORM\Column(name:'permissions', type:'integer', nullable: false, options: ["default" => 0])]
    private int $permissions = 0;

    #[ORM\Column(name: "is_active", type: "boolean", options: ["default" => true])]
    private bool $isActive = true;

    /**
     * @var int|null Pending permissions bitmask requested via the "Request Access" page.
     * NULL means the user has no pending request. Cleared on approve/deny.
     */
    #[ORM\Column(name: 'requested_permissions', type: 'integer', nullable: true)]
    private ?int $requestedPermissions = null;

    #[ORM\Column(name: "last_login", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(name: "created_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeInterface $createdAt;

    /**
     * Without this, a plain `new User()` (e.g. in UserORMTest) leaves
     * $createdAt uninitialized until something explicitly sets it, which
     * surfaces as "must not be accessed before initialization" the moment
     * anything calls getCreatedAt() on that row.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAsurite(): string
    {
        $parts = explode('@', (string)$this->email);
        $asurite = $parts[0] ?? 'user';
        return $asurite;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }
    public function getPasswordHash(): string
    {
        return $this->password;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->password = $passwordHash;
        return $this;
    }

    /**
     * Returns if a user is currently active in the database
     * For database rollbacks to undo user deletion
     * @return bool if the user row is active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }


    /**
     * Allows users to be deactivated to allow for rollbacks
     *
     * @param bool $isActive    New active state
     * @return User             for the method chaining pattern (fluent interface)
     */
    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Returns last user login time
     *
     * @return \DateTimeInterface|null
     */
    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    /**
     * Allows people to set last login time
     * Note: we should update this to updateLastLoginTime();
     *
     * @param mixed $lastLogin new last login time
     * @return User             for method chaining
     */
    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    /**
     * The time the user was created at
     *
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * A visual identifier that represents this user.
     * Email in this case
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    private function bitmaskToRoles(int $bitmask): array {
        $roles = [];

        foreach (Permissions::cases() as $permission) {
            if ($bitmask & $permission->value) {
                $roles[] = $permission->name;
            }
        }

        return $roles;
    }

    /**
     * Possible roles are in the Permissions enum
     * @see Permissions
     * @see UserInterface
     */
    public function getRoles(): array
    {
        // Mirrors hasPermission()'s admin short-circuit: an admin implicitly
        // has every permission. Without this, Symfony's access_control /
        // #[IsGranted] checks (which read getRoles(), not hasPermission())
        // would deny an admin who doesn't happen to also have the specific
        // permission bit for a given tool.
        if ($this->permissions & Permissions::ROLE_ADMIN->value) {
            $roles = array_map(static fn (Permissions $permission): string => $permission->name, Permissions::cases());
        } else {
            $roles = $this->bitmaskToRoles($this->permissions);
        }

        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * Returns whether the user has a certian permission
     * Will always be true if the user is an admin
     * @param Permissions $permission   The permission you want to read
     * @throws InvalidArgumentException         If the input is not a valid permission (or is not handled...)
     * @return bool                             The value of the user's access.
     */
    public function hasPermission(Permissions $permission) : bool {
    if ($this->permissions & Permissions::ROLE_ADMIN->value) {
        return true;
    }
    return ($this->permissions & $permission->value) != 0;
    }

    /**
     * Sets a certain permission to the indicated Value
     * @param Permissions $permission   The permission you want to change
     * @param bool $active                      The new state of the permission
     * @return void
     */
    public function setPermission(Permissions $permission, bool $active) : void {
        if ($active) {
            $this->permissions |= $permission->value;
        }
        else {
            $this->permissions &= ~$permission->value;
        }
    }

    /**
     * printable permissions
     * @return int                             The value of the user's access.
     */
    public function getPermissions() : int {
        return $this->permissions;
    }

    /**
     * Overwrites the full permissions bitmask directly.
     * @param int $permissions   The new permissions bitmask.
     */
    public function setPermissions(int $permissions) : void {
        $this->permissions = $permissions;
    }

    /**
     * The bitmask of permissions this user has requested but not yet been granted.
     * NULL means there is no pending request.
     */
    public function getRequestedPermissions(): ?int {
        return $this->requestedPermissions;
    }

    /**
     * Sets the pending requested-permissions bitmask.
     * Pass null to clear a pending request (e.g. after approve/deny).
     */
    public function setRequestedPermissions(?int $requestedPermissions): self {
        $this->requestedPermissions = $requestedPermissions;
        return $this;
    }

    /**
     * Names of the currently-requested permissions, for display purposes.
     * @return list<string>
     */
    public function getRequestedPermissionNames(): array {
        if ($this->requestedPermissions === null) {
            return [];
        }
        return $this->bitmaskToRoles($this->requestedPermissions);
    }

    // /**
    //  * Possible roles are in the Permissions enum
    //  * @see Permissions
    //  * @param list<string> $roles
    //  */
    // public function setRoles(array $roles): static
    // {
    //     $this->roles = $roles;

    //     return $this;
    // }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    /**
     * Intentionally left blank.
     *
     * $password stores a bcrypt hash (never plaintext), so there is nothing
     * sensitive here that needs erasing after authentication.
     *
     * Do NOT null out $permissions here. $permissions is a typed, non-nullable
     * int column — assigning null to it throws a TypeError, which previously
     * caused an immediate crash right after a successful login (see #132).
     */

    public function eraseCredentials(): void
    {
    }

}
