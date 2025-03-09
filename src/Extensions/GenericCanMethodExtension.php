<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Class \Sunnysideup\PermissionProvider\Extensions\GenericCanMethodExtension
 *
 * @property Signatory|GenericCanMethodExtension $owner
 */
class GenericCanMethodExtension extends Extension
{
    public function genericCanMethod(string $methodName, ?Member $member = null): ?bool
    {
        $owner = $this->getOwner();
        $code = $owner->getPermissionCodeForThisClass() . '_CAN_' . strtoupper($methodName);
        if (Permission::check($code, 'any', $member)) {
            return true;
        }
        return null;
    }

    public function canCreate($member = null, $context = [])
    {
        $owner = $this->getOwner();
        if ($owner->genericCanMethod('create', $member)) {
            return true;
        }
        return null;
    }

    public function canView($member = null)
    {
        $owner = $this->getOwner();
        if ($owner->canEdit($member)) {
            return true;
        }
        if ($owner->genericCanMethod('view', $member)) {
            return true;
        }
        return null;
    }

    public function canEdit($member = null)
    {
        $owner = $this->getOwner();
        if ($owner->genericCanMethod('edit', $member)) {
            return true;
        }
        if ($owner->canEditAsMember($member)) {
            return true;
        }
        if ($owner->canEditAsOwner($member)) {
            return true;
        }
        return null;
    }

    public function canPublish($member = null)
    {
        $owner = $this->getOwner();
        if ($owner->canEdit($member) && $owner->genericCanMethod('publish', $member)) {
            return true;
        }
        return null;
    }

    /**
     * for canDelete, we see if it returns true, but if not, we just return canEdit as a proxy.
     *
     * @param Member $member
     * @return boolean|null
     */
    public function canDelete($member = null)
    {
        $owner = $this->getOwner();
        if ($owner->canEdit($member) && ! $owner->genericCanMethod('delete', $member)) {
            return true;
        }
        return null;
    }

    /**
     * Return a map of permission codes to add to the list shown
     * in the Security section of the CMS.
     */
    public function providePermissionsHelper(): array
    {
        $owner = $this->getOwner();
        $code = $this->getPermissionCodeForThisClass();
        $name = $owner->i18n_plural_name();
        $perms = [];
        $perms[$code . '_CAN_CREATE'] = [
            'name' => 'Create ' . $name,
            'category' => $name,
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];
        $perms[$code . '_CAN_VIEW'] = [
            'name' => 'View ' . $name,
            'category' => $name,
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];
        $perms[$code . '_CAN_EDIT'] = [
            'name' => 'Edit ' . $name,
            'category' => $name,
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];
        if ($owner->hasMethod('hasStages') && $owner->hasStages()) {
            $perms[$code . '_CAN_PUBLISH'] = [
                'name' => 'Publish ' . $name,
                'category' => $name,
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ];
        }
        if ($owner->hasMethod('MembersForPermissionCheck')) {
            $perms[$code . '_CAN_EDIT_AS_OWNER'] = [
                'name' => 'Edit ' . $name . ' as owner of record',
                'category' => $name,
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ];
        }
        if ($owner->hasMethod('OwnersForPermissionCheck')) {
            $perms[$code . '_CAN_EDIT_AS_MEMBER'] = [
                'name' => 'Edit ' . $name . ' as member of record',
                'category' => $name,
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ];
        }

        $perms[$code . '_CAN_DELETE'] = [
            'name' => 'Delete ' . $name,
            'category' => $name,
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];

        return $perms;
    }

    public function canEditAsMember($member = null): ?bool
    {
        $owner = $this->getOwner();
        if ($owner->canEditAsOwner($member)) {
            return true;
        }
        $this->getPermissionCodeForThisClass();
        return $this->canEditAsMemberOrOwner(
            'MembersForPermissionCheck',
            $member
        );
    }

    public function canEditAsOwner($member = null): ?bool
    {
        $this->getOwner();
        $this->getPermissionCodeForThisClass();
        return $this->canEditAsMemberOrOwner(
            'OwnersForPermissionCheck',
            $member
        );
    }

    private function canEditAsMemberOrOwner(string $methodName, ?Member $member = null): ?bool
    {
        $owner = $this->getOwner();
        if (! $member instanceof Member) {
            $member = Security::getCurrentUser();
        }
        if ($member && $owner->hasMethod($methodName)) {
            $member = Security::getCurrentUser();
            if ($member) {
                $owners = $owner->$methodName();
                if ($owners && $owners->exists()) {
                    return $owners->filter(['ID' => $member->ID])->exists();
                }
            }
        }
        return null;
    }

    private $table_cache_for_permissions = [];

    public function getPermissionCodeForThisClass(): string
    {
        $owner = $this->getOwner();
        if ($owner->hasMethod('getPermissionCodeClassNameForThisClass')) {
            $className = $owner->getPermissionCodeClassNameForThisClass();
        } else {
            $className = $owner::class;
        }
        if (! isset($this->table_cache_for_permissions[$owner::class])) {
            $schema = $owner::getSchema();

            $this->table_cache_for_permissions[$owner::class] = strtoupper(
                //underscore before caps
                preg_replace(
                    '/(?<!^)([A-Z])/',
                    '_$1',
                    $schema->tableName($className) ?: $className
                )
            );
        }
        return $this->table_cache_for_permissions[$owner::class];
    }
}
