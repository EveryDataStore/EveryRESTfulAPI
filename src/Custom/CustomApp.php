<?php

namespace EveryRESTfulAPI\Custom;

use EveryRESTfulAPI\Helper\CustomAppHelper;
use EveryDataStore\Model\App;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;

class CustomApp extends Controller {
    
    public function init() {
        parent::init();
    }
   
    public static function installFreeApp($request, $appSlug) {
        return CustomAppHelper::installFreeApp($appSlug);
    }
    
    public static function deinstallApp($request, $appSlug) {
        return CustomAppHelper::deinstallApp($appSlug);
    }
    
    
    
    public static function getAvaibleApps() {
         $apps = App::get()->filter(['Active' => 1]);
         return $apps ? self::apps2array($apps) : null;
    }
    
    public static function getInstalledApps() {
        $apps = EveryRESTfulAPIHelper::getCurrentDataStore()->Apps();
        return $apps->Count() > 0 ? self::apps2array($apps) : null;
        
    }
    
    public static function apps2array($apps) {
        $ret = [];
        foreach ($apps as $app) {
            $ret[] = array(
                'Slug' => $app->Slug,
                'Active' => $app->Active,
                'Title' => $app->Title,
                'Description' => $app->Description,
                'ShortDescription' => $app->ShortDescription,
                'Author' => $app->Author,
                'Website' => $app->Website,
                'Version' => $app->Version,
                'Type' => $app->Type,
                'Price' => $app->Price,
                'Installed' => $app->Installed
               
            );
        }
        return $ret;
    }

}
