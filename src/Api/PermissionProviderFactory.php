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
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\PermissionRole;
use SilverStripe\Security\PermissionRoleCode;

/** @property null|Member $member */
class PermissionProviderFactory implements PermissionProvider
{
    use Injectable;
    use Configurable;

    protected static bool $debug = false;
    protected string $email = '';
    protected string $firstName = '';
    protected string $surname = '';
    protected string $password = '';
    protected bool $replaceExistingPassword = false;
    protected bool $forcePasswordReset = true;
    protected string $code = '';
    protected string $groupName = '';
    protected Group|string $parentGroup;
    protected array $mergeGroupCodes = [];
    protected string $permissionCode = '';
    protected string $roleTitle = '';
    protected array $otherRoleTitles = [];
    protected array $permissionArray = [];
    protected ?Member $member = null;
    protected ?Group $group = null;
    protected ?PermissionRole $permissionRole = null;
    protected bool $sendPasswordResetLink = true;
    protected string $subjectNew = 'your login details has been set up';
    protected string $subjectExisting = 'your login details have been updated';
    protected bool $isNewMember = false;
    protected int $sort = 0;
    protected string $description = '';

    public static function set_debug(bool $b = true)
    {
        self::$debug = $b;
    }

    public function providePermissions()
    {
        $permissions = [];
        $groups = Group::get()->filter(['MainPermissionCode:not' => ['', null]]);
        foreach ($groups as $group) {
            $category = 'Group based permissions';
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
        return Injector::inst()->create(PermissionProviderFactory::class);
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
            $baseURL = str_replace('https://', '', (string) $baseURL);
            $baseURL = str_replace('http://', '', (string) $baseURL);
            $baseURL = trim((string) $baseURL, '/');
            $baseURL = trim($baseURL, '/');
            $before = strtolower($this->email ?: $this->getFirstName() . '.' . $this->getSurname());
            $before = strtolower(preg_replace('#[^\pL\pN]+#u', '-', $before));
            $this->email = $before . '@' . $baseURL;
        }

        return $this->email;
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

    public function setForcePasswordReset(bool $b): PermissionProviderFactory
    {
        $this->forcePasswordReset = $b;

        return $this;
    }

    public function setCode(string $code): PermissionProviderFactory
    {
        $this->code = $this->codeToCleanCode($code);
        if ($this->code !== $code) {
            user_error(
                'Please provide a code that will not be changed to avoid unexpected results.
                The current code ' . $code . ' changed to ' . $this->code . '.'
            );
        }

        return $this;
    }

    public function getCode(): string
    {
        if ($this->code === '' || $this->code === '0') {
            $this->code = $this->groupName;
            $this->code = str_replace(' ', '_', $this->code);
            $this->code = preg_replace('#[\\W_]+#u', '', (string) $this->code);
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
        $this->validatePermissionCodes();
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

        // @property Member|null $member
        $this->member = Member::get_one(
            Member::class,
            $filter,
            $cacheDataObjectGetOne = false
        );
        if (! $this->member) {
            $this->isNewMember = true;
            // @property Member $member
            $this->member = Member::create($filter);
        }

        $this->member->FirstName = $this->getFirstName();
        $this->member->Surname = $this->getSurname();
        $this->member->IsPermissionProviderCreated = true;
        $this->member->write();
        $this->updatePassword();
        // @return Member
        return $this->member;
    }

    /**
     * set up a group with permissions, roles, etc...
     */
    public function CreateGroup(?Member $member = null): Group
    {
        $this->showDebugMessage('=== ' . __FUNCTION__ . ' ===');
        $this->checkVariables();
        if ($member instanceof \SilverStripe\Security\Member) {
            $this->member = $member;
        }

        if ($this->getCode() === '' || $this->getCode() === '0') {
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
            // @property Group|null $group
            $this->group = Group::create($filterAnyArrayForGroup);
            $this->group->write();
            $groupStyle = 'created';
        } else {
            // @property Group|null $group
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
        if (isset($this->parentGroup)) {
            $this->showDebugMessage('adding parent group');
            if (is_string($this->parentGroup)) {
                $parentGroupName = $this->parentGroup;
                $code = $this->codeToCleanCode($parentGroupName);
                $filter = ['Title' => $parentGroupName, 'Code' => $code];
                $candidate = Group::get()->filterAny($filter)->first();
                if (null === $candidate) {
                    $this->parentGroup = Group::create($filter);
                    $parentGroupStyle = 'created';
                    $this->parentGroup->Title = $parentGroupName;
                    $this->parentGroup->write();
                    $this->showDebugMessage("{$parentGroupStyle} {$parentGroupName}");
                } else {
                    $this->parentGroup = $candidate;
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
        if ($groupCodes !== [] && $this->group && $this->group->ID) {
            $doubleGroups = Group::get()
                ->filter(['Code' => $groupCodes])
                ->exclude(['ID' => (int) $this->group->ID]);
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
        $this->showDebugMessage('=== ' . __FUNCTION__ . ' ===');
        if ('' !== $this->getRoleTitle()) {
            $code = $this->getPermissionCode();
            if ($code === '' || $code === '0') {
                return;
            }
            $filter = ['MainPermissionCode' => $code];
            $count = PermissionRole::get()
                ->Filter($filter)
                ->Count();
            if ($count > 1) {
                $this->showDebugMessage("There is more than one Permission Role with title {$this->getRoleTitle()} ({$count})", 'deleted');
                $permissionRolesFirst = DataObject::get_one(
                    PermissionRole::class,
                    $filter,
                    $cacheDataObjectGetOne = false
                );
                $permissionRolesToDelete = PermissionRole::get()
                    ->Filter($filter)
                    ->Exclude(['ID' => $permissionRolesFirst->ID]);
                foreach ($permissionRolesToDelete as $permissionRoleToDelete) {
                    $this->showDebugMessage("DELETING double permission role {$this->getRoleTitle()}", 'deleted');
                    $permissionRoleToDelete->delete();
                }
            } elseif (1 === $count) {
                //do nothing
                $this->showDebugMessage("{$this->getRoleTitle()} role in place");
            } else {
                $this->showDebugMessage("adding {$this->getRoleTitle()} role", 'created');
                // @property PermissionRole|null $permissionRole
                $this->permissionRole = PermissionRole::create();
                $this->permissionRole->Title = $this->getRoleTitle();
                $this->permissionRole->OnlyAdminCanApply = true;
                $this->permissionRole->MainPermissionCode = $code;

                $this->permissionRole->write();
            }

            if (! $this->permissionRole instanceof PermissionRole) {
                // @property PermissionRole|null $permissionRole
                $this->permissionRole = DataObject::get_one(
                    PermissionRole::class,
                    $filter,
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
        $this->showDebugMessage('=== ' . __FUNCTION__ . ' ===');
        if ($this->permissionRole instanceof \SilverStripe\Security\PermissionRole && (is_array($this->permissionArray) && count($this->permissionArray))) {
            $this->validatePermissionCodes();
            $this->showDebugMessage('working with ' . implode(', ', $this->permissionArray));
            $privilegedCodes = Permission::config()->privileged_permissions;
            foreach ($this->permissionArray as $permissionRoleCode) {
                if (! $permissionRoleCode) {
                    continue;
                }
                if (in_array($permissionRoleCode, $privilegedCodes)) {
                    $this->showDebugMessage('CAREFUL ' . $permissionRoleCode . ' as it is a privileged code');
                    DataObject::config()->set('validation_enabled', false);
                } else {
                    DataObject::config()->set('validation_enabled', true);
                }
                $filter = ['Code' => $permissionRoleCode, 'RoleID' => $this->permissionRole->ID];
                $permissionRoleCodeObject = DataObject::get_one(
                    PermissionRoleCode::class,
                    $filter,
                    $cacheDataObjectGetOne = false
                );
                $count = PermissionRoleCode::get()
                    ->Filter($filter)
                    ->Count();
                $action = 'updated';
                if ($count > 1) {
                    $permissionRoleCodeObjectsToDelete = PermissionRoleCode::get()
                        ->Filter($filter)
                        ->Exclude(['ID' => $permissionRoleCodeObject->ID]);
                    foreach ($permissionRoleCodeObjectsToDelete as $permissionRoleCodeObjectToDelete) {
                        $this->showDebugMessage("DELETING double permission code {$permissionRoleCode} for " . $this->permissionRole->Title, 'deleted');
                        $permissionRoleCodeObjectToDelete->delete();
                    }

                    $this->showDebugMessage('
                            There is more than one Permission Role Code in ' . $this->permissionRole->Title . "
                            with Code = {$permissionRoleCode} ({$count})", 'deleted');
                } elseif (1 > $count) {
                    $action = 'adding';
                    $permissionRoleCodeObject = PermissionRoleCode::create($filter);
                }

                $this->showDebugMessage($action . ' ' . $permissionRoleCodeObject->Code . ' to ' . $this->permissionRole->Title);
                $permissionRoleCodeObject->write();
            }
        }
    }

    protected function addRoleToGroup()
    {
        $this->addRoleToGroupInner();
    }

    protected function addOtherRolesToGroup()
    {
        foreach ($this->otherRoleTitles as $roleObjectTitle) {
            $roleObject = DataObject::get_one(
                PermissionRole::class,
                ['Title' => $roleObjectTitle],
                $cacheDataObjectGetOne = true
            );
            $this->addRoleToGroupInner();
        }
    }

    protected function addRoleToGroupInner()
    {
        if ($this->group && $this->permissionRole instanceof PermissionRole) {
            $count = (int) DB::query(
                'SELECT COUNT(*)
                FROM Group_Roles
                WHERE GroupID = ' . $this->group->ID . ' AND PermissionRoleID = ' . $this->permissionRole->ID
            )->value();
            if (0 === $count) {
                $this->showDebugMessage('ADDING ' . $this->permissionRole->Title . ' permission role  to ' . $this->group->Title . ' group', 'created');
                $existingGroups = $this->permissionRole->Groups();
                $existingGroups->add($this->group);
            } else {
                $this->showDebugMessage('CHECKED ' . $this->permissionRole->Title . ' permission role  to ' . $this->group->Title . ' group');
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
            if ($this->{$requiredField} === '' || $this->{$requiredField} === '0') {
                user_error('Please provide ' . $requiredField);
            }
        }

        if ('' === $this->groupName) {
            $number = rand();
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
        $passwordReset = false;
        if ($this->isNewMember && ! $this->password) {
            $passwordReset = true;
            $this->addRandomPassword();
        }

        if ('' !== $this->password && ($this->isNewMember || $this->replaceExistingPassword)) {
            $passwordReset = true;
            $this->member->changePassword($this->password);
            $this->member->write();
            if ($this->sendPasswordResetLink) {
                $this->sendEmailToMember();
            }
        }
        if ($passwordReset) {
            $this->member->PasswordExpiry = $this->forcePasswordReset ? date('Y-m-d') : null;
        }
        $this->member->write();
    }

    protected function sendEmailToMember()
    {
        $link = Director::absoluteURL('/Security/lostpassword');
        $from = Config::inst()->get(Email::class, 'admin_email');
        $to = $this->getEmail();
        $subject = $this->isNewMember ? $this->subjectNew : $this->subjectExisting;
        if ($from && $to) {
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
                ->setTo($to)
                ->setSubject($subject);
            //$email->send();
        }

        // there may have been 1 or more failures
    }

    protected function showDebugMessage(string $message, $style = '')
    {
        if (self::$debug) {
            DB::alteration_message($message, $style);
        }
    }

    protected function validatePermissionCodes()
    {
        /**
         * @var  array $codes
         */
        $codes = Permission::get_codes(false);
        foreach ($this->permissionArray as $key => $code) {
            if (!isset($code, $codes)) {
                if (class_exists($code)) {
                    $this->permissionArray[$key] = 'CMS_ACCESS_' . $code;
                }

                user_error(
                    "
                        Permission code $code is not valid.
                        The available codes are: " .
                        implode(', ', array_keys($codes))
                );
            }
        }
    }
}
