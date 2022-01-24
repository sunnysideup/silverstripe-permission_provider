<?php

namespace Sunnysideup\PermissionProvider\Interfaces;

use SilverStripe\Security\Group;

interface PermissionProviderFactoryProvider
{
    public static function permission_provider_factory_runner(): Group;
}
