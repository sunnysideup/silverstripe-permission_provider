<?php

namespace Sunnysideup\PermissionProvider\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionRoleCode;
use Sunnysideup\PermissionProvider\Api\PermissionProviderFactory;
use Sunnysideup\PermissionProvider\Interfaces\PermissionProviderFactoryProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class PermissionProviderBuildTask extends BuildTask
{
    protected static string $commandName = 'permission-provider:build';

    protected string $title = 'Create Default Permissions';

    protected static string $description = 'Goes through all the permissions and cleans them up, then creates all the default ones.';

    /**
     * delete permissions you no longer want
     *
     * @var array
     */
    protected $_permissions = [];

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->deletePermissionsNoLongerRequired($output);
        $this->createDefaultPermissions($output);
        $this->deletePermissionsNoLongerRequired($output);
        $this->removeObsoletePermissionCodeRoles($output);
        $output->writeln('All permissions aligned');
        
        return Command::SUCCESS;
    }

    protected function createDefaultPermissions(PolyOutput $output)
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

    /**
     * @todo: fix
     */
    protected function deletePermissionsNoLongerRequired(PolyOutput $output)
    {
        if ($this->_permissions !== []) {
            $permissions = Permission::get();
            foreach ($permissions as $permission) {
                if (isset($this->_permissions[$permission->Code]) && 0 === $permission->Arg && 1 === $permission->Type) {
                    $output->writeln('Deleting double permission with code: ' . $permission->Code);
                    $permission->delete();
                }
            }
        }
    }

    protected function removeObsoletePermissionCodeRoles(PolyOutput $output)
    {
        $codes = Permission::get_codes(false);
        $list = PermissionRoleCode::get();
        foreach ($list as $code) {
            foreach ($list as $code) {
                if (! isset($codes[$code->Code])) {
                    $output->writeln('Deleting obsolete permission code role with code: ' . $code->Code);
                    $code->delete();
                }
            }
        }
    }
}
