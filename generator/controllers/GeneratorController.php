<?php
namespace Craft;

/**
 * Generator Controller
 *
 * @package Craft
 */
class GeneratorController extends BaseController {
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
     * actionGenerateList [list the files inside the content folder]
     * @return null
     */
    public function actionGenerateList() {
        $files = array();
        if ($handle = opendir(craft()->path->getPluginsPath() . 'generator/content')) {
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

        $this->renderTemplate('generator/files', $variables);
    }


    /**
     * actionGenerateLoad [load the selected file for review before processing]
     * @return null
     */
    public function actionGenerateLoad() {
        // Prevent GET Requests
        $this->requirePostRequest();

        $fileName = craft()->request->getRequiredPost('fileName');

        $filePath = craft()->path->getPluginsPath() . 'generator/content/' . $fileName;

        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);

            $variables = array(
                'json' => $json,
                'filename' => $fileName
            );

            $this->renderTemplate('generator/index', $variables);
        }
    }

    /**
     * actionGenerate
     * @return null
     */
    public function actionGenerate() {
        // Prevent GET Requests
        $this->requirePostRequest();

        $json = craft()->request->getRequiredPost('json');

        if ($json) {
            $notice = $this->parseJson($json);

            $variables = array(
                'json' => $json,
                'result' => $notice
            );

            $this->renderTemplate('generator/index', $variables);
        }
    }

    // Private Methods
    // =========================================================================

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
                // Append Notice to Display Results
                $notice[] = array(
                    "type" => "Group",
                    "name" => $group,
                    "result" => $this->addGroup($group),
                    "errors" => false
                );
            }
        }

        $this->groups = craft()->fields->getAllGroups();

        // Add Fields from JSON
        if (isset($result->fields)) {
            foreach ($result->fields as $field) {
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
        // Generate handle if one wasn't provided
        else {
            $field->handle = $this->generateHandle($jsonField->name);
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

        if (isset($jsonField->typeSettings)) {
            // Convert Object to Array for saving
            $jsonField->typeSettings = json_decode(json_encode($jsonField->typeSettings), true);

            // $field->settings requires an array of the settings
            $field->settings = $jsonField->typeSettings;
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
        // Generate handle if one wasn't provided
        else {
            $section->handle = $this->generateHandle($jsonSection->name);
        }

        $section->type = $jsonSection->type;

        // Set enableVersioning if it was provided
        if (isset($jsonSection->typeSettings->enableVersioning)) {
            $section->enableVersioning = $jsonSection->typeSettings->enableVersioning;
        } else {
            $section->enableVersioning = 1;
        }

        // Set hasUrls if it was provided
        if (isset($jsonSection->typeSettings->hasUrls)) {
            $section->hasUrls = $jsonSection->typeSettings->hasUrls;
        }

        // Set template if it was provided
        if (isset($jsonSection->typeSettings->template)) {
            $section->template = $jsonSection->typeSettings->template;
        }

        // Set maxLevels if it was provided
        if (isset($jsonSection->typeSettings->maxLevels)) {
            $section->maxLevels = $jsonSection->typeSettings->maxLevels;
        }

        // Set Locale Information
        // Pulled from SectionController.php aprox. Ln 170
        $locales = array();
		$primaryLocaleId = craft()->i18n->getPrimarySiteLocaleId();
		$localeIds = array($primaryLocaleId);
        foreach ($localeIds as $localeId) {
            if (isset($jsonSection->typeSettings->urlFormat)) {
    			$urlFormat = $jsonSection->typeSettings->urlFormat;
            } else {
                $urlFormat = null;
            }

            if (isset($jsonSection->typeSettings->nestedUrlFormat)) {
    			$nestedUrlFormat = $jsonSection->typeSettings->nestedUrlFormat;
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
        // Generate handle if one wasn't provided
        else {
            $entryType->handle = $this->generateHandle($jsonEntryType->name);
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
        // Generate handle if one wasn't provided
        else {
            $source->handle = $this->generateHandle($jsonSource->name);
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
        // Generate handle if one wasn't provided
        else {
            $transform->handle = $this->generateHandle($jsonAssetTransform->name);
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
        // Generate handle if one wasn't provided
        else {
            $globalSet->handle = $this->generateHandle($jsonGlobalSet->name);
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
     * generateHandle
     * @param  String $str [input string]
     * @return String      [the generated handle]
     */
    private function generateHandle($string) {
        $string = strtolower($string);

        $words = explode(" ", $string);

        for ($i = 1; $i < count($words); $i++ ) {
        	$words[$i] = ucfirst($words[$i]);
        }

        return implode("", $words);
    }
}
