<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

/**
 * Class \Sunnysideup\PermissionProvider\Extensions\GroupExtension.
 *
 * @property PermissionRole|RoleExtension $owner
 * @property ?string $MainPermissionCode
 */
class RoleExtension extends Extension
{
    private static $db = [
        'MainPermissionCode' => 'Varchar',
    ];

    private static $indexes = [
        'MainPermissionCode' => true,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName(['MainPermissionCode']);
        $fields->addFieldsToTab(
            'Root.Permissions',
            [
                ReadonlyField::create('MainPermissionCode', 'Main Permission Code'),
            ]
        );
    }

    public function IsCreatedThroughFactory(): bool
    {
        return (bool) $this->getOwner()->MainPermissionCode;
    }

    /**
     * DataObject delete permissions
     * @param Member $member
     * @return null|boolean
     */
    public function canEdit($member = null)
    {
        if ($this->IsCreatedThroughFactory()) {
            return false;
        }
        return null;
    }

    /**
     * DataObject delete permissions
     * @param Member $member
     * @return null|boolean
     */
    public function canDelete($member = null)
    {
        if ($this->IsCreatedThroughFactory()) {
            return false;
        }
        return null;
    }
}
