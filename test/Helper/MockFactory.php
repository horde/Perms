<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Perms\Test\Helper;

use Horde_Cache_Null;
use Horde_Db_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * Factory for creating test doubles (mocks/stubs).
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */
class MockFactory
{
    /**
     * Creates a cache stub (no expectations).
     */
    public static function createCacheStub(TestCase $testCase): object
    {
        return $testCase->createStub(Horde_Cache_Null::class);
    }

    /**
     * Creates a database adapter mock (can set expectations).
     */
    public static function createDbMock(TestCase $testCase): object
    {
        return $testCase->createMock(Horde_Db_Adapter::class);
    }

    /**
     * Creates a null cache implementation (real object, no-op).
     */
    public static function createNullCache(): Horde_Cache_Null
    {
        return new Horde_Cache_Null();
    }
}
