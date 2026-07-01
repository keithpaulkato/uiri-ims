<?php

namespace Tests\Unit;

use App\Support\RoleHierarchy;
use PHPUnit\Framework\TestCase;

class RoleHierarchyTest extends TestCase
{
    public function test_administrator_outranks_staff(): void
    {
        $this->assertTrue(RoleHierarchy::isAtOrAbove('Administrator', 'Staff'));
    }

    public function test_staff_does_not_outrank_store_manager(): void
    {
        $this->assertFalse(RoleHierarchy::isAtOrAbove('Staff', 'Store Manager'));
    }

    public function test_equal_role_satisfies_at_or_above(): void
    {
        $this->assertTrue(RoleHierarchy::isAtOrAbove('Store Manager', 'Store Manager'));
    }

    public function test_unknown_role_is_level_zero(): void
    {
        $this->assertSame(0, RoleHierarchy::level('Department Head'));
    }
}
