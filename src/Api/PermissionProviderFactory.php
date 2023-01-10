<?php

namespace Sunnysideup\PermissionProvider\Api;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\PermissionRole;
use SilverStripe\Security\PermissionRoleCode;
use Sunnysideup\PermissionProvider\Interfaces\PermissionProviderFactoryProvider;

class PermissionProviderFactory implements PermissionProvider
{
    use Injectable;
    use Configurable;

    /**
     * @var bool
     */
    protected static $debug = false;

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
     * other group codes you are keen to merge.
     *
     * @var array
     */
    protected $mergeGroupCodes = [];

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
    protected $otherRoleTitles = [];

    /**
     * @var array
     */
    protected $permissionArray = [];

    /**
     * @var Member|null
     */
    protected $member;

    /**
     * @var Group|null
     */
    protected $group;

    /**
     * @var PermissionRole|null
     */
    protected $permissionRole;

    /**
     * @var bool
     */
    protected $sendPasswordResetLink = true;

    /**
     * @var string
     */
    protected $subjectNew = 'your login details has been set up';

    /**
     * @var string
     */
    protected $subjectExisting = 'your login details have been updated';

    /**
     * @var bool
     */
    protected $isNewMember = false;

    /**
     * @var int
     */
    protected $sort = 0;

    /**
     * @var string
     */
    protected $description = '';

    public static function set_debug(bool $b = true)
    {
        self::$debug = $b;
    }

    public function providePermissions()
    {
        $permissions = [];
        $classNames = ClassInfo::implementorsOf(PermissionProviderFactoryProvider::class);
        foreach ($classNames as $className) {
            $group = $className::permission_provider_factory_runner();
            $parentGroup = $group->Parent();
            $category = $parentGroup && $parentGroup->exists() ? $parentGroup->Title : 'OTHER';
            $permissions[$group->MainPermissionCode] = [
                'name' => $group->Title,
                'category' => $category,
                'help' => $group->Description,
                'sort' => $group->Sort,
            ];
        }

        return $permissions;
    }

    public static function inst()
    {
        return new PermissionProviderFactory();
    }

    public function setEmail(string $email): PermissionProviderFactory
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): string
    {
        if (! $this->isEmail($this->email)) {
            $baseURL = Director::absoluteBaseURL();
            $baseURL = str_replace('https://', '', $baseURL);
            $baseURL = str_replace('http://', '', $baseURL);
            $baseURL = trim($baseURL, '/');
            $baseURL = trim($baseURL, '/');
            $before = strtolower($this->email ?: $this->getFirstName() . '.' . $this->getSurname());
            $before = strtolower(preg_replace('#[^\pL\pN]+#u', '-', $before));
            $this->email = $before . '@' . $baseURL;
        }

        return (string) $this->email;
    }

    public function setFirstName(string $firstName): PermissionProviderFactory
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName ?: 'Editor';
    }

    public function setSurname(string $surname): PermissionProviderFactory
    {
        $this->surname = $surname;

        return $this;
    }

    public function getSurname(): string
    {
        return $this->surname ?: $this->groupName;
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
        $this->code = $this->codeToCleanCode($code);

        return $this;
    }

    public function getCode(): string
    {
        if (! $this->code) {
            $this->code = $this->groupName;
            $this->code = str_replace(' ', '_', $this->code);
            $this->code = preg_replace('#[\\W_]+#u', '', $this->code);
            //changing to lower case seems to be very important
            //unidentified bug so far
            $this->code = $this->codeToCleanCode($this->code);
        }

        return $this->code;
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

    public function addMergeCode(string $code): PermissionProviderFactory
    {
        $this->mergeGroupCodes[] = $code;

        return $this;
    }

    public function addMergeCodes(array $array): PermissionProviderFactory
    {
        $this->mergeGroupCodes = array_merge([$this->mergeGroupCodes], $array);

        return $this;
    }

    public function setPermissionCode(string $permissionCode): PermissionProviderFactory
    {
        $this->permissionCode = $permissionCode;

        return $this;
    }

    public function getPermissionCode(): string
    {
        return $this->permissionCode ?: strtoupper('CMS_ACCESS_' . $this->getCode());
    }

    public function setRoleTitle(string $roleTitle): PermissionProviderFactory
    {
        $this->roleTitle = $roleTitle;

        return $this;
    }

    public function addRoleTitle(string $roleTitle): PermissionProviderFactory
    {
        $this->otherRoleTitles[] = $roleTitle;

        return $this;
    }

    public function addRoleTitles(array $array): PermissionProviderFactory
    {
        $this->otherRoleTitles = array_merge([$this->otherRoleTitles], $array);

        return $this;
    }

    public function getRoleTitle(): string
    {
        return $this->roleTitle ?: $this->groupName . ' Role';
    }

    public function setPermissionArray(array $permissionArray): PermissionProviderFactory
    {
        $this->permissionArray = $permissionArray;

        return $this;
    }

    public function setMember(Member $member): PermissionProviderFactory
    {
        $this->member = $member;

        return $this;
    }

    public function setDescription(string $string): PermissionProviderFactory
    {
        $this->description = $string;

        return $this;
    }

    public function setSort(int $int): PermissionProviderFactory
    {
        $this->sort = $int;

        return $this;
    }

    /**
     * @return Group and this->member, using the default settings
     */
    public function CreateGroupAndMember(): Group
    {
        $this->showDebugMessage('=== ' . __FUNCTION__ . ' ===');
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
        $this->showDebugMessage('=== ' . __FUNCTION__ . ' ===');
        $this->checkVariables();
        $filter = ['Email' => $this->getEmail()];
        $this->isNewMember = false;

        /** @property Member|null $member */
        $this->member = Member::get_one(
            Member::class,
            $filter,
            $cacheDataObjectGetOne = false
        );
        if (! $this->member) {
            $this->isNewMember = true;
            /** @property Member|null $member */
            $this->member = Member::create($filter);
        }

        $this->member->FirstName = $this->getFirstName();
        $this->member->Surname = $this->getSurname();
        $this->member->write();
        $this->updatePassword();

        return $this->member;
    }

    /**
     * set up a group with permissions, roles, etc...
     */
    public function CreateGroup(?Member $member = null): Group
    {
        $this->showDebugMessage('=== ' . __FUNCTION__ . ' ===');
        $this->checkVariables();
        if (null !== $member) {
            $this->member = $member;
        }

        if (! $this->getCode()) {
            user_error('No group code set for the creation of group');
        }

        $filterAnyArrayForGroup = ['Code' => $this->getCode(), 'Title' => $this->groupName];
        $groupDataList = Group::get()->filterAny($filterAnyArrayForGroup);
        $groupCount = $groupDataList->limit(2)->count();
        $groupStyle = 'updated';
        if ($groupCount > 1) {
            $this->showDebugMessage("There is more than one group with the {$this->getCode()} Code");
        }

        if (0 === $groupCount) {
            // @var Group|null $this->group
            $this->group = Group::create($filterAnyArrayForGroup);
            $this->group->write();
            $groupStyle = 'created';
        } else {
            // @var Group|null $this->group
            $this->group = $groupDataList->First();
        }

        $this->group->Locked = 1;
        $this->group->Title = $this->groupName;
        $this->group->Sort = $this->sort;
        $this->group->MainPermissionCode = $this->getPermissionCode();
        $this->group->Description = $this->description;
        $this->group->setCode($this->getCode());

        // remove the other ones before we save ...
        $this->checkDoubleGroups();
        $this->group->write();

        $this->showDebugMessage("{$groupStyle} {$this->groupName} ({$this->getCode()}) group", $groupStyle);

        $this->addOrUpdateParentGroup();
        $this->AddMemberToGroup($this->member);
        $this->grantPermissions();
        $this->addOrUpdateRole();
        $this->addPermissionsToRole();
        $this->addRoleToGroup();

        return $this->group;
    }

    public function AddMemberToGroup(?Member $member = null): PermissionProviderFactory
    {
        $this->checkVariables();
        if ($member instanceof Member) {
            $this->member = $member;
            $this->showDebugMessage(' adding: ' . $this->member->Email . ' to group ' . $this->group->Title, 'created');
            $this->member->Groups()->add($this->group);
        } else {
            $this->showDebugMessage('No user provided.');
        }

        return $this;
    }

    protected function isEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    protected function addOrUpdateParentGroup()
    {
        $parentGroupStyle = 'updated';
        if ($this->parentGroup) {
            $this->showDebugMessage('adding parent group');
            if (is_string($this->parentGroup)) {
                $parentGroupName = $this->parentGroup;
                if ($parentGroupName) {
                    $code = $this->codeToCleanCode($parentGroupName);
                    $filter = ['Title' => $parentGroupName, 'Code' => $code];
                    $this->parentGroup = Group::get()->filterAny($filter)->first();
                    if (null === $this->parentGroup) {
                        $this->parentGroup = Group::create($filter);
                        $parentGroupStyle = 'created';
                        $this->parentGroup->Title = $parentGroupName;
                        $this->parentGroup->write();
                        $this->showDebugMessage("{$parentGroupStyle} {$parentGroupName}");
                    }
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
        $groupCodes = [$this->getCode()];
        foreach ($this->mergeGroupCodes as $code) {
            $groupCodes[] = $code;
        }

        $groupCodes = array_filter($groupCodes);
        if (! empty($groupCodes) && $this->group && $this->group->ID) {
            $doubleGroups = Group::get()
                ->filter(['Code' => $groupCodes])
                ->exclude(['ID' => (int) $this->group->ID])
            ;
            if ($doubleGroups->exists()) {
                $this->showDebugMessage($doubleGroups->count() . ' groups with the same name', 'deleted');
                $realMembers = $this->group->Members();
                foreach ($doubleGroups as $doubleGroup) {
                    $wrongGroupMemberIds = $doubleGroup->Members()->columnUnique();
                    $this->showDebugMessage('adding members to right group ' . print_r($wrongGroupMemberIds), 'created');
                    $realMembers->addMany($wrongGroupMemberIds);
                    $doubleGroup->Members()->removeAll();
                    $this->showDebugMessage('deleting double group with code: ' . $doubleGroup->Code, 'deleted');
                    $doubleGroup->delete();
                }
            }
        }
    }

    protected function codeToCleanCode(string $code): string
    {
        $group = Injector::inst()->get(Group::class);
        $group->setCode($code);

        return $group->Code;
    }

    /**
     * add permission codes to group.
     */
    protected function grantPermissions()
    {
        $this->showDebugMessage('=== ' . __FUNCTION__ . ' ===');
        if ('' !== $this->getPermissionCode()) {
            $permissionCodeCount = (int) DB::query(
                "SELECT COUNT(*)
                FROM \"Permission\"
                WHERE \"GroupID\" = '" . $this->group->ID . "' AND \"Code\" LIKE '" . $this->getPermissionCode() . "'"
            )->value();
            if (0 === $permissionCodeCount) {
                $this->showDebugMessage('granting ' . $this->groupName . " permission code {$this->getPermissionCode()} ", 'created');
                Permission::grant($this->group->ID, $this->getPermissionCode());
            } else {
                $this->showDebugMessage($this->groupName . " permission code {$this->getPermissionCode()} already granted");
            }
        }

        $this->permissionArray[] = $this->getPermissionCode();
    }

    /**
     * create / update PermissionRole (role).
     */
    protected function addOrUpdateRole()
    {
        if ('' !== $this->getRoleTitle()) {
            $count = PermissionRole::get()
                ->Filter(['Title' => $this->getRoleTitle()])
                ->Count()
            ;
            if ($count > 1) {
                $this->showDebugMessage("There is more than one Permission Role with title {$this->getRoleTitle()} ({$count})", 'deleted');
                $permissionRolesFirst = DataObject::get_one(
                    PermissionRole::class,
                    ['Title' => $this->getRoleTitle()],
                    $cacheDataObjectGetOne = false
                );
                $permissionRolesToDelete = PermissionRole::get()
                    ->Filter(['Title' => $this->getRoleTitle()])
                    ->Exclude(['ID' => $permissionRolesFirst->ID])
                ;
                foreach ($permissionRolesToDelete as $permissionRoleToDelete) {
                    $this->showDebugMessage("DELETING double permission role {$this->getRoleTitle()}", 'deleted');
                    $permissionRoleToDelete->delete();
                }
            } elseif (1 === $count) {
                //do nothing
                $this->showDebugMessage("{$this->getRoleTitle()} role in place");
            } else {
                $this->showDebugMessage("adding {$this->getRoleTitle()} role", 'created');
                // @var PermissionRole|null $this->permissionRole
                $this->permissionRole = PermissionRole::create();
                $this->permissionRole->Title = $this->getRoleTitle();
                $this->permissionRole->OnlyAdminCanApply = true;
                $this->permissionRole->write();
            }

            if (! $this->permissionRole instanceof PermissionRole) {
                // @var PermissionRole|null $this->permissionRole
                $this->permissionRole = DataObject::get_one(
                    PermissionRole::class,
                    ['Title' => $this->getRoleTitle()],
                    $cacheDataObjectGetOne = false
                );
            }
        }
    }

    /**
     * add permission codes (PermissionRoleCode) to rol.
     */
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
                    $count = PermissionRoleCode::get()
                        ->Filter(['Code' => $permissionRoleCode, 'RoleID' => $this->permissionRole->ID])
                        ->Count()
                    ;
                    if ($count > 1) {
                        $permissionRoleCodeObjectsToDelete = PermissionRoleCode::get()
                            ->Filter(['Code' => $permissionRoleCode, 'RoleID' => $this->permissionRole->ID])
                            ->Exclude(['ID' => $permissionRoleCodeObject->ID])
                        ;
                        foreach ($permissionRoleCodeObjectsToDelete as $permissionRoleCodeObjectToDelete) {
                            $this->showDebugMessage("DELETING double permission code {$permissionRoleCode} for " . $this->permissionRole->Title, 'deleted');
                            $permissionRoleCodeObjectToDelete->delete();
                        }

                        $this->showDebugMessage('
                            There is more than one Permission Role Code in ' . $this->permissionRole->Title . "
                            with Code = {$permissionRoleCode} ({$count})", 'deleted');
                    } elseif (1 === $count) {
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
        $this->addRoleToGroupInner($this->permissionRole);
    }

    protected function addOtherRolesToGroup()
    {
        foreach ($this->otherRoleTitles as $roleObjectTitle) {
            $roleObject = DataObject::get_one(
                PermissionRole::class,
                ['Title' => $roleObjectTitle],
                $cacheDataObjectGetOne = true
            );
            $this->addRoleToGroupInner($roleObject);
        }
    }

    protected function addRoleToGroupInner($roleObject)
    {
        if ($this->group && $roleObject instanceof PermissionRole) {
            $count = (int) DB::query(
                'SELECT COUNT(*)
                FROM Group_Roles
                WHERE GroupID = ' . $this->group->ID . ' AND PermissionRoleID = ' . $roleObject->ID
            )->value();
            if (0 === $count) {
                $this->showDebugMessage('ADDING ' . $roleObject->Title . ' permission role  to ' . $this->group->Title . ' group', 'created');
                $existingGroups = $roleObject->Groups();
                $existingGroups->add($this->group);
            } else {
                $this->showDebugMessage('CHECKED ' . $roleObject->Title . ' permission role  to ' . $this->group->Title . ' group');
            }
        } else {
            $this->showDebugMessage('ERROR: missing group or roleObject', 'deleted');
        }
    }

    protected function checkVariables()
    {
        $this->copyFromMember();
        $requiredFields = [
            'groupName',
            'permissionCode',
        ];
        foreach ($requiredFields as $requiredField) {
            if (! $this->{$requiredField}) {
                user_error('Please provide ' . $requiredField);
            }
        }

        if ('' === $this->groupName) {
            $number = rand(0, 99999999);
            $this->groupName = 'New Group ' . $number;
        }
    }

    protected function copyFromMember()
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
        $subject = $this->isNewMember ? $this->subjectNew : $this->subjectExisting;
        $email = Email::create()
            ->setHTMLTemplate(self::class . 'UpdateEmail')
            ->setData(
                [
                    'Firstname' => $this->getFirstName(),
                    'Surname' => $this->getSurname(),
                    'Link' => $link,
                    'IsNew' => $this->isNewMember,
                    'AbsoluteUrl' => Director::absoluteURL('/'),
                ]
            )
            ->setFrom($from)
            ->setTo($this->getEmail())
            ->setSubject($subject)
        ;
        if ($email->send()) {
            //email sent successfully
        }

        // there may have been 1 or more failures
    }

    protected function showDebugMessage(string $message, $style = '')
    {
        if (self::$debug) {
            DB::alteration_message($message, $style);
        }
    }
}
