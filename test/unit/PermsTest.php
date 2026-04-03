<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Perms\Unit;

use Horde_Perms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Perms core class.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
#[CoversClass(Horde_Perms::class)]
class PermsTest extends TestCase
{
    public function testShowConstant(): void
    {
        $this->assertEquals(2, Horde_Perms::SHOW);
    }

    public function testReadConstant(): void
    {
        $this->assertEquals(4, Horde_Perms::READ);
    }

    public function testEditConstant(): void
    {
        $this->assertEquals(8, Horde_Perms::EDIT);
    }

    public function testDeleteConstant(): void
    {
        $this->assertEquals(16, Horde_Perms::DELETE);
    }

    public function testAllConstant(): void
    {
        $this->assertEquals(30, Horde_Perms::ALL);
        $this->assertEquals(
            Horde_Perms::SHOW | Horde_Perms::READ | Horde_Perms::EDIT | Horde_Perms::DELETE,
            Horde_Perms::ALL
        );
    }

    public function testRootConstant(): void
    {
        $this->assertEquals(-1, Horde_Perms::ROOT);
    }

    public function testGetPermsArrayReturnsAllPermissions(): void
    {
        $perms = Horde_Perms::getPermsArray();

        $this->assertIsArray($perms);
        $this->assertArrayHasKey(Horde_Perms::SHOW, $perms);
        $this->assertArrayHasKey(Horde_Perms::READ, $perms);
        $this->assertArrayHasKey(Horde_Perms::EDIT, $perms);
        $this->assertArrayHasKey(Horde_Perms::DELETE, $perms);
        $this->assertCount(4, $perms);
    }

    public function testIntegerToArrayConvertsShowPermission(): void
    {
        $result = Horde_Perms::integerToArray(Horde_Perms::SHOW);

        $this->assertArrayHasKey(Horde_Perms::SHOW, $result);
        $this->assertTrue($result[Horde_Perms::SHOW]);
        $this->assertCount(1, $result);
    }

    public function testIntegerToArrayConvertsReadPermission(): void
    {
        $result = Horde_Perms::integerToArray(Horde_Perms::READ);

        $this->assertArrayHasKey(Horde_Perms::READ, $result);
        $this->assertTrue($result[Horde_Perms::READ]);
        $this->assertCount(1, $result);
    }

    public function testIntegerToArrayConvertsMultiplePermissions(): void
    {
        $result = Horde_Perms::integerToArray(Horde_Perms::READ | Horde_Perms::EDIT);

        $this->assertArrayHasKey(Horde_Perms::READ, $result);
        $this->assertArrayHasKey(Horde_Perms::EDIT, $result);
        $this->assertTrue($result[Horde_Perms::READ]);
        $this->assertTrue($result[Horde_Perms::EDIT]);
        $this->assertCount(2, $result);
    }

    public function testIntegerToArrayConvertsAllPermissions(): void
    {
        $result = Horde_Perms::integerToArray(Horde_Perms::ALL);

        $this->assertArrayHasKey(Horde_Perms::SHOW, $result);
        $this->assertArrayHasKey(Horde_Perms::READ, $result);
        $this->assertArrayHasKey(Horde_Perms::EDIT, $result);
        $this->assertArrayHasKey(Horde_Perms::DELETE, $result);
        $this->assertCount(4, $result);
    }

    public function testIntegerToArrayReturnsEmptyArrayForZero(): void
    {
        $result = Horde_Perms::integerToArray(0);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testIntegerToArrayCachesResults(): void
    {
        $value = Horde_Perms::READ | Horde_Perms::EDIT;

        $result1 = Horde_Perms::integerToArray($value);
        $result2 = Horde_Perms::integerToArray($value);

        $this->assertSame($result1, $result2);
    }
}
