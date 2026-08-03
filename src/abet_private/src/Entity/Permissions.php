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
}
