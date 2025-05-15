<?php

namespace EveryRESTfulAPI\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Config\Config;
use EveryRESTfulAPI\Helper\EveryRESTfulAPIHelper;


include_once dirname(__DIR__, 1).'/ProtectedAPI/ProtectedDataObjectExtension'.EveryRESTfulAPIHelper::getPhpVersionIconCube().'.php';


class EveryRESTfulAPIDataObjectExtension extends DataExtension {

    private static $db = array();
    private static $has_one = [];
    private static $has_many = [];
    private static $belongs_many_many = array();
    private $slug = null;
    private $dbObject = null;


    public function CountItems() {
      return CountItems($this, $this->owner->ClassName);
    }

    public function CreatedByFullname() {
        if ($this->owner->CreatedByID > 0) {
            return $this->owner->CreatedBy()->getFullName();
        }
    }

    public function getPrevNextObjects($objectID) {
        getPrevNextObjects($objectID, $this->owner->ClassName);
    }

    public function getFormFields($request = false) {
        $Client = isset($_GET['Client']) && $_GET['Client'] ? strip_tags($_GET['Client']): 'frontend';
        $fields = setFormFields($this->owner->ClassName, $this->owner->getCMSFields(), $this, $this->owner, $this->owner->Slug, $this->dbObject);
        $formFields = [];
        $checkFields = [];
        $s = 1;

        $this->slug = getItemSlugFromFields($fields);
        $this->dbObject = getObjectBySlug($this->owner->ClassName, $this->owner->Slug);
        $formFields['TapedForm'] = Config::inst()->get($this->owner->ClassName, 'FrontendTapedForm') ? 1 : 0;
        $formFields['PrevNextItems'] = $this->dbObject ? $this->getPrevNextObjects($this->dbObject->ID) : '';
        $formFields['Reorderable'] = $this->isReorderable();
        foreach ($fields as $key => $val) {
            if (!empty($val)) {
                $columnFields = [];
                $f = 1;
                foreach ($val as $columnField) {
                    $name = isset($columnField['name']) ? $columnField['name'] : null;
                    $type = isset($columnField['type']) ? $columnField['type'] : null;
                    $class = isset($columnField['class']) ? $columnField['class'] : null;
                    $sourceClassName = isset($columnField['sourceClassName']) ? $columnField['sourceClassName'] : null;
                    if (!in_array($name, $checkFields) && !empty($name)) {
                        $setting = getNiceFieldSetting($columnField, $class);
                        $columnFields[] = array(
                            'Slug' => $name,
                            'Name' => isset($sourceClassName) ? _t($sourceClassName. '.' . strtoupper($name), $name) : $name,
                            'Type' => strip_tags($name) == 'Slug' ? 'readonlyfield' : strip_tags(getNiceFieldType($type, $class, $name, $this->owner->ClassName)),
                            'Index' => $f,
                            'Setting' => $setting,
                            'Value' => $Client == 'mobileapp' ? ['data' => getNiceFieldValue($columnField, $this->slug, $this->owner->ClassName)] : getNiceFieldValue($columnField, $this->slug, $this->owner->ClassName)
                        );
                        $checkFields[] = strip_tags($name);
                        $f++;
                    }
                }
            }

            $column = array(
                'Slug' => EveryRESTfulAPIHelper::getRandomString(40),
                'Index' => 1,
                'Fields' => $columnFields
            );

            if (!empty($column['Fields'])) {
                $formFields['Sections'][] = array(
                    'Title' => strip_tags($key),
                    'Slug' => EveryRESTfulAPIHelper::getRandomString(40),
                    'Index' => $key == "Main" ? 1 : $s,
                    'Columns' => $column
                );
                $s++;
            }
        }
        return $formFields;
    }
    
     function isReorderable() {
        return Config::inst()->get($this->owner->ClassName, 'default_sort') ? 1 : 0;
        
    }
}

?>