<?php

declare(strict_types=1);

namespace Horde\Perms\Test\Unnamespaced;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

use Horde\Perms\Test\Helper\SqlFixtures;

// Load cache stub if Horde_Cache is not available
if (!class_exists('Horde_Cache')) {
    require_once __DIR__ . '/../Helper/CacheStub.php';
}

use Horde_Cache;
use Horde_Db_Adapter;
use Horde_Perms_Sql;
use PHPUnit\Framework\TestCase;

/**
 * Base class for SQL integration tests.
 *
 * Note: This class is intentionally NOT namespaced as it's in test/Unnamespaced/
 * to be loaded via regular class autoloading for PHPUnit base classes.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
abstract class SqlTestBase extends TestCase
{
    protected Horde_Db_Adapter $db;
    protected Horde_Perms_Sql $perms;
    protected string $dbType;

    /**
     * Get database adapter for specific database type.
     */
    abstract protected function getDbAdapter(): Horde_Db_Adapter;

    /**
     * Get database type identifier.
     */
    abstract protected function getDbType(): string;

    protected function setUp(): void
    {
        // Set up global conf array for cache lifetime
        $GLOBALS['conf']['cache']['default_lifetime'] = 86400;

        $this->db = $this->getDbAdapter();
        $this->dbType = $this->getDbType();
        $this->createTables();

        $this->perms = new Horde_Perms_Sql([
            'db' => $this->db,
            'cache' => new Horde_Cache(),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->dropTables();
        }
    }

    /**
     * Create horde_perms table.
     */
    protected function createTables(): void
    {
        $sql = SqlFixtures::getCreateTableSql($this->dbType);
        $this->db->execute($sql);
    }

    /**
     * Drop horde_perms table.
     */
    protected function dropTables(): void
    {
        $sql = SqlFixtures::getDropTableSql();
        $this->db->execute($sql);
    }

    /**
     * Get count of rows in horde_perms table.
     */
    protected function getPermissionCount(): int
    {
        return (int) $this->db->selectValue('SELECT COUNT(*) FROM horde_perms');
    }

    /**
     * Verify permission exists in database.
     */
    protected function assertPermissionExistsInDb(string $name): void
    {
        $count = $this->db->selectValue(
            'SELECT COUNT(*) FROM horde_perms WHERE perm_name = ?',
            [$name]
        );
        $this->assertEquals(1, $count, "Permission '{$name}' should exist in database");
    }

    /**
     * Verify permission does not exist in database.
     */
    protected function assertPermissionNotExistsInDb(string $name): void
    {
        $count = $this->db->selectValue(
            'SELECT COUNT(*) FROM horde_perms WHERE perm_name = ?',
            [$name]
        );
        $this->assertEquals(0, $count, "Permission '{$name}' should not exist in database");
    }
}
