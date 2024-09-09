<?php

namespace EveryRESTfulAPI\Custom;

use EveryRESTfulAPI\Helper\CustomAssetHelper;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;

class CustomCore extends Controller {
    
    public function init() {
        parent::init();
    }
    
    public static function getAllowedModelNames() {
        $models = Config::inst()->get('API_RealationField_Allowed_Models');
        sort($models);
        return $models;
    }
    
    
}
