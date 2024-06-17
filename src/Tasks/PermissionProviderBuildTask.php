<?php

namespace Sunnysideup\PermissionProvider\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use Sunnysideup\PermissionProvider\Api\PermissionProviderFactory;
use Sunnysideup\PermissionProvider\Interfaces\PermissionProviderFactoryProvider;

class PermissionProviderBuildTask extends BuildTask
{
    protected $title = 'Create Default Permissions';

    protected $description = 'Goes through all the permissions and cleans them up, then creates all the default ones.';

    /**
     * delete permissions you no longer want
     *
     * @var array
     */
    protected $_permissions = [];

    /**
     *
     *
     * @param null|HTTPRequest $request
     * @return void
     */
    public function run($request)
    {

        $this->deletePermissionsNoLongerRequired();
        $this->createDefaultPermissions();
        $this->deletePermissionsNoLongerRequired();
    }

    protected function createDefaultPermissions()
    {
        PermissionProviderFactory::set_debug();
        $classNames = ClassInfo::implementorsOf(PermissionProviderFactoryProvider::class);
        foreach ($classNames as $className) {
            $className::permission_provider_factory_runner();
        }
    }

    protected function getRidOfEmptyPermissions()
    {
        DB::query('DELETE FROM PermissionRoleCode WHERE Code IS NULL');
    }

    protected function deletePermissionsNoLongerRequired()
    {
        if(!empty($this->_permissions)) {
            $permissions = Permission::get();
            foreach ($permissions as $permission) {
                if (isset($this->_permissions[$permission->Code]) && 0 === $permission->Arg && 1 === $permission->Type) {
                    DB::alteration_message('Deleting double permission with code: ' . $permission->Code, 'deleted');
                    $permission->delete();
                }
            }
        }
    }
}
