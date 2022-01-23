<?php

namespace Sunnysideup\PermissionProvider\Interfaces;

use SilverStripe\Security\Group;
use SilverStripe\Security\PermissionProvider;

interface PermissionProviderFactoryProvider
{

    public static function permission_provider_factory_runner() : Group;

}
