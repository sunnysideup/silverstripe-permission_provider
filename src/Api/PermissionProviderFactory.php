<?php

namespace Sunnysideup\PermissionProvider\Api;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionRole;
use SilverStripe\Security\PermissionRoleCode;

class PermissionProviderFactory
{
    use Injectable;
    use Configurable;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var string
     */
    protected $email = '';

    /**
     * @var string
     */
    protected $firstName = '';

    /**
     * @var string
     */
    protected $surname = '';

    /**
     * @var string
     */
    protected $password = '';

    /**
     * @var bool
     */
    protected $replaceExistingPassword = false;

    /**
     * @var string
     */
    protected $code = '';

    /**
     * @var string
     */
    protected $groupName = '';

    /**
     * @var string|Group
     */
    protected $parentGroup = null;

    /**
     * @var string
     */
    protected $permissionCode = '';

    /**
     * @var string
     */
    protected $roleTitle = '';

    /**
     * @var array
     */
    protected $permissionArray = [];

    /**
     * @var Member|null
     */
    protected $member = null;

    /**
     * @var Group|null
     */
    protected $group = null;

    /**
     * @var PermissionRole|null
     */
    protected $permissionRole = null;

    private static $_instance = null;

    public static function inst()
    {
        if (self::$_instance === null) {
            self::$_instance = Injector::inst()->get(PermissionProviderFactory::class);
        }

        return self::$_instance;
    }

    public function setEmail(string $email): PermissionProviderFactory
    {
        $this->email = $email;
        return $this;
    }

    public function setFirstName(string $firstName): PermissionProviderFactory
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function setSurname(string $surname): PermissionProviderFactory
    {
        $this->surname = $surname;

        return $this;
    }

    public function setPassword(string $password): PermissionProviderFactory
    {
        $this->password = $password;

        return $this;
    }

    public function addRandomPassword(): PermissionProviderFactory
    {
        $pass = [];
        for ($i = 0; $i < 23; $i++) {
            $pass[] = chr(rand(32, 126));
        }
        $this->password = implode($pass);

        return $this;
    }

    public function setReplaceExistingPassword(bool $b): PermissionProviderFactory
    {
        $this->replaceExistingPassword = $b;

        return $this;
    }

    public function setCode(string $code): PermissionProviderFactory
    {
        $this->code = $code;

        return $this;
    }

    public function setName(string $groupName): PermissionProviderFactory
    {
        $this->groupName = $groupName;

        return $this;
    }

    /**
     * @param string|Group $parentGroup
     * @return PermissionProviderFactory
     */
    public function setParentGroup($parentGroup): PermissionProviderFactory
    {
        $this->parentGroup = $parentGroup;

        return $this;
    }

    public function setPermissionCode(string $permissionCode): PermissionProviderFactory
    {
        $this->permissionCode = $permissionCode;

        return $this;
    }

    public function setRoleTitle(string $roleTitle): PermissionProviderFactory
    {
        $this->roleTitle = $roleTitle;

        return $this;
    }

    public function setPermissionArray(array $permissionArray): PermissionProviderFactory
    {
        $this->permissionArray = $permissionArray;

        return $this;
    }

    public function setMember(Member $member): PermissionProviderFactory
    {
        $this->this->member = $member;

        return $this;
    }

    /**
     * @return Group and this->member, using the default settings
     */
    public function CreateGroupAndMember(): Group
    {
        $this->checkVariables();
        $this->member = $this->CreateDefaultMember();
        $this->group = $this->CreateGroup($this->member);

        return $this->group;
    }

    /**
     * Create a member
     *
     * @return Member
     */
    public function CreateDefaultMember(): Member
    {
        $this->checkVariables();
        $filter = ['Email' => $this->email];
        $newMember = false;

        /** @var Member|null */
        $this->member = Member::get_one(
            Member::class,
            $filter,
            $cacheDataObjectGetOne = false
        );
        if (! $this->member) {
            $newMember = true;
            $this->member = Member::create($filter);
        }

        $this->member->FirstName = $this->firstName;
        $this->member->Surname = $this->surname;
        $this->member->write();
        $this->updatePassword($newMember);

        /** @var Member */
        return $this->member;
    }

    /**
     * set up a group with permissions, roles, etc...
     */
    public function CreateGroup(?Member $member = null): Group
    {
        if ($member) {
            $this->member = $member;
        }
        $this->checkVariables();

        $filterArrayForGroup = ['Code' => $this->code];
        $this->groupDataList = Group::get()->filter($filterArrayForGroup);
        $this->groupCount = $this->groupDataList->count();
        $groupStyle = 'updated';
        if ($this->groupCount > 1) {
            user_error("There is more than one group with the {$this->groupName} ({$this->code}) Code", E_USER_ERROR);
        }
        if ($this->groupCount === 0) {
            $this->group = Group::create($filterArrayForGroup);
            $groupStyle = 'created';
        } else {
            $this->group = $this->groupDataList->First();
        }
        $this->group->Locked = 1;
        $this->group->Title = $this->groupName;

        $this->addOrUpdateParentGroup();

        $this->showDebugMessage("{$groupStyle} {$this->groupName} ({$this->code}) group", $groupStyle);

        $this->checkDoubleGroups();
        $this->addMemberToGroup();
        $this->grantPermissions();
        $this->addOrUpdateRole();
        $this->addPermissionsToRole();
        $this->addRoleToGroup();

        return $this->group;
    }

    public function AddMemberToGroup(?Member $member = null): PermissionProviderFactory
    {
        if ($member) {
            $this->member = $member;
        }
        $this->checkVariables();
        if ($this->member) {
            if (is_string($this->member)) {
                $this->email = $this->member;
                $this->member = $this->CreateDefaultMember();
            }
            $this->showDebugMessage(' adding this->member ' . $this->member->Email . ' to group ' . $this->group->Title, 'created');
            $this->member->Groups()->add($this->group);
        } else {
            $this->showDebugMessage('No user provided.');
        }

        return $this;
    }

    protected function addOrUpdateParentGroup()
    {
        $parentGroupStyle = 'updated';
        if ($this->parentGroup) {
            $this->showDebugMessage('adding parent group');
            if (is_string($this->parentGroup)) {
                $this->parentGroupName = $this->parentGroup;
                $this->parentGroup = DataObject::get_one(
                    Group::class,
                    ['Title' => $this->parentGroupName],
                    $cacheDataObjectGetOne = false
                );
                if (! $this->parentGroup) {
                    $this->parentGroup = Group::create();
                    $parentGroupStyle = 'created';
                    $this->parentGroup->Title = $this->parentGroupName;
                    $this->parentGroup->write();
                    $this->showDebugMessage("{$parentGroupStyle} {$this->parentGroupName}");
                }
            }
            if ($this->parentGroup instanceof Group) {
                $this->group->ParentID = $this->parentGroup->ID;
                $this->group->write();
            }
        }
    }

    protected function checkDoubleGroups(): void
    {
        $doubleGroups = Group::get()
            ->filter(['Code' => $this->code])
            ->exclude(['ID' => $this->group->ID]);
        if ($doubleGroups->count()) {
            $this->showDebugMessage($doubleGroups->count() . ' groups with the same name', 'deleted');
            $realMembers = $this->group->Members();
            foreach ($doubleGroups as $doubleGroup) {
                $fakeMembers = $doubleGroup->Members();
                foreach ($fakeMembers as $fakeMember) {
                    $this->showDebugMessage('adding customers: ' . $fakeMember->Email, 'created');
                    $realMembers->add($fakeMember);
                }
                $this->showDebugMessage('deleting double group ', 'deleted');
                $doubleGroup->delete();
            }
        }
    }

    protected function grantPermissions()
    {
        if ($this->permissionCode) {
            $this->permissionCodeCount = DB::query("SELECT * FROM \"Permission\" WHERE \"GroupID\" = '" . $this->group->ID . "' AND \"Code\" LIKE '" . $this->permissionCode . "'")->numRecords();
            if ($this->permissionCodeCount === 0) {
                $this->showDebugMessage('granting ' . $this->groupName . " permission code {$this->permissionCode} ", 'created');
                Permission::grant($this->group->ID, $this->permissionCode);
            } else {
                $this->showDebugMessage($this->groupName . " permission code {$this->permissionCode} already granted");
            }
        }
        //we unset it here to avoid confusion with the
        //other codes we use later on
        $this->permissionArray[] = $this->permissionCode;
        unset($this->permissionCode);
    }

    protected function addOrUpdateRole()
    {
        if ($this->roleTitle) {
            $permissionRoleCount = PermissionRole::get()
                ->Filter(['Title' => $this->roleTitle])
                ->Count();
            if ($permissionRoleCount > 1) {
                $this->showDebugMessage("There is more than one Permission Role with title {$this->roleTitle} (${permissionRoleCount})", 'deleted');
                $permissionRolesFirst = DataObject::get_one(
                    PermissionRole::class,
                    ['Title' => $this->roleTitle],
                    $cacheDataObjectGetOne = false
                );
                $permissionRolesToDelete = PermissionRole::get()
                    ->Filter(['Title' => $this->roleTitle])
                    ->Exclude(['ID' => $permissionRolesFirst->ID]);
                foreach ($permissionRolesToDelete as $permissionRoleToDelete) {
                    $this->showDebugMessage("DELETING double permission role {$this->roleTitle}", 'deleted');
                    $permissionRoleToDelete->delete();
                }
            } elseif ($permissionRoleCount === 1) {
                //do nothing
                $this->showDebugMessage("{$this->roleTitle} role in place");
            } else {
                $this->showDebugMessage("adding {$this->roleTitle} role", 'created');
                $this->permissionRole = PermissionRole::create();
                $this->permissionRole->Title = $this->roleTitle;
                $this->permissionRole->OnlyAdminCanApply = true;
                $this->permissionRole->write();
            }
            $this->permissionRole = DataObject::get_one(
                PermissionRole::class,
                ['Title' => $this->roleTitle],
                $cacheDataObjectGetOne = false
            );
        }
    }

    protected function addPermissionsToRole()
    {
        if ($this->permissionRole) {
            if (is_array($this->permissionArray) && count($this->permissionArray)) {
                $this->showDebugMessage('working with ' . implode(', ', $this->permissionArray));
                foreach ($this->permissionArray as $permissionRoleCode) {
                    $permissionRoleCodeObject = DataObject::get_one(
                        PermissionRoleCode::class,
                        ['Code' => $permissionRoleCode, 'RoleID' => $this->permissionRole->ID],
                        $cacheDataObjectGetOne = false
                    );
                    $permissionRoleCodeObjectCount = PermissionRoleCode::get()
                        ->Filter(['Code' => $permissionRoleCode, 'RoleID' => $this->permissionRole->ID])
                        ->Count();
                    if ($permissionRoleCodeObjectCount > 1) {
                        $permissionRoleCodeObjectsToDelete = PermissionRoleCode::get()
                            ->Filter(['Code' => $permissionRoleCode, 'RoleID' => $this->permissionRole->ID])
                            ->Exclude(['ID' => $permissionRoleCodeObject->ID]);
                        foreach ($permissionRoleCodeObjectsToDelete as $permissionRoleCodeObjectToDelete) {
                            $this->showDebugMessage("DELETING double permission code ${permissionRoleCode} for " . $this->permissionRole->Title, 'deleted');
                            $permissionRoleCodeObjectToDelete->delete();
                        }
                        $this->showDebugMessage('There is more than one Permission Role Code in ' . $this->permissionRole->Title . " with Code = ${permissionRoleCode} (${permissionRoleCodeObjectCount})", 'deleted');
                    } elseif ($permissionRoleCodeObjectCount === 1) {
                        //do nothing
                    } else {
                        $permissionRoleCodeObject = PermissionRoleCode::create();
                        $permissionRoleCodeObject->Code = $permissionRoleCode;
                        $permissionRoleCodeObject->RoleID = $this->permissionRole->ID;
                    }
                    $this->showDebugMessage('adding ' . $permissionRoleCodeObject->Code . ' to ' . $this->permissionRole->Title);
                    $permissionRoleCodeObject->write();
                }
            }
        }
    }

    protected function addRoleToGroup()
    {
        if ($this->group && $this->permissionRole) {
            $count = DB::query('SELECT COUNT(*) FROM Group_Roles WHERE GroupID = ' . $this->group->ID . ' AND PermissionRoleID = ' . $this->permissionRole->ID)->value();
            $count = intval($count);
            if ($count === 0) {
                $this->showDebugMessage('ADDING ' . $this->permissionRole->Title . ' permission role  to ' . $this->group->Title . ' group', 'created');
                $existingGroups = $this->permissionRole->Groups();
                $existingGroups->add($this->group);
            } else {
                $this->showDebugMessage('CHECKED ' . $this->permissionRole->Title . ' permission role  to ' . $this->group->Title . ' group');
            }
        } else {
            $this->showDebugMessage('ERROR: missing group or this->permissionRole', 'deleted');
        }
    }

    protected function checkVariables()
    {
        if ($this->member && $this->member instanceof Member) {
            if (! $this->email) {
                $this->email = $this->member->Email;
            }
            if (! $this->firstName) {
                $this->firstName = $this->member->FirstName;
            }
            if (! $this->surname) {
                $this->surname = $this->member->Surname;
            }
        }
        if (! $this->email) {
            $baseURL = Director::absoluteBaseURL();
            $baseURL = str_replace('https://', '', $baseURL);
            $baseURL = str_replace('http://', '', $baseURL);
            $baseURL = trim($baseURL, '/');
            $this->email = 'random.email.' . rand(0, 999999) . '@' . $baseURL;
        }

        if (! $this->firstName) {
            $this->firstName = 'Default';
        }

        if (! $this->surname) {
            $this->surname = 'User';
        }

        if (! $this->groupName) {
            $number = rand(0, 99999999);
            $this->groupName = 'New Group ' . $number;
        }
        if (! $this->code) {
            $this->createCodeFromName();
        }
    }

    protected function createCodeFromName()
    {
        $this->code = $this->groupName;
        $this->code = str_replace(' ', '_', $this->code);
        $this->code = preg_replace("/[\W_]+/u", '', $this->code);
        //changing to lower case seems to be very important
        //unidentified bug so far
        $this->code = strtolower($this->code);
    }

    protected function updatePassword(bool $newMember)
    {
        if ($newMember && ! $this->password) {
            $this->addRandomPassword();
        }
        if ($this->password) {
            if ($newMember || $this->replaceExistingPassword) {
                $this->member->changePassword($this->password);
                $this->member->PasswordExpiry = date('Y-m-d');
                $this->member->write();
            }
        }
    }

    protected function showDebugMessage(string $message, $style = '')
    {
        if ($this->debug) {
            DB::alteration_message($message, $style);
        }
    }
}
