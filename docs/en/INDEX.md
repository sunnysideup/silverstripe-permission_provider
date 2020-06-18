# Example

The following code snippet creates a Tour Manager group
with a default member

```php
    PermissionProviderFactory::inst()
        ->setEmail('a@b.com')
        ->setFirstName('Tour')
        ->setSurname('Manager')
        ->setPassword('change-on-next-login-123')
        ->setName('Tour Managers')
        ->setCode('tour_managers')
        ->setPermissionCode('CMS_ACCESS_TOUR_ADMIN')
        ->setRoleTitle('Tour Manager Privileges')
        ->setPermissionArray(['CMS_ACCESS_TourBookingsAdmin'])
        ->CreateGroupAndMember();
        // ->CreateDefaultMember();
        // ->CreateGroup();
        // ->AddMemberToGroup($member);
```
