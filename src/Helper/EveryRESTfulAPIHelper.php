<?php
namespace EveryRESTfulAPI\Helper;

use EveryDataStore\Helper\EveryDataStoreHelper;
use EveryRESTfulAPI\Helper\CustomAssetHelper;
use EveryDataStore\Helper\AssetHelper;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;
use SilverStripe\Security\PasswordEncryptor;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\MemberPassword;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DB;

/** EveryDataStore/EveryRESTfulAPI v1.0
 *
 * This class includes implementation of functions that ensure secure exchange of information
 * as well as implementation of object relations
 *
 */

class EveryRESTfulAPIHelper extends EveryDataStoreHelper {

    /**
     * This function returns an array containing apitoken of the user
     *
     * @param HTTPRequest $request
     * @return array
     */
    public static function getToken($request) {
        return self::login($request, false);
    }

    /**
     * This function resets the current token and replaces its value with a newly generated one
     *
     * @param HTTPRequest $request
     * @return array
     */
    public static function resetToken($request) {
        return self::login($request, true);
    }
    
    /**
     * This functions returns the api token from request
     * @return string
     */
    public static function getRequestToken() {
        $token = strip_tags(trim(str_replace(['Bearer', ' '], ['', ''], $_SERVER['HTTP_AUTHORIZATION'])));
        if ($token) {
            return $token;
        }

        if (isset($_GET['apitoken'])) {
            return strip_tags($_GET['apitoken']);
        }

        if (isset($_POST['apitoken'])) {
            return strip_tags($_POST['apitoken']);
        }
    }

    /**
     * This function checks the validity of the apitoken
     *
     * @param string $token
     * @param HTTPRequest $request
     * @return boolean
     */
    public static function validiateToken($token, $request) {
        $member = Member::get()->filter(['RESTFulToken' => $token, 'RESTFulTokenExpire:GreaterThanOrEqual' => time(), 'Active' => true])->First();
        if ($member) {
            Config::nest();
            Config::modify()->set(Member::class, 'session_regenerate_id', true);
            $identityStore = Injector::inst()->get(IdentityStore::class);
            $identityStore->logIn($member, false, $request);
            Config::unnest();
            return true;
        }
    }

    /**
     * This function checks whether the member with given credentials exists
     * and logs in the user
     *
     * @param HTTPRequest $request
     * @param boolean $reset
     * @return array
     */
    public static function login($request, $reset = false) {
        $member = false;
        $response = [];
        $fields = self::getRequestParams($request, 'Fields');

        if (isset($fields['Email']) && isset($fields['Password'])) {
            $member = self::getMemberByEmailAndPassword($fields['Email'], $fields['Password'], $request, null);
            if ($member) {
                $response = self::getViewFieldsValues($member);
                $response['Settings'] = self::getDataStoreSettings($member);
                if ($member->RESTFulTokenExpire >= time() && $member->RESTFulToken && $reset == false) {
                    $token = $member->RESTFulToken;
                    $response['Token'] = $token;
                    $response['Expire'] = $member->RESTFulTokenExpire;
                    $response['CurrentDataStoreSlug'] = $member->CurrentDataStore()->Slug;
                    $response['CurrentDataStoreName'] = $member->CurrentDataStore()->Title;
                } else {
                    $token = self::generateToken();
                    $expire = time() + Config::inst()->get('API_Token_Lifetime');
                    $member->RESTFulToken = $token;
                    $member->RESTFulTokenExpire = $expire;
                    $member->write();
                    $response['Token'] = $token;
                    $response['Expire'] = $expire;
                    $response['CurrentDataStoreSlug'] = $member->CurrentDataStore()->Slug;
                    $response['CurrentDataStoreName'] = $member->CurrentDataStore()->Title;

                }
          } else {
                $response = ['HTTPCode' => 404, 'ErrorMSG' => 'Login failed for '. strip_tags(($fields['Email']))];
            }
        } else {
            $response = ['HTTPCode' => 404, 'ErrorMSG' => 'E-Mail or password are missing!'];
        }
        return $response;
    }

    /**
     * This function checks whether the provided credentials are correct
     *
     * @param HTTPRequest $request
     * @return array|string
     */
    public static function checkPassword($request) {
        $fields = self::getRequestParams($request, 'Fields');
        $token = self::getRequestToken();
        
        if (isset($fields['Email']) && isset($fields['OldPassword']) && $token) {
     
            $member = self::getMemberByEmailAndPassword($fields['Email'], $fields['OldPassword'], $request, $token);
            if ($member) {
                $response = 'OK';
            } else {
                  $response =  ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\Member.ERRORWRONGCRED', "The provided details do not seem to be correct. Please try again.")];
            }
        } else {
               $response =  ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\Member.MISSINGLOGINDATA', 'Email, OldPassword, Password or API-Token are missing')];
        }
        return $response;
    }

    /**
     * This function checks whether a member with provided credentials exist and
     * if so, validates new password
     *
     * @param HTTPRequest $request
     * @return string|array
     */
    public static function validatePassword($request) {
        $fields = self::getRequestParams($request, 'Fields');
        $token = self::getRequestToken();
        if (isset($fields['Email']) && isset($fields['OldPassword']) && isset($fields['Password']) && $token) {
            $member = self::getMemberByEmailAndPassword($fields['Email'], $fields['OldPassword'], $request, $token);
            if ($member) {
                $response = self::checkIfPasswordOk($member, $fields['Password']);
            } else {
                $response =  ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\Member.ERRORWRONGCRED', "The provided details do not seem to be correct. Please try again.")];
            }
        } else {
                $response =  ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\Member.MISSINGLOGINDATA', 'Email, OldPassword, Password ord API-Token are missing')];
        }
        return $response;
    }

    /**
     * This function updates the value of member's password
     *
     * @param HTTPRequest $request
     * @return string|array
     */
    public static function changePassword($request) {
        $fields = self::getRequestParams($request, 'Fields');
        $token = self::getRequestToken();
        
        if (isset($fields['Email']) && isset($fields['OldPassword']) && isset($fields['Password']) && $token) {
            $member = self::getMemberByEmailAndPassword($fields['Email'], $fields['OldPassword'], $request, $token);
            if ($member) {
                $member->Password = $fields['Password'];
                $member->write();
                $response = 'OK';
            } else {
                $response =  ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\Member.ERRORWRONGCRED', "The provided details do not seem to be correct. Please try again.")];
            }
        } else {
            $response =  ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\Member.MISSINGLOGINDATA', 'Email, OldPassword, Password ord API-Token are missing')];

        }
        return $response;
    }

    /**
     * This function checks if the password complies to minimum requirements
     *
     * @param DataObject $member
     * @param string $password
     * @return string|array
     */
    public static function checkIfPasswordOk($member, $password) {
        $configMinLength = Config::inst()->get('SilverStripe\Security\PasswordValidator', 'min_length');
        $configMaxLength = Config::inst()->get('SilverStripe\Security\PasswordValidator', 'max_length');
        $previousPasswords = MemberPassword::get()
                ->where(['"MemberPassword"."MemberID"' => $member->ID])
                ->sort('"Created" DESC, "ID" DESC');

        //Check password length
        if (strlen($password) < $configMinLength) {
           return ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\PasswordValidator.TOOSHORT', 'Password is too short, it must be {minimum} or more characters long', ['minimum' => $configMinLength])];
        }

        if (strlen($password) > $configMaxLength) {
            return ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\PasswordValidator.TOOLONG', 'Password is too long, it must be {maximum} or less characters long', ['maximum' => $configMaxnLength])];
        }

        // Check if password used in the past
        foreach ($previousPasswords as $previousPassword) {
            if ($previousPassword->checkPassword($password)) {
                return ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\PasswordValidator.PREVPASSWORD', 'You have already used that password in the past, please choose a new password')];
            }
        }
        return 'OK';
    }

    /**
     * This function returns a Member class object that corresponds to the provided credentials
     *
     * @param string $email
     * @param string $password
     * @param string $token
     * @param HTTPRequest $request
     * @return DataObject
     */
    public static function getMemberByEmailAndPassword($email, $password, $request, $token = null) {
        $login['Email'] = $email;
        $login['Password'] = $password;
        $login['Active'] = true;
        if ($token)
            $login['RESTFulToken'] = $token;

        return Injector::inst()->get(MemberAuthenticator::class)->authenticate($login, $request);
    }

    /**
     * This function sends a password reset link to the member with provided email address
     *
     * @param HTTPRequest $request
     * @return string|array
     */
    public static function checkEmail($request) {
        $email = $request->getVars()['Email'] ? $request->getVars()['Email']  :'';
        if($email){
           $member = Member::get()->filter(['Email' => $email, 'Active' => true])->first();
           if($member){
               $member->SendPasswordResetLink = true;
               $member->write();
               return 'OK';
           }
        }

        return ['HTTPCode' => 200, 'ErrorMSG' => _t('SilverStripe\Security\Member.SENDRESETLINKMEMAILMISING', 'The Email {email} not exist. Please contact the App Administrator', ['email' => $email])];
    }

    /**
     * This function provides access to the "change password" page for the member
     * who received password reset link
     *
     * @param HTTPRequest $request
     * @return type
     */
    public static function checkAutoLoginToken($request) {
        $slug = self::getRequestParams($request, 'Slug');
        $autoLoginToken = self::getRequestParams($request, 'Token');

        $member = $slug && $autoLoginToken ? Member::get()->filter(['Slug' => $slug, 'AutoLoginHash' => $autoLoginToken, 'Active' => true])->first() : null;
        if ($member) {
                $token = self::generateToken();
                $expire = time() + Config::inst()->get('API_Token_Lifetime');
                $member->RESTFulToken = $token;
                $member->RESTFulTokenExpire = $expire;
                $member->write();
                $response['Token'] = $token;
                $response['Slug'] = $slug;
                $response['Expire'] = $expire;
                return $response;
            }
        return ['HTTPCode' => 200, 'ErrorMSG' => _t(
                'SilverStripe\Security\Member.PASSWORDRESETLINKINVALID',
                'The password reset link is invalid or expired')];
    }

    /**
     * This function resets member's password
     *
     * @param HTTPRequest $request
     * @return string|array
     */
    public static function resetPassword($request) {
        $fields = self::getRequestParams($request, 'Fields');
        $Passord = self::getRequestParams($request, 'Password');
        $token = self::getRequestToken();
        
        if (isset($fields['Password']) && isset($fields['Slug']) && $token) {
            $member = Member::get()->filter(['Slug' => $fields['Slug'], 'RESTFulToken' => $token])->first();
            if ($member) {
                $checkIfPasswordOk = self::checkIfPasswordOk($member, $fields['Password']);
                if ($checkIfPasswordOk == 'OK') {
                    $member->Password = $fields['Password'];
                    $member->AutoLoginHash = null;
                    $member->RESTFulToken = null;
                    $member->RESTFulTokenExpire = null;
                    $member->write();
                    return 'OK';
                } else {
                    $response = $checkIfPasswordOk;
                }
            } else {
                $response = _t('SilverStripe\Security\Member.ERRORWRONGCRED', "The provided login details do not seem to be correct. Please try again.");
            }
        } else {
            $response = _t('SilverStripe\Security\Member.MISSINGSETPASSWORDDATA', ' Slug, Password or API-Token are missing');
        }
       return $response;
         //return ['HTTPCode' => 200, 'ErrorMSG' => $response];
    }

    /**
     * This function ends the current session of the user and logs out member
     * @param HTTPRequest $request
     * @return array
     */
    public static function logout($request) {
        $getVars = $request->getVars();
        $token = self::getRequestToken();

        $member = Member::get()->filter(['RESTFulToken' => $token])->First();
        if ($member) {
            Config::nest();
            Config::modify()->set(Member::class, 'session_regenerate_id', false);
            $identityStore = Injector::inst()->get(IdentityStore::class);
            $identityStore->logOut($request);
            Config::unnest();
            $member->RESTFulToken = '';
            $member->RESTFulTokenExpire = time();
            $member->write();
            $member->logout();
            return array('Token is destroyed');
        } else {
            return ['HTTPCode' => 300, 'ErrorMSG' => 'Logout is failed'];
        }
    }

    /**
     * This function generates encrypted apitoken
     *
     * @return string
     */
    public static function generateToken() {
        $generator = new RandomGenerator();
        $tokenString = $generator->randomToken();
        $encryptor = PasswordEncryptor::create_for_algorithm('blowfish'); //blowfish isn't URL safe and maybe too long?
        $salt = $encryptor->salt($tokenString);
        return $encryptor->encrypt($tokenString, $salt);
    }

    /**
     * This function creates an object named after $className
     * with properties corresponding to $fields
     *
     * @param string $className
     * @param array $fields
     * @return type
     */
    public static function createObject($className, $fields) {
        $object = Injector::inst()->create($className);
        $relations = self::getObjectRelations($className);

        $createFields = Config::inst()->get($className, 'API_Create_Fields');
        $classShorname = ClassInfo::shortName($object);

        if (empty($createFields)) {
            return ['HTTPCode' => 400, 'ErrorMSG' => $classShorname . 'has no API_Create_Fields in config.yml defined'];
        }

        $object = self::setObject($object, $relations, $createFields, $fields);

        if ($object && $object->CanCreate()) {
            $object->write();
            return $object;
        } else {
            return ['HTTPCode' => 400, 'ErrorMSG' => 'Please check the permission CanCreate for this Class' . $classShorname];
        }
    }

    /**
     * This function updates property values of $object
     *
     * @param DataObject $object
     * @param string $className
     * @param array $fields
     * @return DataObject
     */
    public static function updateObject($object, $className, $fields) {
        $relations = self::getObjectRelations($className);
        $editFields = Config::inst()->get($className, 'API_Edit_Fields');
        $classShorname = ClassInfo::shortName($object);

        if (empty($editFields)) {
            return ['HTTPCode' => 400, 'ErrorMSG' => $classShorname . 'has no API_Create_Fields in config.yml defined'];
        }

        $object = self::setObject($object, $relations, $editFields, $fields);

        if ($object && $object->CanEdit()) {
            $object->write();
        }
           return $object;
    }

    /**
     * This function writes property values and relations for existing objects
     *
     * @param DataObject $object
     * @param array $relations
     * @param array $createEditFields
     * @param array $fields
     * @return DataObject
     */
    public static function setObject($object, $relations, $createEditFields, $fields) {
        $doWrite = false;
        foreach ($createEditFields as $field) {
            if (isset($fields[$field])) {
                $doWrite = true;
                $object->$field = is_array($fields[$field]) ? serialize($fields[$field]): $fields[$field];
            }
        }
        
        if (!empty($relations)) {

            $hasOneRelations = self::getObjectRelationsType($relations, 'has_one');

            $object = !empty($hasOneRelations)  ? self::createObjectHasOneRelations($object, $hasOneRelations, $fields): $object;


            $hasManyRelations = self::getObjectRelationsType($relations, 'has_many');
            $object =  !empty($hasManyRelations)  ? self::createObjectHasManyRelation($object, $hasManyRelations, $fields): $object;


            $manyManyRelations = self::getObjectRelationsType($relations, 'many_many');
            $object = !empty($manyManyRelations)  ?  self::createObjectManyManyRelations($object, $manyManyRelations, $fields): $object;


            $belongsManyMany = self::getObjectRelationsType($relations, 'belongs_many_many');
            if (!empty($belongsManyMany)) {
               self::createObjectBelongsManyManyRelations($object, $belongsManyMany, $fields);
            }

        }
       return $object;
    }

    /**
     * This function sets an versioned object to a specific version
     *
     * @param DataObject $object
     * @param string $version
     * @param string $rollback
     * @return DataObject
     */
    public static function rollbackObject($objectID, $className, $rollback, $version){
         $object = DataObject::get($className)->byID($objectID);
         if($rollback == 'recursiv') {
             $object->rollbackRecursive($version);
             $object->publishRecursive();
             return $object;
         }

         $object->rollbackSingle($version);
         $object->publishRecursive();
         return $object;
    }

    /**
     * This function creates an HasOne object
     * @param DataObject $object
     * @param array $hasOneRelations
     * @param array $fields
     * @return DataObject
     */
    public static function createObjectHasOneRelations($object, $hasOneRelations, $fields) {
        foreach ($hasOneRelations as $ho) {
            if ($ho['ClassName'] == 'SilverStripe\Assets\Image' || $ho['ClassName'] == 'SilverStripe\Assets\File') {
                
                $files = isset($_FILES[$ho['Name']]) && !empty($_FILES[$ho['Name']]) ? $_FILES[$ho['Name']] : null;
                if($files){
                    $object = $files ? self::uploadObjectFiles($object, $ho['Name'], 'has_one', ClassInfo::shortname($object), $files) : $object;
                }elseif(is_array($fields[$ho['Name']]) && isset($fields[$ho['Name']][0]) && AssetHelper::isStringBase64($fields[$ho['Name']][0])){
                    $object = self::prepareAndCreateFileFromBase64($object, $ho['Name'], 'has_one', $fields[$ho['Name']][0]);
                } else {
                    $object = $object;
                }
            } else {
                $relationField = $ho['Name'] . 'ID';
                $postField = $ho['Name'];
                if (isset($fields[$postField])) {
                    $relationObjectID = self::getObjectIDbySlug($ho['ClassName'], $fields[$postField]);
                    if ($relationObjectID) {
                        $object->{$relationField} = $relationObjectID;
                    }
                }
            }
        }
        return $object;
    }

    /**
     * This function creates an HasMany object
     * @param DataObject $object
     * @param array $relations
     * @param array $fields
     * @return DataObject
     */
    public static function createObjectHasManyRelation($object, $relations, $fields) {
        foreach ($relations as $r) {
            $relationClassName = isset($r['ClassName']) ? $r['ClassName'] : null;
            $objectClassShorname = ClassInfo::shortName($object);
            if ($relationClassName == 'SilverStripe\Security\Permission') {
                self::setGroupPermissions($object, $fields);
            } else {
                if (isset($fields[$r['Name']])) {
                    $relationObject = self::createObject($relationClassName, $fields);
                    if ($relationObject) {
                        $relationField = $objectClassShorname . 'ID';
                        $relationObject->{$relationField} = $object->ID;
                        $relationObject->write();
                    }
                }
            }
            return $object;
        }
    }


    /**
     * This function creates a ManyMany object
     * @param DataObject $object
     * @param array $ManyManyRelations
     * @param array $fields
     * @return DataObject
     */
    public static function createObjectManyManyRelations($object, $ManyManyRelations, $fields) {
        foreach ($ManyManyRelations as $mm) {
            $relationName = $mm['Name'];
            $relationClassName = $mm['ClassName'];
            $postField = $mm['Name'];
            if ($mm['ClassName'] == 'SilverStripe\Assets\Image' || $mm['ClassName'] == 'SilverStripe\Assets\File') {
                $files = isset($_FILES[$mm['Name']]) && !empty($_FILES[$mm['Name']]) ? $_FILES[$mm['Name']] : null;
                $object = $files ? self::uploadObjectFiles($object, $mm['Name'], 'many_many', ClassInfo::shortname($object), $files) : $object;
            } else {
                if (isset($fields[$postField]) && is_array($fields[$postField])) {
                  // self::removeObjectManyManyRelation($object, $relationName);
                    foreach ($fields[$postField] as $field) {
                        $relationObject = self::getObjectbySlug($relationClassName, $field);
                        if ($relationObject) {
                            $object->{$relationName}()->add($relationObject);
                        }
                    }
                    $object->write();
                }
            }
        }
        return $object;
    }

    /**
     * This function creates a BelongsManyMany object
     * @param DataObject $object
     * @param array $ManyManyRelations
     * @param array $fields
     */
    public static function createObjectBelongsManyManyRelations($object, $ManyManyRelations, $fields) {

        foreach ($ManyManyRelations as $bmm) {
            $relationName = $bmm['Name'];
            $relationClassName = $bmm['ClassName'];
            $RelationObjectRelations = self::getObjectRelations($relationClassName);
            $RelationobjectRelation = self::getObjectRelationNameByClassType($RelationObjectRelations, $object->ClassName);

            $postField = $bmm['Name'];
            if ($bmm['ClassName'] == 'SilverStripe\Assets\Image' || $bmm['ClassName'] == 'SilverStripe\Assets\File') {
                $files = isset($_FILES[$bmm['Name']]) && !empty($_FILES[$bmm['Name']]) ? $_FILES[$bmm['Name']] : null;
                $object = $files ? self::uploadObjectFiles($object, $bmm['Name'], 'belongs_many_many', ClassInfo::shortname($object), $files) : $object;
            } else {
                if (isset($fields[$postField]) && is_array($fields[$postField]) && count($fields[$postField]) > 0) {
                   self::removeObjectBelongsToManyManyRelation($relationClassName, $relationName, $RelationobjectRelation['Name'], $object);
                    foreach ($fields[$postField] as $field) {
                        $relationObject = self::getObjectbySlug($relationClassName, $field);
                        if ($relationObject) {
                            $relationObject->{$RelationobjectRelation['Name']}()->add($object->ID);
                        }
                    }
                   // $relationObject->write();
                }
            }
        }
    }

    /**
     * This function adds files to appropriate folder under RecordSetItem object
     *
     * @param DataObject $object
     * @param string $relationName
     * @param string $realationType
     * @param string $folderName
     * @param array $files
     * @return DataObject
     */
    public static function uploadObjectFiles($object, $relationName, $realationType, $folderName, $files) {
        if (!empty($files) && $folderName && $object) {
            $folder = Folder::find_or_make(Config::inst()->get('SilverStripe\Assets\File', 'root_dir_name').'/'. $folderName);
            $uploadFiles = CustomAssetHelper::prepareAndUploadFiles($files, $folder);
            if ($uploadFiles) {
                foreach ($uploadFiles as $file) {
                    if ($file['ID'] > 1) {
                        if ($realationType == 'has_one') {
                            $relationField = $relationName . 'ID';
                            $object->$relationField = $file['ID'];
                        } else {
                            $object->$relationName()->add($file['ID']);
                        }
                    }
                }
            }
        }
        return $object;
    }

    /**
     * This function returns all relations of a specific class
     * @param string $ClassName
     * @return array
     */
    public static function getObjectRelations($ClassName) {
        $relations = [];
        $extensions = Config::inst()->get($ClassName, 'API_Dataobject_Allowed_Extensions');

        $has_one = Config::inst()->get($ClassName, 'has_one');
        $has_many = Config::inst()->get($ClassName, 'has_many');
        $many_many = Config::inst()->get($ClassName, 'many_many');
        $belongs_many_many = Config::inst()->get($ClassName, 'belongs_many_many');
        $allowedRelations = Config::inst()->get($ClassName, 'API_Dataobject_Allowed_Realations');



        if (!empty($has_one)) {
            foreach ($has_one as $kho => $vho) {
                if (is_array($allowedRelations) && in_array($kho, $allowedRelations)) {
                    $relations[] = ['Type' => 'has_one', 'Name' => $kho, 'ClassName' => $vho];
                }
            }
        }

        if (!empty($has_many)) {
            foreach ($has_many as $khm => $vhm) {
                if (is_array($allowedRelations) && in_array($khm, $allowedRelations)) {
                    $relations[] = ['Type' => 'has_many', 'Name' => $khm, 'ClassName' => $vhm];
                }
            }
        }

        if (!empty($many_many)) {
            foreach ($many_many as $kmm => $vmm) {
                if (is_array($allowedRelations) && in_array($kmm, $allowedRelations)) {
                    $relations[] = ['Type' => 'many_many', 'Name' => $kmm, 'ClassName' => $vmm];
                }
            }
        }

        if (!empty($belongs_many_many)) {
            foreach ($belongs_many_many as $kbmm => $vbmm) {
                if (is_array($allowedRelations) && in_array($kbmm, $allowedRelations)) {
                    $relations[] = ['Type' => 'belongs_many_many', 'Name' => $kbmm, 'ClassName' => $vbmm];
                }
            }
        }
        return !empty($relations) ? $relations : false;
    }

    /**
     * This function returns DataObject that corresponds to the provided parameters
     *
     * @param string $className
     * @param string $slug
     * @return DataObject
     */
    public static function getObjectbySlug($className, $slug) {
        $obj = DataObject::get($className, ['Slug' => $slug])->First();
        return $obj ? $obj : null;
    }

    /**
     * This function returns ID of the DataObject that corresponds to the provided parameters
     *
     * @param string $className
     * @param string $slug
     * @return integer
     */
    public static function getObjectIDbySlug($className, $slug) {
        $obj = DataObject::get($className, ['Slug' => $slug])->First();
        return $obj ? $obj->ID : null;
    }

    /**
     * This function returns all relations of the $relationName object
     *
     * @param array $relations
     * @param string $relationName
     * @return array
     */
    public static function getObjectRelationsByName($relations, $relationName) {
        $objectRelations = [];
        foreach ((array)$relations as $relation) {
            if (isset($relation['Name']) && $relation['Name'] == $relationName) {
                $objectRelations[] = $relation;
            }
        }
        return $objectRelations;
    }

    /**
     * This function returns all object relations of the same $relationType
     *
     * @param array $relations
     * @param string $relationType
     * @return array
     */
    public static function getObjectRelationsType($relations, $relationType) {
        $objectRelations = [];
        foreach ((array)$relations as $relation) {
            if (isset($relation['Type']) && $relation['Type'] == $relationType) {
                $objectRelations[] = $relation;
            }
        }
        return $objectRelations;
    }

    /**
     * This function returns object relation with specific $ClassName
     *
     * @param array $relations
     * @param string $ClassName
     * @return DataObject
     */
    public static function getObjectRelationNameByClassType($relations, $ClassName) {
        foreach ((array)$relations as $relation) {
            if (isset($relation['ClassName']) && $relation['ClassName'] == $ClassName) {
                return $relation;
            }
        }
    }

    /**
     * This function removes relation between two objects
     *
     * @param DataObject $object
     * @param DataObject $relation
     */
    public static function removeObjectManyManyRelation($object, $relation) {
        if ($object->{$relation}()) {
            foreach ($object->{$relation}() as $r) {
                $object->{$relation}()->remove($r);
            }
        }
    }

    /**
     * This function removes instances of the many-many relationship
     *
     * @param string $RelationClassName
     * @param string $relationName
     * @param string $objectRelationName
     * @param DataObject $object
     */
    public static function removeObjectBelongsToManyManyRelation($RelationClassName, $relationName, $objectRelationName, $object) {
        $splitR = explode('\\', $RelationClassName);

        $niceR = $splitR[2];
        $table = $niceR.'_'.$objectRelationName;
        $fieldID = ClassInfo::shortname($object).'ID';

        DB::prepared_query('DELETE FROM "'.$table.'" WHERE "'.$fieldID.'" = ?', array($object->ID));
    }

    /**
     * This function links Groups with permissions
     * @param DataObject $object
     * @param array $fields
     */
    public static function setGroupPermissions($object, $fields) {
       if(isset($fields['Permissions'])){
           if($object->Permissions()->Count() > 0){
               foreach($object->Permissions() as $p){
                   $p->delete();
               }
           }

           foreach($fields['Permissions'] as $pr){
               $premission = new Permission();
               $premission->Code = $pr;
               $premission->Type = 1;
               $premission->GroupID = $object->ID;
               $premission->write();
           }
       }
    }


    /**
     * This function returns true if the $slug already exists
     *
     * @param string $className
     * @param string $slug
     * @return boolean
     */
    public static function SlugExists($className, $slug) {
        if($className && $slug){
            $obj = DataObject::get($className)->filter(['Slug' => $slug])->First();
            return $obj ? true : false;
        }
    }

    /**
     * This function returns either the HTTP method or an object slug from the request
     *
     * @param array $params
     * @param string $slug
     * @return string
     */
    public static function getParamMethod($params, $slug) {
        if (isset($params['Method'])) {
            return $params['Method'];
        } else if ($slug == null && isset($params['Slug'])) {
            return $params['Slug'];
        } else {
            return null;
        }
    }
    
    /**
     * This function creates nice class name for api customizing logic
     * @param string $controllerName
     * @return string
     */
    public static function getNiceCustomClassName($controllerName) {
        if(strpos($controllerName, "CustomFnc") !== false && strlen(str_replace("CustomFnc", "", $controllerName)) > 0){
            return str_replace("CustomFnc", "", $controllerName). "\EveryRESTfulAPI\Custom\\" . $controllerName;
        }
        return "EveryRESTfulAPI\Custom\\" . $controllerName;
    }

    /**
     * This function defines filtering options for searches,
     * and returns filters as an array of key-value pairs
     *
     * @param string $filter
     * @param string $className
     * @return array
     */
    public static function getNiceFilter($filter = null, $className = null) {
        if (!empty($filter)) {
            $niceFilter = [];
            if (isset($filter['searchAll'])) {
                $searchableFields = Config::inst()->get($className, 'searchable_fields');
                foreach ($searchableFields as $key => $val) {
                    if (!is_numeric($key) && !is_array($key) && $key != 'Array') {
                        $niceFilter[$key . ':' . str_replace('Filter', '', $val['filter'])] = $filter['searchAll'];
                    }

                    if(isset($filter['Created:GreaterThanOrEqual'])){
                        $niceFilter['Created:GreaterThanOrEqual'] = $filter['Created:GreaterThanOrEqual'];
                    }

                    if(isset($filter['Created:LessThanOrEqual'])){
                        $niceFilter['Created:LessThanOrEqual'] = $filter['Created:LessThanOrEqual'];
                    }
                }
            } else {
                foreach ($filter as $key => $val) {

                    if (strpos($key, '()') === false && strpos($key, ':') === false) {
                        //$niceFilter[$key . ':PartialMatch'] = $val;
                        $niceFilter[$key] = $val;
                    } else {
                        $niceFilter[$key] = $val;
                    }
                }
            }
            return $niceFilter;
        }
    }

    /**
     * This function returns true if the $val was successfully unserialized
     *
     * @param string $val
     * @return boolean
     */
    public static function getNiceValue($val) {
        return is_string($val) && is_array(unserialize($val)) ? unserialize($val) : $val;
    }

    /**
     * This function returns true if some filter is applied
     * and false otherwise
     *
     * @param string $anyFilter
     * @param string $filter
     * @return boolean
     */
    public static function isAnyFilter($filter, $anyFilter = false) {
        return isset($filter['searchAll']) || $anyFilter == 'true' ? true : false;
    }

    /**
     * This function returns the name of the column according to which the RecordSetItem
     * is sorted as well as the sorting direction
     *
     * @param string $orderColumn
     * @param string $orderDirection
     * @return string
     */
    public static function getRecordSetItemOrderColumn($orderColumn, $orderDirection = 'ASC') {
        $Sort = "CASE ".
                 "WHEN `itemdata_formfield_settings_FormFieldSetting`.`Title` = 'label' and `itemdata_formfield_settings_FormFieldSetting`.`Value` = '".strip_tags($orderColumn)."' THEN `itemdata_RecordSetItemData`.`Value` ".
                 "ELSE `RecordSetItem`.`Created`".
                "END";

        $sortOrder = "$Sort $orderDirection";
        return $sortOrder;
    }

    /**
     * Builds the object values in an nice array
     * @param object $obj
     * @return array
     */
    public static function getViewFieldsValues($obj) {
            $fields = [];
            $viewFields = Config::inst()->get($obj->getClassName(), 'API_View_Fields');
            foreach ($viewFields as $field) {
                if (strpos($field, '()') === false) {
                    $fields[$field] = $obj->$field;
                } else {
                    $niceMethod = str_replace('()', '', $field);
                    $fields[$niceMethod] = self::hasMethod($obj, $niceMethod) ? $obj->$niceMethod() : null;
                }
            }
            return $fields;
    }

    /**
     *
     * Create file as Base64 content
     * @param DataObject $object
     * @param string $relationName
     * @param string $relationType
     * @param string $base64
     * @return DataObject
     */
    public static function prepareAndCreateFileFromBase64($object, $relationName, $relationType, $base64) {
        if (strlen($base64) >= 20) {
            $ClassShortName = ClassInfo::shortname($object);
            $filename = AssetHelper::getAvailableAssetName('SilverStripe\Assets\Folder', 10);
            $folderName = Config::inst()->get('SilverStripe\Assets\File', 'root_dir_name').'/' . self::getCurrentDataStore()->Folder()->Filename;
            $parentFolder = Folder::find_or_make($folderName);
            $createdFiles[] = AssetHelper::createFileFromBase64($base64, $parentFolder, $ClassShortName, $filename);
            if (!empty($createdFiles)) {
                if ($relationType == 'has_one') {
                    $relationField = $relationName . 'ID';
                    $object->$relationField = $createdFiles[0]['ID'];
                } else {
                    foreach ($createdFiles as $f) {
                        $object->$relationName()->add($f['ID']);
                    }
                }
            }
            $object->write();
        }
        return $object;
    }

    /**
     * This function checks if an app is exiting and active
     * @param string $slug
     * @return boolean
     */
    public static function isActiveApp($slug) {
        $apps = self::getCurrentDataStore()->Apps();
        $app =  $apps->filter(['AppActive' => true, 'AppSlug' => strtolower($slug)])->first();
        if($app){
            return true;
        }
        return false;
    }

}
?>