<?php

namespace EveryRESTfulAPI\Helper;

use EveryRESTfulAPI\Helper\EveryRESTfulAPIHelper;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;


/** EveryDataStore/EveryRESTfulAPI v1.3
 * 
 * This class implements asset related functions, i.e., folders, files and notes
 * 
 */
class CustomAssetHelper extends EveryRESTfulAPIHelper {


    public static function getRecordResultlistFields() {
        
    }

    /**
     * This function returns all existing versions of a $file as an array
     * @param DataObject $file
     * @return array
     */
    public static function getFileVersions($file) {
        if ($file->Versions()) {
            $versions = [];
            foreach ($file->Versions() as $obj) {
                if ($obj->CanView()) {
                    $versions[] = self::getFileProperties($obj);
                }
            }
            return $versions;
        }
    }

    /**
     * This function returns an array of file properties
     * @param DataObject $obj
     * @param array $versions
     * @param array $notes
     * @param array $permissions
     * @return array
     */
    public static function getFileProperties($obj, $versions = false, $notes = false, $permissions = false) {
        $RESTFulToken = EveryRESTfulAPIHelper::getMember()->RESTFulToken;

        if ($obj->CanViewType == "Anyone") {
            $CanViewType = '';
        } else {
            $CanViewType = '?hash=' . $obj->FileHash;
        }
        return array(
            'Version' => $obj->Version,
            'Slug' => $obj->Slug,
            'Title' => $obj->Title,
            'Name' => $obj->Name,
            'Version' => $obj->Version,
            'ClassName' => str_replace('SilverStripe\Assets\/', '', $obj->className),
            'IconCls' => self::getFileIconCls($obj->Extension),
            'ThumbnailURL' => $obj->getThumbnailURL(),
            'CreatedBy' => $obj->CreatedBy()->getFullName(),
            'UpdatedBy' => $obj->UpdatedBy()->getFullName(),
            'LastEdited' => self::getNiceDateTimeFormat($obj->LastEdited),
            'Created' => self::getNiceDateTimeFormat($obj->Created),
            'Filename' => $obj->URL,
            'Link' => Director::absoluteBaseURL().$obj->URL . $CanViewType,
            'CanViewType' => $obj->CanViewType,
            'CanEditType' => $obj->CanEditType,
            'ViewerLink' => Director::absoluteBaseURL(). $obj->URL . $CanViewType,
            'Notes' => $notes ? self::getFileNotes($obj) : '',
            'Versions' => $versions ? self::getFileVersions($obj) : '',
            'NextFileSlug' => self::getNextFileSlug($obj),
            'PrevFileSlug' => self::getPrevFileSlug($obj),
            'ViewerGroupSlugs' => self::getAssetViewerGroupSlugs($obj),
            'EditorGroupSlugs' => self::getAssetEditorGroupSlugs($obj),
            'Permissions' => $permissions ? self::getAssetPermissions($obj) : ''
        );
    }

    /**
     * This function returns folder tree structure 
     * @param DatObject $folder
     * @param string $currentFolderSlugSlug
     * @param boolean $base
     * @return array
     */
    public static function getFolderTree($folder, $currentFolderSlugSlug, $base = false) {
        if ($folder) {
            $items = array();
            if ($base) {
                $items[] = array(
                    'href' => $folder->Slug,
                    'text' => '',
                    'nodes' => self::getFolderTree($folder, $currentFolderSlugSlug),
                    'selected' => $folder->Slug == $currentFolderSlugSlug ? true : false,
                    'currentFolderSlug' => $currentFolderSlugSlug,
                    'parentSlug' => $folder->Parent()->Slug
                );
            } else {
                $folders = Versioned::get_by_stage('SilverStripe\Assets\Folder', Versioned::LIVE)->filter(array('ClassName' => 'SilverStripe\Assets\Folder', 'ParentID' => $folder->ID))->Sort('Name', 'ASC');
                if ($folders) {
                    foreach ($folders as $folderChild) {
                        $items[] = array(
                            'href' => $folderChild->Slug,
                            'text' => $folderChild->Name,
                            'nodes' => self::getFolderTree($folderChild, $currentFolderSlugSlug),
                            'selected' => $folderChild->Slug == $currentFolderSlugSlug ? true : false,
                            'currentFolderSlug' => $currentFolderSlugSlug,
                            'parentSlug' => $folder->Parent()->Slug
                        );
                    }
                }
            }
        }
        return $items;
    }

    /**
     * This function returns all files contained in the $folder
     * @param DataObject $folder
     * @param boolean $all
     * @param integer $start
     * @param integer $length
     * @param string $searchColumns
     * @param string $orderColumn
     * @param string $orderOpt
     * @param string $createdFrom
     * @param string $createdTo
     * @param integer $totalSearchResult
     * @return array
     */
    public static function getFolderFiles($folder, $start, $length, $searchColumns, $orderColumn, $orderOpt, $createdFrom, $createdTo, $totalSearchResult, $all = false) {
        if ($folder) {
            $rowItems = [];
            $ParentIDs = $all ? explode(',', self::getAllFolderChildrenFolders($folder)) : $folder->ID;
            $Filter = array('ParentID' => $ParentIDs, 'DeletionDate' => NULL);
            
            if ($createdFrom) {
                $Filter['Created:GreaterThanOrEqual'] = $createdFrom;
            }

            if ($createdTo) {
                $Filter['Created:LessThanOrEqual'] = $createdTo;
            }
            
            $niceItems = Versioned::get_by_stage('SilverStripe\Assets\File', Versioned::LIVE)->filter($Filter)->Sort('Name', 'ASC')->exclude(
                            'ClassName', ['SilverStripe\Assets\Folder']
                    )->limit($totalSearchResult);

            //$niceItems->limit($totalSearchResult);
            //$searchColumnsFilter = !empty($searchColumns) || !empty($createdFrom) || !empty($createdTo) ? self::getSearchColumnsFilter($searchColumns, $createdFrom, $createdTo): '';
            $searchColumnsFilter = $searchColumns;

            $niceItems = !empty($searchColumnsFilter) ? $niceItems->filter($searchColumnsFilter) : $niceItems;
            $recordSetItemsCount = $niceItems->Count();
            $offset = $start >= $recordSetItemsCount ? $start - $recordSetItemsCount : $start;
            //$niceItems = $niceItems->limit(1);
            $niceItems = $niceItems->limit($length, $offset);
            if ($niceItems) {
                foreach ($niceItems as $fileChild) {
                    if ($fileChild->canView()) {
                        $rowItems[] = self::getFileProperties($fileChild, false, false);
                    }
                }
            }
            
          
            $orderColumnName = $orderColumn && isset($orderColumn['Name']) ? $orderColumn['Name']: 'Name';
            $orderRowItems = !empty($rowItems) ? array_column($rowItems, $orderColumnName): [];
            $orderOpt = $orderOpt ? $orderOpt : 'SORT_DESC';
            if(!empty($orderRowItems) && !empty($rowItems) && $orderOpt) {
                array_multisort($orderRowItems, SORT_ASC, SORT_STRING,
                $rowItems, SORT_NUMERIC, SORT_DESC);
                //array_multisort($orderRowItems, $orderOpt, $rowItems);
            }
            return array(
                'rowItems' => $rowItems,
                'recordSetItemsCount' => $recordSetItemsCount);
        }
    }

    /**
     * This function returns IDs of  all children, i.e., child folders, of the $folder
     * @param DataObject $folder
     * @return array
     */
    public static function getAllFolderChildrenFolders($folder) {
        if ($folder) {
            /* 
             * *** SilverStipe Folder::myChildren() doesn't work ****
            foreach($folder->myChildren() as $Children){
                if($Children->myChildren()->Count() > 0 ){
                   $ids .= $Children->ID.','.self::getAllFolderChildrenFolders($folder);
                }else {
                    $ids .= $Children->ID.',';
                }
            }
            return explode(",", $ids);
            */
            

            $ids = '';
            $ids .= $folder->ID.',';
            $folders = Versioned::get_by_stage('SilverStripe\Assets\Folder', Versioned::LIVE)->filter(array('ClassName' => 'SilverStripe\Assets\Folder', 'ParentID' => $folder->ID))->Sort('Name', 'ASC');
            if ($folders) {
                foreach ($folders as $folderChild) {
                    if ($folderChild->Children()->filter(['ClassName' => 'SilverStripe\Assets\Folder'])->Count() > 0) {
                        $ids .= self::getAllFolderChildrenFolders($folderChild);
                    } else {
                        $ids .= $folderChild->ID.',';
                    }
                }
            }
         
            return $ids;
        }
    }

    /**
     * This function returns an array of searchable columns 
     * @param array $columns
     * @return array
     */
    public static function getSerachColumns($columns) {
        if (!empty($columns)) {
            $retColumns = [];
            $resultFields = self::getFileResultListFields();
            $i = 0;
            foreach ($columns as $column) {
                if ($column['search']['value']) {
                    $retColumns[] = array(
                        'Field' => $resultFields[$i],
                        'Value' => $column['search']['value']);
                }
                $i++;
            }
            return $retColumns;
        }
    }

    /**
     * This function retrieves columns according to which results are ordered
     * @param array $order
     * @param array $columns
     * @return array
     */
    public static function getOrderColumn($order, $columns) {
        if (isset($order[0]['column']) && isset($columns[$order[0]['column']])) {
            return array(
                'Name' => $columns[$order[0]['column']]['data']
            );
        }
    }

    /**
     * This function returns the column ordering, i.e., sorting option
     * @param array $order
     * @return integer
     */
    public static function getOrderOpt($order) {
        if (isset($order[0]['dir'])) {
            $orderOpt = $order[0]['dir'] == 'asc' ? SORT_ASC : SORT_DESC;
            return $orderOpt;
        }
        return SORT_ASC;
    }

    /**
     * This function returns an array of applied filtering options
     * @param array $searchColumns
     * @param string $createdFrom
     * @param string $createdTo
     * @return array
     */
    public static function getSearchColumnsFilter($searchColumns, $createdFrom, $createdTo) {
        $filter = [];
        foreach ($searchColumns as $searchColumn) {
            $filter[$searchColumn['Field'] . ':PartialMatch'] = $searchColumn['Value'];
        }


        if ($createdFrom) {
            $filter['Created:GreaterThan'] = $createdFrom;
        }

        if ($createdTo) {
            $filter['Created:LessThan'] = $createdTo;
        }

        return $filter;
    }

    /**
     * This function returns an array of applied filtering options
     * @param array $searchColumns
     * @param string $createdFrom
     * @param string $createdTo
     * @return array
     */
    public static function getSearchColumnsFilter2($searchColumns, $createdFrom, $createdTo) {
        $filter = [];
        foreach ($searchColumns as $key => $val) {
            $filter[$key . ':PartialMatch'] = $val;
        }


        if ($createdFrom) {
            $filter['Created:GreaterThan'] = $createdFrom;
        }

        if ($createdTo) {
            $filter['Created:LessThan'] = $createdTo;
        }

        return $filter;
    }

    /**
     * This function manages HTTP request for editing an asset by updating values of its properties 
     * @param HTTPRequest $request
     * @return DataObject
     */
    public static function editFile($request) {
        $fileTitle = $request->postVar('fileTitle');
        $fileName = $request->postVar('fileName');
        $fileSlug = $request->postVar('fileSlug');
        $fileClass = $request->postVar('fileClass');
        $fileFolderSlug = $request->postVar('fileFolderSlug');
        $fileCanViewType = $request->postVar('fileCanViewType');
        $fileViewerGroups = $request->postVar('fileViewerGroups');
        $fileEditorGroups = $request->postVar('fileEditorGroups');
        $file = $fileSlug ? self::getFileBySlug($fileSlug) : '';
        if ($file) {
            $file->Title = $fileTitle;
            $file->Name = $fileName;
            $fileFolder = $fileFolderSlug ? self::getFolderBySlug($fileFolderSlug) : '';
            $file->ParentID = $fileFolder ? $fileFolder->ID : $file->ParentID;
            self::setAssetPermission($file, $fileCanViewType, $fileViewerGroups, $fileEditorGroups);
            $file->write();
            //$file->publishSingle();
            //$file->publish("Stage", "Live");
            return $file;
        }
    }

    /**
     * This function creates new folder nested at the appropriate place in the folder structure
     * @param HTTPRequest $request
     * @return DataObject
     */
    public static function setFolder($request) {
        $folderName = $request->postVar('folderName');
        $folderSlug = $request->postVar('folderSlug');
        $currentFolderSlug = $request->postVar('currentFolderSlug');
        $folderParentSlug = $request->postVar('folderParentSlug');

        $parentSlug = $currentFolderSlug;

        if ($folderParentSlug) {
            $parentSlug = $folderParentSlug;
        }

        $folder = $folderSlug ? Folder::get()->filter(['Slug' => $folderSlug])->first() : new Folder();
        $parent = $parentSlug ? Folder::get()->filter(['Slug' => $parentSlug])->first() : '';
        if ($folder && $parent) {
            $folder->Title = $folderName;
            $folder->Name = $folderName;
            $folder->ParentID = $parent->ID;
            $folder->write();
            return $folder;
        }
    }

    /**
     * This function deletes file according to the specified deletion type
     * @param string $fileSlug
     * @param string $deleteType
     */
    public static function deleteFile($fileSlug, $deleteType = 'logicaly') {
        $file = $fileSlug ? self::getFileBySlug($fileSlug) : '';
        if ($file) {
            if ($deleteType == 'permanently') {

                self::deleteAssetPermanently($file);
            } else {
                if ($file->CanDelete()) {
                    $file->DeletionDate = (new \DateTime())->format('Y-m-d H:i:s');
                    $file->writeWithoutVersion();
                }
            }
        }
    }

    /**
     * This folder prepares files for upload and calls the upload function for each file
     * @param array $files
     * @param DataObject $folder
     * @return array
     */
    public static function prepareAndUploadFiles($files, $folder) {
        $retFiles = [];
        $i = 0;

        if (!empty($files) && isset($files['name']) && !empty($files['name'][0])) {
            foreach ($files['name'] as $file) {
                $tmpFile = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                );
                $retFiles[] = self::doUpload($tmpFile, $folder);
                $i++;
            }
            return $retFiles;
        }
    }

    /**
     * This function validates prepared files and uploads them
     * in case of errors it logs error and its message
     * @param array $tmpFile
     * @param DataObject $folder
     * @return array
     */
    public static function doUpload($tmpFile, $folder) {
        $allowedFileExtensions = \EveryDataStore\Helper\AssetHelper::getAllowedFileExtensions();
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
            $fileStatus = 1;
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
            'fileUploadError' => $fileUploadError,
            'File' => $file ? $file : null
        );
    }

    /**
     * This function sets upload properties
     * @param array $allowedExtensions
     * @param integer $allowedFileSize
     * @return DataObject
     */
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

    /**
     * This function returns a file that corresponds to the provided $Slug
     * @param string $Slug
     * @return DataObject
     */
    public static function getFileBySlug($Slug) {
        $file = Versioned::get_by_stage('SilverStripe\Assets\File', Versioned::LIVE)->filter(array('Slug' => $Slug))->First();
        return $file ? $file : '';
    }

    /**
     * This function returns a folder that corresponds to the provided $slug
     * @param string $slug
     * @return DataObject
     */
    public static function getFolderBySlug($slug) {
        $folder = Versioned::get_by_stage('SilverStripe\Assets\Folder', Versioned::LIVE)->filter(array('Slug' => $slug))->First();
        return $folder ? $folder : '';
    }

    /**
     * This function returns all notes created for a $file 
     * @param DataObject $file
     * @return array
     */
    public static function getFileNotes($file) {
        if ($file->Notes()->Count() > 0 && self::checkPermission('VIEW_NOTE')) {
            $items = [];
            foreach ($file->Notes() as $note) {
                $items[] = array(
                    'Slug' => $note->Slug,
                    'Content' => $note->Content,
                    'CreatedBy' => $note->CreatedBy()->getFullName(),
                    'Created' => self::getNiceDateTimeFormat($note->Created)
                );
            }
            return $items;
        }
    }

    /**
     * This function deletes the provided $asset 
     * @param DataObject $asset
     */
    public static function deleteAssetPermanently($asset) {
        if ($asset && self::checkPermission('DELETE_FILE_PERMANENTLY')) {
            $asset->deleteFromStage(Versioned::LIVE);
            $asset->deleteFromStage(Versioned::DRAFT);
            $asset->delete();
            $asset->destroy();
            $asset->deleteFile();
        }
    }

    /**
     * This function returns slugs of groups that have VIEW permission for the $asset
     * @param DataObject $asset
     * @return array
     */
    public static function getAssetViewerGroupSlugs($asset) {
        if (!empty($asset->ViewerGroups())) {
            $slugs = [];
            foreach ($asset->ViewerGroups() as $viewerGroup) {
                $slugs[] = $viewerGroup->Slug;
            }
            return $slugs;
        }
    }

    /**
     * This function returns slugs of groups that have EDIT permission for the $asset
     * @param DataObject $asset
     * @return array
     */
    public static function getAssetEditorGroupSlugs($asset) {
        if (!empty($asset->EditorGroups())) {
            $slugs = [];
            foreach ($asset->EditorGroups() as $editorGroup) {
                $slugs[] = $editorGroup->Slug;
            }
            return $slugs;
        }
    }

    /**
     * This function sets VIEW and EDIT $asset permissions for users/groups
     * @param DataObject $asset
     * @param string $canViewType
     * @param array $viewerGroups
     * @param array $editorGroups
     */
    public static function setAssetPermission($asset, $canViewType, $viewerGroups = false, $editorGroups = false) {
        self::resetAssetEditorGroups($asset);
        self::resetAssetViewerGroups($asset);

        if ($canViewType == "Anyone") {
            $asset->CanViewType = 'Anyone';
        } else if ($canViewType == "OnlyTheseUsers") {
            $asset->CanViewType = 'OnlyTheseUsers';
        } else {
            $asset->CanViewType = 'LoggedInUsers';
            $viewerGroups = [];
            $editorGroups = [];
        }

        if (!empty($viewerGroups)) {
            //$asset->CanViewType = 'OnlyTheseUsers';
            self::setAssetViewerGroups($asset, $viewerGroups);
        }

        if (!empty($editorGroups)) {
            //$asset->CanEditType = 'OnlyTheseUsers';
            self::setAssetEditorGroups($asset, $editorGroups);
        }
        $asset->writeWithoutVersion();
    }

    /**
     * This function assigns VIEW $asset permission to a group
     * @param DataObject $asset
     * @param array $viewerGroups
     */
    public static function setAssetViewerGroups($asset, $viewerGroups) {
        self::resetAssetViewerGroups($asset);
        foreach ($viewerGroups as $viewerGroup) {
            $groudID = is_int($viewerGroup) ? $viewerGroup : self::getOneBySlug($viewerGroup, 'SilverStripe\Security\Group')->ID;
            $asset->ViewerGroups()->add($groudID);
        }
        $asset->writeWithoutVersion();
    }

    /**
     * This function assigns EDIT $asset permission to a group
     * @param DataObject $asset
     * @param array $editorGroups
     */
    public static function setAssetEditorGroups($asset, $editorGroups) {
        self::resetAssetEditorGroups($asset);
        foreach ($editorGroups as $editorGroup) {
            $groudID = is_int($editorGroup) ? $editorGroup : self::getOneBySlug($editorGroup, 'SilverStripe\Security\Group')->ID;
            $asset->EditorGroups()->add($groudID);
        }
        $asset->writeWithoutVersion();
    }

    /**
     * This function removes all viewer groups for the $asset 
     * @param DataObject $asset
     */
    public static function resetAssetViewerGroups($asset) {
        if ($asset->ViewerGroups()->Count() > 0) {
            foreach ($asset->ViewerGroups() as $viewerGroup) {
                $asset->ViewerGroups()->remove($viewerGroup);
            }
        }
    }

    /**
     * This function removes all editor groups for the $asset
     * @param DataObject $asset
     */
    public static function resetAssetEditorGroups($asset) {
        if ($asset->EditorGroups()->Count() > 0) {
            foreach ($asset->EditorGroups() as $editorGroup) {
                $asset->EditorGroups()->remove($editorGroup);
            }
        }
    }

    /**
     * This function returns a list of fields that appear on the resultlist page
     * @return array
     */
    public static function getFileResultListFields() {

        return array('Slug',
            _t('Global.NAME', 'Name'),
            _t('Global.TITLE', 'Title'),
            _t('Global.VERSION', 'Version'),
            _t('Global.CREATED', 'Created'), 
            _t('Global.LASTEDITED', 'Last edited'), 
            _t('Global.CREATEDBY', 'Created by'),  
            _t('Global.Updated', 'Updated by'));
    }

    /**
     * This function returns an array containing labels that appear on the resultlist 
     * @return array
     */
    public static function getFileResultListLabels() {
        $labels = [];
        foreach (self::getFileResultListFields() as $field) {
            $labels[] = array(
                'data' => $field
            );
        }

        return $labels;
    }

    public static function getAdditionalFormFields() {}

    /**
     * This function returns a slug of the previous $file 
     * Previous file is determined by its ID number
     * @param DataObject $file
     * @return string
     */
    public static function getPrevFileSlug($file) {
        $prevFile = Versioned::get_by_stage('SilverStripe\Assets\File', Versioned::LIVE)->filter(['ID:LessThan' => $file->ID, 'ParentID' => $file->ParentID])->Sort('Name', 'ASC')->First();
        return $prevFile ? $prevFile->Slug : '';
    }

    /**
     * This function returns a slug of the succeeding $file 
     * Succeeding file is determined by its ID number
     * @param DataObject $file
     * @return string
     */
    public static function getNextFileSlug($file) {
        $nextFile = Versioned::get_by_stage('SilverStripe\Assets\File', Versioned::LIVE)->filter(['ID:GreaterThan' => $file->ID, 'ParentID' => $file->ParentID])->Sort('Name', 'ASC')->First();
        return $nextFile ? $nextFile->Slug : '';
    }

    /**
     * This function returns icon cls name corresponding to the file $extension
     * @param string $extension
     * @return string
     */
    public static function getFileIconCls($extension) {
        $IconCls = '';

        switch ($extension) {
            case 'pdf':
                $IconCls = 'fa-file-pdf-o';
                break;
            case 'doc':
                $IconCls = 'fa fa-file-word-o';
                break;
            case 'docx':
                $IconCls = 'fa fa-file-word-o';
                break;
            case 'txt':
                $IconCls = 'fa fa-file-o';
                break;
            case 'zip':
                $IconCls = 'fa fa-file-archive-o';
                break;
            case 'rar':
                $IconCls = 'fa fa-file-archive-o';
                break;
            case 'tar':
                $IconCls = 'fa fa-file-archive-o';
                break;
            default:
                $IconCls = 'fa fa-file-o';
                return $IconCls;
        }
    }

    /**
     * This function adds a new note to the file 
     * @param HTTPRequest $request
     * @return DataObject
     */
    public static function addNote($request) {
        $fileSlug = $request->postVar('fileSlug');
        $noteContent = $request->postVar('noteContent');
        $file = $fileSlug ? self::getFileBySlug($fileSlug) : '';
        $note = $file ? new \EveryDataStore\Model\Note() : '';
        if ($note) {
            $note->Content = $noteContent;
            $note->FileID = $file->ID;
            return $note->write();
        }
    }

    /**
     * This function returns true if the parent folder is in the current dataStore
     * @param string $Slug
     * @return boolean
     */
    public static function checkisParentInResponsitory($Slug) {

        $ReponsitoryFolderID = self::getCurrentDataStore()->FolderID;

        $Folder = Folder::get()->filter(array('Slug' => $Slug))->First();
        if ($Folder) {
            if ($Folder->ID == $ReponsitoryFolderID) {
                return true;
            } else {
                return self::checkisParentInResponsitory($Folder->Parent()->Slug);
            }
        }
    }

    /**
     * This function returns true if RecordSetItem belongs to the folder with given $Slug 
     * @param string $Slug
     * @param string $recordSetItemFolderID
     * @return boolean
     */
    public static function checkisRecordSetItemFolder($Slug, $recordSetItemFolderID) {
        $Folder = Folder::get()->filter(array('Slug' => $Slug))->First();
        if ($Folder) {
            if ($Folder->ID == $recordSetItemFolderID) {
                return true;
            } else {
                return self::checkisRecordSetItemFolder($Folder->Parent()->Slug, $recordSetItemFolderID);
            }
        }
    }

    /**
     * This function defines which permissions are allowed for the $asset
     * @param DataObject $asset
     * @return array
     */
    public static function getAssetPermissions($asset) {
        $Permissions = [];
        if ($asset->ClassName == 'SilverStripe\Assets\File' || $asset->ClassName == 'SilverStripe\Assets\Image') {
            $Permissions = array(
                'CREATE_FILE' => $asset->CanCreate(),
                'EDIT_FILE' => $asset->CanEdit(),
                'VIEW_FILE' => $asset->CanView(),
                'DELETE_FILE' => $asset->CanDelete(),
                'DELETE_FILE_PERMANENTLY' => self::checkPermission('DELETE_FILE_PERMANENTLY'),
                'VIEW_NOTE' => self::checkPermission('VIEW_NOTE'),
                'CREATE_NOTE' => self::checkPermission('CREATE_NOTE'),
                'DELETE_NOTE' => self::checkPermission('DELETE_NOTE')
            );
        } else {
            $Permissions = array(
                'CREATE_FOLDER' => $asset->CanCreate(),
                'EDIT_FOLDER' => $asset->CanEdit(),
                'VIEW_FOLDER' => $asset->CanView(),
                'DELETE_FOLDER' => $asset->CanDelete()
            );
        }

        return $Permissions;
    }

}
