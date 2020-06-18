2020-06-18 12:27

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/ss3/upgrades/permission-provider
php /var/www/ss3/upgrader/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /var/www/ss3/upgrades/permission-provider/permission_provider  --root-dir=/var/www/ss3/upgrades/permission-provider --write -vvv
Writing changes for 3 files
Running upgrades on "/var/www/ss3/upgrades/permission-provider/permission_provider"
[2020-06-18 12:27:49] Applying RenameClasses to _config.php...
[2020-06-18 12:27:49] Applying ClassToTraitRule to _config.php...
[2020-06-18 12:27:49] Applying RenameClasses to PermissionProviderTest.php...
[2020-06-18 12:27:49] Applying ClassToTraitRule to PermissionProviderTest.php...
[2020-06-18 12:27:49] Applying RenameClasses to PermissionProviderFactory.php...
[2020-06-18 12:27:49] Applying ClassToTraitRule to PermissionProviderFactory.php...
[2020-06-18 12:27:49] Applying RenameClasses to PermissionProviderBuildTask.php...
[2020-06-18 12:27:49] Applying ClassToTraitRule to PermissionProviderBuildTask.php...
modified:	tests/PermissionProviderTest.php
@@ -1,4 +1,6 @@
 <?php
+
+use SilverStripe\Dev\SapphireTest;

 class PermissionProviderTest extends SapphireTest
 {

modified:	src/Api/PermissionProviderFactory.php
@@ -2,15 +2,26 @@

 namespace Sunnysideup\PermissionProvider\Api;

-use Injector;
-use Member;
-use DataObject;
-use Group;
-use DB;
-use Permission;
-use PermissionRole;
-use PermissionRoleCode;
-use Director;
+
+
+
+
+
+
+
+
+
+use SilverStripe\Core\Injector\Injector;
+use Sunnysideup\PermissionProvider\Api\PermissionProviderFactory;
+use SilverStripe\Security\Member;
+use SilverStripe\ORM\DataObject;
+use SilverStripe\Security\Group;
+use SilverStripe\ORM\DB;
+use SilverStripe\Security\Permission;
+use SilverStripe\Security\PermissionRole;
+use SilverStripe\Security\PermissionRoleCode;
+use SilverStripe\Control\Director;
+



@@ -81,7 +92,7 @@
     public static function inst()
     {
         if (self::$_instance === null) {
-            self::$_instance = Injector::inst()->get('PermissionProviderFactory');
+            self::$_instance = Injector::inst()->get(PermissionProviderFactory::class);
         }

         return self::$_instance;
@@ -191,7 +202,7 @@
         $filter = ['Email' => $this->email];
         $memberExists = true;
         $this->member = DataObject::get_one(
-            'Member',
+            Member::class,
             $filter,
             $cacheDataObjectGetOne = false
         );
@@ -243,7 +254,7 @@
             if (is_string($this->parentGroup)) {
                 $this->parentGroupName = $this->parentGroup;
                 $this->parentGroup = DataObject::get_one(
-                    'Group',
+                    Group::class,
                     ['Title' => $this->parentGroupName],
                     $cacheDataObjectGetOne = false
                 );
@@ -331,7 +342,7 @@
             if ($permissionRoleCount > 1) {
                 DB::alteration_message("There is more than one Permission Role with title {$this->roleTitle} (${permissionRoleCount})", 'deleted');
                 $permissionRolesFirst = DataObject::get_one(
-                    'PermissionRole',
+                    PermissionRole::class,
                     ['Title' => $this->roleTitle],
                     $cacheDataObjectGetOne = false
                 );
@@ -353,7 +364,7 @@
                 $permissionRole->write();
             }
             $permissionRole = DataObject::get_one(
-                'PermissionRole',
+                PermissionRole::class,
                 ['Title' => $this->roleTitle],
                 $cacheDataObjectGetOne = false
             );
@@ -362,7 +373,7 @@
                     DB::alteration_message('working with ' . implode(', ', $this->permissionArray));
                     foreach ($this->permissionArray as $permissionRoleCode) {
                         $permissionRoleCodeObject = DataObject::get_one(
-                            'PermissionRoleCode',
+                            PermissionRoleCode::class,
                             ['Code' => $permissionRoleCode, 'RoleID' => $permissionRole->ID],
                             $cacheDataObjectGetOne = false
                         );

Warnings for src/Api/PermissionProviderFactory.php:
 - src/Api/PermissionProviderFactory.php:188 PhpParser\Node\NullableType
 - WARNING: New class instantiated by a dynamic value on line 188

 - src/Api/PermissionProviderFactory.php:218 PhpParser\Node\NullableType
 - WARNING: New class instantiated by a dynamic value on line 218

 - src/Api/PermissionProviderFactory.php:272 PhpParser\Node\NullableType
 - WARNING: New class instantiated by a dynamic value on line 272

modified:	src/Tasks/PermissionProviderBuildTask.php
@@ -2,9 +2,13 @@

 namespace Sunnysideup\PermissionProvider\Tasks;

-use BuildTask;
-use Permission;
-use DB;
+
+
+
+use SilverStripe\Security\Permission;
+use SilverStripe\ORM\DB;
+use SilverStripe\Dev\BuildTask;
+




Writing changes for 3 files
✔✔✔