# Example

The following code snippet creates a Group
with a default member

We use a Research Member as an example below:

```php
    PermissionProviderFactory::inst()
        ->setEmail('a@b.com')
        ->setFirstName('Tour')
        ->setSurname('Manager')
        ->setPassword('change-on-next-login-123')
        ->setName('Research Managers')
        ->setCode('research_managers')
        ->setPermissionCode('CMS_ACCESS_RESEARCH_ADMIN')
        ->setRoleTitle('Research Manager Privileges')
        ->setPermissionArray(['CMS_ACCESS_ResearchAdmin'])
        ->CreateGroupAndMember();
        // ->CreateDefaultMember();
        // ->CreateGroup();
        // ->AddMemberToGroup($member);
```
