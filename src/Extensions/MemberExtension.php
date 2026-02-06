<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;

/**
 * Class \Sunnysideup\PermissionProvider\Extensions\MemberExtension.
 *
 * @property Member|MemberExtension $owner
 * @property bool $IsPermissionProviderCreated
 */
class MemberExtension extends Extension
{
    private static $db = [
        'IsPermissionProviderCreated' => 'Boolean',
    ];

    public function onBeforeWrite()
    {
        $owner = $this->getOwner();
        if ($owner->exists() && $owner->IsPermissionProviderCreated && $owner->isChanged('Email')) {
            $oldMember = Member::get()->byID($owner->ID);
            if ($oldMember && $oldMember->Email) {
                //reset all login links
                $owner->Email = $oldMember->Email;
            }
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        if ($fields->fieldByName('Root.Main.IsPermissionProviderCreated') || $fields->dataFieldByName('IsPermissionProviderCreated')) {
            $fields->removeByName('IsPermissionProviderCreated');
        }
    }
}
