<?php

namespace App\Entity;

/**
 * The matrix of possible permissions a user can have.
 *
 * Always append new values after existing production values. Reordering existing
 * values changes their bit positions and corrupts the meaning of stored bitmasks.
 *
 * Uses a bitmask implementation with a maximum of 32 permissions unless the
 * database column is expanded.
 */
enum Permissions: int
{
    case ROLE_ADMIN = 1 << 0;
    case ROLE_ASSIGNMENTS_GRADES = 1 << 1;
    case ROLE_CANVAS_FORMATTING = 1 << 2;
    case ROLE_REPORTGEN = 1 << 3;
    case ROLE_FACULTY_FORM = 1 << 4;
    case ROLE_COORDINATOR_FORM = 1 << 5;

    /**
     * Human-readable display name — single source of truth for every screen
     * that shows a user's permissions, so they never leak SHOUTY_ROLE_NAMEs.
     */
    public function label(): string {
        return match ($this) {
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_ASSIGNMENTS_GRADES => 'Assignments & Grades',
            self::ROLE_CANVAS_FORMATTING => 'Canvas Formatting',
            self::ROLE_REPORTGEN => 'Report Generation',
            self::ROLE_FACULTY_FORM => 'Faculty Form',
            self::ROLE_COORDINATOR_FORM => 'Coordinator Form',
        };
    }
}
