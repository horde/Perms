<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Perms\Test\Helper;

use Horde_Perms;
use InvalidArgumentException;

/**
 * Test data fixtures for SQL integration tests.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
class SqlFixtures
{
    /**
     * Returns basic permission data for testing.
     */
    public static function basicPermissionData(): array
    {
        return [
            'name' => 'test:resource',
            'users' => ['alice' => Horde_Perms::READ | Horde_Perms::EDIT],
            'groups' => ['admins' => Horde_Perms::ALL],
            'default' => Horde_Perms::SHOW,
        ];
    }

    /**
     * Returns hierarchical permission structure for testing.
     */
    public static function hierarchicalPermissions(): array
    {
        return [
            ['name' => 'app'],
            ['name' => 'app:calendar'],
            ['name' => 'app:calendar:events'],
            ['name' => 'app:calendar:tasks'],
            ['name' => 'app:mail'],
        ];
    }

    /**
     * Returns permission data with all permission types.
     */
    public static function complexPermissionData(): array
    {
        return [
            'name' => 'complex:test',
            'users' => [
                'alice' => Horde_Perms::READ,
                'bob' => Horde_Perms::READ | Horde_Perms::EDIT,
                'charlie' => Horde_Perms::ALL,
            ],
            'groups' => [
                'readers' => Horde_Perms::READ,
                'editors' => Horde_Perms::READ | Horde_Perms::EDIT,
                'admins' => Horde_Perms::ALL,
            ],
            'default' => Horde_Perms::SHOW,
            'guest' => null,
            'creator' => Horde_Perms::ALL,
        ];
    }

    /**
     * Returns SQL schema for horde_perms table (generic).
     */
    public static function getCreateTableSql(string $dbType = 'sqlite'): string
    {
        switch ($dbType) {
            case 'sqlite':
                return <<<SQL
                    CREATE TABLE horde_perms (
                        perm_id INTEGER PRIMARY KEY AUTOINCREMENT,
                        perm_name VARCHAR(255) UNIQUE NOT NULL,
                        perm_parents VARCHAR(255),
                        perm_data TEXT
                    )
                    SQL;

            case 'mysql':
                return <<<SQL
                    CREATE TABLE horde_perms (
                        perm_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        perm_name VARCHAR(255) NOT NULL,
                        perm_parents VARCHAR(255),
                        perm_data TEXT,
                        PRIMARY KEY (perm_id),
                        UNIQUE KEY perm_name_idx (perm_name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    SQL;

            case 'pgsql':
                return <<<SQL
                    CREATE TABLE horde_perms (
                        perm_id SERIAL PRIMARY KEY,
                        perm_name VARCHAR(255) UNIQUE NOT NULL,
                        perm_parents VARCHAR(255),
                        perm_data TEXT
                    )
                    SQL;

            default:
                throw new InvalidArgumentException("Unsupported database type: {$dbType}");
        }
    }

    /**
     * Returns SQL to drop horde_perms table.
     */
    public static function getDropTableSql(): string
    {
        return 'DROP TABLE IF EXISTS horde_perms';
    }
}
