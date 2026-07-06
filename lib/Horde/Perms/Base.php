<?php

/**
 * The Horde_Perms_Base class provides the Horde permissions system.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @package  Perms
 * @todo     Remove $GLOBALS['injector'] fallback in _getGroupsForUser()
 *           for Horde 7 once all instantiation sites pass 'group' explicitly.
 */
abstract class Horde_Perms_Base
{
    /**
     * Cache object.
     *
     * @var Horde_Cache
     */
    protected $_cache;

    /**
     * Group backend.
     *
     * Preferred injection point. Pass 'group' in the constructor $params.
     * When null the resolver falls back to
     * $GLOBALS['injector']->getInstance('Horde_Group'), and finally to an
     * empty groups list if that also fails. See _getGroupsForUser().
     *
     * @var Horde_Group|null
     */
    protected $_group;

    /**
     * Logger.
     *
     * Both PSR-3 and the legacy Horde_Log_Logger shape are accepted. The
     * single call site in this class branches on the concrete type. This
     * is a pre-step to a broader PSR-4 overhaul; once the legacy backend is
     * gone the union collapses to Psr\Log\LoggerInterface.
     *
     * @var Psr\Log\LoggerInterface|Horde_Log_Logger|null
     */
    protected $_logger;

    /**
     * Constructor.
     *
     * @param array $params  Configuration parameters:
     * <pre>
     * 'cache' - (Horde_Cache) The object to use to cache perms.
     * 'group' - (Horde_Group) A group backend used to resolve which groups
     *           a user belongs to during permission evaluation. Optional;
     *           when omitted, resolution falls back to the legacy
     *           $GLOBALS['injector']->getInstance('Horde_Group') lookup,
     *           and finally to "user has no groups" if the injector is
     *           unavailable.
     * 'logger' - (Psr\Log\LoggerInterface|Horde_Log_Logger) A logger object.
     *            Both PSR-3 and the legacy Horde_Log_Logger shape are
     *            accepted; the call site branches on the concrete type.
     * </pre>
     *
     * @throws Horde_Perms_Exception
     */
    public function __construct($params = [])
    {
        if (isset($params['cache'])) {
            $this->_cache = $params['cache'];
        }

        if (isset($params['group'])) {
            $this->_group = $params['group'];
        }

        if (isset($params['logger'])) {
            $this->_logger = $params['logger'];
        }
    }

    /**
     * Returns the short name of an object, the last portion of the full name.
     *
     * @param string $name  The name of the object.
     *
     * @return string  The object's short name.
     */
    public function getShortName($name)
    {
        /* If there are several components to the name, explode and
         * get the last one, otherwise just return the name. */
        if (strpos($name, ':') !== false) {
            $tmp = explode(':', $name);
            return array_pop($tmp);
        }

        return $name;
    }

    /**
     * Returns a new permissions object.
     *
     * @param string $name   The permission's name.
     * @param string $type   The permission type.
     * @param array $params  The permission parameters.
     *
     * @return Horde_Perms_Permission  A new permissions object.
     * @throws Horde_Perms_Exception
     */
    abstract public function newPermission($name, $type = 'matrix', $params = null);

    /**
     * Returns an object corresponding to the named permission, with the users
     * and other data retrieved appropriately.
     *
     * @param string $name  The name of the permission to retrieve.
     *
     * @return Horde_Perms_Permission  A permissions object.
     * @throws Horde_Perms_Exception
     */
    abstract public function getPermission($name);

    /**
     * Returns an object corresponding to the given unique ID, with the users
     * and other data retrieved appropriately.
     *
     * @param integer $cid  The unique ID of the permission to retrieve.
     *
     * @return Horde_Perms_Permission  A permissions object.
     * @throws Horde_Perms_Exception
     */
    abstract public function getPermissionById($cid);

    /**
     * Adds a permission to the permissions system. The permission must first
     * be created with newPermission(), and have any initial users added to
     * it, before this function is called.
     *
     * @param Horde_Perms_Permission $perm  The permissions object.
     *
     * @throws Horde_Perms_Exception
     */
    abstract public function addPermission(Horde_Perms_Permission $perm);

    /**
     * Removes a permission from the permissions system permanently.
     *
     * @param Horde_Perms_Permission $perm  The permission to remove.
     * @param boolean $force                Force to remove every child.
     *
     * @throws Horde_Perms_Exception
     */
    abstract public function removePermission(
        Horde_Perms_Permission $perm,
        $force = false
    );

    /**
     * Finds out what rights the given user has to this object.
     *
     * Grants and denies compose in a specificity-ordered cascade from broad
     * to narrow: default (ALL AUTHENTICATED), then group membership, then
     * creator, then user. At each step grants collect onto the running
     * effective mask and denies subtract from it. A more specific grant
     * can restore a bit that a less specific deny removed. A more specific
     * deny can remove a bit that a less specific grant added.
     *
     * Guest users are resolved through a disjunct branch. The deny cascade
     * only applies to authenticated users. Non-matrix, non-boolean types
     * (numeric permissions) do not support denies at the data layer, so
     * their resolution is grant-only and keeps its original array shape.
     *
     * @param mixed $permission  The full permission name of the object to
     *                           check the permissions of, or the
     *                           Horde_Permissions object.
     * @param string $user       The user to check for.
     * @param string $creator    The user who created the event.
     *
     * @return mixed  A bitmask of permissions the user has, false if there
     *                are none.
     * @todo For a future major version, collapse the return type. Matrix
     *       should return int (with 0 meaning "no bits"). Non-matrix should
     *       return [] instead of false. Callers currently juggle int|false
     *       because zero-mask outcomes were unreachable under the old
     *       grant-only OR-fold. The deny cascade makes them reachable.
     */
    public function getPermissions($permission, $user, $creator = null)
    {
        if (is_string($permission)) {
            try {
                $permission = $this->getPermission($permission);
            } catch (Horde_Perms_Exception $e) {
                /* Ignore not exists errors. */
                if ($this->_logger
                    && ($e->getCode() != Horde_Perms_Exception::NOT_EXIST)) {
                    if ($this->_logger instanceof \Psr\Log\LoggerInterface) {
                        // PSR-3 idiomatic: message-first, exception in
                        // structured context so handlers can inspect it.
                        $this->_logger->debug(
                            $e->getMessage(),
                            ['exception' => $e]
                        );
                    } else {
                        // Legacy Horde_Log_Logger. Its log() reads $level as
                        // the integer level constant, not the level name —
                        // passing the string 'DEBUG' would raise
                        // "Bad log level" here. Use the constant.
                        $this->_logger->log((string) $e, Horde_Log::DEBUG);
                    }
                }
                return false;
            }
        }

        // Guest branch is disjunct. No cascade, no denies.
        if (empty($user)) {
            return $permission->getGuestPermissions();
        }

        $type = $permission->get('type');

        if ($type == 'matrix') {
            return $this->_composeMatrix($permission, $user, $creator);
        }
        if ($type == 'boolean') {
            return $this->_composeBoolean($permission, $user, $creator);
        }
        return $this->_composeOther($permission, $user, $creator);
    }

    /**
     * Matrix-type resolution. Specificity cascade with bitmask math.
     *
     * At each scope the grant OR-folds into $effective and the deny
     * AND-NOTs out of it, in the order default -> group -> creator -> user.
     * That ordering means a user-scope grant can restore a bit denied at
     * group scope, and a user-scope deny beats every less specific grant.
     *
     * Groups: intra-scope grants collect across all of the user's groups
     * first, then the intra-scope denies subtract. So if Alice is in
     * "sales" (grants READ) and "contractors" (denies READ) the group
     * step's contribution is zero. Only a user-scope grant can restore it.
     *
     * @return int|false  A bitmask, or false when zero bits remain.
     *                    @todo see class-level note on collapsing this.
     */
    private function _composeMatrix($permission, $user, $creator)
    {
        $effective = 0;

        // Default (ALL AUTHENTICATED). Baseline for every logged-in user.
        if (($g = $permission->getDefaultPermissions()) !== null) {
            $effective |= $g;
        }
        if (($d = $permission->getDefaultDenies()) !== null) {
            $effective &= ~$d;
        }

        // Group. Collect across every group the user is a member of.
        // _getGroupsForUser() returns [] when no backend is reachable,
        // so no explicit "skip" branch is needed here.
        $groups = $this->_getGroupsForUser($user);
        $groupGrants = 0;
        foreach ($permission->getGroupPermissions() as $g => $p) {
            if (isset($groups[$g])) {
                $groupGrants |= $p;
            }
        }
        $groupDenies = 0;
        foreach ($permission->getGroupDenies() as $g => $p) {
            if (isset($groups[$g])) {
                $groupDenies |= $p;
            }
        }
        $effective = ($effective | $groupGrants) & ~$groupDenies;

        // Creator. Only when the effective user IS the creator.
        if (!is_null($creator) && strlen($user) && ($user === $creator)) {
            if (($g = $permission->getCreatorPermissions()) !== null) {
                $effective |= $g;
            }
            if (($d = $permission->getCreatorDenies()) !== null) {
                $effective &= ~$d;
            }
        }

        // User. The most specific scope, final override.
        $userGrants = $permission->getUserPermissions();
        if (isset($userGrants[$user])) {
            $effective |= $userGrants[$user];
        }
        $userDenies = $permission->getUserDenies();
        if (isset($userDenies[$user])) {
            $effective &= ~$userDenies[$user];
        }

        // @todo collapse to plain "return $effective" once the return-type
        //       cleanup lands. Keep the false compat for now.
        return $effective ?: false;
    }

    /**
     * Boolean-type resolution. Same specificity cascade, no bitmask math.
     *
     * At each scope: a truthy grant sets $granted to true. A truthy deny
     * sets it back to false. Later (more specific) scopes override.
     *
     * @return bool  Whether the user is granted this boolean permission.
     */
    private function _composeBoolean($permission, $user, $creator)
    {
        $granted = false;

        if (!empty($permission->data['default'])) {
            $granted = true;
        }
        if (!empty($permission->data['default_deny'])) {
            $granted = false;
        }

        $groups = $this->_getGroupsForUser($user);
        $groupGrant = false;
        $groupDeny = false;
        if (isset($permission->data['groups']) && is_array($permission->data['groups'])) {
            foreach ($permission->data['groups'] as $g => $p) {
                if (isset($groups[$g]) && !empty($p)) {
                    $groupGrant = true;
                }
            }
        }
        if (isset($permission->data['groups_deny']) && is_array($permission->data['groups_deny'])) {
            foreach ($permission->data['groups_deny'] as $g => $p) {
                if (isset($groups[$g]) && !empty($p)) {
                    $groupDeny = true;
                }
            }
        }
        if ($groupGrant) {
            $granted = true;
        }
        if ($groupDeny) {
            $granted = false;
        }

        if (!is_null($creator) && strlen($user) && ($user === $creator)) {
            if (!empty($permission->data['creator'])) {
                $granted = true;
            }
            if (!empty($permission->data['creator_deny'])) {
                $granted = false;
            }
        }

        $userGrants = $permission->getUserPermissions();
        if (!empty($userGrants[$user])) {
            $granted = true;
        }
        $userDenies = $permission->getUserDenies();
        if (!empty($userDenies[$user])) {
            $granted = false;
        }

        return $granted;
    }

    /**
     * Non-matrix, non-boolean resolution. Grant-only, original array shape.
     *
     * Preserved verbatim from the pre-cascade algorithm: collect every
     * matching scope's value into a list, in the historic order
     * creator -> user -> group -> default. Callers of hasPermission() only
     * see whether the list is empty.
     *
     * The deny data layer refuses to write denies on these types (raises
     * \Horde\Exception\HordeLogicException), so no deny data can exist here
     * and the pre-cascade behavior is unchanged.
     *
     * @return array|false
     */
    private function _composeOther($permission, $user, $creator)
    {
        $composite_perm = [];

        if (!is_null($creator)
            && strlen($user)
            && ($user === $creator)
            && (($perms = $permission->getCreatorPermissions()) !== null)) {
            $composite_perm[] = $perms;
        }

        $userperms = $permission->getUserPermissions();
        if (isset($userperms[$user])) {
            $composite_perm[] = $userperms[$user];
        }

        if (isset($permission->data['groups'])
            && is_array($permission->data['groups'])
            && count($permission->data['groups'])) {
            $groups = $this->_getGroupsForUser($user);
            foreach ($permission->data['groups'] as $group => $perms) {
                if (isset($groups[$group])) {
                    $composite_perm[] = $perms;
                }
            }
        }

        if (($perms = $permission->getDefaultPermissions()) !== null) {
            $composite_perm[] = $perms;
        }

        // @todo collapse to plain "return $composite_perm" once the
        //       return-type cleanup lands. Keep the false compat for now.
        return $composite_perm ?: false;
    }

    /**
     * Fetches the group memberships for a user.
     *
     * Three-tier fallback:
     *   1. Group backend passed via the 'group' constructor param.
     *   2. Legacy $GLOBALS['injector']->getInstance('Horde_Group') lookup.
     *      Kept for compatibility with older instantiation sites.
     *   3. An empty list, meaning "user has no groups". A missing group
     *      backend must not deny the whole permission lookup. It just
     *      means the group step of the cascade contributes nothing.
     *
     * @return array  Group id => group name hash. Empty when no backend
     *                is reachable.
     */
    private function _getGroupsForUser($user)
    {
        $group = $this->_group;
        if ($group === null && isset($GLOBALS['injector'])) {
            try {
                $group = $GLOBALS['injector']->getInstance('Horde_Group');
            } catch (\Throwable $e) {
                // Injector present but no Horde_Group binding. Treat as
                // no groups. The empty return makes the group step of the
                // cascade a no-op rather than blocking resolution.
                return [];
            }
        }
        if ($group === null) {
            return [];
        }
        try {
            return $group->listGroups($user);
        } catch (\Throwable $e) {
            // Group backend blew up mid-lookup. Same policy as above:
            // treat as no groups rather than fail the permission check.
            return [];
        }
    }

    /**
     * Returns the unique identifier of this permission.
     *
     * @param Horde_Perms_Permission $permission  The permission object to get
     *                                            the ID of.
     *
     * @return integer  The unique id.
     * @throws Horde_Perms_Exception
     */
    abstract public function getPermissionId($permission);

    /**
     * Finds out if the user has the specified rights to the given object.
     *
     * @param string $permission  The permission to check.
     * @param string $user        The user to check for.
     * @param integer $perm       The permission level that needs to be checked
     *                            for.
     * @param string $creator     The creator of the event
     *
     * @return boolean  Whether the user has the specified permissions.
     */
    public function hasPermission($permission, $user, $perm, $creator = null)
    {
        $perms = $this->getPermissions($permission, $user, $creator);
        if (is_array($perms)) {
            $perms = $perms ? 1 : 0;
        }
        return (bool) ($perms & $perm);
    }

    /**
     * Checks if a permission exists in the system.
     *
     * @param string $permission  The permission to check.
     *
     * @return boolean  True if the permission exists.
     */
    abstract public function exists($permission);

    /**
     * Returns a list of parent permissions.
     *
     * @param string $child  The name of the child to retrieve parents for.
     *
     * @return array  A hash with all parents in a tree format.
     * @throws Horde_Perms_Exception
     */
    abstract public function getParents($child);

    /**
     * Returns all permissions of the system in a tree format.
     *
     * @return array  A hash with all permissions in a tree format.
     */
    abstract public function getTree();
}
