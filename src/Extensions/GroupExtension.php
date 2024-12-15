<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PermissionProvider\Tasks\PermissionProviderBuildTask;

/**
 * Class \Sunnysideup\PermissionProvider\Extensions\GroupExtension.
 *
 * @property Group|GroupExtension $owner
 * @property string $MainPermissionCode
 * @property string $DefaultLoginLink
 */
class GroupExtension extends Extension
{
    private static $db = [
        'MainPermissionCode' => 'Varchar',
        'DefaultLoginLink' => 'Text',
    ];

    private static $indexes = [
        'MainPermissionCode' => true,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName(['MainPermissionCode']);
        $fields->addFieldsToTab(
            'Root.Members',
            [
                ReadonlyField::create('Code', 'Code'),
            ],
            'Description'
        );
        $fields->addFieldsToTab(
            'Root.Permissions',
            [
                ReadonlyField::create('MainPermissionCode', 'Main Permission Code'),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Redirect',
            [
                TextField::create('DefaultLoginLink', 'Default Login Link')
                    ->setDescription('Optional. This is the link that the user, who belongs to this group, will be redirected to after login. If they belong to more than one group with redirection then you can not sure how they will be redirected.'),
            ]
        );
    }

    public function requireDefaultRecords()
    {
        $obj = PermissionProviderBuildTask::create();
        $obj->run(null);
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
