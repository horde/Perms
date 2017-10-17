<?php
/**
 * Copyright 2006-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Perms
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * Maps a single Kolab_Storage known user ACL element to the Horde permission
 * system.
 *
 * @category Horde
 * @package  Perms
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Perms_Permission_Kolab_Acl_Anyone
extends Horde_Perms_Permission_Kolab_Acl
{
    /**
     * Converts the ACL string to a Horde_Perms mask and stores it in the
     * provided data array.
     *
     * @param array &$data The horde permission data.
     */
    public function toHorde(array &$data)
    {
        $data['default'] = $this->convertAclToMask();
    }
}
