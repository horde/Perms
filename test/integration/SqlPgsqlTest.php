<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Perms\Integration;

use Horde\Perms\Test\Unnamespaced\SqlTestBase;
use Horde_Db_Adapter;
use Horde_Db_Adapter_Pdo_Pgsql;
use Horde_Perms;
use Horde_Perms_Exception;
use Horde_Perms_Permission_Sql;
use Horde_Perms_Sql;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Db_Exception;

/**
 * Integration tests for Horde_Perms_Sql with PostgreSQL backend.
 *
 * Requires environment variables:
 * - PGSQL_HOST (default: localhost)
 * - PGSQL_DATABASE (default: horde_test)
 * - PGSQL_USER (default: postgres)
 * - PGSQL_PASSWORD (default: empty)
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
#[CoversClass(Horde_Perms_Sql::class)]
#[CoversClass(Horde_Perms_Permission_Sql::class)]
class SqlPgsqlTest extends SqlTestBase
{
    protected function setUp(): void
    {
        // Skip if PostgreSQL is not available
        try {
            parent::setUp();
        } catch (Horde_Db_Exception $e) {
            $this->markTestSkipped('PostgreSQL database not available: ' . $e->getMessage());
        }
    }

    protected function getDbAdapter(): Horde_Db_Adapter
    {
        $dsn = sprintf(
            'pgsql:host=%s;dbname=%s',
            getenv('PGSQL_HOST') ?: 'localhost',
            getenv('PGSQL_DATABASE') ?: 'horde_test'
        );

        return new Horde_Db_Adapter_Pdo_Pgsql([
            'dsn' => $dsn,
            'username' => getenv('PGSQL_USER') ?: 'postgres',
            'password' => getenv('PGSQL_PASSWORD') ?: '',
        ]);
    }

    protected function getDbType(): string
    {
        return 'pgsql';
    }
    public function testNewPermissionCreatesPermissionObject(): void
    {
        $perm = $this->perms->newPermission('testresource');

        $this->assertInstanceOf(Horde_Perms_Permission_Sql::class, $perm);
        $this->assertEquals('testresource', $perm->getName());
        $this->assertEquals('matrix', $perm->get('type'));
    }

    public function testNewPermissionCanSetCustomType(): void
    {
        $perm = $this->perms->newPermission('test', 'custom', ['key' => 'value']);

        $this->assertEquals('custom', $perm->get('type'));
        $this->assertEquals(['key' => 'value'], $perm->get('params'));
    }

    public function testAddPermissionInsertsIntoDatabase(): void
    {
        $perm = $this->perms->newPermission('testresource');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);

        $id = $this->perms->addPermission($perm);

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, $id);
        $this->assertPermissionExistsInDb('testresource');
    }

    public function testAddPermissionThrowsExceptionForEmptyName(): void
    {
        $perm = $this->perms->newPermission('');

        $this->expectException(Horde_Perms_Exception::class);
        $this->expectExceptionMessage('non-empty');
        $this->perms->addPermission($perm);
    }

    public function testAddPermissionSetsIdOnPermissionObject(): void
    {
        $perm = $this->perms->newPermission('testresource');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);

        $id = $this->perms->addPermission($perm);

        $this->assertEquals($id, $perm->getId());
    }

    public function testGetPermissionRetrievesFromDatabase(): void
    {
        $originalPerm = $this->perms->newPermission('testresource');
        $originalPerm->addUserPermission('alice', Horde_Perms::READ, false);
        $originalPerm->addDefaultPermission(Horde_Perms::SHOW, false);
        $this->perms->addPermission($originalPerm);

        $retrievedPerm = $this->perms->getPermission('testresource');

        $this->assertInstanceOf(Horde_Perms_Permission_Sql::class, $retrievedPerm);
        $this->assertEquals('testresource', $retrievedPerm->getName());
        $this->assertEquals(Horde_Perms::READ, $retrievedPerm->getUserPermissions()['alice']);
        $this->assertEquals(Horde_Perms::SHOW, $retrievedPerm->getDefaultPermissions());
    }

    public function testGetPermissionThrowsExceptionForNonExistent(): void
    {
        $this->expectException(Horde_Perms_Exception::class);
        $this->expectExceptionMessage('Does not exist');
        $this->perms->getPermission('nonexistent');
    }

    public function testGetPermissionByIdRetrievesPermission(): void
    {
        $perm = $this->perms->newPermission('testresource');
        $perm->addUserPermission('bob', Horde_Perms::EDIT, false);
        $id = $this->perms->addPermission($perm);

        $retrievedPerm = $this->perms->getPermissionById($id);

        $this->assertEquals('testresource', $retrievedPerm->getName());
        $this->assertEquals(Horde_Perms::EDIT, $retrievedPerm->getUserPermissions()['bob']);
    }

    public function testGetPermissionByIdThrowsExceptionForNonExistent(): void
    {
        $this->expectException(Horde_Perms_Exception::class);
        $this->expectExceptionMessage('Does not exist');
        $this->perms->getPermissionById(999999);
    }

    public function testGetPermissionByIdWithRootReturnsRootPermission(): void
    {
        $rootPerm = $this->perms->getPermissionById(Horde_Perms::ROOT);

        $this->assertInstanceOf(Horde_Perms_Permission_Sql::class, $rootPerm);
        $this->assertEquals(Horde_Perms::ROOT, $rootPerm->getName());
    }

    public function testExistsReturnsTrueForExistingPermission(): void
    {
        $perm = $this->perms->newPermission('testresource');
        $this->perms->addPermission($perm);

        $this->assertTrue($this->perms->exists('testresource'));
    }

    public function testExistsReturnsFalseForNonExistentPermission(): void
    {
        $this->assertFalse($this->perms->exists('nonexistent'));
    }

    public function testRemovePermissionDeletesFromDatabase(): void
    {
        $perm = $this->perms->newPermission('testresource');
        $this->perms->addPermission($perm);
        $this->assertPermissionExistsInDb('testresource');

        $result = $this->perms->removePermission($perm);

        $this->assertTrue($result);
        $this->assertPermissionNotExistsInDb('testresource');
    }

    public function testRemovePermissionWithForceRemovesChildren(): void
    {
        $parent = $this->perms->newPermission('app');
        $this->perms->addPermission($parent);

        $child1 = $this->perms->newPermission('app:calendar');
        $this->perms->addPermission($child1);

        $child2 = $this->perms->newPermission('app:mail');
        $this->perms->addPermission($child2);

        $this->assertEquals(3, $this->getPermissionCount());

        $result = $this->perms->removePermission($parent, true);

        $this->assertTrue($result);
        $this->assertPermissionNotExistsInDb('app');
        $this->assertPermissionNotExistsInDb('app:calendar');
        $this->assertPermissionNotExistsInDb('app:mail');
    }

    public function testGetPermissionIdReturnsId(): void
    {
        $perm = $this->perms->newPermission('testresource');
        $id = $this->perms->addPermission($perm);

        $retrievedId = $this->perms->getPermissionId($perm);

        $this->assertEquals($id, $retrievedId);
    }

    public function testGetPermissionIdReturnsRootForRootPermission(): void
    {
        $perm = $this->perms->newPermission(Horde_Perms::ROOT);

        $id = $this->perms->getPermissionId($perm);

        $this->assertEquals(Horde_Perms::ROOT, $id);
    }

    public function testGetParentReturnsRootForTopLevelPermission(): void
    {
        $perm = $this->perms->newPermission('app');
        $this->perms->addPermission($perm);

        $parentId = $this->perms->getParent('app');

        $this->assertEquals(Horde_Perms::ROOT, $parentId);
    }

    public function testGetParentReturnsParentIdForChildPermission(): void
    {
        $parent = $this->perms->newPermission('app');
        $parentId = $this->perms->addPermission($parent);

        $child = $this->perms->newPermission('app:calendar');
        $this->perms->addPermission($child);

        $retrievedParentId = $this->perms->getParent('app:calendar');

        $this->assertEquals($parentId, $retrievedParentId);
    }

    public function testGetParentsReturnsParentTree(): void
    {
        $p1 = $this->perms->newPermission('app');
        $p1Id = $this->perms->addPermission($p1);

        $p2 = $this->perms->newPermission('app:calendar');
        $p2Id = $this->perms->addPermission($p2);

        $p3 = $this->perms->newPermission('app:calendar:events');
        $this->perms->addPermission($p3);

        $parents = $this->perms->getParents('app:calendar:events');

        $this->assertIsArray($parents);
        // Parents should be in tree format with nested structure
        $this->assertNotEmpty($parents);
    }

    public function testGetParentsThrowsExceptionForNonExistent(): void
    {
        $this->expectException(Horde_Perms_Exception::class);
        $this->expectExceptionMessage('Does not exist');
        $this->perms->getParents('nonexistent');
    }

    public function testGetTreeReturnsAllPermissions(): void
    {
        $perm1 = $this->perms->newPermission('app');
        $id1 = $this->perms->addPermission($perm1);

        $perm2 = $this->perms->newPermission('app:calendar');
        $id2 = $this->perms->addPermission($perm2);

        $tree = $this->perms->getTree();

        $this->assertIsArray($tree);
        $this->assertArrayHasKey($id1, $tree);
        $this->assertArrayHasKey($id2, $tree);
        $this->assertArrayHasKey(Horde_Perms::ROOT, $tree);
        $this->assertEquals('app', $tree[$id1]);
        $this->assertEquals('app:calendar', $tree[$id2]);
        $this->assertEquals(Horde_Perms::ROOT, $tree[Horde_Perms::ROOT]);
    }

    public function testPermissionSavePersistsChanges(): void
    {
        $perm = $this->perms->newPermission('testresource');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $this->perms->addPermission($perm);

        // Modify and save
        $perm->addUserPermission('bob', Horde_Perms::EDIT, false);
        $perm->save();

        // Retrieve fresh and verify
        $retrieved = $this->perms->getPermission('testresource');
        $users = $retrieved->getUserPermissions();

        $this->assertArrayHasKey('alice', $users);
        $this->assertArrayHasKey('bob', $users);
        $this->assertEquals(Horde_Perms::READ, $users['alice']);
        $this->assertEquals(Horde_Perms::EDIT, $users['bob']);
    }

    public function testCachingBehaviorOnRepeatedRetrieval(): void
    {
        $perm = $this->perms->newPermission('testcache');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $this->perms->addPermission($perm);

        $first = $this->perms->getPermission('testcache');
        $second = $this->perms->getPermission('testcache');

        // Both should have same data
        $this->assertEquals($first->getName(), $second->getName());
        $this->assertEquals($first->getUserPermissions(), $second->getUserPermissions());
    }

    public function testHierarchicalPermissionStructure(): void
    {
        // Create nested structure
        $app = $this->perms->newPermission('myapp');
        $this->perms->addPermission($app);

        $calendar = $this->perms->newPermission('myapp:calendar');
        $this->perms->addPermission($calendar);

        $events = $this->perms->newPermission('myapp:calendar:events');
        $this->perms->addPermission($events);

        // Verify all exist
        $this->assertTrue($this->perms->exists('myapp'));
        $this->assertTrue($this->perms->exists('myapp:calendar'));
        $this->assertTrue($this->perms->exists('myapp:calendar:events'));

        // Verify parent relationships
        $this->assertEquals(Horde_Perms::ROOT, $this->perms->getParent('myapp'));
        $appId = $this->perms->getPermissionId($app);
        $this->assertEquals($appId, $this->perms->getParent('myapp:calendar'));
    }

    public function testAddPermissionFailsWhenParentDoesNotExist(): void
    {
        $child = $this->perms->newPermission('nonexistent:child');

        $this->expectException(Horde_Perms_Exception::class);
        $this->expectExceptionMessage('parent permission');
        $this->perms->addPermission($child);
    }

    public function testComplexPermissionDataPersistence(): void
    {
        $perm = $this->perms->newPermission('complex');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $perm->addUserPermission('bob', Horde_Perms::EDIT, false);
        $perm->addGroupPermission('admins', Horde_Perms::ALL, false);
        $perm->addGroupPermission('users', Horde_Perms::SHOW, false);
        $perm->addDefaultPermission(Horde_Perms::SHOW, false);
        $perm->addGuestPermission(Horde_Perms::SHOW, false);
        $perm->addCreatorPermission(Horde_Perms::ALL, false);

        $this->perms->addPermission($perm);

        $retrieved = $this->perms->getPermission('complex');

        $this->assertEquals(Horde_Perms::READ, $retrieved->getUserPermissions()['alice']);
        $this->assertEquals(Horde_Perms::EDIT, $retrieved->getUserPermissions()['bob']);
        $this->assertEquals(Horde_Perms::ALL, $retrieved->getGroupPermissions()['admins']);
        $this->assertEquals(Horde_Perms::SHOW, $retrieved->getGroupPermissions()['users']);
        $this->assertEquals(Horde_Perms::SHOW, $retrieved->getDefaultPermissions());
        $this->assertEquals(Horde_Perms::SHOW, $retrieved->getGuestPermissions());
        $this->assertEquals(Horde_Perms::ALL, $retrieved->getCreatorPermissions());
    }
}
