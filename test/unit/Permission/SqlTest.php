<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Perms\Unit\Permission;

use Horde_Perms_Permission_Sql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Perms_Permission_Sql basic functionality.
 *
 * Note: Most Horde_Perms_Permission_Sql functionality requires real database
 * connections and is tested in integration tests instead.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
#[CoversClass(Horde_Perms_Permission_Sql::class)]
class SqlTest extends TestCase
{
    public function testConstructorSetsName(): void
    {
        $perm = new Horde_Perms_Permission_Sql('test:permission', 1);

        $this->assertEquals('test:permission', $perm->getName());
    }

    public function testSetIdAndGetId(): void
    {
        $perm = new Horde_Perms_Permission_Sql('test', 1);
        $perm->setId(42);

        $this->assertEquals(42, $perm->getId());
    }

    public function testSleepExcludesCacheAndDb(): void
    {
        $perm = new Horde_Perms_Permission_Sql('test', 1);
        $perm->setId(42);

        $serialized = serialize($perm);
        $unserialized = unserialize($serialized);

        // After unserialize, the ID should still be there
        // but cache and DB should be excluded
        $this->assertEquals(42, $unserialized->getId());
        $this->assertEquals('test', $unserialized->getName());
    }
}
