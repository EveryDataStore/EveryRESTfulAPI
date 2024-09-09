<?php

namespace EveryRESTfulAPI\Custom;
use SilverStripe\Control\Controller;
use EveryRESTfulAPI\Helper\CustomDataExporterHelper;

class CustomDataExporter extends Controller {
    
    private static $allowed_actions = [];
    public static $Helper = null;

    public function init() {
        parent::init();
    }
    
    public static function exportRecordData($request, $recordSetSlug){
      return CustomDataExporterHelper::exportRecordData($request, $recordSetSlug );
    }
    
    public static function exportModelData($request, $modelName){
        return CustomDataExporterHelper::exportModelData($request, $modelName);
    }
}

