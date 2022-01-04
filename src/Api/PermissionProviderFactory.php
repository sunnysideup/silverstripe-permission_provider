<?php

namespace Sunnysideup\PermissionProvider\Api;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
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

    public $this;

    /**
     * @var mixed|\SilverStripe\ORM\DataList
     */
    public $groupDataList;

    /**
     * @var int
     */
    public $groupCount = 0;

    /**
     * @var mixed|string
     */
    public $parentGroupName;

    /**
     * @var int
     */
    public $permissionCodeCount = 0;

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
     * @var Group|string
     */
    protected $parentGroup;

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
     * @var Member
     */
    protected $member;

    /**
     * @var Group
     */
    protected $group;

    /**
     * @var bool
     */
    protected $sendPasswordResetLink = true;

    /**
     * @var string
     */
    protected $emailSubjectNew = 'your login details has been set up';

    /**
     * @var string
     */
    protected $emailSubjectExisting = 'your login details have been updated';

    /**
     * @var bool
     */
    protected $isNewMember = false;

    /**
     * @var PermissionRole
     */
    protected $permissionRole;

    private static $_instance;

    public static function inst()
    {
        if (null === self::$_instance) {
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

    public function setSendEmailAboutPassword(bool $b): PermissionProviderFactory
    {
        $this->sendPasswordResetLink = $b;

        return $this;
    }

    public function addRandomPassword(): PermissionProviderFactory
    {
        $pass = [];
        for ($i = 0; $i < 23; ++$i) {
            $pass[] = chr(rand(32, 126));
        }
        $this->password = implode('', $pass);

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

    public function setGroupName(string $groupName): PermissionProviderFactory
    {
        $this->groupName = $groupName;

        return $this;
    }

    /**
     * @param Group|string $parentGroup
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
     * Create a member.
     */
    public function CreateDefaultMember(): Member
    {
        $this->checkVariables();
        $filter = ['Email' => $this->email];
        $this->isNewMember = false;

        // @var Member|null $this
        $this->member = Member::get_one(
            Member::class,
            $filter,
            $cacheDataObjectGetOne = false
        );
        if (! $this->member) {
            $this->isNewMember = true;
            $this->member = Member::create($filter);
        }

        $this->member->FirstName = $this->firstName;
        $this->member->Surname = $this->surname;
        $this->member->write();
        $this->updatePassword();

        return $this->member;
    }

    /**
     * set up a group with permissions, roles, etc...
     */
    public function CreateGroup(?Member $member = null): Group
    {
        if (null !== $member) {
            $this->member = $member;
        }
        $this->checkVariables();
        if(! $this->code) {
            user_error('No group code set for the creation of group');
        }
        $filterArrayForGroup = ['Code' => $this->code];
        $this->groupDataList = Group::get()->filter($filterArrayForGroup);
        $this->groupCount = $this->groupDataList->limit(2)->count();
        $groupStyle = 'updated';
        if ($this->groupCount > 1) {
            user_error("There is more than one group with the {$this->groupName} ({$this->code}) Code", E_USER_ERROR);
        }
        if (0 === $this->groupCount) {
            $this->group = Group::create($filterArrayForGroup);
            $groupStyle = 'created';
        } else {
            $this->group = $this->groupDataList->First();
        }
        $this->group->Locked = 1;
        $this->group->Title = $this->groupName;
        $this->group->Code = strtolower($this->code);

        $this->showDebugMessage("{$groupStyle} {$this->groupName} ({$this->code}) group", $groupStyle);

        $this->addOrUpdateParentGroup();
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
        if (null !== $member) {
            $this->member = $member;
        }
        $this->checkVariables();
        if (null !== $this->member) {
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
                if (null === $this->parentGroup) {
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
            ->filter(['Title' => $this->groupName, 'Code' => ['', strtolower($this->code), $this->code]])
            ->exclude(['ID' => $this->group->ID])
        ;
        if ($doubleGroups->exists()) {
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
        if ('' !== $this->permissionCode) {
            $this->permissionCodeCount = DB::query("SELECT * FROM \"Permission\" WHERE \"GroupID\" = '" . $this->group->ID . "' AND \"Code\" LIKE '" . $this->permissionCode . "'")->numRecords();
            if (0 === $this->permissionCodeCount) {
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
        if ('' !== $this->roleTitle) {
            $permissionRoleCount = PermissionRole::get()
                ->Filter(['Title' => $this->roleTitle])
                ->Count()
            ;
            if ($permissionRoleCount > 1) {
                $this->showDebugMessage("There is more than one Permission Role with title {$this->roleTitle} ({$permissionRoleCount})", 'deleted');
                $permissionRolesFirst = DataObject::get_one(
                    PermissionRole::class,
                    ['Title' => $this->roleTitle],
                    $cacheDataObjectGetOne = false
                );
                $permissionRolesToDelete = PermissionRole::get()
                    ->Filter(['Title' => $this->roleTitle])
                    ->Exclude(['ID' => $permissionRolesFirst->ID])
                ;
                foreach ($permissionRolesToDelete as $permissionRoleToDelete) {
                    $this->showDebugMessage("DELETING double permission role {$this->roleTitle}", 'deleted');
                    $permissionRoleToDelete->delete();
                }
            } elseif (1 === $permissionRoleCount) {
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
        if (null !== $this->permissionRole) {
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
                        ->Count()
                    ;
                    if ($permissionRoleCodeObjectCount > 1) {
                        $permissionRoleCodeObjectsToDelete = PermissionRoleCode::get()
                            ->Filter(['Code' => $permissionRoleCode, 'RoleID' => $this->permissionRole->ID])
                            ->Exclude(['ID' => $permissionRoleCodeObject->ID])
                        ;
                        foreach ($permissionRoleCodeObjectsToDelete as $permissionRoleCodeObjectToDelete) {
                            $this->showDebugMessage("DELETING double permission code {$permissionRoleCode} for " . $this->permissionRole->Title, 'deleted');
                            $permissionRoleCodeObjectToDelete->delete();
                        }
                        $this->showDebugMessage('There is more than one Permission Role Code in ' . $this->permissionRole->Title . " with Code = {$permissionRoleCode} ({$permissionRoleCodeObjectCount})", 'deleted');
                    } elseif (1 === $permissionRoleCodeObjectCount) {
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
            $count = (int) $count;
            if (0 === $count) {
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
            if ('' === $this->email) {
                $this->email = $this->member->Email;
            }
            if ('' === $this->firstName) {
                $this->firstName = $this->member->FirstName;
            }
            if ('' === $this->surname) {
                $this->surname = $this->member->Surname;
            }
        }
        if ('' === $this->email) {
            $baseURL = Director::absoluteBaseURL();
            $baseURL = str_replace('https://', '', $baseURL);
            $baseURL = str_replace('http://', '', $baseURL);
            $baseURL = trim($baseURL, '/');
            $this->email = 'random.email.' . rand(0, 999999) . '@' . $baseURL;
        }

        if ('' === $this->firstName) {
            $this->firstName = 'Default';
        }

        if ('' === $this->surname) {
            $this->surname = 'User';
        }

        if ('' === $this->groupName) {
            $number = rand(0, 99999999);
            $this->groupName = 'New Group ' . $number;
        }
        if ('' === $this->code) {
            $this->createCodeFromName();
        }
    }

    protected function createCodeFromName()
    {
        $this->code = $this->groupName;
        $this->code = str_replace(' ', '_', $this->code);
        $this->code = preg_replace('#[\\W_]+#u', '', $this->code);
        //changing to lower case seems to be very important
        //unidentified bug so far
        $this->code = strtolower($this->code);
    }

    protected function updatePassword()
    {
        if ($this->isNewMember && ! $this->password) {
            $this->addRandomPassword();
        }
        if ('' !== $this->password) {
            if ($this->isNewMember || $this->replaceExistingPassword) {
                $this->member->changePassword($this->password);
                $this->member->PasswordExpiry = date('Y-m-d');
                $this->member->write();
                if ($this->sendPasswordResetLink) {
                    $this->sendEmailToMember();
                }
            }
        }
    }

    protected function sendEmailToMember()
    {
        $link = Director::absoluteURL('Security/lostpassword');
        $from = Config::inst()->get(Email::class, 'admin_email');
        $subject = $this->isNewMember ? $this->emailSubjectNew : $this->emailSubjectExisting;
        $email = Email::create()
            ->setHTMLTemplate(self::class . 'UpdateEmail')
            ->setData(
                [
                    'Firstname' => $this->firstName,
                    'Surname' => $this->surname,
                    'Link' => $link,
                    'IsNew' => $this->isNewMember,
                    'AbsoluteUrl' => Director::absoluteURL('/'),
                ]
            )
            ->setFrom($from)
            ->setTo($this->email)
            ->setSubject($subject)
        ;
        if ($email->send()) {
            //email sent successfully
        }
        // there may have been 1 or more failures
    }

    protected function showDebugMessage(string $message, $style = '')
    {
        if ($this->debug) {
            DB::alteration_message($message, $style);
        }
    }
}
