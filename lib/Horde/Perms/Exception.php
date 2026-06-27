<?php

/**
 * Exception handler for the Horde_Perms package.
 *
 * Copyright 2009-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @package  Perms
 */
class Horde_Perms_Exception extends Horde_Exception_Wrapped
{
    public const NOT_EXIST = 1;

    /**
     * Constructor.
     *
     * @param mixed $message           The exception message, a PEAR_Error
     *                                 object, or an Exception object.
     * @param integer $code            A numeric error code.
     */
    public function __construct($message = 'Permission error', $code = 0)
    {
        parent::__construct($message, $code);
    }
}
