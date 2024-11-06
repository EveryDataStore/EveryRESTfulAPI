<?php

namespace EveryRESTfulAPI\Helper;

use EveryRESTfulAPI\Helper\EveryRESTfulAPIHelper;
use EveryRESTfulAPI\Helper\CustomAssetHelper;
use EveryDataStore\Model\RecordSet\RecordSet;
use EveryDataStore\Model\RecordSet\RecordSetItem;
use EveryDataStore\Model\RecordSet\RecordSetItemData;
use EveryDataStore\Model\RecordSet\Form\FormField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Assets\Folder;

/** EveryDataStore/EveryRESTfulAPI v1.0
 * 
 * This class implements import functions for Model and Record data
 * 
 */
class CustomDataImporterHelper extends EveryRESTfulAPIHelper {

    /**
     * This function imports a file containing the data for the provided Record
     * @param HTTPRequest $request
     * @param string $recordSetSlug
     * @return string
     */
    public static function importRecordData($request, $recordSetSlug) {
        $recordSet = RecordSet::get()->filter(['Slug' => $recordSetSlug])->first();
        if ($recordSet) {
            $postVars = $request->postVars();
            $file = self::uploadImportFile($postVars['file']);
            $importedFile = isset($file['File']) && $file['File'] ? $file['File'] : null;
            if ($importedFile) {
                if (strtolower($importedFile->getExtension()) == 'csv') {
                    return self::importRecordDataCSV(ASSETS_PATH . '/.protected/' . str_replace('/assets', '', $importedFile->getSourceURL()), $recordSet->ID);
                }

                if (strtolower($importedFile->getExtension()) == 'json') {
                    return self::importRecordDataJSON(ASSETS_PATH . '/.protected/' . str_replace('/assets', '', $importedFile->getSourceURL()), $recordSet->ID);
                }

                if (strtolower($importedFile->getExtension()) == 'sql') {
                    return self::importRecordDataSQL(ASSETS_PATH . '/.protected/' . str_replace('/assets', '', $importedFile->getSourceURL()), $recordSet->ID);
                }
            }
        }
    }

    /**
     * This function imports RecordSetItems from JSON file
     * @param string $filePath
     * @param string $recordSetID
     * @return string
     */
    public static function importRecordDataJSON($filePath, $recordSetID) {
        $data = json_decode(file_get_contents($filePath), true);
        if ($data) {
            $row = 0;
            foreach ($data as $d) {
                $recordSetItem = new RecordSetItem();
                $recordSetItem->RecordSetID = $recordSetID;
                $recordSetItem->write();
                foreach ($d as $k => $v) {
                    if ($k !== 'Slug') {
                        $formFieldSlug = explode('_', $k)[0];
                        $formField = FormField::get()->filter(['Slug' => $formFieldSlug])->First();
                        $RecordSetItemData = $obj ? $obj : new RecordSetItemData();
                        $RecordSetItemData->Value = $v;
                        $RecordSetItemData->FormFieldID = $formField->ID;
                        $RecordSetItemData->RecordSetItemID = $recordSetItem->ID;
                        $RecordSetItemData->write();
                    }
                }
                $row++;
            }
            return $row . ' Rows has been added';
        }
    }

    /**
     * This function imports RecordSetItems from CSV file
     * @param string $filePath
     * @param string $recordSetID
     * @return string
     */
    public static function importRecordDataCSV($filePath, $recordSetID) {
        $file = fopen($filePath, 'r');
        $row = 0;
        if ($file) {
            $formFieldIDs = [];
            while (($line = fgetcsv($file)) !== FALSE) {
                $niceLine = explode(";", $line[0]);
                if ($row == 0) {
                    $formFieldIDs = self::getNiceFormFieldIDs($niceLine);
                } else {
                    self::createRecordSetItem($recordSetID, $formFieldIDs, $niceLine);
                }
  
                $row++;
            }
            fclose($file);
        }
        return $row . ' Rows has been added';
    }

    /**
     * Still in development 
     * @param  string $filePath
     * @param DataObject $recordSet
     */
    public static function importRecordDataSQL($filePath, $recordSet) {}

    /**
     * This function imports a file containing the data for the provided Model
     * @param HTTPRequest $request
     * @param string $modelName
     * @return string
     */
    public static function importModelData($request, $modelName) {
        $modelClass = Config::inst()->get('API_Namespace_Class_Map', $modelName);
        if ($modelClass) {
            $postVars = $request->postVars();
            $file = self::uploadImportFile($postVars['file']);
            $importedFile = isset($file['File']) && $file['File'] ? $file['File'] : null;
            if ($importedFile) {
                $importedFile->getSourceURL();
                if (strtolower($importedFile->getExtension()) == 'csv') {
                    return self::importModelDataCSV(ASSETS_PATH . '/.protected/' . str_replace('/assets', '', $importedFile->getSourceURL()), $modelClass);
                }
                if (strtolower($importedFile->getExtension()) == 'json') {
;
                    return self::importModelDataJSON(ASSETS_PATH . '/.protected/' . str_replace('/assets', '', $importedFile->getSourceURL()), $modelClass);
                }
            }
        }
    }

    /**
     * Imports Model items from json file
     * @param string $filePath
     * @param string  $modelClass
     * @return string
     */
    public static function importModelDataJSON($filePath, $modelClass) {
        $data = json_decode(file_get_contents($filePath), true);
        if ($data) {
            $row = 0;
            foreach ($data as $d) {
                $obj = Injector::inst()->create($modelClass);
                foreach ($d as $k => $v) {
                    if ($k !== 'Slug' && strpos($k, '(') === false && $v) {
                        $obj->{$k} = $v;
                    }
                }
                $obj->write();
                $row++;
            }
        }
        $msg = $row . ' Rows has been added!';
        return $msg;
    }

    /**
     * This function adds ModelItems and returns the number of items that have been added
     * @param string $filePath
     * @param string $modelClass
     * @return string
     */
    public static function importModelDataCSV($filePath, $modelClass) {
        $file = fopen($filePath, 'r');
        $row = 0;
        if ($file) {
            $modelFields = [];
            while (($line = fgetcsv($file)) !== FALSE) {
                $niceLine = explode(";", $line[0]);
                if ($row == 0) {
                    $modelFields = self::getNiceModelFields($niceLine);
                } else {
                    self::createModelItem($modelClass, $modelFields, $niceLine);
                }
                $row++;
            }
            fclose($file);
        }
        $msg = $row . ' Rows has been added!';
        return $msg;
    }

    /**
     * This function creates and uploads a temporary file to the appropriate folder 
     * @param DataObject $file
     * @return array
     */
    public static function uploadImportFile($file) {
        $folderPath = str_replace(' ', '', self::getCurrentDataStore()->Folder()->Filename . '/tmp/import/' . date("Ymdhis"));
        $folder = Folder::find_or_make($folderPath);
        $tmpFile = array(
            'name' => isset($file['name']) ? $file['name'] : null,
            'type' => isset($file['type']) ? $file['type'] : null,
            'tmp_name' => isset($file['tmp_name']) ? $file['tmp_name'] : null,
            'error' => isset($file['error']) ? $file['error'] : null,
            'size' => isset($file['size']) ? $file['size'] : null
        );
        return CustomAssetHelper::doUpload($tmpFile, $folder);
    }

    /**
     * This function extracts field slugs from the CSV file and returns them as
     * an array of key-value pairs
     * @param array $csvFirstLine
     * @return array
     */
    public static function getNiceFormFieldIDs($csvFirstLine) {
        $ret = [];
        foreach ($csvFirstLine as $key => $val) {
            $niceSlug = is_array(explode('_', $val)) ? explode('_', $val)[0] : $val;
            $formField = FormField::get()->filter(['Slug' => $niceSlug])->first();
            $ret[$key] = $formField ? $formField->ID : 0;
        }
        return $ret;
    }

    /**
     * This function creates a new RecordSetItem
     * @param string $recordSetID
     * @param array $formFieldIDs
     * @param array $itemData
     */
    public static function createRecordSetItem($recordSetID, $formFieldIDs, $itemData) {

        $recordSetItem = new RecordSetItem();
        $recordSetItem->RecordSetID = $recordSetID;
        $recordSetItem->write();
        self::createRecordSetItemData($recordSetItem->ID, $formFieldIDs, $itemData);
    }

    /**
     * This function adds data to the RecordSetItem 
     * @param string $recordSetItemID
     * @param array $formFieldIDs
     * @param array $itemData
     */
    public static function createRecordSetItemData($recordSetItemID, $formFieldIDs, $itemData) {

        if (!empty($itemData) && $recordSetItemID > 0) {
            $i = 0;
            foreach ($itemData as $data) {
                $recordSetItemData = new RecordSetItemData();
                $recordSetItemData->RecordSetItemID = $recordSetItemID;
                $recordSetItemData->FormFieldID = $formFieldIDs[$i];
                $recordSetItemData->Value = $data;
                $recordSetItemData->write();
                $i++;
            }
        }
    }

    /**
     * This function extracts fields and values from the CSV file and returns them as
     * an array of key-value pairs
     * @param array $csvFirstLine
     * @return array
     */
    public static function getNiceModelFields($csvFirstLine) {
        $ret = [];
        foreach ($csvFirstLine as $key => $val) {
            $ret[$key] = $val;
        }
        return $ret;
    }

    /**
     * This function creates a new ModelItem
     * @param string $modelClass
     * @param array $fields
     * @param array $objData
     */
    public static function createModelItem($modelClass, $fields, $objData) {
        $obj = Injector::inst()->create($modelClass);
        if ($obj && count($objData) > 0) {
            $i = 0;
            $obj->write();
            foreach ($objData as $data) {
                $niceField = $fields[$i];
                if (strpos($niceField, '.') !== false) {
                    $explode = explode('.', $niceField);
                    $obj = self::createModelItemRelations($obj, $explode[0], $modelClass, $data);
                } else {
                    $obj->{$niceField} = $data;
                }
                $i++;
            }
            $obj->write();
        }
    }

    /**
     * This function sets Model relations 
     * @param DataObject $obj
     * @param string $fieldName
     * @param string $modelClass
     * @param string $data
     * @return DataObject
     */
    private static function createModelItemRelations($obj, $fieldName, $modelClass, $data) {
        $relations = self::getObjectRelations($modelClass);
        $relation = $relations ? self::getObjectRelationsByName($relations, $fieldName)[0] : null;
        $relationName = $relation['Name'] ? $relation['Name'] : null;
        $relationType = $relation['Type'] ? $relation['Type'] : null;
        $relationClassName = $relation['ClassName'] ? $relation['ClassName'] : null;
        if ($relationName && $relationClassName) {
            if ($relationType == 'has_many' || $relationType == 'many_many' || $relationType == 'belongs_many_many') {
                $obj = self::createModelItemRelationMany($obj, $relationName, $relationClassName, $data);
            } else {
                $obj = self::createModelItemRelationOne($obj, $relationName, $relationClassName, $data);
            }
        }
        return $obj;
    }

    /**
     * This function defines relations that have at least one "many" side
     * @param DataObject $obj
     * @param string $relationName
     * @param string $relationClassName
     * @param string $data
     * @return DataObject
     */
    private static function createModelItemRelationMany($obj, $relationName, $relationClassName, $data) {
        if (strpos($data, ',') !== false) {
            foreach (explode(',', $data) as $relationSlug) {
                $relationItem = self::getObjectbySlug($relationClassName, str_replace('"', '', $relationSlug));
                if ($relationItem) {
                    $obj->$relationName()->add($relationItem);
                }
            }
        } else {
            $relationItem = self::getObjectbySlug($relationClassName, str_replace('"', '', $data));
            if ($relationItem) {
                $obj->$relationName()->add($relationItem);
            }
        }
        return $obj;
    }

    /**
     * This function defines "has_one" model relations 
     * @param DataObject $obj
     * @param string $relationName
     * @param string $relationClassName
     * @param string $data
     * @return DataObject
     */
    private static function createModelItemRelationOne($obj, $relationName, $relationClassName, $data) {
        $relationItem = self::getObjectbySlug($relationClassName, $data);
        if ($relationItem) {
            $niceField = $relationName . 'ID';
            $obj->{$niceField} = $relationItem->ID;
        }
        return $obj;
    }

}
