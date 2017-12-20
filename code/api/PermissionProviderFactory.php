<?php


class PermissionProviderFactory extends Object
{

    private static $_instance = null;

    public static function inst()
    {
        if(self::$_instance === null) {
            self::$_instance = Injector::inst()->get('PermissionProviderFactory');
        }

        return self::$_instance;
    }

    protected $email = '';
    protected $firstName = '';
    protected $surname = '';
    protected $code = '';
    protected $name = '';
    protected $parentGroup = null;
    protected $permissionCode = '';
    protected $roleTitle = '';
    protected $permissionArray = [];
    protected $member = null;

    public function setEmail($email){$this->email = $email; return $this;}
    public function setFirstName($firstName){$this->firstName = $firstName; return $this;}
    public function setSurname($surname){$this->surname = $surname; return $this;}
    public function setPassword($password){$this->password = $password; return $this;}
    public function setCode($code){$this->code = $code; return $this;}
    public function setName($name){$this->name = $name; return $this;}
    public function setParentGroup($parentGroup){$this->parentGroup = $parentGroup; return $this;}
    public function setPermissionCode($permissionCode){$this->permissionCode = $permissionCode; return $this;}
    public function setRoleTitle($roleTitle){$this->roleTitle = $roleTitle; return $this;}
    public function setPermissionArray($permissionArray){$this->permissionArray = $permissionArray; return $this;}
    public function setMember($member){$this->member = $member; return $this;}

    /**
     *
     * @return Group and member, using the default settings
     */
    function CreateGroupAndMember()
    {
        $member = $this->CreateDefaultMember(
            $this->email,
            $this->firstName,
            $this->surname,
            $this->password
        );
        $group = $this->CreateGroup(
            $this->code,
            $this->name,
            $this->parentGroup,
            $this->permissionCode,
            $this->roleTitle,
            $this->permissionArray,
            $member
        );

        return $group;
    }


    /**
     * Create a member
     * @param       string $email
     * @param       string $firstName   OPTIONAL
     * @param       string $surname     OPTIONAL
     * @param       string $password    OPTIONAL
     *
     * @return Member
     */
    public function CreateDefaultMember(
        $email,
        $firstName = '',
        $surname = '',
        $password = ''
    )
    {
        if(! $email) {$email = $this->email;}
        if(! $firstName) {$firstName = $this->firstName;}
        if(! $surname) {$surname = $this->surname;}
        if(! $password) {$password = $this->password;}

        if(! $email) {
            $baseURL = Director::absoluteBaseURL();
            $baseURL = str_replace('https://', '', $baseURL);
            $baseURL = str_replace('http://', '', $baseURL);
            $baseURL = trim( $baseURL, '/' );
            $email = 'random.email.'.rand(0,999999).'@'.$baseURL;
        }
        if(! $firstName) {
            $firstName = 'Default';
        }
        if(! $surname) {
            $surname = 'User';
        }

        $filter = array('Email' => $email);
        $member = DataObject::get_one(
            'Member',
            $filter,
            $cacheDataObjectGetOne = false
        );
        if (! $member) {
            $member = Member::create($filter);
        }

        $member->FirstName = $firstName;
        $member->Surname = $surname;
        $member->write();
        if ($password) {
            $member->changePassword($password);
            $member->PasswordExpiry = date('Y-m-d');
            $member->write();
        }

        return $member;
    }

    /**
     * set up a group with permissions, roles, etc...
     * also note that this class implements PermissionProvider.
     *
     * @param string          $code            code for the group - will always be converted to lowercase
     * @param string          $name            title for the group
     * @param Group | String  $parentGroup     group object that is the parent of the group. You can also provide a string (name / title of group)
     * @param string          $permissionCode  Permission Code for the group (e.g. CMS_DO_THIS_OR_THAT)
     * @param string          $roleTitle       Role Title - e.g. Store Manager
     * @param array           $permissionArray Permission Array - list of permission codes applied to the group
     * @param Member | String $member          Default Member added to the group (e.g. sales@mysite.co.nz). You can also provide an email address
     */
    public function CreateGroup(
        $code = '',
        $name,
        $parentGroup = null,
        $permissionCode = '',
        $roleTitle = '',
        array $permissionArray = [],
        $member = null
    )
    {
        if(! $name) {$name = $this->name;}
        if(! $code) {$code = $this->code;}
        if(! $parentGroup) { $parentGroup = $this->parentGroup;}
        if(! $permissionCode) { $permissionCode = $this->permissionCode;}
        if(! $permissionArray || count($permissionArray) === 0) { $permissionArray = $this->permissionArray;}
        if(! $member) { $member = $this->member;}
        if(!$name) {
            $name = 'New Group '.rand(0,999999);
        }
        if(!$code) {
            $code = $name;
        }
        $code = str_replace(' ', '_', $code);
        $code = preg_replace("/[\W_]+/u", '', $code);
        //changing to lower case seems to be very important
        //unidentified bug so far
        $code = strtolower($code);

        $filterArrayForGroup = array('Code' => $code);
        $groupDataList = Group::get()->filter($filterArrayForGroup);
        $groupCount = $groupDataList->count();
        $groupStyle = 'updated';
        if ($groupCount > 1) {
            user_error("There is more than one group with the $name ($code) Code");
        }
        if ($groupCount == 0) {
            $group = Group::create($filterArrayForGroup);
            $groupStyle = 'created';
        } else {
            $group = $groupDataList->First();
        }
        $group->Locked = 1;
        $group->Title = $name;
        $parentGroupStyle = 'updated';
        if ($parentGroup) {
            DB::alteration_message('adding parent group');
            if (is_string($parentGroup)) {
                $parentGroupName = $parentGroup;
                $parentGroup = DataObject::get_one(
                    'Group',
                    array('Title' => $parentGroupName),
                    $cacheDataObjectGetOne = false
                );
                if (!$parentGroup) {
                    $parentGroup = Group::create();
                    $parentGroupStyle = 'created';
                    $parentGroup->Title = $parentGroupName;
                    $parentGroup->write();
                    DB::alteration_message("$parentGroupStyle $parentGroupName", $parentGroupStyle);
                }
            }
            if ($parentGroup) {
                $group->ParentID = $parentGroup->ID;
            }
        }
        $group->write();
        DB::alteration_message("$groupStyle $name ($code) group", $groupStyle);
        $doubleGroups = Group::get()
            ->filter(array('Code' => $code))
            ->exclude(array('ID' => $group->ID));
        if ($doubleGroups->count()) {
            DB::alteration_message($doubleGroups->count().' groups with the same name', 'deleted');
            $realMembers = $group->Members();
            foreach ($doubleGroups as $doubleGroup) {
                $fakeMembers = $doubleGroup->Members();
                foreach ($fakeMembers as $fakeMember) {
                    DB::alteration_message('adding customers: '.$fakeMember->Email, 'created');
                    $realMembers->add($fakeMember);
                }
                DB::alteration_message('deleting double group ', 'deleted');
                $doubleGroup->delete();
            }
        }
        if ($permissionCode) {
            $permissionCodeCount = DB::query("SELECT * FROM \"Permission\" WHERE \"GroupID\" = '".$group->ID."' AND \"Code\" LIKE '".$permissionCode."'")->numRecords();
            if ($permissionCodeCount == 0) {
                DB::alteration_message('granting '.$name." permission code $permissionCode ", 'created');
                Permission::grant($group->ID, $permissionCode);
            } else {
                DB::alteration_message($name." permission code $permissionCode already granted");
            }
        }
        //we unset it here to avoid confusion with the
        //other codes we use later on
        $permissionArray[] = $permissionCode;
        unset($permissionCode);
        if ($roleTitle) {
            $permissionRoleCount = PermissionRole::get()
                ->Filter(array('Title' => $roleTitle))
                ->Count();
            if ($permissionRoleCount > 1) {
                db::alteration_message("There is more than one Permission Role with title $roleTitle ($permissionRoleCount)", 'deleted');
                $permissionRolesFirst = DataObject::get_one(
                    'PermissionRole',
                    array('Title' => $roleTitle),
                    $cacheDataObjectGetOne = false
                );
                $permissionRolesToDelete = PermissionRole::get()
                    ->Filter(array('Title' => $roleTitle))
                    ->Exclude(array('ID' => $permissionRolesFirst->ID));
                foreach ($permissionRolesToDelete as $permissionRoleToDelete) {
                    db::alteration_message("DELETING double permission role $roleTitle", 'deleted');
                    $permissionRoleToDelete->delete();
                }
            }
            elseif ($permissionRoleCount == 1) {
                //do nothing
                DB::alteration_message("$roleTitle role in place");
            } else {
                DB::alteration_message("adding $roleTitle role", 'created');
                $permissionRole = PermissionRole::create();
                $permissionRole->Title = $roleTitle;
                $permissionRole->OnlyAdminCanApply = true;
                $permissionRole->write();
            }
            $permissionRole = DataObject::get_one(
                'PermissionRole',
                array('Title' => $roleTitle),
                $cacheDataObjectGetOne = false
            );
            if ($permissionRole) {
                if (is_array($permissionArray) && count($permissionArray)) {
                    DB::alteration_message('working with '.implode(', ', $permissionArray));
                    foreach ($permissionArray as $permissionRoleCode) {
                        $permissionRoleCodeObject = DataObject::get_one(
                            'PermissionRoleCode',
                            array('Code' => $permissionRoleCode, 'RoleID' => $permissionRole->ID),
                            $cacheDataObjectGetOne = false
                        );
                        $permissionRoleCodeObjectCount = PermissionRoleCode::get()
                            ->Filter(array('Code' => $permissionRoleCode, 'RoleID' => $permissionRole->ID))
                            ->Count();
                        if ($permissionRoleCodeObjectCount > 1) {
                            $permissionRoleCodeObjectsToDelete = PermissionRoleCode::get()
                                ->Filter(array('Code' => $permissionRoleCode, 'RoleID' => $permissionRole->ID))
                                ->Exclude(array('ID' => $permissionRoleCodeObject->ID));
                            foreach ($permissionRoleCodeObjectsToDelete as $permissionRoleCodeObjectToDelete) {
                                db::alteration_message("DELETING double permission code $permissionRoleCode for ".$permissionRole->Title, 'deleted');
                                $permissionRoleCodeObjectToDelete->delete();
                            }
                            db::alteration_message('There is more than one Permission Role Code in '.$permissionRole->Title." with Code = $permissionRoleCode ($permissionRoleCodeObjectCount)", 'deleted');
                        }
                        elseif ($permissionRoleCodeObjectCount == 1) {
                            //do nothing
                        } else {
                            $permissionRoleCodeObject = PermissionRoleCode::create();
                            $permissionRoleCodeObject->Code = $permissionRoleCode;
                            $permissionRoleCodeObject->RoleID = $permissionRole->ID;
                        }
                        DB::alteration_message('adding '.$permissionRoleCodeObject->Code.' to '.$permissionRole->Title);
                        $permissionRoleCodeObject->write();
                    }
                }
                if ($group && $permissionRole) {
                    if (DB::query('SELECT COUNT(*) FROM Group_Roles WHERE GroupID = '.$group->ID.' AND PermissionRoleID = '.$permissionRole->ID)->value() == 0) {
                        db::alteration_message('ADDING '.$permissionRole->Title.' permission role  to '.$group->Title.' group', 'created');
                        $existingGroups = $permissionRole->Groups();
                        $existingGroups->add($group);
                    } else {
                        db::alteration_message('CHECKED '.$permissionRole->Title.' permission role  to '.$group->Title.' group');
                    }
                } else {
                    db::alteration_message('ERROR: missing group or permissionRole', 'deleted');
                }
            }
        }
        if ($member) {
            if (is_string($member)) {
                $email = $member;
                $member = $this->CreateDefaultMember($email, $code, $name);
            }
            if ($member) {
                DB::alteration_message(' adding member '.$member->Email.' to group '.$group->Title, 'created');
                $member->Groups()->add($group);
            }
        } else {
            DB::alteration_message('No user provided.');
        }

        return $group;
    }
}
