<?php

namespace Sunnysideup\PermissionProvider\Traits;

use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

trait GenericCanMethodTrait
{
    public function genericCanMethod(string $methodName, ...$arguments): bool
    {
        $methodName = 'can' . ucfirst($methodName);
        $methodNameMoreSpecific = $methodName.'MoreSpecific';
        if ($this->hasMethod($methodNameMoreSpecific)) {
            $value = $this->$methodNameMoreSpecific(...$arguments);
            if($value !== null) {
                return $value;
            }
        }
        $code = $this->getPermissionCodeForThisClass();
        $code .= '_CAN_'.strtoupper($methodName);
        if(Permission::check($code, 'any', $this)) {
            return true;
        }
        return parent::$methodName(...$arguments);
    }

    /**
     * DataObject create permissions
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        return $this->genericCanMethod('create', $member);
    }

    public function canView($member = null)
    {
        if($this->genericCanMethod('edit', $member)) {
            return true;
        }
        return $this->genericCanMethod('view', $member);
    }

    public function canEdit($member = null)
    {
        if($this->genericCanMethod('edit', $member)) {
            return true;
        }
        if($this->canEditAsMember($member)) {
            return true;
        }
        if($this->canEditAsOwner($member)) {
            return true;
        }
        return false;
    }

    /**
     * for canDelete, we see if it returns true, but if not, we just return canEdit as a proxy.
     *
     * @param Member $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        if(! $this->genericCanMethod('delete', $member)) {
            return false;
        }
        return $this->canEdit($member);
    }

    /**
     * Return a map of permission codes to add to the dropdown shown
     * in the Security section of the CMS.
     * @return array
     */

    public function providePermissions()
    {
        $code = $this->getPermissionCodeForThisClass();
        $name = $this->i18n_plural_name();
        $perms = [];
        $perms[] = [
            $code.'_CAN_VIEW' => [
                'name' => 'View ' . $name,
                'category' => 'View Records',
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ]
        ];
        $perms[] = [
            $code.'_CAN_EDIT' => [
                'name' => 'Edit ' . $name,
                'category' => 'Edit Records',
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ]
        ];
        if($this->hasMethod('MembersForPermissionCheck')) {
            $perms[] = [
                $code.'_CAN_EDIT_AS_OWNER' => [
                    'name' => 'Edit ' . $name.' as owner',
                    'category' => 'Edit Records',
                    // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                    // 'sort' => -100
                ]
            ];
        }
        if($this->hasMethod('OwnersForPermissionCheck')) {
            $perms[] = [
                $code.'_CAN_EDIT_AS_MEMBER' => [
                    'name' => 'Edit ' . $name.' as member',
                    'category' => 'Edit Records',
                    // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                    // 'sort' => -100
                ]
            ];
        }

        $perms[] = [
            $code.'_CAN_DELETE' => [
                'name' => 'Delete ' . $name,
                'category' => 'Delete Records',
                // 'help' => _t(__CLASS__ . '.ACCESSALLINTERFACESHELP', 'Overrules more specific access settings.'),
                // 'sort' => -100
            ]
        ];

        return $perms;
    }

    protected function getPermissionCodeForThisClass(): string
    {
        $schema = static::getSchema();
        return $schema->tableName(static::class);
    }


    protected function canEditAsMember($member = null): bool
    {
        $code = $this->getPermissionCodeForThisClass();
        return $this->canEditAsMemberOrOwner(
            $code.'_CAN_EDIT_AS_MEMBER',
            'MembersForPermissionCheck',
            $member
        );
    }

    protected function canEditAsOwner($member = null): bool
    {
        $code = $this->getPermissionCodeForThisClass();
        return $this->canEditAsMemberOrOwner(
            $code.'_CAN_EDIT_AS_OWNER',
            'OwnersForPermissionCheck',
            $member
        );
    }


    private function canEditAsMemberOrOwner(string $code, string $methodName, ?Member $member = null)
    {
        if($this->hasMethod($methodName)) {
            $member = Security::getCurrentUser();
            if($member) {
                $owners = $this->OwnersForPermissionCheck();
                if($owners->exists()) {
                    return $owners->filter(['ID' => $member->ID])->exists();
                }
            }
        }
    }
}
