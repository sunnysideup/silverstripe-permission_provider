<?php

namespace Sunnysideup\PermissionProvider\Tasks;

use BuildTask;
use Permission;
use DB;



class PermissionProviderBuildTask extends BuildTask
{
    protected $title = 'Clean up Permissions';

    protected $description = 'Goes through all the permissions and cleans them up.';

    protected $_permissions = array();

    public function run($request)
    {
        $permissions = Permission::get();
        foreach ($permissions as $permission) {
            if ($permission->Arg == 0 && $permission->Type == 1) {
                if (isset($this->_permissions[$permission->Code])) {
                    DB::alteration_message("Deleting double permission with code: ".$permission->Code, 'deleted');
                    $permission->delete();
                }
                $this->_permissions[$permission->Code];
            }
        }
    }
}
