# Example

The following code snippet creates a Group
with a default member

We use a Research Member as an example below:

```php
$group = PermissionProviderFactory::inst()
    // required
    ->setGroupName('Research Managers') // optional

    // optionals
    ->setEmail('a@b.com') // option
    ->setFirstName('Research') // optional
    ->setSurname('Manager') // optional
    ->setPassword('change-on-next-login-123') // optional
    ->addRandomPassword() // optional
    ->setReplaceExistingPassword(false) // optional
    ->setCode('research-managers') // optional - IMPORTANT - DO NOT USE UNDERSCORES
    ->addMergeCode('research_managers_old') // optional
    ->addMergeCodes(['research_managers_old_also', ]) // optional
    ->setPermissionCode('CMS_ACCESS_RESEARCH_ADMIN') // optional
    ->setRoleTitle('Research Manager Privileges') // optional
    ->addRoleTitle('SomeOther Role') // optional
    ->addRoleTitles(['Some Other Role Also',]) // optional
    ->setPermissionArray(['CMS_ACCESS_ResearchAdmin'])
    ->CreateGroupAndMember();
    // ->CreateDefaultMember();
    // ->CreateGroup();
    // ->AddMemberToGroup($member);
```

OR

```php
$member = PermissionProviderFactory::inst()
    ->setGroupName('Research Managers') // required
    ->setFirstName('Research') // optional
    ->setSurname('Manager') // optional
    ->setPassword('change-on-next-login-123') // optional
    ->addRandomPassword() // optional
    ->setReplaceExistingPassword(false) // optional    
    ->CreateDefaultMember();
```

OR

```php
$group = PermissionProviderFactory::inst()
    ->setGroupName('Research Managers')
    ->CreateGroup();

```

OR

```php
$member = Member::get_one();
PermissionProviderFactory::inst()
    ->setGroupName('Research Managers') // required
    ->AddMemberToGroup($member);

```

## setting up access rules for a specific DataObject

```php

use SilverStripe\Security\PermissionProvider;
use Sunnysideup\PermissionProvider\Traits\GenericCanMethodTrait;

class MyDataObject extends DataObject implements PermissionProvider
{
    private static array $extensions = [
        GenericCanMethodTrait::class,
    ];

    private static $table_name = 'Unique';
        
    public function providePermissions()
    {
        return $this->providePermissionsHelper();
    }

    ##########################
    // the following code is ALL OPTIONAL EXTRAS....
    ##########################


    private static $has_many = [
        'Members' => Member::class,
        'Owners' => Member::class,
    ];

    protected function MembersForPermissionCheck(): DataList
    {
        return $this->Members();
    }

    protected function OwnersForPermissionCheck(): DataList
    {
        return $this->Owners();
    }
}
```

Once this is set up you can then grant access for a specific group like this:

```php
$group = PermissionProviderFactory::inst()
    ->setGroupName('Research Managers') // required
    ->setPermissionCode('CMS_ACCESS_RESEARCH_ADMIN') // optional
    ->setRoleTitle('Research Manager Privileges') // optional
    ->setPermissionArray(
        [
            'UNIQUE_CAN_CREATE',
            'UNIQUE_CAN_VIEW',
            // etc....
        ]
    )
    ->CreateGroupAndMember();
    // ->CreateDefaultMember();
    // ->CreateGroup();
    // ->AddMemberToGroup($member);

```
