<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\PermissionProvider\Tasks\PermissionProviderBuildTask;

class GroupExtension extends DataExtension
{
    private static $db = [
        'MainPermissionCode' => 'Varchar',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName(['MainPermissionCode']);
        $fields->addFieldsToTab(
            'Root.Permissions',
            [
                ReadonlyField::create('MainPermissionCode', 'Main Permission Code'),
                TextField::create('Code', 'Code'),
            ]
        );

        return $fields;
    }

    public function requireDefaultRecords()
    {
        $obj = PermissionProviderBuildTask::create();
        $obj->run(null);
    }

    public function createdThroughFactory(): bool
    {
        if ($this->getOwner()->MainPermissionCode) {
            return true;
        }

        return false;
    }
}
