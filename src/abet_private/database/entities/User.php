<?php
declare(strict_types=1);
namespace Entity;

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;

use DateTime;


// generated php import
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Column;
use InvalidArgumentException;

#[Entity]
#[Table(name: "users")]
class User
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "integer")]
    private ?int $id = null;

    #[Column(type: "string", length: 255, unique: true)]
    private string $email;

    #[Column(name: "password_hash", type: "string", length: 255)]
    private string $passwordHash;

    #[Column(type: "string", columnDefinition: "ENUM('admin', 'faculty')")]
    private string $role = 'faculty';

    #[Column(name:'permissions', type:'integer', nullable: false, options: ["default" => 0])]
    private int $permissions = 0;

    #[Column(name: "is_active", type: "boolean", options: ["default" => true])]
    private bool $isActive = true;

    #[Column(name: "last_login", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[Column(name: "created_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        if (!in_array($role, ['admin', 'faculty'])) {
            throw new InvalidArgumentException("Invalid role");
        }

        $this->role = $role;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /////////////////////////////////
    // PERMISSIONS INTERFACE
    // Getters and setters for user permissions
    /////////////////////////////////

    /**
     * Returns whether the user has a certian permission
     * Will always be true if the user is an admin
     * @param \Entity\Permissions $permission   The permission you want to read
     * @throws InvalidArgumentException         If the input is not a valid permission (or is not handled...)
     * @return bool                             The value of the user's access.
     */
    public function hasPermission(Permissions $permission) : bool {
        if ($this->getRole() === 'admin') {
            return true;
        }
        return ($this->permissions & $permission->value) != 0;
    }

    /**
     * Sets a certain permission to the indicated Value
     * @param \Entity\Permissions $permission   The permission you want to change
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
     * Only for development
     * Really useless since I gave the admins all permissions anyways
     * @return void
     */
    public function giveAllPermissions() : void {
        $numPerms = Permissions::cases();
        foreach ($numPerms as $perm) {
            $this->permissions |= $perm->value;
        }
    }
}


/**
 * The Matrix of possible permissions a user can have
 * NOTE: If this is in production, ALWAYS add values afterward! (by production i mean has REAL user data)
 * Otherwise, you WILL ruin the implementation!!
 * 
 * Uses a bitmask implementation, max of 32 permissions unless we change column length (very possible)
 */
enum Permissions : int {
    case AdminPanel = 1 << 0;
    case GradeDataTool = 1 << 1;
    case CanvasFormattingTool = 1 << 2;
    case ReportGenTool = 1 << 3;
    case FacultyFormTool = 1 << 4;
    case CoordinatorFormTool = 1 << 5;
}