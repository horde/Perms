<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Perms\Unit;

use Horde_Perms_Exception;
use Horde_Perms_Null;
use Horde_Perms_Permission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Perms_Null.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
#[CoversClass(Horde_Perms_Null::class)]
class NullTest extends TestCase
{
    private Horde_Perms_Null $perms;

    protected function setUp(): void
    {
        $this->perms = new Horde_Perms_Null();
    }

    public function testNewPermissionThrowsException(): void
    {
        $this->expectException(Horde_Perms_Exception::class);
        $this->perms->newPermission('test');
    }

    public function testGetPermissionThrowsException(): void
    {
        $this->expectException(Horde_Perms_Exception::class);
        $this->perms->getPermission('test');
    }

    public function testGetPermissionByIdThrowsException(): void
    {
        $this->expectException(Horde_Perms_Exception::class);
        $this->perms->getPermissionById(1);
    }

    public function testAddPermissionThrowsException(): void
    {
        $perm = $this->createStub(Horde_Perms_Permission::class);

        $this->expectException(Horde_Perms_Exception::class);
        $this->perms->addPermission($perm);
    }

    public function testRemovePermissionThrowsException(): void
    {
        $perm = $this->createStub(Horde_Perms_Permission::class);

        $this->expectException(Horde_Perms_Exception::class);
        $this->perms->removePermission($perm);
    }

    public function testGetPermissionIdThrowsException(): void
    {
        $perm = $this->createStub(Horde_Perms_Permission::class);

        $this->expectException(Horde_Perms_Exception::class);
        $this->perms->getPermissionId($perm);
    }

    public function testExistsAlwaysReturnsFalse(): void
    {
        $this->assertFalse($this->perms->exists('test'));
        $this->assertFalse($this->perms->exists('any:permission:name'));
        $this->assertFalse($this->perms->exists(''));
    }

    public function testGetParentsThrowsException(): void
    {
        $this->expectException(Horde_Perms_Exception::class);
        $this->perms->getParents('test');
    }

    public function testGetTreeReturnsEmptyArray(): void
    {
        $tree = $this->perms->getTree();

        $this->assertIsArray($tree);
        $this->assertEmpty($tree);
    }
}
