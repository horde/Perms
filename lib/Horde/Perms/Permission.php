<?php

/**
 * Instance of a single permissioning object.
 *
 * Copyright 2009-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @package  Perms
 */
class Horde_Perms_Permission
{
    /**
     * TODO
     */
    public $data;

    /**
     * TODO
     */
    public $name;

    /**
     * Incrementing version number if cached classes change.
     *
     * @var integer
     */
    protected $_cacheVersion;

    /**
     * Constructor.
     *
     * @param string $name           The name of the perm.
     * @param integer $cacheVersion  The revision number of the class.
     * @param string $type           The permission type.
     * @param array $params          A hash with any parameters that the
     *                               permission type needs.
     */
    public function __construct(
        $name,
        $cacheVersion = null,
        $type = 'matrix',
        $params = null
    ) {
        $this->setName($name);
        $this->setCacheVersion($cacheVersion);
        $this->data['type'] = $type;
        if (is_array($params)) {
            $this->data['params'] = $params;
        }
    }

    /**
     * Sets the revision number of the class.
     *
     * @param integer $cacheVersion  The revision number of the class.
     */
    public function setCacheVersion($cacheVersion)
    {
        $this->_cacheVersion = $cacheVersion;
    }

    /**
     * Gets one of the attributes of the object, or null if it isn't defined.
     *
     * @param string $attribute  The attribute to get.
     *
     * @return mixed  The value of the attribute, or null.
     */
    public function get($attribute)
    {
        if (isset($this->data[$attribute])) {
            return $this->data[$attribute];
        }

        return ($attribute == 'type') ? 'matrix' : null;
    }

    /**
     * Get permission name.
     *
     * @return string  Permission name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set permission name
     *
     * @param string $name  Permission name.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get permission details.
     *
     * @return array  Permission details.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set permission details.
     *
     * @param string $data  Permission details.
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Updates the permissions based on data passed in the array.
     *
     * @param array $perms  An array containing the permissions which are to
     *                      be updated. Recognised keys:
     *                      'default', 'guest', 'creator', 'u', 'g' — grants,
     *                      as before. 'default_deny', 'creator_deny',
     *                      'u_deny', 'g_deny' — denies, matching the grant
     *                      keys pair-wise. Guest has no deny counterpart;
     *                      guest resolution is disjunct from every other
     *                      scope. Deny keys are only meaningful for 'matrix'
     *                      and 'boolean' typed permissions; setting a deny
     *                      on any other type raises \Horde\Exception\HordeLogicException.
     */
    public function updatePermissions($perms)
    {
        $type = $this->get('type');

        if ($type == 'matrix') {
            /* Array of permission types to iterate through. */
            $perm_types = Horde_Perms::getPermsArray();
        }

        foreach ($perms as $perm_class => $perm_values) {
            switch ($perm_class) {
                case 'default':
                case 'guest':
                case 'creator':
                    if ($type == 'matrix') {
                        foreach ($perm_types as $val => $label) {
                            if (!empty($perm_values[$val])) {
                                $this->setPerm($perm_class, $val, false);
                            } else {
                                $this->unsetPerm($perm_class, $val, false);
                            }
                        }
                    } elseif (!empty($perm_values)) {
                        $this->setPerm($perm_class, $perm_values, false);
                    } else {
                        $this->unsetPerm($perm_class, null, false);
                    }
                    break;

                case 'default_deny':
                case 'creator_deny':
                    // Guest has no deny counterpart on purpose — guests are
                    // resolved through the disjunct guest branch and never
                    // reach the deny cascade. If the caller sends
                    // 'guest_deny' it silently falls through the switch.
                    $this->_setScopeDenies($perm_class, $perm_values, $type, $perm_types ?? []);
                    break;

                case 'u':
                case 'g':
                    $permId = ['class' => $perm_class == 'u' ? 'users' : 'groups'];
                    /* Figure out what names that are stored in this permission
                     * class have not been submitted for an update, ie. have been
                     * removed entirely. */
                    $current_names = isset($this->data[$permId['class']])
                        ? array_keys($this->data[$permId['class']])
                        : [];
                    $updated_names = array_keys($perm_values);
                    $removed_names = array_diff($current_names, $updated_names);

                    /* Remove any names that have been completely unset. */
                    foreach ($removed_names as $name) {
                        unset($this->data[$permId['class']][$name]);
                    }

                    /* If nothing to actually update finish with this case. */
                    if (is_null($perm_values)) {
                        break;
                    }

                    /* Loop through the names and update permissions for each. */
                    // @todo for Horde 7 - allow integer 0 values?
                    foreach ($perm_values as $name => $name_values) {
                        $permId['name'] = $name;

                        if ($type == 'matrix') {
                            foreach ($perm_types as $val => $label) {
                                // Need to shield against $val key not set at all
                                if (isset($name_values[$val]) && ($name_values[$val] === '0' || !empty($name_values[$val]))) {
                                    $this->setPerm($permId, $val, false);
                                } else {
                                    $this->unsetPerm($permId, $val, false);
                                }
                            }
                        } elseif ($name_values === '0' || !empty($name_values)) {
                            $this->setPerm($permId, $name_values, false);
                        } else {
                            $this->unsetPerm($permId, null, false);
                        }
                    }
                    break;

                case 'u_deny':
                case 'g_deny':
                    $this->_setPerNameDenies($perm_class, $perm_values, $type, $perm_types ?? []);
                    break;
            }
        }
    }

    /**
     * Applies the payload for the 'default_deny' / 'creator_deny' keys of
     * updatePermissions(). Kept out of the switch body so the deny-handling
     * pattern lives in one place and mirrors the grant handling shape.
     *
     * @param string $perm_class   'default_deny' or 'creator_deny'.
     * @param mixed  $perm_values  Payload; matrix expects an array keyed by
     *                             permission constants, boolean a scalar.
     * @param string $type         Permission type.
     * @param array  $perm_types   Horde_Perms::getPermsArray() output when
     *                             $type == 'matrix', empty otherwise.
     *
     * @throws \Horde\Exception\HordeLogicException  When the permission type does not support denies.
     */
    private function _setScopeDenies($perm_class, $perm_values, $type, $perm_types)
    {
        $this->_assertDenySupported();

        // 'default_deny' -> 'default_deny', 'creator_deny' -> 'creator_deny'.
        // The storage key is identical to the input key. Kept explicit so
        // future renames don't silently divorce the two.
        $storage_key = $perm_class;

        if ($type == 'matrix') {
            foreach ($perm_types as $val => $label) {
                if (!empty($perm_values[$val])) {
                    $this->_setDenyBit($storage_key, null, $val);
                } else {
                    $this->_unsetDenyBit($storage_key, null, $val);
                }
            }
        } elseif (!empty($perm_values)) {
            $this->data[$storage_key] = $perm_values;
        } else {
            unset($this->data[$storage_key]);
        }
    }

    private function _setPerNameDenies($perm_class, $perm_values, $type, $perm_types)
    {
        $this->_assertDenySupported();

        $storage_key = $perm_class == 'u_deny' ? 'users_deny' : 'groups_deny';

        $current_names = isset($this->data[$storage_key])
            ? array_keys($this->data[$storage_key])
            : [];
        $updated_names = is_array($perm_values) ? array_keys($perm_values) : [];
        $removed_names = array_diff($current_names, $updated_names);

        foreach ($removed_names as $name) {
            unset($this->data[$storage_key][$name]);
        }

        if (is_null($perm_values)) {
            return;
        }

        foreach ($perm_values as $name => $name_values) {
            if ($type == 'matrix') {
                foreach ($perm_types as $val => $label) {
                    if (isset($name_values[$val]) && ($name_values[$val] === '0' || !empty($name_values[$val]))) {
                        $this->_setDenyBit($storage_key, $name, $val);
                    } else {
                        $this->_unsetDenyBit($storage_key, $name, $val);
                    }
                }
            } elseif ($name_values === '0' || !empty($name_values)) {
                $this->data[$storage_key][$name] = $name_values;
            } else {
                unset($this->data[$storage_key][$name]);
            }
        }
    }

    /**
     * OR-fold a single bit into the deny mask at ($storage_key, $name).
     * $name = null targets the scope-level slot (default_deny, creator_deny);
     * otherwise the per-name slot (users_deny[$name], groups_deny[$name]).
     */
    private function _setDenyBit($storage_key, $name, $bit)
    {
        if ($name === null) {
            $this->data[$storage_key] = ($this->data[$storage_key] ?? 0) | $bit;
        } else {
            $this->data[$storage_key][$name] = ($this->data[$storage_key][$name] ?? 0) | $bit;
        }
    }

    /**
     * AND-NOT a single bit out of the deny mask at ($storage_key, $name).
     * Empties an entry when the mask reaches 0 so serialized data stays
     * lean — mirrors what unsetPerm() does for the grant side.
     */
    private function _unsetDenyBit($storage_key, $name, $bit)
    {
        if ($name === null) {
            if (!isset($this->data[$storage_key])) {
                return;
            }
            $this->data[$storage_key] &= ~$bit;
            if (empty($this->data[$storage_key])) {
                unset($this->data[$storage_key]);
            }
        } else {
            if (!isset($this->data[$storage_key][$name])) {
                return;
            }
            $this->data[$storage_key][$name] &= ~$bit;
            if (empty($this->data[$storage_key][$name])) {
                unset($this->data[$storage_key][$name]);
            }
        }
    }

    /**
     * TODO
     */
    public function setPerm($permId, $permission, $update = true)
    {
        if (is_array($permId)) {
            if (empty($permId['name'])) {
                return;
            }
            if ($this->get('type') == 'matrix'
                && isset($this->data[$permId['class']][$permId['name']])) {
                $this->data[$permId['class']][$permId['name']] |= $permission;
            } else {
                $this->data[$permId['class']][$permId['name']] = $permission;
            }
        } else {
            if ($this->get('type') == 'matrix'
                && isset($this->data[$permId])) {
                $this->data[$permId] |= $permission;
            } else {
                $this->data[$permId] = $permission;
            }
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * TODO
     */
    public function unsetPerm($permId, $permission, $update = true)
    {
        if (is_array($permId)) {
            if (empty($permId['name'])) {
                return;
            }

            if ($this->get('type') == 'matrix') {
                if (isset($this->data[$permId['class']][$permId['name']])) {
                    $this->data[$permId['class']][$permId['name']] &= ~$permission;
                    if (empty($this->data[$permId['class']][$permId['name']])) {
                        unset($this->data[$permId['class']][$permId['name']]);
                    }
                } else {
                    $update = false;
                }
            } else {
                unset($this->data[$permId['class']][$permId['name']]);
            }
        } else {
            if ($this->get('type') == 'matrix') {
                if (isset($this->data[$permId])) {
                    $this->data[$permId] &= ~$permission;
                } else {
                    $update = false;
                }
            } else {
                unset($this->data[$permId]);
            }
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Grants a user additional permissions to this object.
     *
     * @param string $uer          The user to grant additional permissions
     *                             to.
     * @param integer $permission  The permission (DELETE, etc.) to add.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function addUserPermission($user, $permission, $update = true)
    {
        if (empty($user)) {
            return;
        }

        if ($this->get('type') == 'matrix'
            && isset($this->data['users'][$user])) {
            $this->data['users'][$user] |= $permission;
        } else {
            $this->data['users'][$user] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Adds an explicit deny for a specific user on this object.
     *
     * Denies are applied per-scope during resolution and beat grants from
     * less specific scopes. See Horde_Perms_Base::getPermissions() for the
     * exact specificity order.
     *
     * @param string $user         The user to deny permission to.
     * @param integer $permission  The permission bits to deny; for boolean
     *                             types any truthy value.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     *
     * @throws \Horde\Exception\HordeLogicException  When called on a permission whose type is neither
     *                      'matrix' nor 'boolean'.
     */
    public function addUserDeny($user, $permission, $update = true)
    {
        if (empty($user)) {
            return;
        }
        $this->_assertDenySupported();

        if ($this->get('type') == 'matrix'
            && isset($this->data['users_deny'][$user])) {
            $this->data['users_deny'][$user] |= $permission;
        } else {
            $this->data['users_deny'][$user] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Grants guests additional permissions to this object.
     *
     * @param integer $permission  The permission (DELETE, etc.) to add.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function addGuestPermission($permission, $update = true)
    {
        if ($this->get('type') == 'matrix'
            && isset($this->data['guest'])) {
            $this->data['guest'] |= $permission;
        } else {
            $this->data['guest'] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Grants creators additional permissions to this object.
     *
     * @param integer $permission  The permission (DELETE, etc.) to add.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function addCreatorPermission($permission, $update = true)
    {
        if ($this->get('type') == 'matrix'
            && isset($this->data['creator'])) {
            $this->data['creator'] |= $permission;
        } else {
            $this->data['creator'] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Adds an explicit deny for the creator scope on this object.
     *
     * Applied at the creator step of the cascade (only when the effective
     * user equals $creator at resolution time). Beats grants from default
     * and group scopes.
     *
     * @param integer $permission  The permission bits to deny.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     *
     * @throws \Horde\Exception\HordeLogicException  When called on a permission whose type is neither
     *                      'matrix' nor 'boolean'.
     */
    public function addCreatorDeny($permission, $update = true)
    {
        $this->_assertDenySupported();

        if ($this->get('type') == 'matrix'
            && isset($this->data['creator_deny'])) {
            $this->data['creator_deny'] |= $permission;
        } else {
            $this->data['creator_deny'] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Grants additional default permissions to this object.
     *
     * @param integer $permission  The permission (DELETE, etc.) to add.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function addDefaultPermission($permission, $update = true)
    {
        if ($this->get('type') == 'matrix'
            && isset($this->data['default'])) {
            $this->data['default'] |= $permission;
        } else {
            $this->data['default'] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Adds an explicit deny at the default (baseline) scope on this object.
     *
     * The default scope corresponds to "all authenticated users" — a deny
     * here removes bits from that baseline that any less specific rule
     * would otherwise provide. Group / creator / user grants at more
     * specific scopes still restore bits per the cascade rules.
     *
     * @param integer $permission  The permission bits to deny.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     *
     * @throws \Horde\Exception\HordeLogicException  When called on a permission whose type is neither
     *                      'matrix' nor 'boolean'.
     */
    public function addDefaultDeny($permission, $update = true)
    {
        $this->_assertDenySupported();

        if ($this->get('type') == 'matrix'
            && isset($this->data['default_deny'])) {
            $this->data['default_deny'] |= $permission;
        } else {
            $this->data['default_deny'] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Grants a group additional permissions to this object.
     *
     * @param integer $groupId     The id of the group to grant additional
     *                             permissions to.
     * @param integer $permission  The permission (DELETE, etc.) to add.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function addGroupPermission($groupId, $permission, $update = true)
    {
        if (empty($groupId)) {
            return;
        }

        if ($this->get('type') == 'matrix'
            && isset($this->data['groups'][$groupId])) {
            $this->data['groups'][$groupId] |= $permission;
        } else {
            $this->data['groups'][$groupId] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Adds an explicit deny for a specific group on this object.
     *
     * Denies are applied at the group step of the resolution cascade and
     * beat grants from less specific scopes (default). Within the group
     * step, grants across all of a user's groups collect first and are then
     * masked by the accumulated group denies.
     *
     * @param integer $groupId     The id of the group to deny permission to.
     * @param integer $permission  The permission bits to deny; for boolean
     *                             types any truthy value.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     *
     * @throws \Horde\Exception\HordeLogicException  When called on a permission whose type is neither
     *                      'matrix' nor 'boolean'.
     */
    public function addGroupDeny($groupId, $permission, $update = true)
    {
        if (empty($groupId)) {
            return;
        }
        $this->_assertDenySupported();

        if ($this->get('type') == 'matrix'
            && isset($this->data['groups_deny'][$groupId])) {
            $this->data['groups_deny'][$groupId] |= $permission;
        } else {
            $this->data['groups_deny'][$groupId] = $permission;
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a permission that a user currently has on this object.
     *
     * @param string $user         The user to remove the permission from.
     *                             Defaults to all users.
     * @param integer $permission  The permission (DELETE, etc.) to
     *                             remove. Defaults to all permissions.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeUserPermission(
        $user = null,
        $permission = null,
        $update = true
    ) {
        if (is_null($user)) {
            $this->data['users'] = [];
        } else {
            if (!isset($this->data['users'][$user])) {
                return;
            }

            if ($permission && $this->get('type') == 'matrix') {
                $this->data['users'][$user] &= ~$permission;
                if (empty($this->data['users'][$user])) {
                    unset($this->data['users'][$user]);
                }
            } else {
                unset($this->data['users'][$user]);
            }
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a permission that guests currently have on this object.
     *
     * @param integer $permission  The permission (DELETE, etc.) to
     *                             remove. Defaults to all permissions.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeGuestPermission($permission = null, $update = true)
    {
        if (!isset($this->data['guest'])) {
            return;
        }

        if ($permission && $this->get('type') == 'matrix') {
            $this->data['guest'] &= ~$permission;
        } else {
            unset($this->data['guest']);
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a permission that creators currently have on this object.
     *
     * @param integer $permission  The permission (DELETE, etc.) to
     *                             remove. Defaults to all permissions.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeCreatorPermission($permission = null, $update = true)
    {
        if (!isset($this->data['creator'])) {
            return;
        }

        if ($permission && $this->get('type') == 'matrix') {
            $this->data['creator'] &= ~$permission;
        } else {
            unset($this->data['creator']);
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a default permission on this object.
     *
     * @param integer $permission  The permission (DELETE, etc.) to
     *                             remove. Defaults to all permissions.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeDefaultPermission($permission = null, $update = true)
    {
        if (!isset($this->data['default'])) {
            return;
        }

        if ($permission && $this->get('type') == 'matrix') {
            $this->data['default'] &= ~$permission;
        } else {
            unset($this->data['default']);
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a permission that a group currently has on this object.
     *
     * @param integer $groupId     The id of the group to remove the
     *                             permission from. Defaults to all groups.
     * @param integer $permission  The permission (DELETE, etc.) to
     *                             remove. Defaults to all permissions.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeGroupPermission(
        $groupId = null,
        $permission = null,
        $update = true
    ) {
        if (is_null($groupId)) {
            $this->data['groups'] = [];
        } else {
            if (!isset($this->data['groups'][$groupId])) {
                return;
            }

            if ($permission && $this->get('type') == 'matrix') {
                $this->data['groups'][$groupId] &= ~$permission;
                if (empty($this->data['groups'][$groupId])) {
                    unset($this->data['groups'][$groupId]);
                }
            } else {
                unset($this->data['groups'][$groupId]);
            }
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Returns an array of all user permissions on this object.
     *
     * @param integer $perm  List only users with this permission level.
     *                       Defaults to all users.
     *
     * @return array  All user permissions for this object, indexed by user.
     */
    public function getUserPermissions($perm = null)
    {
        if (!isset($this->data['users']) || !is_array($this->data['users'])) {
            return [];
        } elseif (!$perm) {
            return $this->data['users'];
        }

        $users = [];
        foreach ($this->data['users'] as $user => $uperm) {
            if ($uperm & $perm) {
                $users[$user] = $uperm;
            }
        }

        return $users;
    }

    /**
     * Returns the guest permissions on this object.
     *
     * @return integer  The guest permissions on this object.
     */
    public function getGuestPermissions()
    {
        return empty($this->data['guest'])
            ? null
            : $this->data['guest'];
    }

    /**
     * Returns the creator permissions on this object.
     *
     * @return integer  The creator permissions on this object.
     */
    public function getCreatorPermissions()
    {
        return empty($this->data['creator'])
            ? null
            : $this->data['creator'];
    }

    /**
     * Returns the default permissions on this object.
     *
     * @return integer  The default permissions on this object.
     */
    public function getDefaultPermissions()
    {
        return empty($this->data['default'])
            ? null
            : $this->data['default'];
    }

    /**
     * Returns an array of all group permissions on this object.
     *
     * @param integer $perm  List only users with this permission level.
     *                       Defaults to all users.
     *
     * @return array  All group permissions for this object, indexed by group.
     */
    public function getGroupPermissions($perm = null)
    {
        if (!isset($this->data['groups'])
            || !is_array($this->data['groups'])) {
            return [];
        } elseif (!$perm) {
            return $this->data['groups'];
        }

        $groups = [];
        foreach ($this->data['groups'] as $group => $gperm) {
            if ($gperm & $perm) {
                $groups[$group] = $gperm;
            }
        }

        return $groups;
    }

    /**
     * Removes a deny that a user currently has on this object.
     *
     * Symmetric with removeUserPermission() — pass $user = null to clear
     * every user-level deny, pass $permission = null to clear all deny bits
     * for that user, or pass both to clear a specific bit mask.
     *
     * @param string $user         The user to remove the deny from.
     *                             Defaults to all users.
     * @param integer $permission  The permission bits to clear from the
     *                             deny mask. Defaults to all bits.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeUserDeny(
        $user = null,
        $permission = null,
        $update = true
    ) {
        if (is_null($user)) {
            $this->data['users_deny'] = [];
        } else {
            if (!isset($this->data['users_deny'][$user])) {
                return;
            }

            if ($permission && $this->get('type') == 'matrix') {
                $this->data['users_deny'][$user] &= ~$permission;
                if (empty($this->data['users_deny'][$user])) {
                    unset($this->data['users_deny'][$user]);
                }
            } else {
                unset($this->data['users_deny'][$user]);
            }
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a deny that a group currently has on this object.
     *
     * @param integer $groupId     The group to remove the deny from.
     *                             Defaults to all groups.
     * @param integer $permission  The permission bits to clear. Defaults to
     *                             all bits.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeGroupDeny(
        $groupId = null,
        $permission = null,
        $update = true
    ) {
        if (is_null($groupId)) {
            $this->data['groups_deny'] = [];
        } else {
            if (!isset($this->data['groups_deny'][$groupId])) {
                return;
            }

            if ($permission && $this->get('type') == 'matrix') {
                $this->data['groups_deny'][$groupId] &= ~$permission;
                if (empty($this->data['groups_deny'][$groupId])) {
                    unset($this->data['groups_deny'][$groupId]);
                }
            } else {
                unset($this->data['groups_deny'][$groupId]);
            }
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a creator-scope deny from this object.
     *
     * @param integer $permission  The permission bits to clear. Defaults to
     *                             all bits.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeCreatorDeny($permission = null, $update = true)
    {
        if (!isset($this->data['creator_deny'])) {
            return;
        }

        if ($permission && $this->get('type') == 'matrix') {
            $this->data['creator_deny'] &= ~$permission;
        } else {
            unset($this->data['creator_deny']);
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Removes a default-scope deny from this object.
     *
     * @param integer $permission  The permission bits to clear. Defaults to
     *                             all bits.
     * @param boolean $update      Whether to automatically update the
     *                             backend.
     */
    public function removeDefaultDeny($permission = null, $update = true)
    {
        if (!isset($this->data['default_deny'])) {
            return;
        }

        if ($permission && $this->get('type') == 'matrix') {
            $this->data['default_deny'] &= ~$permission;
        } else {
            unset($this->data['default_deny']);
        }

        if ($update) {
            $this->save();
        }
    }

    /**
     * Returns an array of all user-scope denies on this object.
     *
     * Mirrors getUserPermissions() — matrix filter narrows the return set
     * to entries whose deny mask overlaps $perm; without $perm the full
     * user_id => mask hash is returned.
     *
     * @param integer $perm  If set, only return users whose deny mask
     *                       overlaps this bit set.
     *
     * @return array  All user-scope denies, indexed by user.
     */
    public function getUserDenies($perm = null)
    {
        if (!isset($this->data['users_deny']) || !is_array($this->data['users_deny'])) {
            return [];
        } elseif (!$perm) {
            return $this->data['users_deny'];
        }

        $users = [];
        foreach ($this->data['users_deny'] as $user => $uperm) {
            if ($uperm & $perm) {
                $users[$user] = $uperm;
            }
        }

        return $users;
    }

    /**
     * Returns an array of all group-scope denies on this object.
     *
     * @param integer $perm  If set, only return groups whose deny mask
     *                       overlaps this bit set.
     *
     * @return array  All group-scope denies, indexed by group id.
     */
    public function getGroupDenies($perm = null)
    {
        if (!isset($this->data['groups_deny']) || !is_array($this->data['groups_deny'])) {
            return [];
        } elseif (!$perm) {
            return $this->data['groups_deny'];
        }

        $groups = [];
        foreach ($this->data['groups_deny'] as $group => $gperm) {
            if ($gperm & $perm) {
                $groups[$group] = $gperm;
            }
        }

        return $groups;
    }

    /**
     * Returns the creator-scope deny mask, or null when nothing is denied.
     *
     * @return integer|null
     */
    public function getCreatorDenies()
    {
        return empty($this->data['creator_deny'])
            ? null
            : $this->data['creator_deny'];
    }

    /**
     * Returns the default-scope deny mask, or null when nothing is denied.
     *
     * @return integer|null
     */
    public function getDefaultDenies()
    {
        return empty($this->data['default_deny'])
            ? null
            : $this->data['default_deny'];
    }

    /**
     * Guards deny-writing entry points against types that cannot express
     * a meaningful negation. 'matrix' (bitmask) and 'boolean' (single bit)
     * are supported; anything else — including 'int' or caller-invented
     * numeric types — raises \Horde\Exception\HordeLogicException because there is no principled
     * way to "un-set" an arbitrary scalar value.
     *
     * @throws \Horde\Exception\HordeLogicException
     */
    private function _assertDenySupported()
    {
        $type = $this->get('type');
        if ($type != 'matrix' && $type != 'boolean') {
            throw new \Horde\Exception\HordeLogicException(
                'Deny permissions are only defined for matrix and boolean '
                . 'permission types; got "' . $type . '".'
            );
        }
    }

    /**
     * TODO
     */
    public function save() {}

}
