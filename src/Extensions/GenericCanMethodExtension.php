<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class GenericCanMethodExtension extends DataExtension
{
    public function genericCanMethod(string $methodName, ?Member $member = null): ?bool
    {
        $owner = $this->getOwner();
        $code = $owner->getPermissionCodeForThisClass().'_CAN_'.strtoupper($methodName);
        if(Permission::check($code, 'any', $member)) {
            return true;
        }
        return null;
    }

    public function canCreate($member = null, $context = [])
    {
        $owner = $this->getOwner();
        if($owner->genericCanMethod('create', $member)) {
            return true;
        }
        return null;
    }

    public function canView($member = null)
    {
        $owner = $this->getOwner();
        if($owner->genericCanMethod('edit', $member)) {
            return true;
        }
        if($owner->genericCanMethod('view', $member)) {
            return true;
        }
        return null;
    }

    public function canEdit($member = null)
    {
        $owner = $this->getOwner();
        if($owner->genericCanMethod('edit', $member)) {
            return true;
        }
        if($owner->canEditAsMember($member)) {
            return true;
        }
        if($owner->canEditAsOwner($member)) {
            return true;
        }
        return null;
    }

    public function canPublish($member = null)
    {
        $owner = $this->getOwner();
        if($owner->canEdit($member) && $owner->genericCanMethod('publish', $member)) {
            return true;
        }
        return null;
    }

    /**
     * for canDelete, we see if it returns true, but if not, we just return canEdit as a proxy.
     *
     * @param Member $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        $owner = $this->getOwner();
        if($owner->canEdit($member)) {
            if(!$owner->genericCanMethod('delete', $member)) {
                return true;
            }
        }
        return null;
    }

    /**
     * Return a map of permission codes to add to the list shown
     * in the Security section of the CMS.
     * @return array
     */
    public function providePermissionsHelper(): array
    {
        $owner = $this->getOwner();
        $code = $this->getPermissionCodeForThisClass();
        $name = $owner->i18n_plural_name();
        $perms = [];
        $perms[$code.'_CAN_CREATE'] = [
            'name' => 'Create ' . $owner->i18n_singular_name(),
            'category' => 'Create Records',
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];
        $perms[$code.'_CAN_VIEW'] = [
            'name' => 'View ' . $name,
            'category' => 'View Records',
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];
        $perms[$code.'_CAN_EDIT'] = [
            'name' => 'Edit ' . $name,
            'category' => 'Edit Records',
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];
        if($owner->hasMethod('hasStages') && $owner->hasStages()) {
            $perms[$code.'_CAN_PUBLISH'] = [
                'name' => 'Publish ' . $name,
                'category' => 'Publish Records',
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ];
        }
        if($owner->hasMethod('MembersForPermissionCheck')) {
            $perms[$code.'_CAN_EDIT_AS_OWNER'] = [
                'name' => 'Edit ' . $name.' as owner',
                'category' => 'Edit Records',
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ];
        }
        if($owner->hasMethod('OwnersForPermissionCheck')) {
            $perms[$code.'_CAN_EDIT_AS_MEMBER'] = [
                'name' => 'Edit ' . $name.' as member',
                'category' => 'Edit Records',
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ];
        }

        $perms[$code.'_CAN_DELETE'] = [
            'name' => 'Delete ' . $name,
            'category' => 'Delete Records',
            // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
            // 'sort' => -100
        ];

        return $perms;
    }



    protected function canEditAsMember($member = null): bool
    {
        $owner = $this->getOwner();
        if($owner->canEditAsOwner($member)) {
            return true;
        }
        $code = $this->getPermissionCodeForThisClass();
        return $this->canEditAsMemberOrOwner(
            $code.'_CAN_EDIT_AS_MEMBER',
            'MembersForPermissionCheck',
            $member
        );
    }

    protected function canEditAsOwner($member = null): bool
    {
        $owner = $this->getOwner();
        $code = $this->getPermissionCodeForThisClass();
        return $this->canEditAsMemberOrOwner(
            $code.'_CAN_EDIT_AS_OWNER',
            'OwnersForPermissionCheck',
            $member
        );
    }


    private function canEditAsMemberOrOwner(string $code, string $methodName, ?Member $member = null)
    {
        $owner = $this->getOwner();
        if($owner->hasMethod($methodName)) {
            $member = Security::getCurrentUser();
            if($member) {
                $owners = $owner->OwnersForPermissionCheck();
                if($owners->exists()) {
                    return $owners->filter(['ID' => $member->ID])->exists();
                }
            }
        }
    }

    private $table_cache_for_permissions = [];

    public function getPermissionCodeForThisClass(): string
    {
        $owner = $this->getOwner();
        if(!isset($this->table_cache_for_permissions[$owner::class])) {
            $schema = $owner::getSchema();
            $this->table_cache_for_permissions[$owner::class] = strtoupper(
                //underscore before caps
                preg_replace(
                    '/(?<!^)([A-Z])/',
                    '_$1',
                    $schema->tableName($owner::class)
                )
            );
        }
        return $this->table_cache_for_permissions[$owner::class];
    }
}