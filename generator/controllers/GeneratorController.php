<?php
namespace Craft;

/**
 * Generator Controller
 *
 * @package Craft
 */
class GeneratorController extends BaseController
{
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
	public function init()
	{
		// All section actions require an admin
		craft()->userSession->requireAdmin();
	}

    /**
     * actionImport
     * @return null
     */
    public function actionGenerate()
    {
        // Prevent GET Requests
        $this->requirePostRequest();

        $json = craft()->request->getRequiredPost('json');

        if ($json)
        {
            $result = json_decode($json);

            $notice = array();

            // Add Groups from JSON
            if (isset($result->groups))
            {
                foreach ($result->groups as $group)
                {
                    // Append Notice to Display Results
                    $notice[] = array(
                        "type" => "Group",
                        "name" => $group,
                        "result" => $this->addGroup($group)
                    );
                }
            }

            $this->groups = craft()->fields->getAllGroups();

            // Add Fields from JSON
            if (isset($result->fields))
            {
                foreach ($result->fields as $field)
                {
                    // Append Notice to Display Results
                    $notice[] = array(
                        "type" => "Field",
                        "name" => $field->name,
                        "result" => $this->addField($field)
                    );
                }
            }

            $this->fields = craft()->fields->getAllFields();

            // Add Sections from JSON
            if (isset($result->sections))
            {
                foreach ($result->sections as $section)
                {
                    // Append Notice to Display Results
                    $notice[] = array(
                        "type" => "Sections",
                        "name" => $section->name,
                        "result" => $this->addSection($section)
                    );
                }
            }

            $this->sections = craft()->sections->getAllSections();

            // Add Entry Types from JSON
            if (isset($result->entryTypes))
            {
                foreach ($result->entryTypes as $entryType) {
                    if (isset($entryType->titleLabel))
                    {
                        $entryTypeName = $entryType->titleLabel;
                    }
                    else
                    {
                        $entryTypeName = $entryType->titleFormat;
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        "type" => "Entry Types",
                        "name" => $entryType->sectionName . ( (isset($entryType->name)) ? ' > ' . $entryType->name : '' ) . ' > ' . $entryTypeName,
                        "result" => $this->addEntryType($entryType)
                    );
                }
            }

            craft()->urlManager->setRouteVariables(array(
                'json' => $json,
                'result' => $notice
            ));
        }
    }

    // Private Methods
    // =========================================================================

        /**
         * addGroup
         * @param String $name []
         * @return Boolean     [success]
         */
    private function addGroup($name)
    {
        $group = new FieldGroupModel();
        $group->name = $name;
        if (craft()->fields->saveGroup($group))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * addField
     * @param String $jsonField [input string]
     * @return Boolean          [success]
     */
    private function addField($jsonField)
    {
        $field = new FieldModel();

        // If group is set find groupId
        if (isset($jsonField->group))
        {
    		$field->groupId = $this->getGroupId($jsonField->group);
        }

        $field->name = $jsonField->name;

        // Set handle if it was provided
		if (isset($jsonField->handle))
        {
            $field->handle = $jsonField->handle;
        }
        // Generate handle if one wasn't provided
        else
        {
            $field->handle = $this->generateHandle($jsonField->name);
        }

        // Set instructions if it was provided
        if (isset($jsonField->instructions))
        {
            $field->instructions = $jsonField->instructions;
        }

        // Set translatable if it was provided
        if (isset($jsonField->translatable))
        {
            $field->translatable = $jsonField->translatable;
        }

        $field->type = $jsonField->type;

        if (isset($jsonField->typeSettings))
        {
            // Convert Object to Array for saving
            $jsonField->typeSettings = json_decode(json_encode($jsonField->typeSettings), true);

            // $field->settings requires an array of the settings
            $field->settings = $jsonField->typeSettings;
        }

        if (craft()->fields->saveField($field))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * addSection
     * @param String $jsonSection [input string]
     * @return Boolean          [success]
     */
    private function addSection($jsonSection)
    {
        $section = new SectionModel();

        $section->name = $jsonSection->name;

        // Set handle if it was provided
		if (isset($jsonSection->handle))
        {
            $section->handle = $jsonSection->handle;
        }
        // Generate handle if one wasn't provided
        else
        {
            $section->handle = $this->generateHandle($jsonSection->name);
        }

        $section->type = $jsonSection->type;

        // Set enableVersioning if it was provided
        if (isset($jsonSection->typeSettings->enableVersioning))
        {
            $section->enableVersioning = $jsonSection->typeSettings->enableVersioning;
        } else {
            $section->enableVersioning = 1;
        }

        // Set hasUrls if it was provided
        if (isset($jsonSection->typeSettings->hasUrls))
        {
            $section->hasUrls = $jsonSection->typeSettings->hasUrls;
        }

        // Set template if it was provided
        if (isset($jsonSection->typeSettings->template))
        {
            $section->template = $jsonSection->typeSettings->template;
        }

        // Set maxLevels if it was provided
        if (isset($jsonSection->typeSettings->maxLevels))
        {
            $section->maxLevels = $jsonSection->typeSettings->maxLevels;
        }

        $locales = array();
		$primaryLocaleId = craft()->i18n->getPrimarySiteLocaleId();
		$localeIds = array($primaryLocaleId);
        foreach ($localeIds as $localeId)
		{
            if (isset($jsonSection->typeSettings->urlFormat))
            {
    			$urlFormat = $jsonSection->typeSettings->urlFormat;
            }
            else
            {
                $urlFormat = null;
            }

            if (isset($jsonSection->typeSettings->nestedUrlFormat))
            {
    			$nestedUrlFormat = $jsonSection->typeSettings->nestedUrlFormat;
            }
            else
            {
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

        if (craft()->sections->saveSection($section))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * addEntryType
     * @param String $jsonEntryType [input string]
     * @return Boolean          [success]
     */
    private function addEntryType($jsonEntryType)
    {
        $entryType = new EntryTypeModel();

        $entryType->sectionId = $this->getSectionId($jsonEntryType->sectionName);

        // Check for name if not set name as sectionName
        if (!isset($jsonEntryType->name))
        {
            $jsonEntryType->name = $jsonEntryType->sectionName;
        }
        $entryType->name = $jsonEntryType->name;

        $sectionHandle = $this->getSectionHandle($entryType->sectionId);
        $entryTypes = craft()->sections->getEntryTypesByHandle($sectionHandle);
        if ($entryTypes) {
            $entryType = craft()->sections->getEntryTypeById($entryTypes[0]->attributes['id']);
        }

        // Set handle if it was provided
		if (isset($jsonEntryType->handle))
        {
            $entryType->handle = $jsonEntryType->handle;
        }
        // Generate handle if one wasn't provided
        else
        {
            $entryType->handle = $this->generateHandle($jsonEntryType->name);
        }

        // If titleLabel set hasTitleField to True
        if (isset($jsonEntryType->titleLabel))
        {
            $entryType->hasTitleField = true;
    		$entryType->titleLabel = $jsonEntryType->titleLabel;
        }
        // If titleFormat set hasTitleField to False
        else
        {
            $entryType->hasTitleField = false;
    		$entryType->titleFormat = $jsonEntryType->titleFormat;
        }

        $fieldLayoutPost = array();

        foreach ($jsonEntryType->fieldLayout as $tab => $fields)
        {
            $fieldLayoutPost[$tab] = array();
            foreach ($fields as $field)
            {
                $fieldLayoutPost[$tab][] = $this->getFieldId($field);
            }
        }

        $fieldLayout = craft()->fields->assembleLayout($fieldLayoutPost, array());

        $entryType->setFieldLayout($fieldLayout);

        if (craft()->sections->saveEntryType($entryType))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * getGroupId
     * @param String $name [name to find ID for]
     * @return Int
     */
    private function getGroupId($name)
    {
        $firstId = $this->groups[0]['id'];
        foreach ($this->groups as $group)
        {
            if ($group->attributes['name'] == $name)
            {
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
    private function getFieldId($name)
    {
        foreach ($this->fields as $field)
        {
            // Return ID if handle matches the search
            if ($field->attributes['handle'] == $name)
            {
                return $field->attributes['id'];
            }

            // Return ID if name matches the search
            if ($field->attributes['name'] == $name)
            {
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
    private function getSectionId($name)
    {
        foreach ($this->sections as $section)
        {
            // Return ID if handle matches the search
            if ($section->attributes['handle'] == $name)
            {
                return $section->attributes['id'];
            }

            // Return ID if name matches the search
            if ($section->attributes['name'] == $name)
            {
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
    private function getSectionHandle($id)
    {
        foreach ($this->sections as $section)
        {
            // Return ID if handle matches the search
            if ($section->attributes['id'] == $id)
            {
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
    private function generateHandle($string)
    {
        $string = strtolower($string);

        $words = explode(" ", $string);

        for ($i = 1; $i < count($words); $i++ )
        {
        	$words[$i] = ucfirst($words[$i]);
        }

        return implode("", $words);
    }
}
