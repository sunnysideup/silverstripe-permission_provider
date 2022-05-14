<?php
namespace Sunnysideup\PermissionProvider\Traits;

use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\InheritedPermissionsExtension;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionChecker;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;

use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TreeMultiselectField;

use SilverStripe\ORM\DataObject;


/**
 * trait you can add to any dataobject to make it awesome
 * object needs to have $this->ParentID
 * use `$this->addPermissionFields($fields)` to add fields to getCMSFields
 *
 * @todo: see vendor/sunnysideup/elemental-can-view/src
 */
trait PermissionCheckerTrait
{

    private static $extensions = [
        InheritedPermissionsExtension::class,
    ];

    /**
     * List of permission codes a user can have to allow a user to create a page of this type.
     * Note: Value might be cached, see {@link $allowed_chilren}.
     *
     * @config
     * @var array
     */
    private static $need_permission = null;

    /**
     * This function should return true if the current user can execute this action. It can be overloaded to customise
     * the security model for an application.
     *
     * Slightly altered from parent behaviour in {@link DataObject->can()}:
     * - Checks for existence of a method named "can<$perm>()" on the object
     * - Calls decorators and only returns for FALSE "vetoes"
     * - Falls back to {@link Permission::check()}
     * - Does NOT check for many-many relations named "Can<$perm>"
     *
     * @uses DataObjectDecorator->can()
     *
     * @param string $perm The permission to be checked, such as 'View'
     * @param Member $member The member whose permissions need checking. Defaults to the currently logged in user.
     * @param array $context Context argument for canCreate()
     * @return bool True if the the member is allowed to do the given action
     */
    public function can($perm, $member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($member && Permission::checkMember($member, "ADMIN")) {
            return true;
        }

        if (is_string($perm) && method_exists($this, 'can' . ucfirst($perm ?? ''))) {
            $method = 'can' . ucfirst($perm ?? '');
            return $this->$method($member);
        }

        $results = $this->extend('can', $member);
        if ($results && is_array($results)) {
            if (!min($results)) {
                return false;
            }
        }

        return ($member && Permission::checkMember($member, $perm));
    }

    /**
     * Recursively determine whether an object has, or inherits, restricted permissions.
     *
     * @param DataObject $object
     * @return bool
     */
    private function hasRestrictedPermissions($object): bool
    {
        $id = $object->ID;
        $parentID = $object->ParentID ?? 0;
        $canViewType = $object->CanViewType;
        if (in_array($canViewType, [InheritedPermissions::LOGGED_IN_USERS, InheritedPermissions::ONLY_THESE_USERS])) {
            self::$has_restricted_permissions_cache[$id] = true;
            return true;
        }
        if ($canViewType == InheritedPermissions::INHERIT && $parentID != 0) {
            if (isset(self::$has_restricted_permissions_cache[$parentID])) {
                return self::$has_restricted_permissions_cache[$parentID];
            }
            if($parentID && $object->hasMethod('Parent')) {
                $parent = $object->Parent();
                if ($parent->exists()) {
                    $value = $this->hasRestrictedPermissions($parent);
                    self::$has_restricted_permissions_cache[$parentID] = $value;
                    return $value;
                }
            }
        }
        self::$has_restricted_permissions_cache[$id] = false;
        return self::$has_restricted_permissions_cache[$id];
    }

    /**
     * @internal
     * @see hasRestrictedPermissions
     */
    protected static $has_restricted_permissions_cache = [];

    public static function reset()
    {
        parent::reset();
        $className = static::class;
        // Flush permissions on modification
        $permissions = $className::singleton()->getPermissionChecker();
        if ($permissions instanceof InheritedPermissions) {
            $permissions->clearCache();
        }
    }

    /**
     * @param Member $member
     * @return bool
     */
    public function canView($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        $result = $this->extendedCan('canView', $member);
        if ($result !== null) {
            return $result;
        }

        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }
        $parentID = $this->ParentID ?? 0;
        // Check inherited permissions from the parent folder
        if ($this->CanViewType === InheritedPermissions::INHERIT && $parentID) {
            return $this->getPermissionChecker()->canView($parentID, $member);
        }

        // Any logged in user can view this object
        if ($this->CanViewType === InheritedPermissions::LOGGED_IN_USERS && !$member) {
            return false;
        }

        // Specific user groups can view this object
        if ($this->CanViewType === InheritedPermissions::ONLY_THESE_USERS) {
            if (!$member) {
                return false;
            }
            return $member->inGroups($this->ViewerGroups());
        }

        // Check default root level permissions
        return $this->getPermissionChecker()->canView($this->ID, $member);
    }

    /**
     * Check if this object can be modified
     *
     * @param Member $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        $result = $this->extendedCan('canEdit', $member);
        if ($result !== null) {
            return $result;
        }

        if (Permission::checkMember($member, 'EDIT_ALL')) {
            return true;
        }
        $parentID = $this->ParentID ?? 0;
        // Delegate to parent if inheriting permissions
        if ($this->CanEditType === 'Inherit' && $parentID) {
            return $this->getPermissionChecker()->canEdit($parentID, $member);
        }

        // Check inherited permissions
        return $this->getPermissionChecker()->canEdit($this->ID, $member);
    }

    /**
     * Check if a object can be created
     *
     * @param Member $member
     * @param array $context
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        $result = $this->extendedCan('canCreate', $member, $context);
        if ($result !== null) {
            return $result;
        }

        if (Permission::checkMember($member, 'EDIT_ALL')) {
            return true;
        }

        // If Parent is provided, object can be created if parent can be edited
        /** @var Folder $parent */
        $parent = isset($context['Parent']) ? $context['Parent'] : null;
        if ($parent) {
            return $parent->canEdit($member);
        }

        return false;
    }

    /**
     * Check if this object can be deleted
     *
     * @param Member $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        $result = $this->extendedCan('canDelete', $member);
        if ($result !== null) {
            return $result;
        }

        if (!$member) {
            return false;
        }

        // Default permission check
        if (Permission::checkMember($member, 'EDIT_ALL')) {
            return true;
        }

        // Check inherited permissions
        return static::getPermissionChecker()
            ->canDelete($this->ID, $member);
    }


    /**
     * @return PermissionChecker
     */
    public function getPermissionChecker()
    {
        return Injector::inst()->get(PermissionChecker::class.'.'.$this->myClassForPermissions());
    }

    protected function myClassForPermissions() : string
    {
        return strtolower(str_replace('\\', '_', static::class));
    }

    protected function addPermissionFields($fields)
    {
        $mapFn = function ($groups = []) {
            $map = [];
            foreach ($groups as $group) {
                // Listboxfield values are escaped, use ASCII char instead of &raquo;
                $map[$group->ID] = $group->getBreadcrumbs(' > ');
            }
            asort($map);
            return $map;
        };
        $viewAllGroupsMap = $mapFn(Permission::get_groups_by_permission([strtoupper($this->myClassForPermissions()).'_VIEW_ALL', 'ADMIN']));
        $editAllGroupsMap = $mapFn(Permission::get_groups_by_permission([strtoupper($this->myClassForPermissions()).'_EDIT_ALL', 'ADMIN']));

        $fields = new FieldList(
            $rootTab = new TabSet(
                "Root",
                $tabPermissions = new Tab(
                    'Permissions',
                    $viewersOptionsField = new OptionsetField(
                        "CanViewType",
                        _t(__CLASS__.'.ACCESSHEADER', "Who can view this page?")
                    ),
                    $viewerGroupsField = TreeMultiselectField::create(
                        "ViewerGroups",
                        _t(__CLASS__.'.VIEWERGROUPS', "Viewer Groups"),
                        Group::class
                    ),
                    $editorsOptionsField = new OptionsetField(
                        "CanEditType",
                        _t(__CLASS__.'.EDITHEADER', "Who can edit this page?")
                    ),
                    $editorGroupsField = TreeMultiselectField::create(
                        "EditorGroups",
                        _t(__CLASS__.'.EDITORGROUPS', "Editor Groups"),
                        Group::class
                    )
                )
            )
        );

        $tabPermissions->setTitle(_t(__CLASS__.'.PERMISSIONS', "Permissions"));

        $viewersOptionsField->setSource($this->viewersOptions());

        $editorsOptionsField->setSource($this->editorOptions());

        if ($viewAllGroupsMap) {
            $viewerGroupsField->setDescription(_t(
                $this->myClassForPermissions().'.VIEWER_GROUPS_FIELD_DESC',
                'Groups with global view permissions: {groupList}',
                ['groupList' => implode(', ', array_values($viewAllGroupsMap ?? []))]
            ));
        }

        if ($editAllGroupsMap) {
            $editorGroupsField->setDescription(_t(
                $this->myClassForPermissions().'.EDITOR_GROUPS_FIELD_DESC',
                'Groups with global edit permissions: {groupList}',
                ['groupList' => implode(', ', array_values($editAllGroupsMap ?? []))]
            ));
        }

        if (!Permission::check(strtoupper($this->myClassForPermissions()).'_GRANT_ACCESS')) {
            $fields->makeFieldReadonly($viewersOptionsField);
            if ($this->CanEditType === InheritedPermissions::ONLY_THESE_USERS) {
                $fields->makeFieldReadonly($viewerGroupsField);
            } else {
                $fields->removeByName('ViewerGroups');
            }

            $fields->makeFieldReadonly($editorsOptionsField);
            if ($this->CanEditType === InheritedPermissions::ONLY_THESE_USERS) {
                $fields->makeFieldReadonly($editorGroupsField);
            } else {
                $fields->removeByName('EditorGroups');
            }
        }

        return $fields;
    }

    protected function viewersOptions() : array
    {
        $list = [
            InheritedPermissions::INHERIT => _t(__CLASS__.'.INHERITED', "Inherited"),
            InheritedPermissions::ANYONE => _t(__CLASS__.'.ACCESSANYONE', "Anyone"),
            InheritedPermissions::LOGGED_IN_USERS => _t(__CLASS__.'.ACCESSLOGGEDIN', "Logged-in users"),
            InheritedPermissions::ONLY_THESE_USERS => _t(
                __CLASS__.'.ACCESSONLYTHESE',
                "Only these groups (choose from list)"
            ),
        ];
        $parentID = $this->ParentID ?? 0;
        if(! $parentID) {
            unset($list[InheritedPermissions::INHERIT]);
        }
    }
    protected function editorOptions() : array
    {
        // Editors have same options, except no "Anyone"
        $list = $this->viewersOptions();
        unset($list[InheritedPermissions::ANYONE]);
        return $list;
    }

}
