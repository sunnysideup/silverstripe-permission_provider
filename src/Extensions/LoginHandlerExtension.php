<?php

namespace Sunnysideup\PermissionProvider\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\LoginHandler;
use SilverStripe\Security\Security;

/**
 * Class \Sunnysideup\PermissionProvider\Extensions\LoginHandlerExtension.
 *
 * @property LoginHandler|LoginHandlerExtension $owner
 */
class LoginHandlerExtension extends Extension
{
    public function afterLogin(?Member $member = null)
    {
        if ($member) {
            $redirectorGroup = $member->Groups()->filter('DefaultLoginLink:not', ['', null])->first();
            if ($redirectorGroup) {
                Config::modify()->merge(
                    Security::class,
                    'default_login_dest',
                    $redirectorGroup->DefaultLoginLink
                );
            }
        }
    }
}
