<?php
namespace Craft;

/**
 * TheArchitectController
 *
 * @package Craft
 */
class TheArchitectController extends BaseController {
    private $groups;
    private $fields;
    private $sections;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseController::init()
	 *
	 * @throws HttpException
	 * @return null
	 */
	public function init() {
		// All section actions require an admin
		craft()->userSession->requireAdmin();
	}


    /**
     * actionBlueprint [list the exportable items]
     * @return null
     */
    public function actionBlueprint() {
        $variables = array(
            'assetSources' => craft()->assetSources->getAllSources(),
            'assetTransforms' => craft()->assetTransforms->getAllTransforms()
        );

        $this->renderTemplate('thearchitect/blueprint', $variables);
    }


    /**
     * actionConstructBlueprint [ TODO ]
     * @return null
     */
    public function actionConstructBlueprint() {
        // Prevent GET Requests
        $this->requirePostRequest();

        $post = craft()->request->getPost();

        $groups = [];
        $fields = [];
        $sections = [];
        $entryTypes = [];
        $transforms = [];
        $globals = [];
        if (isset($post['fieldSelection'])) {
            foreach ($post['fieldSelection'] as $id) {
                $field = craft()->fields->getFieldById($id);

                if (!in_array($field->group->name, $groups)) {
                    array_push($groups, $field->group->name);
                }

                $tmpField = [
                    "group" => $field->group->name,
                    "name" => $field->name,
                    "handle" => $field->handle,
                    "instructions" => $field->instructions,
                    "required" => $field->required,
                    "type" => $field->type,
                    "typesettings" => $field->settings
                ];

                if ($field->type == 'Neo') {
                    $blockTypes = craft()->neo->getBlockTypesByFieldId($id);
                    $blockCount = 0;
                    foreach ($blockTypes as $blockType) {
                        $tmpField["typesettings"]["blockTypes"]["new" . $blockCount] = [
                            "sortOrder" => $blockType->sortOrder,
                            "name" => $blockType->name,
                            "handle" => $blockType->handle,
                            "maxBlocks" => $blockType->maxBlocks,
                            "childBlocks" => $blockType->childBlocks,
                            "topLevel" => $blockType->topLevel,
                            "fieldLayout" => []
                        ];
                        foreach ($blockType->getFieldLayout()->getTabs() as $tab) {
                            $tmpField["typesettings"]["blockTypes"]["new" . $blockCount]["fieldLayout"][$tab->name] = [];
                            foreach ($tab->getFields() as $tabField) {
                                array_push($tmpField["typesettings"]["blockTypes"]["new" . $blockCount]["fieldLayout"][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                            }
                        }
                        $blockCount++;
                    }
                }

                if ($field->type == 'Matrix') {
                    $blockTypes = craft()->matrix->getBlockTypesByFieldId($id);
                    $blockCount = 1;
                    foreach ($blockTypes as $blockType) {
                        $tmpField["typesettings"]["blockTypes"]["new" . $blockCount] = [
                            "name" => $blockType->name,
                            "handle" => $blockType->handle,
                            "fields" => []
                        ];
                        $fieldCount = 1;
                        foreach ($blockType->fields as $blockField) {
                            $tmpField["typesettings"]["blockTypes"]["new" . $blockCount]["fields"]["new" . $fieldCount] = [
                                "name" => $blockField->name,
                                "handle" => $blockField->handle,
                                "instructions" => $blockField->instructions,
                                "required" => $blockField->required,
                                "type" => $blockField->type,
                                "typesettings" => $blockField->settings
                            ];
                            $fieldCount++;
                        }
                        $blockCount++;
                    }
                }

                array_push($fields, $tmpField);
            }
        }

        $output = [
            'groups' => $groups,
            'sections' => $sections,
            'fields' => $fields,
            'entryTypes' => $entryTypes,
            'tranforms' => $tranforms,
            'globals' => $globals
        ];

        foreach ($output as $key => $value) {
            if ($value == []) {
                unset($output[$key]);
            }
        }

        if ($output == []) {
            $this->redirect('thearchitect/blueprint');
        } else {
            $variables = array(
                'json' => json_encode($output, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT)
            );

            $this->renderTemplate('thearchitect/output', $variables);
        }
    }


    /**
     * actionConstructList [list the files inside the content folder]
     * @return null
     */
    public function actionConstructList() {
        $files = array();
        if ($handle = opendir(craft()->path->getPluginsPath() . 'thearchitect/content')) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) == "json") {
                    $files[] = $entry;
                }
            }
            closedir($handle);
        }

        natsort($files);

        $groups = craft()->fields->getAllGroups();
        $fields = craft()->fields->getAllFields();
        $sections = craft()->sections->getAllSections();

        foreach ($fields as $field) {
            if ($field->type == 'Matrix') {
                $blockTypes = craft()->matrix->getBlockTypesByFieldId($field->id);
                foreach ($blockTypes as $blockType) {
                    $blockType->getFields();
                }
            }
        }

        $variables = array(
            'files' => $files
        );

        $this->renderTemplate('thearchitect/files', $variables);
    }


    /**
     * actionConstructLoad [load the selected file for review before processing]
     * @return null
     */
    public function actionConstructLoad() {
        // Prevent GET Requests
        $this->requirePostRequest();

        $fileName = craft()->request->getRequiredPost('fileName');

        $filePath = craft()->path->getPluginsPath() . 'thearchitect/content/' . $fileName;

        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);

            $variables = array(
                'json' => $json,
                'filename' => $fileName
            );

            $this->renderTemplate('thearchitect/index', $variables);
        }
    }

    /**
     * actionConstruct
     * @return null
     */
    public function actionConstruct() {
        // Prevent GET Requests
        $this->requirePostRequest();

        $json = craft()->request->getRequiredPost('json');

        if ($json) {
            $notice = $this->parseJson($json);

            $variables = array(
                'json' => $json,
                'result' => $notice
            );

            $this->renderTemplate('thearchitect/index', $variables);
        }
    }

    // Private Methods
    // =========================================================================

    private function getSourceByHandle($handle) {
        $assetSources = craft()->assetSources->getAllSources();
        foreach ($assetSources as $key => $assetSource) {
            if ($assetSource->handle === $handle) {
                return $assetSource;
            }
        }
    }

    private function getCategoryByHandle($handle) {
        $categories = craft()->categories->getAllGroups();
        foreach ($categories as $key => $category) {
            if ($category->handle === $handle) {
                return $category;
            }
        }
    }

    private function getTagGroupByHandle($handle) {
        $tagGroups = craft()->tags->getAllTagGroups();
        foreach ($tagGroups as $key => $tagGroup) {
            if ($tagGroup->handle === $handle) {
                return $tagGroup;
            }
        }
    }

    private function getUserGroupByHandle($handle) {
        $userGroups = craft()->userGroups->getAllGroups();
        foreach ($userGroups as $key => $userGroup) {
            if ($userGroup->handle === $handle) {
                return $userGroup;
            }
        }
    }

    private function replaceSourcesHandles(&$object) {
        if ($object->type == 'Matrix') {
            if (isset($object->typesettings->blockTypes)) {
                foreach ($object->typesettings->blockTypes as &$blockType) {
                    foreach ($blockType->fields as &$field) {
                        $this->replaceSourcesHandles($field);
                    }
                }
            }
        }
        if ($object->type == 'Neo') {
            if (isset($object->typesettings->blockTypes)) {
                foreach ($object->typesettings->blockTypes as &$blockType) {
                    foreach ($blockType->fieldLayout as &$fieldLayout) {
                        foreach ($fieldLayout as &$fieldHandle) {
                            $field = craft()->fields->getFieldByHandle($fieldHandle);
                            $fieldHandle = $field->id;
                        }
                    }
                }
            }
        }
        if ($object->type == 'SuperTable') {
            if (isset($object->typesettings->blockTypes)) {
                foreach ($object->typesettings->blockTypes as &$blockType) {
                    foreach ($blockType->fields as &$field) {
                        $this->replaceSourcesHandles($field);
                    }
                }
            }
        }
        if ($object->type == 'Entries') {
            if (isset($object->typesettings->sources)) {
                foreach ($object->typesettings->sources as $k => &$v) {
                    $section = craft()->sections->getSectionByHandle($v);
                    if ($section) {
                        $v = 'section:' . $section->id;
                    }
                }
            }
        }
        if ($object->type == 'Assets') {
            if (isset($object->typesettings->sources)) {
                foreach ($object->typesettings->sources as $k => &$v) {
                    $assetSource = $this->getSourceByHandle($v);
                    if ($assetSource) {
                        $v = 'folder:' . $assetSource->id;
                    }
                }
            }
        }
        if ($object->type == 'Categories') {
            if (isset($object->typesettings->source)) {
                $category = $this->getCategoryByHandle($object->typesettings->source);
                if ($category) {
                    $object->typesettings->source = 'group:' . $category->id;
                }
            }
        }
        if ($object->type == 'Tags') {
            if (isset($object->typesettings->source)) {
                $category = $this->getTagGroupByHandle($object->typesettings->source);
                if ($category) {
                    $object->typesettings->source = 'taggroup:' . $category->id;
                }
            }
        }
        if ($object->type == 'Users') {
            if (isset($object->typesettings->sources)) {
                foreach ($object->typesettings->sources as $k => &$v) {
                    $userGroup = $this->getUserGroupByHandle($v);
                    if ($userGroup) {
                        $v = 'group:' . $userGroup->id;
                    }
                }
            }
        }
    }

    /**
     * parseJson
     * @param String $json
     * @return Array [successfulness]
     */
    private function parseJson($json) {
        $result = json_decode($json);

        $notice = array();

        // Add Groups from JSON
        if (isset($result->groups)) {
            foreach ($result->groups as $group) {
                $addGroupResult = $this->addGroup($group);
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "Group",
                    "name" => $group,
                    "result" => $addGroupResult,
                    "errors" => false
                );
            }
        }

        $this->groups = craft()->fields->getAllGroups();

        // Add Fields from JSON
        if (isset($result->fields)) {
            foreach ($result->fields as $field) {
                $this->replaceSourcesHandles($field);
                $addFieldResult = $this->addField($field);
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "Field",
                    "name" => $field->name,
                    "result" => $addFieldResult[0],
                    "errors" => $addFieldResult[1],
                    "errors_alt" => $addFieldResult[2]
                );
            }
        }

        $this->fields = craft()->fields->getAllFields();

        // Add Sections from JSON
        if (isset($result->sections)) {
            foreach ($result->sections as $section) {
                $addSectionResult = $this->addSection($section);
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "Sections",
                    "name" => $section->name,
                    "result" => $addSectionResult[0],
                    "errors" => $addSectionResult[1]
                );
            }
        }

        $this->sections = craft()->sections->getAllSections();

        // Add Entry Types from JSON
        if (isset($result->entryTypes)) {
            foreach ($result->entryTypes as $entryType) {
                if (isset($entryType->titleLabel)) {
                    $entryTypeName = $entryType->titleLabel;
                } else {
                    $entryTypeName = $entryType->titleFormat;
                }
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "Entry Types",
                    // Channels Might have an additional name.
                    "name" => $entryType->sectionName . ( (isset($entryType->name)) ? ' > ' . $entryType->name : '' ) . ' > ' . $entryTypeName,
                    "result" => $this->addEntryType($entryType),
                    "errors" => false
                );
            }
        }

        // Add Entry Types from JSON
        if (isset($result->sources)) {
            foreach ($result->sources as $source) {
                $assetSourceResult = $this->addAssetSource($source);
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "Asset Source",
                    "name" => $source->name,
                    "result" => $assetSourceResult[0],
                    "errors" => $assetSourceResult[1]
                );
            }
        }

        // Add Entry Types from JSON
        if (isset($result->transforms)) {
            foreach ($result->transforms as $transform) {
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "Asset Transform",
                    "name" => $transform->name,
                    "result" => $this->addAssetTransform($transform),
                    "errors" => false
                );
            }
        }

        // Add Entry Types from JSON
        if (isset($result->globals)) {
            foreach ($result->globals as $global) {
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "GlobalSet",
                    "name" => $global->name,
                    "result" => $this->addGlobalSet($global),
                    "errors" => false
                );
            }
        }

        return $notice;
    }

    /**
     * addGroup
     * @param String $name []
     * @return Boolean     [success]
     */
    private function addGroup($name) {
        $group = new FieldGroupModel();
        $group->name = $name;

        // Save Group to DB
        if (craft()->fields->saveGroup($group)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * addField
     * @param ArrayObject $jsonField
     * @return Boolean [success]
     */
    private function addField($jsonField) {
        $field = new FieldModel();

        // If group is set find groupId
        if (isset($jsonField->group)) {
    		$field->groupId = $this->getGroupId($jsonField->group);
        }

        $field->name = $jsonField->name;

        // Set handle if it was provided
		if (isset($jsonField->handle)) {
            $field->handle = $jsonField->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $field->handle = $this->constructHandle($jsonField->name);
        }

        // Set instructions if it was provided
        if (isset($jsonField->instructions)) {
            $field->instructions = $jsonField->instructions;
        }

        // Set translatable if it was provided
        if (isset($jsonField->translatable)) {
            $field->translatable = $jsonField->translatable;
        }

        $field->type = $jsonField->type;

        if (isset($jsonField->typesettings)) {
            // Convert Object to Array for saving
            $jsonField->typesettings = json_decode(json_encode($jsonField->typesettings), true);

            // $field->settings requires an array of the settings
            $field->settings = $jsonField->typesettings;
        }

        // Save Field to DB
        if (craft()->fields->saveField($field)) {
            return [true, false, false];
        } else {
            return [false, $field->getErrors(), $field->getSettingErrors()];
        }
    }

    /**
     * addSection
     * @param ArrayObject $jsonSection
     * @return Boolean [success]
     */
    private function addSection($jsonSection) {
        $section = new SectionModel();

        $section->name = $jsonSection->name;

        // Set handle if it was provided
		if (isset($jsonSection->handle)) {
            $section->handle = $jsonSection->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $section->handle = $this->constructHandle($jsonSection->name);
        }

        $section->type = $jsonSection->type;

        // Set enableVersioning if it was provided
        if (isset($jsonSection->typesettings->enableVersioning)) {
            $section->enableVersioning = $jsonSection->typesettings->enableVersioning;
        } else {
            $section->enableVersioning = 1;
        }

        // Set hasUrls if it was provided
        if (isset($jsonSection->typesettings->hasUrls)) {
            $section->hasUrls = $jsonSection->typesettings->hasUrls;
        }

        // Set template if it was provided
        if (isset($jsonSection->typesettings->template)) {
            $section->template = $jsonSection->typesettings->template;
        }

        // Set maxLevels if it was provided
        if (isset($jsonSection->typesettings->maxLevels)) {
            $section->maxLevels = $jsonSection->typesettings->maxLevels;
        }

        // Set Locale Information
        // Pulled from SectionController.php aprox. Ln 170
        $locales = array();
		$primaryLocaleId = craft()->i18n->getPrimarySiteLocaleId();
		$localeIds = array($primaryLocaleId);
        foreach ($localeIds as $localeId) {
            if (isset($jsonSection->typesettings->urlFormat)) {
    			$urlFormat = $jsonSection->typesettings->urlFormat;
            } else {
                $urlFormat = null;
            }

            if (isset($jsonSection->typesettings->nestedUrlFormat)) {
    			$nestedUrlFormat = $jsonSection->typesettings->nestedUrlFormat;
            } else {
                $nestedUrlFormat = null;
            }

			$locales[$localeId] = new SectionLocaleModel(array(
				'locale'           => $localeId,
				'enabledByDefault' => null,
				'urlFormat'        => $urlFormat,
				'nestedUrlFormat'  => $nestedUrlFormat,
			));
		}
		$section->setLocales($locales);

        // Save Section to DB
        if (craft()->sections->saveSection($section)) {
            return [true, false];
        } else {
            return [false, $section->getErrors()];
        }
    }

    /**
     * addEntryType
     * @param ArrayObject $jsonEntryType
     * @return Boolean [success]
     */
    private function addEntryType($jsonEntryType) {
        $entryType = new EntryTypeModel();

        $entryType->sectionId = $this->getSectionId($jsonEntryType->sectionName);

        // Check for name if not set name as sectionName
        if (!isset($jsonEntryType->name)) {
            $jsonEntryType->name = $jsonEntryType->sectionName;
        }
        $entryType->name = $jsonEntryType->name;

        // If the Entry Type exists load it so that it udpates it.
        $sectionHandle = $this->getSectionHandle($entryType->sectionId);
        $entryTypes = craft()->sections->getEntryTypesByHandle($sectionHandle);
        if ($entryTypes) {
            $entryType = craft()->sections->getEntryTypeById($entryTypes[0]->attributes['id']);
        }

        // Set handle if it was provided
		if (isset($jsonEntryType->handle)) {
            $entryType->handle = $jsonEntryType->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $entryType->handle = $this->constructHandle($jsonEntryType->name);
        }

        // If titleLabel set hasTitleField to True
        if (isset($jsonEntryType->titleLabel)) {
            $entryType->hasTitleField = true;
    		$entryType->titleLabel = $jsonEntryType->titleLabel;
        }
        // If titleFormat set hasTitleField to False
        else {
            $entryType->hasTitleField = false;
    		$entryType->titleFormat = $jsonEntryType->titleFormat;
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonEntryType->fieldLayout)) {
            $fieldLayout = $this->assembleLayout($jsonEntryType->fieldLayout);
            $fieldLayout->type = ElementType::Entry;
            $entryType->setFieldLayout($fieldLayout);
        }

        // Save Entry Type to DB
        if (craft()->sections->saveEntryType($entryType)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * addAssetSource
     * @param ArrayObject $jsonSection
     * @return Boolean [success]
     */
    private function addAssetSource($jsonSource) {
        $source = new AssetSourceModel();

		$source->name   = $jsonSource->name;

        // Set handle if it was provided
		if (isset($jsonSource->handle)) {
            $source->handle = $jsonSource->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $source->handle = $this->constructHandle($jsonSource->name);
        }

        $source->type  = $jsonSource->type;

        // Convert Object to Array for saving
        $source->settings = json_decode(json_encode($jsonSource->settings), true);


        // Parse & Set Field Layout if Provided
        if (isset($jsonSource->fieldLayout)) {
    		$fieldLayout = $this->assembleLayout($jsonSource->fieldLayout);
    		$fieldLayout->type = ElementType::Asset;
    		$source->setFieldLayout($fieldLayout);
        }

        // Save Asset Source to DB
        if (craft()->assetSources->saveSource($source)) {
            return [true, null];
        } else {
            return [false, $source->getErrors()];
        }
    }

    /**
     * addAssetTransform
     * @param ArrayObject $jsonAssetTransform
     * @return Boolean [success]
     */
    private function addAssetTransform($jsonAssetTransform) {
        $transform = new AssetTransformModel();

        $transform->name = $jsonAssetTransform->name;

        // Set handle if it was provided
		if (isset($jsonAssetTransform->handle)) {
            $transform->handle = $jsonAssetTransform->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $transform->handle = $this->constructHandle($jsonAssetTransform->name);
        }

        // One of these fields are required.
        if (isset($jsonAssetTransform->width) OR isset($jsonAssetTransform->height)) {
            if (isset($jsonAssetTransform->width)) {
                $transform->width = $jsonAssetTransform->width;
            }
            if (isset($jsonAssetTransform->height)) {
                $transform->height = $jsonAssetTransform->height;
            }
        } else {
            return false;
        }

        // Set mode if it was provided
        if (isset($jsonAssetTransform->mode)) {
            $transform->mode = $jsonAssetTransform->mode;
        }

        // Set position if it was provided
        if (isset($jsonAssetTransform->position)) {
            $transform->position = $jsonAssetTransform->position;
        }

        // Set quality if it was provided
        if (isset($jsonAssetTransform->quality)) {
            // Quality must be greater than 0
            if ($jsonAssetTransform->quality < 1) {
                $transform->quality = 1;
            }
            // Quality must not be greater than 100
            elseif ($jsonAssetTransform->quality > 100) {
                $transform->quality = 100;
            } else {
                $transform->quality = $jsonAssetTransform->quality;
            }
        }

        // Set format if it was provided
        if (isset($jsonAssetTransform->format)) {
            $transform->format = $jsonAssetTransform->format;
        }
        // If not provided set to Auto format
        else {
            $transform->format = null;
        }

        // Save Asset Source to DB
        if (craft()->assetTransforms->saveTransform($transform)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * addGlobalSet
     * @param ArrayObject $jsonGlobalSet
     * @return Boolean [success]
     */
    private function addGlobalSet($jsonGlobalSet) {
        $globalSet = new GlobalSetModel();

        $globalSet->name = $jsonGlobalSet->name;

        // Set handle if it was provided
		if (isset($jsonGlobalSet->handle)) {
            $globalSet->handle = $jsonGlobalSet->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $globalSet->handle = $this->constructHandle($jsonGlobalSet->name);
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonGlobalSet->fieldLayout)) {
            $fieldLayout = $this->assembleLayout($jsonGlobalSet->fieldLayout);
    		$fieldLayout->type = ElementType::GlobalSet;
    		$globalSet->setFieldLayout($fieldLayout);
        }

        // Save Asset Source to DB
        if (craft()->globals->saveSet($globalSet)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * assembleLayout
     * @param Array $fieldLayout
     * @param Array $requiredFields
     * @return FieldLayoutModel
     */
    private function assembleLayout($fieldLayout, $requiredFields = array()) {
        $fieldLayoutPost = array();

        foreach ($fieldLayout as $tab => $fields) {
            $fieldLayoutPost[$tab] = array();
            foreach ($fields as $field) {
                $fieldLayoutPost[$tab][] = $this->getFieldId($field);
            }
        }
        return craft()->fields->assembleLayout($fieldLayoutPost, $requiredFields);
    }

    /**
     * getGroupId
     * @param String $name [name to find ID for]
     * @return Int
     */
    private function getGroupId($name) {
        $firstId = $this->groups[0]['id'];
        foreach ($this->groups as $group) {
            if ($group->attributes['name'] == $name) {
                return $group->attributes['id'];
            }
        }
        return $firstId;
    }

    /**
     * getFieldId
     * @param String $name [name to find ID for]
     * @return Int
     */
    private function getFieldId($name) {
        foreach ($this->fields as $field) {
            // Return ID if handle matches the search
            if ($field->attributes['handle'] == $name) {
                return $field->attributes['id'];
            }

            // Return ID if name matches the search
            if ($field->attributes['name'] == $name) {
                return $field->attributes['id'];
            }
        }
        return false;
    }

    /**
     * getSectionId
     * @param String $name [name to find ID for]
     * @return Int
     */
    private function getSectionId($name) {
        foreach ($this->sections as $section) {
            // Return ID if handle matches the search
            if ($section->attributes['handle'] == $name) {
                return $section->attributes['id'];
            }

            // Return ID if name matches the search
            if ($section->attributes['name'] == $name) {
                return $section->attributes['id'];
            }
        }
        return false;
    }

    /**
     * getSectionHandle
     * @param Int $id [ID to find handle for]
     * @return String
     */
    private function getSectionHandle($id) {
        foreach ($this->sections as $section) {
            // Return ID if handle matches the search
            if ($section->attributes['id'] == $id) {
                return $section->attributes['handle'];
            }
        }
        return false;
    }

    /**
     * constructHandle
     * @param  String $str [input string]
     * @return String      [the constructed handle]
     */
    private function constructHandle($string) {
        $string = strtolower($string);

        $words = explode(" ", $string);

        for ($i = 1; $i < count($words); $i++ ) {
        	$words[$i] = ucfirst($words[$i]);
        }

        return implode("", $words);
    }
}
