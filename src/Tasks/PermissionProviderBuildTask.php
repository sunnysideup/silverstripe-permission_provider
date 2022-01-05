<?php

namespace Sunnysideup\PermissionProvider\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

use SilverStripe\Core\ClassInfo;

use Sunnysideup\PermissionProvider\Api\PermissionProviderFactory;
use Sunnysideup\PermissionProvider\Interfaces\PermissionProviderFactoryProvider;

class PermissionProviderBuildTask extends BuildTask
{
    protected $title = 'Create Default Permissions';

    protected $description = 'Goes through all the permissions and cleans them up, then creates all the default ones.';

    protected $_permissions = [];

    public function run($request)
    {
        $this->cleanUp();
        $this->createDefaultPermissions();
    }

    protected function createDefaultPermissions()
    {
        $classNames = ClassInfo::implementorsOf(PermissionProviderFactoryProvider::class);
        foreach($classNames as $className) {
            $className::permission_provider_factory_runner();
        }

    }

    protected function cleanUp()
    {
        $permissions = Permission::get();
        foreach ($permissions as $permission) {
            if (0 === $permission->Arg && 1 === $permission->Type) {
                if (isset($this->_permissions[$permission->Code])) {
                    DB::alteration_message('Deleting double permission with code: ' . $permission->Code, 'deleted');
                    $permission->delete();
                }
            }
        }
    }

}
