<?php

namespace App\Support;

class RoleHierarchy
{
    /**
     * Role name => hierarchy level. Higher number outranks lower.
     *
     * "Department Head" is intentionally NOT listed here — it maps to
     * level 0, matching the original app's config.php which defined
     * only these 5 levels.
     *
     * @var array<string, int>
     */
    public const LEVELS = [
        'Administrator' => 5,
        'Campus Manager' => 4,
        'Store Manager' => 3,
        'Section Manager' => 2,
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
