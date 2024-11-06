<?php

namespace EveryRESTfulAPI\Helper;

use EveryRESTfulAPI\Helper\EveryRESTfulAPIHelper;
use EveryDataStore\Model\RecordSet\RecordSet;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverStripe\Control\Director;

/** EveryDataStore/EveryRESTfulAPI v1.0
 * 
 * This class implements export functions for Model and Record data
 * 
 */
class CustomDataExporterHelper extends EveryRESTfulAPIHelper {

    /**
     * This function creates a Record export file 
     * and exports it according to the type of file
     * @param HTTPRequest $request
     * @param string $recordSetSlug
     * @return array
     */
    public static function ExportRecordData($request, $recordSetSlug) {
        $recordSet = RecordSet::get()->filter(['Slug' => $recordSetSlug])->first();
        
        if ($recordSet) {
            $body = json_decode($request->getBody(), true);
            $type = $body['type'];
            $recordSetItems = isset($body['recordItems']) ? $body['recordItems']: [];
            $exportFile = self::createExportTmpFile($type, str_replace(' ','',$recordSet->Title));
            
            if ($exportFile) {
                if ($type == 'csv') {
                    return self::exportRecordDataCSV($exportFile, $recordSet, $recordSetItems);
                }
                if ($type == 'json') {
                    return self::exportRecordDataJSON($exportFile, $recordSet, $recordSetItems);
                }
            }
        }
    }
    
    /**
     * Exports RecordSetItems as json 
     * @param dataobject $file
     * @param dataobject $recordSet
     * @param dataobject $recordSetItems
     */
    public static function exportRecordDataJSON($file, $recordSet, $recordSetItems = null) {
        $items = $recordSetItems ? $recordSet->getNiceItems()->filter(['Slug' => $recordSetItems]) : $recordSet->getNiceItems()->Sort('Created ASC')->limit(10000);
        $fileItems = [];
        $niceNiceRecordLabels = self::getNiceRecordLabels($recordSet);
        $formFieldSlugs = $niceNiceRecordLabels['Slugs'];
        if ($items) {
            foreach ($items as $item) {
                if ($item->ItemData()->Count() > 0) {
                    $fileItemsData = [];
                    $fileItemsData['Slug'] =  $item->Slug;
                    $itemData = $item->ItemData();
                    foreach($formFieldSlugs as $Slug){
                        $data = $itemData->filter(['FormField.Slug' => $Slug])->first();
                        $value = $data ? $data->Value(): null;
                        if(is_array($value)){
                            $value = implode(',', $value);
                        }
                        $niceIndex = $Slug.'_'.$data->FormField()->getLabel();
                        $fileItemsData[$niceIndex]= $value;
                    }
                    $fileItems[] = $fileItemsData;
                    unset($fileItemsData);
                }
            }
           
            file_put_contents($file,json_encode($fileItems));  
            unset($fileItems);
            $objFile = self::createExportFile($file, 'json', $recordSet->Title);
            $fileURL = Director::absoluteBaseURL().$objFile['fileSourceURL'] . '?hash=' . $objFile['file']->FileHash;
            return array('fileURL' => $fileURL);
        }
        return array('No items where found');
    }

    /**
     * This function prepares and creates a CSV file for export
     * @param dataobject $file
     * @param dataobject $recordSet
     * @param dataobject $recordSetItems
     * @return array
     */
    public static function exportRecordDataCSV($file, $recordSet, $recordSetItems = null) {
        $items = $recordSetItems ? $recordSet->getNiceItems()->filter(['Slug' => $recordSetItems]) : $recordSet->getNiceItems()->Sort('Created ASC')->limit(10000);
        $csvItems = [];
        $niceNiceRecordLabels = self::getNiceRecordLabels($recordSet);
        $labels = $niceNiceRecordLabels['Labels'];
        $formFieldSlugs = $niceNiceRecordLabels['Slugs'];
        
        if ($items) {
            $csvItems[] = $labels;
            foreach ($items as $item) {
                if ($item->ItemData()->Count() > 0) {
                    $csvItemsData = [];
                    $csvItemsData[] = $item->Slug;
                    $itemData = $item->ItemData();
                    foreach($formFieldSlugs as $Slug){
                        $data = $itemData->filter(['FormField.Slug' => $Slug])->first();
                        $value = $data ? $data->Value(): null;
                        if(is_array($value)){
                            $value = implode(',', $value);
                        }
                        $csvItemsData[]= $value ? $value : null;
                    }
                    $csvItems[] = $csvItemsData;
                    $csvItemsData = [];
                    unset($csvItemsData);
                }
            }
            $fp = fopen($file, 'w');
            if ($fp) {
                foreach ($csvItems as $csvItem) {
                    fputcsv($fp, $csvItem);
                }
                fclose($fp);
            unset($csvItems);
            $objFile = self::createExportFile($file, 'csv', str_replace(' ','',$recordSet->Title));
            $fileURL = Director::absoluteBaseURL().$objFile['fileSourceURL'] . '?hash=' . $objFile['file']->FileHash;
            return array('fileURL' => $fileURL);
            }
        }
   
        return array('No items where found');
    }

    /**
     * This function creates a Model export file 
     * and exports it according to the type of file
     * @param HTTPRequest $request
     * @param string $modelName
     * @return array
     */
    public static function exportModelData($request, $modelName) {
        $modelClass = Config::inst()->get('API_Namespace_Class_Map', $modelName);
        if ($modelClass) {
            $body = json_decode($request->getBody(), true);
            $type = $body['type'];
            $recordSetItems = $body['recordItems'];
            $file = self::createExportTmpFile($type, $modelName);
            if ($file) {
                if ($type == 'csv') {
                    return self::exportModelDataCSV($file, $modelClass, $recordSetItems, $modelName);
                }
                if ($type == 'json') {
                    return self::exportModelDataJSON($file, $modelClass, $recordSetItems, $modelName);
                }
            }
        }
    }
    
    /**
     * Exports Model items as json
     * @param string $filePath
     * @param string $modelClass
     * @param dataobject $recordSetItems
     * @param string $modelName
     */
    public static function exportModelDataJSON($file, $modelClass, $recordSetItems, $modelName) {
        $DataStoreIDMap = Config::inst()->get($modelClass, 'API_Filter_DataStoreID_Map');
        if (!$DataStoreIDMap) {
            return $this->httpError(404, $modelClass . " you should map DataStoreID in the config");
        }
        $filter[$DataStoreIDMap] = self::getCurrentDataStoreID();
        if(count($recordSetItems) > 1){
            $filter['Slug'] = $recordSetItems;
        }
        $labels = Config::inst()->get($modelClass, 'API_View_Fields');
        $items = DataObject::get($modelClass)->filter($filter);
        $fileItems = [];
        if ($items) {
            foreach ($items as $item) {
                $fileItemsData = [];
                $i = 0;
                foreach ($labels as $label) {
                     if(strpos($label, '(') === false){
                             $fileItemsData[$labels[$i]] = $item->$label;
                     }else{
                            $niceLabel = str_replace('()', '', $label);
                            $fileItemsData[$labels[$i]] = is_array($item->$niceLabel()) ? json_encode($item->$niceLabel()): $item->$niceLabel();
                     }
                     $i++;
                }
                $fileItems[] = $fileItemsData;
                $fileItemsData = [];
                unset($fileItemsData);
            }
            
            file_put_contents($file,json_encode($fileItems));  
            unset($fileItems);

            $objFile = self::createExportFile($file, 'json', $modelName);
            $fileURL = Director::absoluteBaseURL().$objFile['file']->URL . '?hash=' . $objFile['file']->FileHash;
            return array('fileURL' => $fileURL);
            
        }
        return array('No items where found');
    }

    /**
     * This function prepares and creates a CSV file for export
     * @param DataObject $file
     * @param string $modelClass
     * @param array $recordSetItems
     * @param string $modelName
     * @return array
     */
    public static function exportModelDataCSV($file, $modelClass, $recordSetItems, $modelName) {
        $DataStoreIDMap = Config::inst()->get($modelClass, 'API_Filter_DataStoreID_Map');
        if (!$DataStoreIDMap) {
            return $this->httpError(404, $modelClass . " you should map DataStoreID in the config");
        }
        $filter[$DataStoreIDMap] = self::getCurrentDataStoreID();
        if(count($recordSetItems) > 1){
            $filter['Slug'] = $recordSetItems;
        }
        $labels = Config::inst()->get($modelClass, 'API_View_Fields');
        $items = DataObject::get($modelClass)->filter($filter);
        $csvItems = [];
        if ($items) {
            $csvItems[] = self::getNiceModelLabels($labels);
            foreach ($items as $item) {
                $csvItemsData = [];
                foreach ($labels as $label) {
                     if(strpos($label, '(') === false){
                             $csvItemsData[] = $item->$label;
                     }else{
                            $niceLabel = str_replace('()', '', $label);
                            $csvItemsData[] = is_array($item->$niceLabel()) ? json_encode($item->$niceLabel()): $item->$niceLabel();
                     }
                }
                $csvItems[] = $csvItemsData;
                $csvItemsData = [];
            }
            $fp = fopen($file, 'w');
            if ($fp) {
                foreach ($csvItems as $csvItem) {
                    try {
                      
                        fputcsv($fp, $csvItem);
                    } catch (Exception $ex) {
                        //handel error
                    }
                }
                fclose($fp);
            $objFile = self::createExportFile($file, 'csv', $modelName);
            $fileURL = Director::absoluteBaseURL().$objFile['file']->URL . '?hash=' . $objFile['file']->FileHash;
            return array('fileURL' => $fileURL);
            }
        }
        return array('No items where found');
    }
    
    /**
     * This function creates a temporary file
     * @param string $type
     * @param string $recordSetName
     * @return file pointer resource on success, or false on failure
     */
    public static function createExportTmpFile($type, $recordSetName) {
        $tmpPath = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';
        $fileName = self::getRandomString(40) . '.' . $type;
        $folderPath = str_replace(' ', '', $tmpPath . self::getCurrentDataStore()->Title . 'export/' . date("Ymdhis") . '/');
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777, true);
        }
         return fopen($folderPath . $fileName, 'w') ? $folderPath . $fileName: null;
    }

    /**
     * This function creates the actual permanent file on the specific location 
     * @param DataObject $fileLocal
     * @param string $type
     * @param string $recordSetName
     * @return array
     */
    public static function createExportFile($fileLocal, $type, $recordSetName, $customFolderPath = null) {
        $folderPath = $customFolderPath ? $customFolderPath : str_replace(' ', '', self::getCurrentDataStore()->Folder()->Filename . 'tmp/export/' . date("Ymdhis"));
    
        $folder = Folder::find_or_make($folderPath);
        $fileName = $recordSetName . '.' . $type;
        $file = new File();
        $file->setFromLocalFile($fileLocal, $folder->Filename . $fileName);
        $file->write();
        $file->publishSingle();
        
        $filePath = ASSETS_PATH . '/.protected' . str_replace('/assets', '', $file->getSourceURL());
        if (fopen($filePath, 'r')) {
    
            return array('file' => $file, 'filePath' => $filePath, 'fileSourceURL' => $file->getSourceURL());
        }
    }
    
    public static function deleteExportTmpFile($file) {
        return true;
    }
    
    private static function getNiceModelLabels($labels){
        $ret = [];
        foreach($labels as $label){
            if(strpos($label, '(') === false){
                $ret[] = str_replace('()', '', $label);
            }else{
                $ret[] = $label;
            }
        }
        return $ret;
    }
    
    /**
     * This function returns an array of labels and their slugs for provided $recordSet
     * @param DataObject $recordSet
     * @return array
     */
    private static function getNiceRecordLabels($recordSet) {
        $labels = $recordSet->getActiveLabels();
        $retLabels = [];
        $retSlugs = [];
        $retLabels[] = 'Slug';
        foreach ($labels as $label) {
             $retLabels[] = $label['Slug'] . '_' . $label['Label'];
             $retSlugs[] = $label['Slug'];
        }
        
        return array ('Labels' =>  $retLabels, 'Slugs' => $retSlugs);
    }

}
