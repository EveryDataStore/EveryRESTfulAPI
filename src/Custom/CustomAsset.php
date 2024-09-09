<?php

namespace EveryRESTfulAPI\Custom;

use EveryRESTfulAPI\Helper\CustomAssetHelper;
use EveryDataStore\Model\RecordSet\RecordSetItem;
use EveryDataStore\Model\Note;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Control\Controller;

class CustomAsset extends Controller {
    private static $helper = null;

    public function init() {
        parent::init();
    }

    public static function setFolder($request, $slug = false) {
        $folder = CustomAssetHelper::setFolder($request);
        if ($folder) {
            $recordSetItem = RecordSetItem::get()->filter(['Slug' => $request->postVars()['itemSlug']])->first();
            $folderTree = $recordSetItem ? CustomAssetHelper::getFolderTree($recordSetItem->Folder(), $request->postVars()['currentFolderSlug'], true) : CustomAssetHelper::getFolderTree($folder, $request->postVars()['currentFolderSlug'], true);
            return $folderTree;
        }
    }

    public static function editFile($request, $slug = false) {
        if (CustomAssetHelper::editFile($request)) {
            return 'success';
        }
    }

    public static function getFolderFiles($request, $slug = false) {
        
        $postVars = $request->postVars();
     
        $currentFolderSlug = isset($postVars['currentFolderSlug']) ? strip_tags($postVars['currentFolderSlug']) : '';
        $itemSlug = isset($postVars['itemSlug']) ? strip_tags($postVars['itemSlug']) : '';
        $columns = isset($postVars['columns']) ? strip_tags($postVars['columns']) : '';
        $order = isset($postVars['order']) ? strip_tags($postVars['order']) : '';
        $orderOpt = isset($postVars['orderOpt']) ? strip_tags($postVars['orderOpt']) : '';
        $start = isset($postVars['start']) ? (int) strip_tags($postVars['start']) : '';
        $createdFrom = isset($postVars['createdFrom']) ? strip_tags($postVars['createdFrom']) : '';
        $createdTo = isset($postVars['createdTo']) ? strip_tags($postVars['createdTo']) : '';
        $totalSearchResult = isset($postVars['totalSearchResult']) ? (int) $postVars['totalSearchResult'] : 10000;
        $length = isset($postVars['length']) ? (int) $postVars['length'] : 10;
        $orderColumn = $order;

        $searchColumns = CustomAssetHelper::getSearchColumnsFilter2(strip_tags($postVars['filter']), $createdFrom, $createdTo);
        $recordSetItem = RecordSetItem::get()->filter(['Slug' => $itemSlug])->first();
        $folder = $currentFolderSlug ? CustomAssetHelper::getFileBySlug($currentFolderSlug) : $recordSetItem->Folder();
        
        $folderFiles = CustomAssetHelper::getFolderFiles($folder, $start, $length, $searchColumns, $orderColumn, $orderOpt, $createdFrom, $createdTo, $totalSearchResult, true);

/*        
        if ($folder && $recordSetItem && $folder->ID == $recordSetItem->Folder()->ID) {
            $folderFiles = CustomAssetHelper::getFolderFiles($folder, $start, $length, $searchColumns, $orderColumn, $orderOpt, $createdFrom, $createdTo, $totalSearchResult, true);
        } else {
            $folderFiles = CustomAssetHelper::getFolderFiles($folder, $start, $length, $searchColumns, $orderColumn, $orderOpt, $createdFrom, $createdTo, $totalSearchResult, false);
        }
*/
        return array(
            'resultListLabels' => CustomAssetHelper::getFileResultListLabels(),
            'draw' => intval($postVars['draw']),
            'iTotalRecords' => $folderFiles['recordSetItemsCount'],
            'iTotalDisplayRecords' => $folderFiles['recordSetItemsCount'],
            'aaData' => $folderFiles['rowItems'],
            'searchColumns' => $searchColumns,
            'orderColumn' => $orderColumn,
            'orderOpt' => $orderOpt,
            ''
        );
    }

    public static function getFolderTree($request, $slug) {
        $postVars = $request->postVars();
        $currentFolderSlug = isset($postVars['currentFolder']) ? strip_tags($postVars['currentFolder']) : '';
        $itemSlug = isset($postVars['itemSlug']) ? strip_tags($postVars['itemSlug']) : '';
        $recordSetItemFolder = CustomAssetHelper::getFolderBySlug($currentFolderSlug);
        //if ($recordSetItemFolder && CustomAssetHelper::checkisParentInResponsitory($currentFolderSlug)) {
        if ($recordSetItemFolder) {
        if ($itemSlug) {
                $recordSetItem = $itemSlug ? RecordSetItem::get()->filter(['Slug' => $itemSlug])->first() : '';
               if ($recordSetItem && CustomAssetHelper::checkisRecordSetItemFolder($currentFolderSlug, $recordSetItem->FolderID)) {
                 return CustomAssetHelper::getFolderTree($recordSetItem->Folder(), $currentFolderSlug, true);
                } else {
                    return 'Wrong RecordSetItem';
                }
            } else {
                return CustomAssetHelper::getFolderTree($recordSetItemFolder, $currentFolderSlug, true);
            }
        }
    }

    public static function getFileLabels() {
        return CustomAssetHelper::getFileResultListLabels();
    }

    public static function getFile($request) {
        $file = $request->getVars()['slug'] ? CustomAssetHelper::getFileBySlug($request->getVars()['slug']) : CustomAssetHelper::getFileBySlug($request->postVars()['slug']);
        if ($file) {
            return CustomAssetHelper::getFileProperties($file, true, true, true);
        }
    }
    
    public static function uploadFiles($request) {
        $currentFolderSlug = $request->postVars()['currentFolderSlug'];
        $folder = CustomAssetHelper::getFileBySlug($currentFolderSlug);
        if (isset($_FILES)) {
            $retFiles = array();
            $i = 0;
            foreach ($_FILES['files']['name'] as $file) {
                $tmpFile = array(
                    'name' => $_FILES['files']['name'][$i],
                    'type' => $_FILES['files']['type'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'error' => $_FILES['files']['error'][$i],
                    'size' => $_FILES['files']['size'][$i]
                );
                $retFiles[] = CustomAssetHelper::doUpload($tmpFile, $folder);
                $i++;
            }
            return array(
                'uploadFiles' => $retFiles
            );
        }
    }
   
    public static function deleteFiles($request) {
        $files = $request->postVars()['items'];
        $deleteType = $request->postVars()['deleteType'];
        if (isset($files)) {
            foreach ($files as $f) {
                CustomAssetHelper::deleteFile($f, $deleteType);
            }
            return 'success';
        }
    }

    private static function doUpload($tmpFile, $folder) {
        $allowedFileExtensions =  \EveryDataStore\Helper\AssetHelper::getAllowedFileExtensions();
        $allowedFileSize = \EveryDataStore\Helper\AssetHelper::getAllowedFileSize();
        $fileUploadError = '';
        $fileStatus = '';
        $upload = self::getUpload($allowedFileExtensions, $allowedFileSize);


        if (!$upload->validate($tmpFile)) {
            $fileStatus = 0;
            $fileUploadError .= $upload->getErrors();
        }

        $fileClass = File::get_class_for_file_extension(File::get_file_extension($tmpFile['name']));
        $file = Injector::inst()->create($fileClass);
        $uploadResult = $upload->loadIntoFile($tmpFile, $file, $folder->getFilename());

        if (!$uploadResult) {
            $fileUploadError .= _t('AssestManager.FAILED_TO_LOAD_FILE', 'Failed to load file');
            $fileStatus = 0;
            $FileID = 0;
        } else {
            $file->ParentID = $folder->ID;
            $FileID = $file->writeWithoutVersion();
            $file->publishSingle();
            $recordSet = Versioned::get_by_stage($fileClass, Versioned::LIVE)->byID($FileID);
            $recordSet->writeWithoutVersion();
            $recordSet->publishSingle();
        }

        return array(
            'ID' => $FileID,
            'Name' => $tmpFile['name'],
            'Status' => $fileStatus,
            'fileUploadError' => $fileUploadError
        );
    }

    private static function checkisRecordSetItemFolder($Slug, $recordSetItemFolderID) {
        $Folder = Folder::get()->filter(array('Slug' => $Slug))->First();
        if ($Folder) {
            if ($Folder->ID == $recordSetItemFolderID) {
                return true;
            } else {
                return self::checkisParentInResponsitory($Folder->Parent()->Slug, $recordSetItemFolderID);
            }
        }
    }


    private static function getUpload($allowedExtensions, $allowedFileSize) {
        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(
                $allowedExtensions
        );
        $upload->getValidator()->setAllowedMaxFileSize(
                $allowedFileSize
        );

        return $upload;
    }

    public static function addNote(HTTPRequest $request) {
        return CustomAssetHelper::AddNote($request);
    }

    public static function deleteNote(HTTPRequest $request) {
        $slug = $request->postVar('slug');
        $note = $slug ? Note::get()->filter(['Slug' => $slug])->first() : '';
        if ($note) {
            $note->delete();
            return "Success";
        }
    }

    public static function previewFile($request, $slug) {
        $thumbnail = $request->postVar('thumbnail');
        $file = $slug ? CustomAssetHelper::getFileBySlug($slug) : '';
        if ($file) {
            if ($thumbnail) {
                $fileURL = rtrim(Director::absoluteBaseURL(), '/') . $file->ThumbnailURL(100, 100);
            } else {
                $fileURL = rtrim(Director::absoluteBaseURL(), '/') . $file->URL;
            }
            header('Content-type: application/' . $file->getExtension());
            header('Content-Disposition: inline; filename="' . $fileURL . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . filesize($fileURL));
            header('Accept-Ranges: bytes');
            readfile($fileURL);
        }
    } 
}
