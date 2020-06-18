<?php

namespace Sunnysideup\PermissionProvider\Api;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionRole;
use SilverStripe\Security\PermissionRoleCode;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

class PermissionProviderFactory
{
    use Extensible;
    use Injectable;
    use Configurable;

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
     * @var string
     */
    protected $code = '';

    /**
     * @var string
     */
    protected $name = '';

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

    public function setCode(string $code): PermissionProviderFactory
    {
        $this->code = $code;

        return $this;
    }

    public function setName(string $name): PermissionProviderFactory
    {
        $this->name = $name;

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
        $this->{$this}->permissionArray = $this->permissionArray;

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
     * @param       boolean $replaceExistingPassword    OPTIONAL
     *
     * @return Member
     */
    public function CreateDefaultMember(?bool $replaceExistingPassword = false): Member
    {
        $this->checkVariables();
        $filter = ['Email' => $this->email];
        $memberExists = true;

        /** @var Member|null */
        $this->member = Member::get_one(
            Member::class,
            $filter,
            $cacheDataObjectGetOne = false
        );
        if (! $this->member) {
            $memberExists = false;
            $this->member = Member::create($filter);
        }

        $this->member->FirstName = $this->firstName;
        $this->member->Surname = $this->surname;
        $this->member->write();
        if (($this->password && ! $memberExists) || ($this->password && $replaceExistingPassword)) {
            $this->member->changePassword($this->password);
            $this->member->PasswordExpiry = date('Y-m-d');
            $this->member->write();
        }

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
        $this->groupStyle = 'updated';
        if ($this->groupCount > 1) {
            user_error("There is more than one group with the {$this->name} ({$this->code}) Code");
        }
        if ($this->groupCount === 0) {
            $this->group = Group::create($filterArrayForGroup);
            $this->groupStyle = 'created';
        } else {
            $this->group = $this->groupDataList->First();
        }
        $this->group->Locked = 1;
        $this->group->Title = $this->name;
        $this->parentGroupStyle = 'updated';
        if ($this->parentGroup) {
            DB::alteration_message('adding parent group');
            if (is_string($this->parentGroup)) {
                $this->parentGroupName = $this->parentGroup;
                $this->parentGroup = DataObject::get_one(
                    Group::class,
                    ['Title' => $this->parentGroupName],
                    $cacheDataObjectGetOne = false
                );
                if (! $this->parentGroup) {
                    $this->parentGroup = Group::create();
                    $this->parentGroupStyle = 'created';
                    $this->parentGroup->Title = $this->parentGroupName;
                    $this->parentGroup->write();
                    DB::alteration_message("{$this->parentGroupStyle} {$this->parentGroupName}", $this->parentGroupStyle);
                }
            }
            if ($this->parentGroup) {
                $this->group->ParentID = $this->parentGroup->ID;
            }
        }
        $this->group->write();
        DB::alteration_message("{$this->groupStyle} {$this->name} ({$this->code}) group", $this->groupStyle);

        $this->checkDoubleGroups();
        $this->addMemberToGroup();
        $this->grantPermissions();

        return $this->group;
    }

    public function AddMemberToGroup(?Member $member = null)
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
            if ($this->member) {
                DB::alteration_message(' adding this->member ' . $this->member->Email . ' to group ' . $this->group->Title, 'created');
                $this->member->Groups()->add($this->group);
            }
        } else {
            DB::alteration_message('No user provided.');
        }
    }

    protected function checkDoubleGroups(): void
    {
        $doubleGroups = Group::get()
            ->filter(['Code' => $this->code])
            ->exclude(['ID' => $this->group->ID]);
        if ($doubleGroups->count()) {
            DB::alteration_message($doubleGroups->count() . ' groups with the same name', 'deleted');
            $realMembers = $this->group->Members();
            foreach ($doubleGroups as $doubleGroup) {
                $fakeMembers = $doubleGroup->Members();
                foreach ($fakeMembers as $fakeMember) {
                    DB::alteration_message('adding customers: ' . $fakeMember->Email, 'created');
                    $realMembers->add($fakeMember);
                }
                DB::alteration_message('deleting double group ', 'deleted');
                $doubleGroup->delete();
            }
        }
    }

    protected function grantPermissions()
    {
        if ($this->permissionCode) {
            $this->permissionCodeCount = DB::query("SELECT * FROM \"Permission\" WHERE \"GroupID\" = '" . $this->group->ID . "' AND \"Code\" LIKE '" . $this->permissionCode . "'")->numRecords();
            if ($this->permissionCodeCount === 0) {
                DB::alteration_message('granting ' . $this->name . " permission code {$this->permissionCode} ", 'created');
                Permission::grant($this->group->ID, $this->permissionCode);
            } else {
                DB::alteration_message($this->name . " permission code {$this->permissionCode} already granted");
            }
        }
        //we unset it here to avoid confusion with the
        //other codes we use later on
        $this->permissionArray[] = $this->permissionCode;
        unset($this->permissionCode);
        if ($this->roleTitle) {
            $permissionRoleCount = PermissionRole::get()
                ->Filter(['Title' => $this->roleTitle])
                ->Count();
            if ($permissionRoleCount > 1) {
                DB::alteration_message("There is more than one Permission Role with title {$this->roleTitle} (${permissionRoleCount})", 'deleted');
                $permissionRolesFirst = DataObject::get_one(
                    PermissionRole::class,
                    ['Title' => $this->roleTitle],
                    $cacheDataObjectGetOne = false
                );
                $permissionRolesToDelete = PermissionRole::get()
                    ->Filter(['Title' => $this->roleTitle])
                    ->Exclude(['ID' => $permissionRolesFirst->ID]);
                foreach ($permissionRolesToDelete as $permissionRoleToDelete) {
                    DB::alteration_message("DELETING double permission role {$this->roleTitle}", 'deleted');
                    $permissionRoleToDelete->delete();
                }
            } elseif ($permissionRoleCount === 1) {
                //do nothing
                DB::alteration_message("{$this->roleTitle} role in place");
            } else {
                DB::alteration_message("adding {$this->roleTitle} role", 'created');
                $permissionRole = PermissionRole::create();
                $permissionRole->Title = $this->roleTitle;
                $permissionRole->OnlyAdminCanApply = true;
                $permissionRole->write();
            }
            $permissionRole = DataObject::get_one(
                PermissionRole::class,
                ['Title' => $this->roleTitle],
                $cacheDataObjectGetOne = false
            );
            if ($permissionRole) {
                if (is_array($this->permissionArray) && count($this->permissionArray)) {
                    DB::alteration_message('working with ' . implode(', ', $this->permissionArray));
                    foreach ($this->permissionArray as $permissionRoleCode) {
                        $permissionRoleCodeObject = DataObject::get_one(
                            PermissionRoleCode::class,
                            ['Code' => $permissionRoleCode, 'RoleID' => $permissionRole->ID],
                            $cacheDataObjectGetOne = false
                        );
                        $permissionRoleCodeObjectCount = PermissionRoleCode::get()
                            ->Filter(['Code' => $permissionRoleCode, 'RoleID' => $permissionRole->ID])
                            ->Count();
                        if ($permissionRoleCodeObjectCount > 1) {
                            $permissionRoleCodeObjectsToDelete = PermissionRoleCode::get()
                                ->Filter(['Code' => $permissionRoleCode, 'RoleID' => $permissionRole->ID])
                                ->Exclude(['ID' => $permissionRoleCodeObject->ID]);
                            foreach ($permissionRoleCodeObjectsToDelete as $permissionRoleCodeObjectToDelete) {
                                DB::alteration_message("DELETING double permission code ${permissionRoleCode} for " . $permissionRole->Title, 'deleted');
                                $permissionRoleCodeObjectToDelete->delete();
                            }
                            DB::alteration_message('There is more than one Permission Role Code in ' . $permissionRole->Title . " with Code = ${permissionRoleCode} (${permissionRoleCodeObjectCount})", 'deleted');
                        } elseif ($permissionRoleCodeObjectCount === 1) {
                            //do nothing
                        } else {
                            $permissionRoleCodeObject = PermissionRoleCode::create();
                            $permissionRoleCodeObject->Code = $permissionRoleCode;
                            $permissionRoleCodeObject->RoleID = $permissionRole->ID;
                        }
                        DB::alteration_message('adding ' . $permissionRoleCodeObject->Code . ' to ' . $permissionRole->Title);
                        $permissionRoleCodeObject->write();
                    }
                }
                if ($this->group && $permissionRole) {
                    $count = DB::query('SELECT COUNT(*) FROM Group_Roles WHERE GroupID = ' . $this->group->ID . ' AND PermissionRoleID = ' . $permissionRole->ID)->value();
                    $count = intval($count);
                    if ($count === 0) {
                        DB::alteration_message('ADDING ' . $permissionRole->Title . ' permission role  to ' . $this->group->Title . ' group', 'created');
                        $existingGroups = $permissionRole->Groups();
                        $existingGroups->add($this->group);
                    } else {
                        DB::alteration_message('CHECKED ' . $permissionRole->Title . ' permission role  to ' . $this->group->Title . ' group');
                    }
                } else {
                    DB::alteration_message('ERROR: missing group or permissionRole', 'deleted');
                }
            }
        }
    }

    protected function checkVariables()
    {
        if ($this->member) {
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

        if (! $this->name) {
            $number = rand(0, 99999999);
            $this->name = 'New Group ' . $number;
        }
        if (! $this->code) {
            $this->code = $this->name;
        }
        $this->code = str_replace(' ', '_', $this->code);
        $this->code = preg_replace("/[\W_]+/u", '', $this->code);
        //changing to lower case seems to be very important
        //unidentified bug so far
        $this->code = strtolower($this->code);
    }
}
