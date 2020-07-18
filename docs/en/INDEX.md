# Example

The following code snippet creates a Group
with a default member

We use a Research Member as an example below:

```php
$group = PermissionProviderFactory::inst()
    ->setEmail('a@b.com')
    ->setFirstName('Tour')
    ->setSurname('Manager')
    ->setPassword('change-on-next-login-123')
    ->addRandomPassword()
    ->setReplaceExistingPassword(false)
    ->setGroupName('Research Managers')
    ->setCode('research_managers')
    ->setPermissionCode('CMS_ACCESS_RESEARCH_ADMIN')
    ->setRoleTitle('Research Manager Privileges')
    ->setPermissionArray(['CMS_ACCESS_ResearchAdmin'])
    ->CreateGroupAndMember();
    // ->CreateDefaultMember();
    // ->CreateGroup();
    // ->AddMemberToGroup($member);
```
OR

```php
$member = PermissionProviderFactory::inst()
    ->setEmail('a@b.com')
    ->setFirstName('Tour')
    ->setSurname('Manager')
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
    ->setGroupName('Research Managers')
    ->AddMemberToGroup($member);

```
