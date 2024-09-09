<?php

namespace EveryRESTfulAPI\Control;

use EveryRESTfulAPI\Helper\EveryRESTfulAPIHelper;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\Core\Config\Config;


include_once str_replace('Control','',__DIR__).'ProtectedAPI/ProtectedAPI.php';

/** EveryDataStore/EveryRESTfulAPI
 * 
 * This class formats HTTP requests and ensures an authenticated and secure exchange of information 
 * 
 */


class EveryRESTfulAPIController extends Controller {

    private static $url_segment = 'restful';
    private static $allowed_actions = ['auth', 'custom', 'info'];
    private static $url_handlers = array(
        'auth/$Action' => 'auth',
        'custom/$Action/$ID/$OtherID' => 'custom',
        '$ClassName/$Slug/$Method/$Param1' => 'index',
        'info' => 'info',
    );
    private $URLparams = [];
    private $ClassName = null;
    private $Locale = 'en_US';
    private $Slug = null;
    private $SlugExists = false;
    private $Method = null;
    private $Fields = [];
    private $Filter = [];
    private $AnyFilter = false;
    private $Page = 1;
    private $Length = 1000;
    private $TotalResults = 10000;
    private $OrderColumn = '';
    private $OrderOpt = 'ASC';
    private $DeleteType = 'logically';
    private $Version = null;
    private $Rollback = null;
    private $Relations = null;
    private $AnswerRequest = null;

    public function init() {
        parent::init();
    }
    
    
    /**
     * In the index function all API properties will be defined from request
     * 
     * @param HTTPRequest $request
     */
    public function index(HTTPRequest $request) {
        $allURLparams = $request->allParams() ? $request->allParams() : array();
        $this->URLparams = $allURLparams;
        $this->ClassName = isset($allURLparams['ClassName']) ? Config::inst()->get('API_Namespace_Class_Map', $allURLparams['ClassName']) : null;
        $this->SlugExists = isset($allURLparams['Slug']) && $allURLparams['Slug'] != 'null' ? EveryRESTfulAPIHelper::SlugExists($this->ClassName, $allURLparams['Slug']) : false;
        $this->Slug = $this->SlugExists ? $allURLparams['Slug'] : null;
        $this->Method = EveryRESTfulAPIHelper::getParamMethod($allURLparams, $this->Slug);
        $this->Fields = EveryRESTfulAPIHelper::getRequestParams($this->request, 'Fields');
        $this->Filter = EveryRESTfulAPIHelper::getNiceFilter(EveryRESTfulAPIHelper::getRequestParams($this->request, 'Filter'), $this->ClassName);
        $this->AnyFilter = EveryRESTfulAPIHelper::isAnyFilter(EveryRESTfulAPIHelper::getRequestParams($this->request, 'Filter'), EveryRESTfulAPIHelper::getRequestParams($this->request, 'AnyFilter'),);
        $this->Page = (int) EveryRESTfulAPIHelper::getRequestParams($this->request, 'Page') && (int) EveryRESTfulAPIHelper::getRequestParams($this->request, 'Page') > 0 ? (int) EveryRESTfulAPIHelper::getRequestParams($this->request, 'Page') : 1;
        $this->Length = EveryRESTfulAPIHelper::getRequestParams($this->request, 'Length') ? EveryRESTfulAPIHelper::getRequestParams($this->request, 'Length') : $this->Length;
        $this->TotalResults = EveryRESTfulAPIHelper::getRequestParams($this->request, 'TotalResults') ? EveryRESTfulAPIHelper::getRequestParams($this->request, 'TotalResults') : $this->TotalResults;
        $this->OrderOpt = EveryRESTfulAPIHelper::getRequestParams($this->request, 'OrderOpt') ? EveryRESTfulAPIHelper::getRequestParams($this->request, 'OrderOpt') : $this->OrderOpt;
        $this->DeleteType = EveryRESTfulAPIHelper::getRequestParams($this->request, 'DeleteType');
        $this->Version = (int) EveryRESTfulAPIHelper::getRequestParams($this->request, 'Version');
        $this->Rollback = EveryRESTfulAPIHelper::getRequestParams($this->request, 'Rollback');
        $this->Relations = EveryRESTfulAPIHelper::getObjectRelations($this->ClassName);
        $defaultOrder = Config::inst()->get($this->owner->ClassName, 'default_sort') ? str_replace(['ASC', 'DESC'], ['', ''], Config::inst()->get($this->owner->ClassName, 'default_sort')) : 'Created';
        $this->OrderColumn = EveryRESTfulAPIHelper::getRequestParams($this->request, 'OrderColumn') ? EveryRESTfulAPIHelper::getRequestParams($this->request, 'OrderColumn') : $defaultOrder;
        protected_api_handle_api($this, $this->getNiceClassProperties());
    }
    
    /**
     * This function executes the requested authentication action and returns the response
     * 
     * @param HTTPRequest $request
     * @return DataObject
     */
    public function auth(HTTPRequest $request) {
        return protected_api_handle_auth($this, $this->getNiceClassProperties());
    }
    
    /**
     * This function authenticates HTTP request with a custom URL scheme
     * 
     * @param HTTPRequest $request
     * @return DataObject
     */
    public function custom(HTTPRequest $request) {
        $props = $this->getNiceClassProperties();
        return protected_api_handle_custom($this, $props);
    }
   
    /**
     * This function authenticates HTTP request with a custom URL scheme
     * 
     * @param HTTPRequest $request
     * @return DataObject
     */
    public function info(HTTPRequest $request) {
        $props = $this->getNiceClassProperties();
        echo 'WHAT';
        return protected_api_info($this, $props);
    }
   
    /**
     * This function creates an array with all class class properties
     * @return integer
     */
    private function getNiceClassProperties() {
        $ret = [];
        $ref = new \ReflectionClass('EveryRESTfulAPI\Control\EveryRESTfulAPIController');
        $props = array_filter($ref->getProperties(), function($property) {
            return $property->class == 'EveryRESTfulAPI\Control\EveryRESTfulAPIController';
        });

        foreach ($props as $prop) {
            if ($prop->name !== 'url_segment' && $prop->name !== 'allowed_actions' && $prop->name !== 'url_handlers') {
                if ($prop->name == 'Filter' || $prop->name == 'Fields' || $prop->name == 'URLparams') {
                    $ret[$prop->name] = $this->{$prop->name} && is_array($this->{$prop->name}) ? $this->{$prop->name} : [];
                } else {
                    $ret[$prop->name] = $this->{$prop->name} ? $this->{$prop->name} : '';
                }
            }
        }
        return $ret;
    }


    /**
     * This function checks for the type of $data, permission authorization and 
     * returns an array of key-value pairs of the $data
     * 
     * @param DataObject|DataList|string|array $data
     * @param string $className
     * @param array $relations
     * @param DataObject $singleobj
     * @return DataObject|int
     */
    public function outdata($data, $className = false, $relations = false, $singleobj = false) {
        return protected_api_outdata($data, $className, $relations, $singleobj, $this);
    }
    
    /**
     * This function returns a JSON representation of response value
     * 
     * @param array $outdata
     * @param integer $statusCode
     * @param string $statusDescription
     */
    public function answer($outdata = null, $statusCode = 200, $statusDescription = null) {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->setStatusCode($statusCode, $statusDescription);
        $out = '';
        if ($outdata !== null) {
           $out = $outdata;
        } else {
            if ($statusDescription !== null) {
                $out = $statusDescription;
            } else {
                $out = array(
                    'Answer' => 'Please check your request',
                    'Request' => $this->AnswerRequest
                );
            }
        }
        echo json_encode($out);
    }
}
