<?php

namespace EveryRESTfulAPI\Custom;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use EveryRESTfulAPI\Helper\CustomDataImporterHelper;

class CustomDataImporter extends Controller {
    
    private static $allowed_actions = [];
    public static $Helper = null;

    public function init() {
        parent::init();
        self::$Helper = Injector::inst()->create('EveryRESTfulAPI\Helper\CustomDataImporterHelper');
    }
    
    public static function importRecordData($request, $recordSetSlug){
      return CustomDataImporterHelper::importRecordData($request, $recordSetSlug );
    }
    
    public static function importModelData($request, $modelName){
        return CustomDataImporterHelper::importModelData($request, $modelName);
    }
}

