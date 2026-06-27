<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Perms\Unit;

use Horde_Group_Base;
use Horde_Perms;
use Horde_Perms_Base;
use Horde_Perms_Exception;
use Horde_Perms_Permission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Perms_Base.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
#[CoversClass(Horde_Perms_Base::class)]
class BaseTest extends TestCase
{
    private Horde_Perms_Base $perms;

    protected function setUp(): void
    {
        // Create anonymous subclass to test abstract base class
        $this->perms = new class extends Horde_Perms_Base {
            public function newPermission($name, $type = 'matrix', $params = null)
            {
                return new Horde_Perms_Permission($name, null, $type, $params);
            }

            public function getPermission($name)
            {
                throw new Horde_Perms_Exception('Not implemented');
            }

            public function getPermissionById($cid)
            {
                throw new Horde_Perms_Exception('Not implemented');
            }

            public function addPermission(Horde_Perms_Permission $perm)
            {
                throw new Horde_Perms_Exception('Not implemented');
            }

            public function removePermission(Horde_Perms_Permission $perm, $force = false)
            {
                throw new Horde_Perms_Exception('Not implemented');
            }

            public function getPermissionId($permission)
            {
                return 1;
            }

            public function exists($permission)
            {
                return true;
            }

            public function getParents($child)
            {
                return [];
            }

            public function getTree()
            {
                return [];
            }
        };
    }

    public function testGetShortNameWithSingleComponent(): void
    {
        $shortName = $this->perms->getShortName('simple');

        $this->assertEquals('simple', $shortName);
    }

    public function testGetShortNameWithMultipleComponents(): void
    {
        $shortName = $this->perms->getShortName('app:calendar:events');

        $this->assertEquals('events', $shortName);
    }

    public function testGetShortNameWithTwoComponents(): void
    {
        $shortName = $this->perms->getShortName('app:calendar');

        $this->assertEquals('calendar', $shortName);
    }

    public function testGetPermissionsForGuestUserReturnsGuestPermissions(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addGuestPermission(Horde_Perms::SHOW, false);

        $result = $this->perms->getPermissions($perm, '');

        $this->assertEquals(Horde_Perms::SHOW, $result);
    }

    public function testGetPermissionsForGuestUserWithoutGuestPermsReturnsNull(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $result = $this->perms->getPermissions($perm, '');

        $this->assertNull($result);
    }

    public function testGetPermissionsForUserWithUserPermissions(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);

        $result = $this->perms->getPermissions($perm, 'alice');

        $this->assertEquals(Horde_Perms::READ, $result);
    }

    public function testGetPermissionsAccumulatesUserAndDefaultPermissions(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);
        $perm->addDefaultPermission(Horde_Perms::SHOW, false);

        $result = $this->perms->getPermissions($perm, 'alice');

        $this->assertEquals(Horde_Perms::READ | Horde_Perms::SHOW, $result);
    }

    public function testGetPermissionsForCreatorIncludesCreatorPermissions(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addCreatorPermission(Horde_Perms::ALL, false);

        $result = $this->perms->getPermissions($perm, 'alice', 'alice');

        $this->assertEquals(Horde_Perms::ALL, $result);
    }

    public function testGetPermissionsForCreatorDoesNotApplyToNonCreator(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addCreatorPermission(Horde_Perms::ALL, false);

        $result = $this->perms->getPermissions($perm, 'bob', 'alice');

        $this->assertFalse($result);
    }

    public function testGetPermissionsWithGroupPermissions(): void
    {
        // Mock the global injector for group access
        $groupMock = $this->createMock(Horde_Group_Base::class);
        $groupMock->method('listGroups')
            ->willReturn(['admins' => 'Administrators']);

        $injectorMock = new class ($groupMock) {
            private $groupMock;
            public function __construct($groupMock)
            {
                $this->groupMock = $groupMock;
            }
            public function getInstance($class)
            {
                return $this->groupMock;
            }
        };

        $GLOBALS['injector'] = $injectorMock;

        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('admins', Horde_Perms::EDIT, false);

        $result = $this->perms->getPermissions($perm, 'alice');

        unset($GLOBALS['injector']);

        $this->assertEquals(Horde_Perms::EDIT, $result);
    }

    public function testGetPermissionsAccumulatesMultipleGroupPermissions(): void
    {
        $groupMock = $this->createMock(Horde_Group_Base::class);
        $groupMock->method('listGroups')
            ->willReturn([
                'readers' => 'Readers',
                'editors' => 'Editors',
            ]);

        $injectorMock = new class ($groupMock) {
            private $groupMock;
            public function __construct($groupMock)
            {
                $this->groupMock = $groupMock;
            }
            public function getInstance($class)
            {
                return $this->groupMock;
            }
        };

        $GLOBALS['injector'] = $injectorMock;

        $perm = new Horde_Perms_Permission('test');
        $perm->addGroupPermission('readers', Horde_Perms::READ, false);
        $perm->addGroupPermission('editors', Horde_Perms::EDIT, false);

        $result = $this->perms->getPermissions($perm, 'alice');

        unset($GLOBALS['injector']);

        $this->assertEquals(Horde_Perms::READ | Horde_Perms::EDIT, $result);
    }

    public function testGetPermissionsReturnsFalseWhenNoPermissionsFound(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $result = $this->perms->getPermissions($perm, 'alice');

        $this->assertFalse($result);
    }

    public function testGetPermissionsCanAcceptPermissionName(): void
    {
        // Override getPermission to return a test permission
        $testPerm = new Horde_Perms_Permission('test');
        $testPerm->addUserPermission('alice', Horde_Perms::READ, false);

        $perms = new class ($testPerm) extends Horde_Perms_Base {
            private $testPerm;

            public function __construct($testPerm)
            {
                $this->testPerm = $testPerm;
                parent::__construct();
            }

            public function getPermission($name)
            {
                return $this->testPerm;
            }

            public function newPermission($name, $type = 'matrix', $params = null)
            {
                return new Horde_Perms_Permission($name, null, $type, $params);
            }

            public function getPermissionById($cid)
            {
                throw new Horde_Perms_Exception('Not implemented');
            }

            public function addPermission(Horde_Perms_Permission $perm)
            {
                throw new Horde_Perms_Exception('Not implemented');
            }

            public function removePermission(Horde_Perms_Permission $perm, $force = false)
            {
                throw new Horde_Perms_Exception('Not implemented');
            }

            public function getPermissionId($permission)
            {
                return 1;
            }

            public function exists($permission)
            {
                return true;
            }

            public function getParents($child)
            {
                return [];
            }

            public function getTree()
            {
                return [];
            }
        };

        $result = $perms->getPermissions('test', 'alice');

        $this->assertEquals(Horde_Perms::READ, $result);
    }

    public function testHasPermissionReturnsTrueWhenUserHasPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ | Horde_Perms::EDIT, false);

        $result = $this->perms->hasPermission($perm, 'alice', Horde_Perms::READ);

        $this->assertTrue($result);
    }

    public function testHasPermissionReturnsFalseWhenUserLacksPermission(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::READ, false);

        $result = $this->perms->hasPermission($perm, 'alice', Horde_Perms::DELETE);

        $this->assertFalse($result);
    }

    public function testHasPermissionReturnsFalseWhenNoPermissions(): void
    {
        $perm = new Horde_Perms_Permission('test');

        $result = $this->perms->hasPermission($perm, 'alice', Horde_Perms::READ);

        $this->assertFalse($result);
    }

    public function testHasPermissionChecksMultiplePermissionBits(): void
    {
        $perm = new Horde_Perms_Permission('test');
        $perm->addUserPermission('alice', Horde_Perms::ALL, false);

        $this->assertTrue($this->perms->hasPermission($perm, 'alice', Horde_Perms::SHOW));
        $this->assertTrue($this->perms->hasPermission($perm, 'alice', Horde_Perms::READ));
        $this->assertTrue($this->perms->hasPermission($perm, 'alice', Horde_Perms::EDIT));
        $this->assertTrue($this->perms->hasPermission($perm, 'alice', Horde_Perms::DELETE));
    }
}
