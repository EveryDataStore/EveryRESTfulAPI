<?php

namespace EveryRESTfulAPI\Helper;

use EveryRESTfulAPI\Helper\EveryRESTfulAPIHelper;
use EveryRESTfulAPI\Helper\CustomRecordSetHelper;
use EveryDataStore\Model\App;
use EveryDataStore\Model\EveryConfiguration;
use EveryDataStore\Model\Menu;
use SilverStripe\Core\Config\Config;

/** EveryDataStore/EveryRESTfulAPI v1.0
 * 
 * This class implements logic behind installing/deinstalling of the apps, 
 * as well as dataStore setups according to app installations
 * 
 */
class CustomAppHelper extends EveryRESTfulAPIHelper {

    /**
     * Install an app by given slug
     * @param string $slug
     * @return string
     */
    public static function installFreeApp($slug) {
        $app = App::get()->filter(['Slug' => $slug, 'Active' => true])->first();
        if ($app) {
            return self::setupApp($app);
        }
    }
    
    /**
     * EveryDataStore 2.0
     */
    public static function purchaseApp() {}

    /**
     * Deinstall an app by given slug
     * @param string $slug
     * @return string 
     */
    public static function deinstallApp($slug) {
        if (!self::isActiveApp($slug)) {
            return $slug;
        }
        
        $app = App::get()->filter(['Slug' => $slug])->first();
        $apps = self::getCurrentDataStore()->Apps();
        if ($apps) {
            $repApp = $apps->filter(['ID' => $app->ID])->first();
            if ($repApp) {
                $apps->remove($repApp);
                $menu = Menu::get()->byID($repApp->AppMenuID);
                if ($menu) {
                    $menu->delete();
                }
            }
        }
        return $slug;
    }

    /**
     * Set up the app defaults and independencies
     * @param DataObject $App
     */
    public static function setupApp($app) {
        $config = Config::inst()->get('app_' . $app->Slug);
        self::setupMenu($config, $app);
        self::setupDefaultConfig($config);
        self::setupRecordSetFile($config);
    }

    /**
     * Creates default menu of app
     * @param array $config
     * @param dataobject $app
     * @return string
     */
    public static function setupMenu($config, $app) {
        $config = Config::inst()->get('app_' . $app->Slug);
        $menuID = '';
        if (isset($config['Menu'])) {
            $menuID = self::createAppMenu($config['Menu']);
        }

        self::setupDataStoreApp($app, [
            'AppSlug' => $app->Slug,
            'AppActive' => true,
            'AppVersion' => isset($config['Version']) ? $config['Version'] : 0,
            'AppMenuID' => $menuID ? $menuID : 0,
            'AppInstalled' => date('d-m-Y H:i:s')
        ]);

        $children = [];
        if (isset($config['Children'])) {
            foreach ($config['Children'] as $key => $val) {
                $niceKey = strtolower($niceKey);
                if (!self::isActiveApp($niceKey)) {
                    if (isset($config['Children'][$key]['Menu'])) {
                        $menuID = self::createAppMenu($config['Children'][$key]['Menu'], $menuID);
                    }
                    $children[$niceKey] = [
                        'Active' => '1',
                        'MenuID' => $menuID ? $menuID : 0
                    ];
                }
            }
        }

        self::setupDataStoreApp($app, [
            'AppSlug' => $app->Slug,
            'AppChildren' => !empty($children) ? serialize($children) : null,
            'AppActive' => true,
            'AppVersion' => isset($config['Version']) ? $config['Version'] : 0,
            'AppMenuID' => $menuID,
            'AppInstalled' => date('d-m-Y H:i:s')
        ]);

        return $app->Slug;
    }

    /**
     * Creates an menu item
     * @param array $args
     * @return string MenuID
     */
    public static function createAppMenu($args, $parentID = 0) {
        $menu = new Menu;
        foreach ($args as $k => $v) {
            $menu->{$k} = $v;
        }
        $menu->ParentID = isset($args['ParentID']) ? $args['ParentID'] : 0;
        return $menu->write();
    }

    /**
     * Add app to DataStore
     * @app DataObject $app
     * @param array $args
     * @return mixed 
     */
    public static function setupDataStoreApp($app, $args) {
        $DataStore = self::getCurrentDataStore();
        if($DataStore){
            return $DataStore->Apps()->add($app, $args);
        }
    }

    /**
     * Creates the app configurations
     * @param array $config
     */
    public static function setupDefaultConfig($config) {
        if (isset($config['Configuration'])) {
            $repID = EveryRESTfulAPIHelper::getCurrentDataStoreID();
            foreach ($config['Configuration'] as $k => $v) {
                $c = EveryConfiguration::get()->filter(['Title' => $k, 'DataStoreID' => $repID])->first();
                if (!$c) {
                    $newc = new EveryConfiguration();
                    $newc->Title = $k;
                    $newc->Value = $v;
                    $newc->DataStoreID = $repID;
                    $newc->write();
                }
            }
        }
    }

    /**
     * Create demo databases of the app
     * @param array $config 
     */
    public static function setupRecordSetFile($config) {
        $ret = '';
        if (isset($config['RecordSetFile'])) {
            foreach ($config['RecordSetFile'] as $k => $v) {
                $content = json_decode(file_get_contents(BASE_PATH . '/' . $v), true);
                if ($content) {
                    foreach ($content as $c) {
                        if (isset($c['Record'])) {
                            $recordSet = \EveryRESTfulAPI\Custom\CustomRecordSet::setRecordSet($c['Record'], true);
                            $recordSetForm = \EveryRESTfulAPI\Custom\CustomRecordSet::initForm($recordSet->ID);
                            $ret .= \EveryRESTfulAPI\Custom\CustomRecordSet::setFormData($c['Record'], $recordSetForm->ID, true);
                        }
                    }
                }
            }
        }
        return $ret;
    }

}
