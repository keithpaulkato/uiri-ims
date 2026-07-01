<?php

namespace App\Support;

class RoleHierarchy
{
    /**
     * Role name => hierarchy level. Higher number outranks lower.
     *
     * "Department Head" oversees a department, which sits under a section in
     * the org structure (departments.section_id), so it ranks above Staff and
     * below Section Manager. Any role not listed maps to level 0 (deny).
     *
     * @var array<string, int>
     */
    public const LEVELS = [
        'Administrator' => 6,
        'Campus Manager' => 5,
        'Store Manager' => 4,
        'Section Manager' => 3,
        'Department Head' => 2,
        'Staff' => 1,
    ];

    public static function level(string $role): int
    {
        return self::LEVELS[$role] ?? 0;
    }

    public static function isAtOrAbove(string $userRole, string $requiredRole): bool
    {
        return self::level($userRole) >= self::level($requiredRole);
    }
}
