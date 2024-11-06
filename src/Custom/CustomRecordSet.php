<?php

namespace EveryRESTfulAPI\Custom;

use SilverStripe\Control\Controller;
use EveryDataStore\Model\RecordSet\RecordSet;
use EveryDataStore\Model\RecordSet\RecordSetItem;

include_once str_replace('Custom','',__DIR__).'ProtectedAPI/ProtectedCustomRecordSet.php';

class CustomRecordSet extends Controller {

    private static $allowed_actions = [];

    public function init() {
        parent::init();
    }

    public function setRecordFormData($request, $recordSlug = null, $import = false) {
        if (checkSetFormDataPermissions()) {
            $formData = json_decode($request->getBody(), true);
            if (!empty($formData) && isset($formData['Record'])) {
                $import = isset($formData['Record']['Import']) ? true : false;
                
                $record = setRecordSet($formData['Record'], $import);
                $recordForm = $record->Form()->ID > 0 ? $record->Form() : initForm($record->ID);
                setFormData($formData['Record'], $recordForm->ID, $import);
                return $record->Slug;
            }
        }
    }
    
    public function importRecordFormData($request, $recordSlug = null) {
        return $this->setRecordFormData($request, $recordSlug, true);
    }

    public function getRecordFormData($request, $recordSlug) {
        if (checkGetFormDataPermissions()) {
            $recordItemSlug = isset($request->getVars()['itemSlug']) ? $request->getVars()['itemSlug'] : null;
            $recordVersion = isset($request->getVars()['recordVersion']) ? $request->getVars()['recordVersion'] : 1;
            $recordItemVersion = isset($request->getVars()['recordItemVersion']) ? $request->getVars()['recordItemVersion'] : 1;
            $recordTranslateLabels = isset($request->getVars()['translateLabels']) ? $request->getVars()['translateLabels'] : 0;
            if ($recordSlug) {
                return getFormData($recordSlug, $recordItemSlug, $recordVersion, $recordItemVersion, $recordTranslateLabels);
            }
        }
    }

    public function getRecordLabels($request, $recordSlug) {
        $record = RecordSet::get()->filter(['Slug' => $recordSlug])->first();
        $labels = [];
        $labels[] = array('slug' => $recordSlug);
        foreach ($record->getRecordResultlistFieldsToArray() as $Field) {
            $labels[] = array(
                'data' => $Field['Lable']
            );
        }
        return $labels;
    }

    public function initRecordItem($request, $recordSlug) {
        $record = RecordSet::get()->filter(['Slug' => $recordSlug])->first();
        if ($record) {
            $recordItem = \EveryDataStore\Model\RecordSet\RecordSetItem::Create();
            $recordItem->RecordSetID = $record->ID;
            $recordItem->writeWithoutVersion();
            return $recordItem->Slug;
        }
    }

    public function setRecordItem($request, $itemSlug) {
        return setRecordSetItem($request, $itemSlug);
    }

    public function getRecordItems($request, $recordSlug) {
        $record = RecordSet::get()->filter(['Slug' => $recordSlug])->first();
        if ($record) {
            $fields = $request->getVars();
            $filter = isset($fields['Filter']) ? str_replace(['{', '}'], ['', ''], $fields['Filter']) : '';
            $page = isset($fields['Page']) ? (int) $fields['Page'] : '';
            $createdFrom = isset($fields['CreatedFrom']) ? $fields['CreatedFrom'] : '';
            $createdTo = isset($fields['CreatedTo']) ? $fields['CreatedTo'] : '';
            $totalResults = isset($fields['TotalResults']) ? (int) $fields['TotalResults'] : 100000;
            $length = isset($fields['Length']) ? (int) $fields['Length'] : '';
            $orderOpt = isset($fields['OrderOpt']) ? $fields['OrderOpt'] : 'ASC';
            $orderColumn = isset($fields['OrderColumn']) ? getOrderColumn($fields['OrderColumn'], $recordSlug) : '';
            $searchColumns = getSearchColumns(explode(',', $filter));
            return getRecordItems($record, $page, $length, $searchColumns, $orderColumn, $createdFrom, $createdTo, $totalResults, $orderOpt);
        }
    }

    public function printRecordItem($request, $itemSlug) {
        $recordItem = RecordSetItem::get()->filter(['Slug' => $itemSlug])->first();
        if ($recordItem) {
            $templateSlug = $request->getVars()['templateSlug'] ? $request->getVars()['templateSlug'] : null;
            return getRecordSetItemAsPDF($recordItem, $templateSlug);
        }
    }

    public function getRecords($request, $itemSlug) {
        return getRecords();
    }

    public static function getSummableItems($request, $recordSlug) {
        $record = RecordSet::get()->filter(['Slug' => $recordSlug])->first();
        if ($record) {
            $fields = $request->getVars();
            $filter = isset($fields['Filter']) ? str_replace(['{', '}'], ['', ''], $fields['Filter']) : '';
            $totalResults = isset($fields['TotalResults']) ? (int) $fields['TotalResults'] : 100000;
            $searchColumns = getSearchColumns(explode(',', $filter));
            return getSummableItems($record, $searchColumns, $totalResults);
        }
    }
    
    public static function setFormFieldSetting($fieldID, $title, $value) {
            setFormFieldSetting($fieldID, $title, $value);
    }
     
    public static function setRecordSet($recordSetData, $import = false){
         setRecordSet($recordSetData, $import);
    }
    
    public static function initForm($recordSetID){
         initForm($recordSetID);
    }
    
    public static function setFormData($formData, $recordSetFormID){
         setFormData($formData, $recordSetFormID);
    }
}
