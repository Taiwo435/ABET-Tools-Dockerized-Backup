<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use InvalidArgumentException;

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

    #[ORM\Column(type: "string", columnDefinition: "ENUM('admin', 'faculty')")]
    private string $role = 'faculty';

    /**
     * @var int The user permissions that define user roles
     * bitmask based on Permissions enum
     */
    #[ORM\Column(name:'permissions', type:'integer', nullable: false, options: ["default" => 0])]
    private int $permissions = 0;

    #[ORM\Column(name: "is_active", type: "boolean", options: ["default" => true])]
    private bool $isActive = true;

    #[ORM\Column(name: "last_login", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(name: "created_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeInterface $createdAt;

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
     * Gets the User role between (admin, faculty)
     * @deprecated Use getRoles() for a more descriptive role interface
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Gets the User role between (admin, faculty)
     * @deprecated Use getRoles() for a more descriptive role interface
     */
    public function setRole(string $role): self
    {
        if (!in_array($role, ['admin', 'faculty'])) {
            throw new InvalidArgumentException("Invalid role");
        }

        $this->role = $role;
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
        // $roles = $this->roles;
        $roles = $this->bitmaskToRoles($this->permissions);

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
        if ($this->role === 'admin') {
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

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        $this->password = null;
        $this->role = "";
        $this->permissions = 0;
    }
}
