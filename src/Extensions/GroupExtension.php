<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\PermissionProvider\Tasks\PermissionProviderBuildTask;

/**
 * Class \Sunnysideup\PermissionProvider\Extensions\GroupExtension
 *
 * @property Group|GroupExtension $owner
 * @property string $MainPermissionCode
 */
class GroupExtension extends DataExtension
{
    private static $db = [
        'MainPermissionCode' => 'Varchar',
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

        return $fields;
    }

    public function requireDefaultRecords()
    {
        $obj = PermissionProviderBuildTask::create();
        $obj->run(null);
    }

    public function createdThroughFactory(): bool
    {
        return (bool) $this->getOwner()->MainPermissionCode;
    }
}
