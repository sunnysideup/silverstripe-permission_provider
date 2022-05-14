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

class Helpers
{
    use Injectable;
    use Configurable;


    public function createEmail(?string $email = '', ?string $firstName = 'firstname', ?string $surname = 'surname'): string
    {
        if($this->isEmail($email)) {
            return $email;
        }
        $baseURL = Director::absoluteBaseURL();
        $baseURL = str_replace('https://', '', $baseURL);
        $baseURL = str_replace('http://', '', $baseURL);
        $baseURL = trim($baseURL, '/');
        $baseURL = trim($baseURL, '/');
        $before = strtolower($email ?: $firstName . '.' . $surname);
        $before = strtolower(preg_replace('~[^\pL\pN]+~u', '-', $before));
        $email = $before . '@' . $baseURL;
        if($this->isEmail($email)) {
            return $email;
        } else {
            return $this->createEmail('', $firstName, $surname);
        }
    }

    public function getPassword(?int $length = 23)
    {
        for ($i = 0; $i < $length; ++$i) {
            $pass[] = chr(rand(32, 126));
        }
        return implode('', $pass);
    }

    public function getCode(string $groupName): string
    {
        $code = $groupName;
        $code = str_replace(' ', '_', $code);
        $code = preg_replace('#[\\W_]+#u', '', $code);
        //changing to lower case seems to be very important
        //unidentified bug so far
        return $this->codeToCleanCode($code);

        return $this->code;
    }



    protected function isEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }


    protected function codeToCleanCode(string $code): string
    {
        $group = Injector::inst()->get(Group::class);
        $group->setCode($code);

        return $group->Code;
    }



}
