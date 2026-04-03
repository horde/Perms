<?php

declare(strict_types=1);

/**
 * Simple no-op cache implementation for tests.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Perms
 */

/**
 * Stub cache class for testing when Horde_Cache is not available.
 */
class Horde_Cache
{
    public function get($key, $lifetime = 0)
    {
        return false;
    }

    public function set($key, $data, $lifetime = 0): void
    {
    }

    public function expire($key): void
    {
    }
}
