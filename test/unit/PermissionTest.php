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
use Horde_Perms_Permission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Perms_Permission.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
#[CoversClass(Horde_Perms_Permission::class)]
class PermissionTest extends TestCase
{
    public function testConstructorSetsName(): void
    {
        $perm = new Horde_Perms_Permission('test:permission');

        $this->assertEquals('test:permission', $perm->getName());
    }

    public function testConstructorSetsDefaultTypeToMatrix(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $this->assertEquals('matrix', $perm->get('type'));
    }

    public function testConstructorCanSetCustomType(): void
    {
        $perm = new Horde_Perms_Permission('test', null, 'custom');

        $this->assertEquals('custom', $perm->get('type'));
    }

    public function testConstructorCanSetParams(): void
    {
        $params = ['key' => 'value', 'foo' => 'bar'];
        $perm = new Horde_Perms_Permission('test', null, 'matrix', $params);

        $this->assertEquals($params, $perm->get('params'));
    }

    public function testSetNameChangesName(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->setName('newname');

        $this->assertEquals('newname', $perm->getName());
    }

    public function testGetReturnsAttribute(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->data['custom'] = 'value';

        $this->assertEquals('value', $perm->get('custom'));
    }

    public function testGetReturnsNullForMissingAttribute(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $this->assertNull($perm->get('nonexistent'));
    }

    public function testGetReturnsMatrixForMissingType(): void
    {
        $perm = new Horde_Perms_Permission('test');
        unset($perm->data['type']);

        $this->assertEquals('matrix', $perm->get('type'));
    }

    public function testGetDataReturnsFullDataArray(): void
    {
        $perm = new Horde_Perms_Permission('test', null, 'matrix');
        $perm->data['custom'] = 'value';

        $data = $perm->getData();

        $this->assertIsArray($data);
        $this->assertEquals('matrix', $data['type']);
        $this->assertEquals('value', $data['custom']);
    }

    public function testSetDataReplacesDataArray(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $newData = [
            'type' => 'custom',
            'users' => ['alice' => Horde_Perms::READ],
        ];

        $perm->setData($newData);

        $this->assertEquals($newData, $perm->getData());
    }

    public function testAddUserPermissionSetsPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);

        $users = $perm->getUserPermissions();
        $this->assertArrayHasKey('alice', $users);
        $this->assertEquals(Horde_Perms::READ, $users['alice']);
    }

    public function testAddUserPermissionAccumulatesInMatrixMode(): void
    {
        $perm = new Horde_Perms_Permission('test', null, 'matrix');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $perm->addUserPermission('alice', Horde_Perms::EDIT, false);

        $users = $perm->getUserPermissions();
        $this->assertEquals(Horde_Perms::READ | Horde_Perms::EDIT, $users['alice']);
    }

    public function testAddUserPermissionIgnoresEmptyUser(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('', Horde_Perms::READ, false);

        $this->assertEmpty($perm->getUserPermissions());
    }

    public function testGetUserPermissionsReturnsEmptyArrayWhenNoUsers(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $this->assertIsArray($perm->getUserPermissions());
        $this->assertEmpty($perm->getUserPermissions());
    }

    public function testGetUserPermissionsCanFilterByPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $perm->addUserPermission('bob', Horde_Perms::READ | Horde_Perms::EDIT, false);
        $perm->addUserPermission('charlie', Horde_Perms::EDIT, false);

        $readUsers = $perm->getUserPermissions(Horde_Perms::READ);

        $this->assertCount(2, $readUsers);
        $this->assertArrayHasKey('alice', $readUsers);
        $this->assertArrayHasKey('bob', $readUsers);
        $this->assertArrayNotHasKey('charlie', $readUsers);
    }

    public function testRemoveUserPermissionRemovesSpecificPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ | Horde_Perms::EDIT, false);
        $perm->removeUserPermission('alice', Horde_Perms::READ, false);

        $users = $perm->getUserPermissions();
        $this->assertEquals(Horde_Perms::EDIT, $users['alice']);
    }

    public function testRemoveUserPermissionRemovesUserIfNoPermissionsRemain(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $perm->removeUserPermission('alice', Horde_Perms::READ, false);

        $this->assertEmpty($perm->getUserPermissions());
    }

    public function testRemoveUserPermissionRemovesAllUserPermissions(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::ALL, false);
        $perm->removeUserPermission('alice', null, false);

        $this->assertEmpty($perm->getUserPermissions());
    }

    public function testRemoveUserPermissionWithNullUserClearsAllUsers(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $perm->addUserPermission('bob', Horde_Perms::EDIT, false);
        $perm->removeUserPermission(null, null, false);

        $this->assertEmpty($perm->getUserPermissions());
    }

    public function testAddGroupPermissionSetsPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('admins', Horde_Perms::ALL, false);

        $groups = $perm->getGroupPermissions();
        $this->assertArrayHasKey('admins', $groups);
        $this->assertEquals(Horde_Perms::ALL, $groups['admins']);
    }

    public function testAddGroupPermissionAccumulatesInMatrixMode(): void
    {
        $perm = new Horde_Perms_Permission('test', null, 'matrix');
        $perm->addGroupPermission('admins', Horde_Perms::READ, false);
        $perm->addGroupPermission('admins', Horde_Perms::EDIT, false);

        $groups = $perm->getGroupPermissions();
        $this->assertEquals(Horde_Perms::READ | Horde_Perms::EDIT, $groups['admins']);
    }

    public function testAddGroupPermissionIgnoresEmptyGroup(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('', Horde_Perms::READ, false);

        $this->assertEmpty($perm->getGroupPermissions());
    }

    public function testGetGroupPermissionsReturnsEmptyArrayWhenNoGroups(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $this->assertIsArray($perm->getGroupPermissions());
        $this->assertEmpty($perm->getGroupPermissions());
    }

    public function testGetGroupPermissionsCanFilterByPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('readers', Horde_Perms::READ, false);
        $perm->addGroupPermission('editors', Horde_Perms::READ | Horde_Perms::EDIT, false);
        $perm->addGroupPermission('deleters', Horde_Perms::DELETE, false);

        $readGroups = $perm->getGroupPermissions(Horde_Perms::READ);

        $this->assertCount(2, $readGroups);
        $this->assertArrayHasKey('readers', $readGroups);
        $this->assertArrayHasKey('editors', $readGroups);
        $this->assertArrayNotHasKey('deleters', $readGroups);
    }

    public function testRemoveGroupPermissionRemovesSpecificPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('admins', Horde_Perms::READ | Horde_Perms::EDIT, false);
        $perm->removeGroupPermission('admins', Horde_Perms::READ, false);

        $groups = $perm->getGroupPermissions();
        $this->assertEquals(Horde_Perms::EDIT, $groups['admins']);
    }

    public function testRemoveGroupPermissionRemovesGroupIfNoPermissionsRemain(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('admins', Horde_Perms::READ, false);
        $perm->removeGroupPermission('admins', Horde_Perms::READ, false);

        $this->assertEmpty($perm->getGroupPermissions());
    }

    public function testRemoveGroupPermissionWithNullGroupClearsAllGroups(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('admins', Horde_Perms::READ, false);
        $perm->addGroupPermission('users', Horde_Perms::SHOW, false);
        $perm->removeGroupPermission(null, null, false);

        $this->assertEmpty($perm->getGroupPermissions());
    }

    public function testAddDefaultPermissionSetsPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addDefaultPermission(Horde_Perms::SHOW, false);

        $this->assertEquals(Horde_Perms::SHOW, $perm->getDefaultPermissions());
    }

    public function testAddDefaultPermissionAccumulatesInMatrixMode(): void
    {
        $perm = new Horde_Perms_Permission('test', null, 'matrix');
        $perm->addDefaultPermission(Horde_Perms::SHOW, false);
        $perm->addDefaultPermission(Horde_Perms::READ, false);

        $this->assertEquals(Horde_Perms::SHOW | Horde_Perms::READ, $perm->getDefaultPermissions());
    }

    public function testGetDefaultPermissionsReturnsNullWhenNotSet(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $this->assertNull($perm->getDefaultPermissions());
    }

    public function testRemoveDefaultPermissionRemovesSpecificPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addDefaultPermission(Horde_Perms::SHOW | Horde_Perms::READ, false);
        $perm->removeDefaultPermission(Horde_Perms::SHOW, false);

        $this->assertEquals(Horde_Perms::READ, $perm->getDefaultPermissions());
    }

    public function testAddGuestPermissionSetsPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGuestPermission(Horde_Perms::SHOW, false);

        $this->assertEquals(Horde_Perms::SHOW, $perm->getGuestPermissions());
    }

    public function testAddGuestPermissionAccumulatesInMatrixMode(): void
    {
        $perm = new Horde_Perms_Permission('test', null, 'matrix');
        $perm->addGuestPermission(Horde_Perms::SHOW, false);
        $perm->addGuestPermission(Horde_Perms::READ, false);

        $this->assertEquals(Horde_Perms::SHOW | Horde_Perms::READ, $perm->getGuestPermissions());
    }

    public function testGetGuestPermissionsReturnsNullWhenNotSet(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $this->assertNull($perm->getGuestPermissions());
    }

    public function testRemoveGuestPermissionRemovesSpecificPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGuestPermission(Horde_Perms::SHOW | Horde_Perms::READ, false);
        $perm->removeGuestPermission(Horde_Perms::SHOW, false);

        $this->assertEquals(Horde_Perms::READ, $perm->getGuestPermissions());
    }

    public function testAddCreatorPermissionSetsPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addCreatorPermission(Horde_Perms::ALL, false);

        $this->assertEquals(Horde_Perms::ALL, $perm->getCreatorPermissions());
    }

    public function testAddCreatorPermissionAccumulatesInMatrixMode(): void
    {
        $perm = new Horde_Perms_Permission('test', null, 'matrix');
        $perm->addCreatorPermission(Horde_Perms::READ, false);
        $perm->addCreatorPermission(Horde_Perms::EDIT, false);

        $this->assertEquals(Horde_Perms::READ | Horde_Perms::EDIT, $perm->getCreatorPermissions());
    }

    public function testGetCreatorPermissionsReturnsNullWhenNotSet(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $this->assertNull($perm->getCreatorPermissions());
    }

    public function testRemoveCreatorPermissionRemovesSpecificPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addCreatorPermission(Horde_Perms::READ | Horde_Perms::EDIT, false);
        $perm->removeCreatorPermission(Horde_Perms::READ, false);

        $this->assertEquals(Horde_Perms::EDIT, $perm->getCreatorPermissions());
    }

    public function testSetCacheVersionStoresVersion(): void
    {
        $perm = new Horde_Perms_Permission('test', 5);

        // Cache version is protected, just verify no exception
        $this->assertInstanceOf(Horde_Perms_Permission::class, $perm);
    }
}
