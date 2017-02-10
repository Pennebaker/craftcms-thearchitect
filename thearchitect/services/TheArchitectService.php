<?php

namespace Craft;

class TheArchitectService extends BaseApplicationComponent
{
    private $groups;
    private $fields;
    private $sections;

    private $addedSources = [];

    /**
     * parseJson.
     *
     * @param string $json
     *
     * @return array [successfulness]
     */
    public function parseJson($json, $migration = false, $force = false)
    {
        $return = craft()->updates->backupDatabase();
        $dbBackupPath = $return['dbBackupPath'];
        try {
            $result = json_decode($json);

            $this->stripHandleSpaces($result);

            $notice = array();

            // Add Groups from JSON
            if (isset($result->groups)) {
                foreach ($result->groups as $group) {
                    $addGroupResult = $this->addGroup($group);
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Group',
                        'name' => $group,
                        'result' => $addGroupResult,
                        'errors' => false,
                    );
                }
            }

            $this->groups = craft()->fields->getAllGroups();

            if ($migration) {
                $entryTypeIds = [];
                foreach ($result->entryTypes as $entryType) {
                    if (!isset($entryTypeIds[$entryType->sectionId])) {
                        $entryTypeIds[$entryType->sectionId] = [];
                    }
                    array_push($entryTypeIds[$entryType->sectionId], $entryType->id);
                }
                if (isset($result->globals)) {
                    foreach ($result->globals as $global) {
                        $this->addGlobalSet($global, $global->id, false);
                    }
                }
            }

            // Add Sections from JSON
            if (isset($result->sections)) {
                foreach ($result->sections as $section) {
                    if ($migration) {
                        $addSectionResult = $this->addSection($section, $section->id);
                    } else {
                        $addSectionResult = $this->addSection($section);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Sections',
                        'name' => $section->name,
                        'result' => $addSectionResult[0],
                        'errors' => $addSectionResult[1],
                    );
                    if ($migration) {
                        /*
                         * Migration Sections Post-Processing
                         */
                        $queryIds = craft()->db->createCommand()->select('id')
                             ->from('entrytypes')
                             ->where('sectionId=:sectionId', array(':sectionId' => $section->id))
                             ->queryColumn();
                        foreach ($queryIds as $queryId) {
                            if (!in_array($queryId, $entryTypeIds[$section->id])) {
                                craft()->db->createCommand()->delete('entrytypes', array('id' => $queryId));
                            }
                        }
                    }
                }
            }

            // Add Asset Sources from JSON
            if (isset($result->sources)) {
                foreach ($result->sources as $key => $source) {
                    if ($migration) {
                        $assetSourceResult = $this->addAssetSource($source, $source->id);
                    } else {
                        $assetSourceResult = $this->addAssetSource($source);
                    }
                    if ($assetSourceResult[0] === false) {
                        unset($result->sources[$key]);
                    } elseif ($assetSourceResult[0] === true) {
                        array_push($this->addedSources, $assetSourceResult[2]);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Asset Source',
                        'name' => $source->name,
                        'result' => $assetSourceResult[0],
                        'errors' => $assetSourceResult[1],
                    );
                }
            }

            // Add UserGroups from JSON
            if (isset($result->userGroups)) {
                foreach ($result->userGroups as $key => $userGroup) {
                    if ($migration) {
                        $userGroupResult = $this->addUserGroup($userGroup, $userGroup->id);
                    } else {
                        $userGroupResult = $this->addUserGroup($userGroup);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'User Group',
                        'name' => $userGroup->name,
                        'result' => $userGroupResult[0],
                        'errors' => $userGroupResult[1],
                    );
                }
            }

            // Add CategoryGroup from JSON
            $addedCategories = [];
            if (isset($result->categories)) {
                foreach ($result->categories as $id => $categoryGroup) {
                    if ($migration) {
                        $addCategoryGroupResult = $this->addCategoryGroup($categoryGroup, $categoryGroup->id);
                    } else {
                        $addCategoryGroupResult = $this->addCategoryGroup($categoryGroup);
                    }
                    if ($addCategoryGroupResult[0]) {
                        array_push($addedCategories, $id);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Category Group',
                        'name' => $categoryGroup->name,
                        'result' => $addCategoryGroupResult[0],
                        'errors' => $addCategoryGroupResult[1],
                    );
                }
            }

            // Add Tags from JSON
            $addedTagGroups = [];
            if (isset($result->tags)) {
                foreach ($result->tags as $id => $tag) {
                    if ($migration) {
                        $addTagGroupResult = $this->addTagGroup($tag, $tag->id);
                    } else {
                        $addTagGroupResult = $this->addTagGroup($tag);
                    }
                    if ($addTagGroupResult[0]) {
                        array_push($addedTagGroups, $id);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Tag',
                        'name' => $tag->name,
                        'result' => $addTagGroupResult[0],
                        'errors' => $addTagGroupResult[1],
                    );
                }
            }

            // Add Fields from JSON
            if (isset($result->fields)) {
                foreach ($result->fields as $field) {
                    $prevContentTable = craft()->content->contentTable;
                    $this->replaceSourcesHandles($field);

                    if ($migration) {
                        /*
                         * Migration Field Pre-Processing
                         */
                        if ($field->type == 'Matrix' || $field->type == 'Neo' || $field->type == 'SuperTable') {
                            $query = craft()->db->createCommand()->select('id')
                                ->from('fields')
                                ->where('id=:id', array(':id' => $field->id))
                                ->queryColumn();
                            if (!$query) {
                                craft()->db->createCommand()->insert('fields', array(
                                    'id'     => $field->id,
                                    'name'   => $field->name,
                                    'handle' => $field->handle,
                                    'type'   => $field->type
                                ));
                            }
                        }
                        if ($field->type == 'Matrix') {
                            foreach ($field->typesettings->blockTypes as $matrixBlockTypeId => $matrixBlockType) {
                                $query = craft()->db->createCommand()->select('id')
                                    ->from('matrixblocktypes')
                                    ->where('id=:id', array(':id' => $matrixBlockTypeId))
                                    ->queryColumn();
                                if (!$query) {
                                    craft()->db->createCommand()->insert('matrixblocktypes', array(
                                        'id'      => $matrixBlockTypeId,
                                        'fieldId' => $field->id,
                                        'name'    => $matrixBlockType->name,
                                        'handle'  => $matrixBlockType->handle
                                    ));
                                }
                                foreach ($matrixBlockType->fields as $matrixFieldId => $matrixField) {
                                    $query = craft()->db->createCommand()->select('id')
                                        ->from('fields')
                                        ->where('id=:id', array(':id' => $matrixFieldId))
                                        ->queryColumn();
                                    if (!$query) {
                                        craft()->db->createCommand()->insert('fields', array(
                                            'id'      => $matrixFieldId,
                                            'name'    => $matrixField->name,
                                            'handle'  => $matrixField->handle,
                                            'context' => 'matrixBlockType:' . $matrixBlockTypeId
                                        ));
                                    }
                                }
                            }
                        }
                        if ($field->type == 'SuperTable') {
                            foreach ($field->typesettings->blockTypes as $supertableBlockTypeId => $supertableBlockType) {
                                $query = craft()->db->createCommand()->select('id')
                                    ->from('supertableblocktypes')
                                    ->where('id=:id', array(':id' => $supertableBlockTypeId))
                                    ->queryColumn();
                                if (!$query) {
                                    craft()->db->createCommand()->insert('supertableblocktypes', array(
                                        'id'      => $supertableBlockTypeId,
                                        'fieldId' => $field->id
                                    ));
                                }
                                foreach ($supertableBlockType->fields as $supertableFieldId => $supertableField) {
                                    $query = craft()->db->createCommand()->select('id')
                                        ->from('fields')
                                        ->where('id=:id', array(':id' => $supertableFieldId))
                                        ->queryColumn();
                                    if (!$query) {
                                        craft()->db->createCommand()->insert('fields', array(
                                            'id'      => $supertableFieldId,
                                            'name'    => $supertableField->name,
                                            'handle'  => $supertableField->handle,
                                            'context' => 'superTableBlockType:' . $supertableBlockTypeId
                                        ));
                                    }
                                }
                            }
                        }
                        if ($field->type == 'Neo') {
                            foreach ($field->typesettings->blockTypes as $neoBlockTypeId => $neoBlockType) {
                                $query = craft()->db->createCommand()->select('id')
                                    ->from('neoblocktypes')
                                    ->where('id=:id', array(':id' => $neoBlockTypeId))
                                    ->queryColumn();
                                if (!$query) {
                                    craft()->db->createCommand()->insert('neoblocktypes', array(
                                        'id'      => $neoBlockTypeId,
                                        'fieldId' => $field->id,
                                        'name'    => $neoBlockType->name,
                                        'handle'  => $neoBlockType->handle
                                    ));
                                }
                            }
                        }

                        $addFieldResult = $this->addField($field, $field->id);
                    } else {
                        // Make sure all used fields are available for import.
                        $addFieldResult = $this->testFieldTypes($field);
                        // If all fields are good lets import.
                        if ($addFieldResult[0]) {
                            $addFieldResult = $this->addField($field);
                        }
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Field',
                        'name' => $field->name,
                        'result' => $addFieldResult[0],
                        'errors' => $addFieldResult[1],
                        'errors_alt' => $addFieldResult[2],
                    );
                    /*
                     * Neo Field Post Processing
                     */
                    if ($field->type == 'Neo' && $addFieldResult[0]) {
                        $generatedField = $addFieldResult[3];
                        $blockTypes = craft()->neo->getBlockTypesByFieldId($generatedField->id);
                        foreach ($field->typesettings['blockTypes'] as $key => $value) {
                            $blockTypeKey = intval(substr($key, 3));
                            $fieldLayoutId = $blockTypes[$blockTypeKey]->getFieldLayout()->id;
                            if (craft()->plugins->getPlugin('relabel')) {
                                if (isset($value['relabel'])) {
                                    foreach ($value['relabel'] as $relabel) {
                                        $relabelModel = new RelabelModel();
                                        $relabelModel->fieldId = craft()->fields->getFieldByHandle($relabel['field'])->id;
                                        $relabelModel->fieldLayoutId = $fieldLayoutId;
                                        $relabelModel->name = $relabel['name'];
                                        $relabelModel->instructions = $relabel['instructions'];
                                        craft()->relabel->saveLabel($relabelModel);
                                    }
                                }
                            }
                            if (craft()->plugins->getPlugin('reasons')) {
                                if (isset($value['reasons'])) {
                                    $reasonsModel = [];
                                    foreach ($value['reasons'] as $fieldHandle => $reasons) {
                                        foreach ($reasons as &$reasonOr) {
                                            foreach ($reasonOr as &$reason) {
                                                $reason['fieldId'] = intval(craft()->fields->getFieldByHandle($reason['fieldId'])->id);
                                            }
                                        }
                                        $reasonsModel[craft()->fields->getFieldByHandle($fieldHandle)->id] = $reasons;
                                    }
                                    $conditionalsModel = new Reasons_ConditionalsModel();
                                    $conditionalsModel->fieldLayoutId = $fieldLayoutId;
                                    $conditionalsModel->conditionals = $reasonsModel;
                                    craft()->reasons->saveConditionals($conditionalsModel);
                                }
                            }
                        }
                    }
                    craft()->content->contentTable = $prevContentTable;
                }
            }

            $this->fields = craft()->fields->getAllFields();

            // Set Asset Source Field Layouts from JSON
            if (isset($result->sources)) {
                foreach ($result->sources as $source) {
                    $assetSourceResult = $this->setAssetSourceFieldLayout($source);
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Asset Source Field Layout',
                        'name' => $source->name,
                        'result' => $assetSourceResult[0],
                        'errors' => $assetSourceResult[1],
                    );
                    /*
                     * Asset Source Post Processing
                     */
                    if ($assetSourceResult[0]) {
                        $generatedAssetSource = $assetSourceResult[2];
                        $fieldLayoutId = $generatedAssetSource->fieldLayoutId;
                        if (craft()->plugins->getPlugin('relabel')) {
                            if (isset($source->relabel)) {
                                foreach ($source->relabel as $relabel) {
                                    $relabelModel = new RelabelModel();
                                    $relabelModel->fieldId = craft()->fields->getFieldByHandle($relabel->field)->id;
                                    $relabelModel->fieldLayoutId = $fieldLayoutId;
                                    $relabelModel->name = $relabel->name;
                                    $relabelModel->instructions = $relabel->instructions;
                                    craft()->relabel->saveLabel($relabelModel);
                                }
                            }
                        }
                        if (craft()->plugins->getPlugin('reasons')) {
                            if (isset($source->reasons)) {
                                $reasonsModel = [];
                                foreach ($source->reasons as $fieldHandle => $reasons) {
                                    foreach ($reasons as &$reasonOr) {
                                        foreach ($reasonOr as &$reason) {
                                            $reason->fieldId = intval(craft()->fields->getFieldByHandle($reason->fieldId)->id);
                                        }
                                    }
                                    $reasonsModel[craft()->fields->getFieldByHandle($fieldHandle)->id] = $reasons;
                                }
                                $conditionalsModel = new Reasons_ConditionalsModel();
                                $conditionalsModel->fieldLayoutId = $fieldLayoutId;
                                $conditionalsModel->conditionals = $reasonsModel;
                                craft()->reasons->saveConditionals($conditionalsModel);
                            }
                        }
                    }
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
                    $addEntryTypeResult = $this->addEntryType($entryType, $migration);
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Entry Types',
                        // Channels Might have an additional name.
                        'name' => $entryType->sectionHandle.((isset($entryType->name)) ? ' > '.$entryType->name : '').' > '.$entryTypeName,
                        'result' => $addEntryTypeResult[0],
                        'errors' => $addEntryTypeResult[1],
                    );
                    /*
                     * Entry Type Post Processing
                     */
                    if ($addEntryTypeResult[0]) {
                        $generatedEntryType = $addEntryTypeResult[2];
                        $fieldLayoutId = $generatedEntryType->fieldLayoutId;
                        if (craft()->plugins->getPlugin('relabel')) {
                            if (isset($entryType->relabel)) {
                                foreach ($entryType->relabel as $relabel) {
                                    $relabelModel = new RelabelModel();
                                    $relabelModel->fieldId = craft()->fields->getFieldByHandle($relabel->field)->id;
                                    $relabelModel->fieldLayoutId = $fieldLayoutId;
                                    $relabelModel->name = $relabel->name;
                                    $relabelModel->instructions = $relabel->instructions;
                                    craft()->relabel->saveLabel($relabelModel);
                                }
                            }
                        }
                        if (craft()->plugins->getPlugin('reasons')) {
                            if (isset($entryType->reasons)) {
                                $reasonsModel = [];
                                foreach ($entryType->reasons as $fieldHandle => $reasons) {
                                    foreach ($reasons as &$reasonOr) {
                                        foreach ($reasonOr as &$reason) {
                                            $reason->fieldId = intval(craft()->fields->getFieldByHandle($reason->fieldId)->id);
                                        }
                                    }
                                    $reasonsModel[craft()->fields->getFieldByHandle($fieldHandle)->id] = $reasons;
                                }
                                $conditionalsModel = new Reasons_ConditionalsModel();
                                $conditionalsModel->fieldLayoutId = $fieldLayoutId;
                                $conditionalsModel->conditionals = $reasonsModel;
                                craft()->reasons->saveConditionals($conditionalsModel);
                            }
                        }
                    }
                }
            }

            // Add Transforms from JSON
            if (isset($result->transforms)) {
                foreach ($result->transforms as $transform) {
                    if ($migration) {
                        $addAssetTransformResult = $this->addAssetTransform($transform, $transform->id);
                    } else {
                        $addAssetTransformResult = $this->addAssetTransform($transform);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Asset Transform',
                        'name' => $transform->name,
                        'result' => $addAssetTransformResult,
                        'errors' => false,
                    );
                }
            }

            // Add Globals from JSON
            if (isset($result->globals)) {
                foreach ($result->globals as $global) {
                    if ($migration) {
                        $addGlobalResult = $this->addGlobalSet($global, $global->id);
                    } else {
                        $addGlobalResult = $this->addGlobalSet($global);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Global Set',
                        'name' => $global->name,
                        'result' => $addGlobalResult[0],
                        'errors' => $addGlobalResult[1],
                    );
                    /*
                     * Global Set Post Processing
                     */
                    if ($addGlobalResult[0]) {
                        $generatedGlobalSet = $addGlobalResult[2];
                        $fieldLayoutId = $generatedGlobalSet->fieldLayoutId;
                        if (craft()->plugins->getPlugin('relabel')) {
                            if (isset($global->relabel)) {
                                foreach ($global->relabel as $relabel) {
                                    $relabelModel = new RelabelModel();
                                    $relabelModel->fieldId = craft()->fields->getFieldByHandle($relabel->field)->id;
                                    $relabelModel->fieldLayoutId = $fieldLayoutId;
                                    $relabelModel->name = $relabel->name;
                                    $relabelModel->instructions = $relabel->instructions;
                                    craft()->relabel->saveLabel($relabelModel);
                                }
                            }
                        }
                        if (craft()->plugins->getPlugin('reasons')) {
                            if (isset($global->reasons)) {
                                $reasonsModel = [];
                                foreach ($global->reasons as $fieldHandle => $reasons) {
                                    foreach ($reasons as &$reasonOr) {
                                        foreach ($reasonOr as &$reason) {
                                            $reason->fieldId = intval(craft()->fields->getFieldByHandle($reason->fieldId)->id);
                                        }
                                    }
                                    $reasonsModel[craft()->fields->getFieldByHandle($fieldHandle)->id] = $reasons;
                                }
                                $conditionalsModel = new Reasons_ConditionalsModel();
                                $conditionalsModel->fieldLayoutId = $fieldLayoutId;
                                $conditionalsModel->conditionals = $reasonsModel;
                                craft()->reasons->saveConditionals($conditionalsModel);
                            }
                        }
                    }
                }
            }

            // Set category group field layout.
            if (isset($result->categories)) {
                foreach ($result->categories as $id => $categoryGroup) {
                    if (in_array($id, $addedCategories)) {
                        $addCategoryGroupResult = $this->setCategoryGroupFieldLayout($categoryGroup);
                        // Append Notice to Display Results
                        $notice[] = array(
                            'type' => 'Category Group Field Layout',
                            'name' => $categoryGroup->name,
                            'result' => $addCategoryGroupResult[0],
                            'errors' => $addCategoryGroupResult[1],
                        );
                        /*
                         * Category Group Post Processing
                         */
                        if ($addCategoryGroupResult[0]) {
                            $generatedCategoryGroup = $addCategoryGroupResult[2];
                            $fieldLayoutId = $generatedCategoryGroup->fieldLayoutId;
                            if (craft()->plugins->getPlugin('relabel')) {
                                if (isset($categoryGroup->relabel)) {
                                    foreach ($categoryGroup->relabel as $relabel) {
                                        $relabelModel = new RelabelModel();
                                        $relabelModel->fieldId = craft()->fields->getFieldByHandle($relabel->field)->id;
                                        $relabelModel->fieldLayoutId = $fieldLayoutId;
                                        $relabelModel->name = $relabel->name;
                                        $relabelModel->instructions = $relabel->instructions;
                                        craft()->relabel->saveLabel($relabelModel);
                                    }
                                }
                            }
                            if (craft()->plugins->getPlugin('reasons')) {
                                if (isset($categoryGroup->reasons)) {
                                    $reasonsModel = [];
                                    foreach ($categoryGroup->reasons as $fieldHandle => $reasons) {
                                        foreach ($reasons as &$reasonOr) {
                                            foreach ($reasonOr as &$reason) {
                                                $reason->fieldId = intval(craft()->fields->getFieldByHandle($reason->fieldId)->id);
                                            }
                                        }
                                        $reasonsModel[craft()->fields->getFieldByHandle($fieldHandle)->id] = $reasons;
                                    }
                                    $conditionalsModel = new Reasons_ConditionalsModel();
                                    $conditionalsModel->fieldLayoutId = $fieldLayoutId;
                                    $conditionalsModel->conditionals = $reasonsModel;
                                    craft()->reasons->saveConditionals($conditionalsModel);
                                }
                            }
                        }
                    }
                }
            }

            // Add Tags from JSON
            if (isset($result->tags)) {
                foreach ($result->tags as $id => $tag) {
                    if (in_array($id, $addedTagGroups)) {
                        $addTagResult = $this->addTagFieldLayout($tag);

                        // Append Notice to Display Results
                        $notice[] = array(
                            'type' => 'Tag Field Layout',
                            'name' => $tag->name,
                            'result' => $addTagResult[0],
                            'errors' => $addTagResult[1],
                        );
                        /*
                         * Tag Group Post Processing
                         */
                        if ($addTagResult[0]) {
                            $generatedTagGroup = $addTagResult[2];
                            $fieldLayoutId = $generatedTagGroup->fieldLayoutId;
                            if (craft()->plugins->getPlugin('relabel')) {
                                if (isset($categoryGroup->relabel)) {
                                    foreach ($categoryGroup->relabel as $relabel) {
                                        $relabelModel = new RelabelModel();
                                        $relabelModel->fieldId = craft()->fields->getFieldByHandle($relabel->field)->id;
                                        $relabelModel->fieldLayoutId = $fieldLayoutId;
                                        $relabelModel->name = $relabel->name;
                                        $relabelModel->instructions = $relabel->instructions;
                                        craft()->relabel->saveLabel($relabelModel);
                                    }
                                }
                            }
                            if (craft()->plugins->getPlugin('reasons')) {
                                if (isset($categoryGroup->reasons)) {
                                    $reasonsModel = [];
                                    foreach ($categoryGroup->reasons as $fieldHandle => $reasons) {
                                        foreach ($reasons as &$reasonOr) {
                                            foreach ($reasonOr as &$reason) {
                                                $reason->fieldId = intval(craft()->fields->getFieldByHandle($reason->fieldId)->id);
                                            }
                                        }
                                        $reasonsModel[craft()->fields->getFieldByHandle($fieldHandle)->id] = $reasons;
                                    }
                                    $conditionalsModel = new Reasons_ConditionalsModel();
                                    $conditionalsModel->fieldLayoutId = $fieldLayoutId;
                                    $conditionalsModel->conditionals = $reasonsModel;
                                    craft()->reasons->saveConditionals($conditionalsModel);
                                }
                            }
                        }
                    }
                }
            }

            // Add Routes from JSON
            if (isset($result->routes)) {
                foreach ($result->routes as $route) {
                    if ($migration) {
                        $addRouteResult = $this->addRoute($route, $route->id);
                    } else {
                        $addRouteResult = $this->addRoute($route);
                    }
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'Route',
                        'name' => json_encode($route->urlParts),
                        'result' => $addRouteResult[0],
                        'errors' => $addRouteResult[1],
                    );
                }
            }

            // Add UserGroupsPermissions from JSON
            if (isset($result->userGroupPermissions)) {
                foreach ($result->userGroupPermissions as $key => $userGroup) {
                    $userGroupResult = $this->addUserGroupPermissions($userGroup);
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'User Group Permissions',
                        'name' => $userGroup->handle,
                        'result' => $userGroupResult[0],
                        'errors' => $userGroupResult[1],
                    );
                }
            }

            // Add Users from JSON
            if (isset($result->users)) {
                foreach ($result->users as $key => $user) {
                    $userResult = $this->addUser($user, $migration);
                    // Append Notice to Display Results
                    $notice[] = array(
                        'type' => 'User',
                        'name' => $user->username,
                        'result' => $userResult[0],
                        'errors' => $userResult[1],
                    );
                }
            }

            return $notice;
        } catch (\Exception $e) {
            $re = '/Property "Craft\\\\ContentModel\..*" is not defined\./';
             // TODO: Figure out why this happens at all.
             // Occurs on Saving a global if any field handles get updated.
             // This field does not even have to be used on any global to trigger.
            preg_match_all($re, $e->getMessage(), $matches);
            if (!$matches) {
                Craft::log('Rolling back any database changes.', LogLevel::Info, true);
                UpdateHelper::rollBackDatabaseChanges($dbBackupPath);
                Craft::log('Done rolling back any database changes.', LogLevel::Info, true);
                // unlink(craft()->path->getDbBackupPath().$dbBackupPath.'.sql'); // NOTE: Delete DB Backups after rollback?
                throw $e;
            }
        }
        unlink(craft()->path->getDbBackupPath().$dbBackupPath.'.sql');
    }

    /**
     * exportConstruct.
     *
     * @param array $post
     *
     * @return array [output]
     */
    public function exportConstruct($post, $includeID = false)
    {
        list($sections, $entryTypes) = $this->sectionExport($post, $includeID);
        list($groups, $fields) = $this->fieldExport($post, $includeID);
        $sources = $this->assetSourceExport($post, $includeID);
        $transforms = $this->transformExport($post, $includeID);
        $globals = $this->globalSetExport($post, $includeID);
        $categories = $this->categoryGroupExport($post, $includeID);
        $routes = $this->routeExport($post, $includeID);
        $tags = $this->tagExport($post, $includeID);
        $users = $this->usersExport($post, $includeID);

        // Add all Arrays into the final output array
        $output = [
            'groups' => $groups,
            'sections' => $sections,
            'fields' => $fields,
            'entryTypes' => $entryTypes,
            'sources' => $sources,
            'transforms' => $transforms,
            'globals' => $globals,
            'categories' => $categories,
            'routes' => $routes,
            'tags' => $tags,
            'users' => $users,
        ];

        if (craft()->getEdition() == Craft::Pro) {
            $userGroups = $this->userGroupsExport($post, $includeID);
            if (is_array($userGroups)) {
                $output['userGroups'] = $userGroups[0];
                $output['userGroupPermissions'] = $userGroups[1];
            }
        }

        // Remove empty sections from the output array
        foreach ($output as $key => $value) {
            if ($value == []) {
                unset($output[$key]);
            }
        }

        return $output;
    }

    /**
     * exportMatrixAsNeo.
     *
     * @param array $post
     *
     * @return array [output, allFields, fields, similarFields]
     */
    public function exportMatrixAsNeo($post)
    {
        $export = $this->exportConstruct($post);

        // Converting arrays to objects.
        $json = json_encode($export, JSON_NUMERIC_CHECK);

        $object = json_decode($json);

        $newObject = clone $object;
        $newObject->fields = [];

        $fields = [];
        $allFields = [];
        $addedMatrixFields = [];

        $fieldLinks = [];
        $similarFields = [];

        foreach ($object->fields as $fieldId => $field) {
            if ($field->type == 'Matrix') {
                $currentGroup = $field->group;
                $maxCount = 0;
                foreach ($field->typesettings->blockTypes as $blockId => $block) {
                    foreach ($block->fields as $blockFieldId => $blockField) {
                        $allFields[] = $blockField;
                        $fieldLoc = array_search($blockField, $fields);
                        for ($i = 0; $i < $maxCount; ++$i) {
                            if ($fieldLoc !== false) {
                                break;
                            }
                            $testBlockField = clone $blockField;
                            $testBlockField->handle = $blockField->handle.'_'.$i;
                            $fieldLoc = array_search($testBlockField, $fields);
                            if ($fieldLoc !== false) {
                                $blockField->handle = $testBlockField->handle;
                            }
                        }
                        if ($fieldLoc === false) {
                            if ($this->hasHandle($blockField->handle, $fields)) {
                                $count = 0;
                                $originalHandle = $blockField->handle;
                                while ($this->hasHandle($blockField->handle, $fields)) {
                                    $blockField->handle = $originalHandle.'_'.$count;
                                    ++$count;
                                }
                                if ($count > $maxCount) {
                                    $maxCount = $count;
                                }
                            }
                            $fields[] = $blockField;
                        }
                    }
                }
                // Append the fields to the new exported fields section. If they don't already exist there.
                foreach ($fields as $addField) {
                    if ($addField->type != 'Matrix') {
                        if (!isset($addField->group)) {
                            $addField->group = $currentGroup;
                            if (!$this->hasHandle($addField->handle, $newObject->fields)) {
                                $newObject->fields[] = clone $addField;
                            }
                        }
                    }
                }
                // Find and "link" similar fields.
                foreach ($fields as $fieldA) {
                    $fAId = $fieldA->handle;
                    foreach ($fields as $fieldB) {
                        $fBId = $fieldB->handle;
                        if ($fieldA != $fieldB && !(in_array([$fAId, $fBId], $fieldLinks) || in_array([$fBId, $fAId], $fieldLinks))) {
                            if ($fieldA->type == $fieldB->type) {
                                if ($fieldA->typesettings == $fieldB->typesettings) {
                                    $fieldLinks[] = [
                                        $fAId,
                                        $fBId,
                                    ];
                                    $similarFields[] = [
                                        'A' => json_encode($fieldA, JSON_PRETTY_PRINT),
                                        'B' => json_encode($fieldB, JSON_PRETTY_PRINT),
                                    ];
                                }
                            }
                        }
                    }
                }
                // Add the current matrix field as a Neo field to the new exported fields section.
                $addedMatrixFields[] = $field->handle;
                $newField = clone $field;
                $newField->type = 'Neo';
                $newField->typesettings = ['maxBlocks' => $field->typesettings->maxBlocks, 'groups' => [], 'blockTypes' => []];
                $count = 0;
                foreach ($field->typesettings->blockTypes as $blockId => $block) {
                    $blockFields = [];
                    $requiredFields = [];
                    foreach ($block->fields as $blockField) {
                        $blockFields[] = $blockField->handle;
                        if ($blockField->required) {
                            $requiredFields[] = $blockField->handle;
                        }
                    }
                    $newField->typesettings['blockTypes']['new'.$count] = [
                        'sortOrder' => strval($count + 1),
                        'name' => $block->name,
                        'handle' => $block->handle,
                        'maxBlocks' => '',
                        'childBlocks' => [],
                        'maxChildBlocks' => '',
                        'topLevel' => true,
                        'fieldLayout' => ['Tab' => $blockFields],
                        'requiredFields' => $requiredFields,
                    ];
                    ++$count;
                }
                $newObject->fields[] = $newField;
            }
        }

        return [$newObject, $allFields, $fields, $similarFields];
    }

    public function exportMigrationConstruct()
    {
        $post = $this->getAllIDs();

        $output = $this->exportConstruct($post, true);
        $json = json_encode($output, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);

        $masterJson = craft()->config->get('modelsPath', 'theArchitect').'_master_.json';
        file_put_contents($masterJson, $json);
    }

    public function importMigrationConstruct($force = false)
    {
        if ($force === false && craft()->theArchitect->compareMigrationConstruct()) {
            return false;
        }

        $masterJson = craft()->config->get('modelsPath', 'theArchitect').'_master_.json';
        $json = file_get_contents($masterJson);
        $output = json_decode($json);

        // Let's get a list of items to delete...
        list($dbAddedIDs, $dbUpdatedIDs, $dbDeleteIDs, $modelAddedIDs, $modelUpdatedIDs, $modelDeleteIDs) = $this->getAddedUpdatedDeletedIds($json);
        // and delete them.
        foreach ($modelDeleteIDs['fieldIDs'] as $fieldId) {
            craft()->fields->deleteField(craft()->fields->getFieldById($fieldId));
        }
        foreach ($modelDeleteIDs['sectionIDs'] as $sectionId) {
            craft()->sections->deleteSectionById($sectionId);
        }
        foreach ($modelDeleteIDs['entryTypeIDs'] as $entryTypeId) {
            craft()->sections->deleteEntryTypeById($entryTypeId);
        }
        foreach ($modelDeleteIDs['entryTypeIDs'] as $entryTypeId) {
            craft()->sections->deleteEntryTypeById($entryTypeId);
        }
        foreach ($modelDeleteIDs['assetSourceIDs'] as $sourceId) {
            craft()->assetSources->deleteSourceById($sourceId);
        }
        foreach ($modelDeleteIDs['assetTransformIDs'] as $transformId) {
            craft()->assetTransforms->deleteTransform($transformId);
        }
        foreach ($modelDeleteIDs['globalIDs'] as $globalSetId) {
            craft()->globals->deleteSetById($globalSetId);
        }
        foreach ($modelDeleteIDs['categoryIDs'] as $groupId) {
            craft()->categories->deleteGroupById($groupId);
        }
        // Should we delete users? There is no migration options to transfer content? Instead we have a notice on the migration page prompting users to be deleted manually.
        // foreach ($modelDeleteIDs['userIDs'] as $userId) {
        //     $userToDelete = craft()->users->getUserById($userId);
        //     craft()->users->deleteUser($userToDelete, $transferContentTo);
        // }
        if (craft()->getEdition() == Craft::Pro) {
            foreach ($modelDeleteIDs['groupIDs'] as $groupId) {
                craft()->userGroups->deleteGroupById($groupId);
            }
        }

        $this->parseJson($json, true, $force);

        craft()->plugins->savePluginSettings(craft()->plugins->getPlugin('theArchitect'), array('lastImport' => (new DateTime())->getTimestamp()));

        return true;
    }

    public function compareMigrationConstruct()
    {
        $masterJson = craft()->config->get('modelsPath', 'theArchitect').'_master_.json';
        if (!file_exists($masterJson)) {
            return [];
        }
        $json = file_get_contents($masterJson);
        $output = json_decode($json);

        $this->groups = craft()->fields->getAllGroups();
        $this->fields = craft()->fields->getAllFields();
        $this->sections = craft()->sections->getAllSections();

        $mismatchFields = [];

        if (isset($output->fields)) {
            foreach ($output->fields as $field) {
                $curField = craft()->fields->getFieldById($field->id);
                if (!is_null($curField)) {
                    if ($field->type != $curField->type) {
                        array_push($mismatchFields, [$curField, $field]);
                    }
                }
            }
        }

        return $mismatchFields;
    }

    /**
     * addGroup.
     *
     * @param string $name []
     *
     * @return bool [success]
     */
    public function addGroup($name)
    {
        $group = new FieldGroupModel();

        $group->name = $name;

        // Save Group to DB
        if (craft()->fields->saveGroup($group)) {
            return true;
        } else {
            return false;
        }
    }

    public function fieldTypes()
    {
        $fieldTypes = [];
        foreach (craft()->fields->allFieldTypes as $key => $value) {
            array_push($fieldTypes, $key);
        }

        return $fieldTypes;
    }

    private function testFieldTypes($jsonField)
    {
        $field = craft()->fields->getFieldType($jsonField->type);
        $missingSubFields = [];

        if ($jsonField->type == 'Matrix') {
            foreach ($jsonField->typesettings->blockTypes as $matrixTabs) {
                foreach ($matrixTabs->fields as $subJsonField) {
                    $result = $this->testFieldTypes($subJsonField);
                    if (!$result[0]) {
                        array_push($missingSubFields, $result);
                    }
                }
            }
        }

        if ($jsonField->type == 'SuperTable') {
            foreach ($jsonField->typesettings->blockTypes->new->fields as $subJsonField) {
                $result = $this->testFieldTypes($subJsonField);
                if (!$result[0]) {
                    array_push($missingSubFields, $result);
                }
            }
        }

        if (!$field) {
            return [false, ['Field Type' => ['Field type "' . $jsonField->type . '" not available']], false, false];
        } elseif (count($missingSubFields) > 0) {
            $missingFields = [];
            foreach ($missingSubFields as $result) {
                foreach ($result[1]['Field Type'] as $error) {
                    array_push($missingFields, $error);
                }
            }
            return [false, ['Field Type' => $missingFields], false, false];
        } else {
            return [true, false, false, false];
        }
    }

    /**
     * addField.
     *
     * @param ArrayObject $jsonField
     *
     * @return bool [success]
     */
    public function addField($jsonField, $fieldID = false)
    {
        if ($fieldID) {
            $field = $this->getFieldById($fieldID);
        } else {
            $field = new FieldModel();
        }

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

        if ($jsonField->type == 'Neo') {
            foreach ($jsonField->typesettings->blockTypes as &$blockType) {
                if (!isset($blockType->maxChildBlocks)) {
                    $blockType->maxChildBlocks = '';
                }
            }
        }

        if (isset($jsonField->typesettings)) {
            // Convert Object to Array for saving
            $jsonField->typesettings = json_decode(json_encode($jsonField->typesettings), true);

            // $field->settings requires an array of the settings
            $field->settings = $jsonField->typesettings;
        }

        // Save Field to DB
        if (craft()->fields->saveField($field)) {
            return [true, false, false, $field];
        } else {
            return [false, $field->getErrors(), $field->getSettingErrors(), false];
        }
    }

    /**
     * addSection.
     *
     * @param ArrayObject $jsonSection
     *
     * @return bool [success]
     */
    public function addSection($jsonSection, $sectionID = false)
    {
        if ($sectionID) {
            $section = $this->getSectionById($sectionID);
        } else {
            $section = new SectionModel();
        }

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
        if (isset($jsonSection->enableVersioning)) {
            $section->enableVersioning = $jsonSection->enableVersioning;
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
        $locales = [];

        $allLocales = craft()->i18n->getAllLocales();
        foreach ($allLocales as $locale) {
            $localeId = $locale->id;
            if (isset($jsonSection->typesettings->$localeId->urlFormat)) {
                $urlFormat = $jsonSection->typesettings->$localeId->urlFormat;
            } else {
                $urlFormat = null;
            }

            if (isset($jsonSection->typesettings->$localeId->nestedUrlFormat)) {
                $nestedUrlFormat = $jsonSection->typesettings->$localeId->nestedUrlFormat;
            } else {
                $nestedUrlFormat = null;
            }

            if (isset($jsonSection->typesettings->$localeId->defaultLocaleStatus)) {
                $defaultLocaleStatus = $jsonSection->typesettings->$localeId->defaultLocaleStatus;
            } else {
                $defaultLocaleStatus = true;
            }

            if (isset($jsonSection->typesettings->$localeId)) {
                $locales[$localeId] = new SectionLocaleModel(array(
                    'locale' => $localeId,
                    'enabledByDefault' => $defaultLocaleStatus,
                    'urlFormat' => $urlFormat,
                    'nestedUrlFormat' => $nestedUrlFormat,
                ));
            }
        }
        // Pulled from SectionController.php aprox. Ln 170
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

            if (isset($jsonSection->typesettings->defaultLocaleStatus)) {
                $defaultLocaleStatus = $jsonSection->typesettings->defaultLocaleStatus;
            } else {
                $defaultLocaleStatus = true;
            }
            if (isset($jsonSection->typesettings->urlFormat) || isset($jsonSection->typesettings->nestedUrlFormat) || isset($jsonSection->typesettings->defaultLocaleStatus)) {
                $locales[$localeId] = new SectionLocaleModel(array(
                    'locale' => $localeId,
                    'enabledByDefault' => $defaultLocaleStatus,
                    'urlFormat' => $urlFormat,
                    'nestedUrlFormat' => $nestedUrlFormat,
                ));
            }
        }
        if ($section->attributes['hasUrls'] === false || $section->attributes['hasUrls'] === 0 || $section->attributes['hasUrls'] === '0') {
            foreach ($jsonSection->typesettings->locales as $localeId => $defaultLocaleStatus) {
                $locales[$localeId] = new SectionLocaleModel(array(
                    'locale' => $localeId,
                    'enabledByDefault' => $defaultLocaleStatus,
                    'urlFormat' => null,
                    'nestedUrlFormat' => null,
                ));
            }
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
     * addEntryType.
     *
     * @param ArrayObject $jsonEntryType
     *
     * @return bool [success]
     */
    public function addEntryType($jsonEntryType, $migration = false)
    {
        if ($migration) {
            $entryType = $this->getEntryTypeById($jsonEntryType->id, $jsonEntryType->sectionId);
        } else {
            $entryType = new EntryTypeModel();
        }

        // Make sure section adding entry type to exists.
        if (!$this->getSectionid($jsonEntryType->sectionHandle)) {
            return [false, ['Section' => ['Section "'.$jsonEntryType->sectionHandle.'" not available']], false, false];
        }

        // Set handle if it was provided
        if (isset($jsonEntryType->handle)) {
            $entryType->handle = $jsonEntryType->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $entryType->handle = $this->constructHandle($jsonEntryType->name);
        }

        $entryType->sectionId = $this->getSectionId($jsonEntryType->sectionHandle);

        if (!$migration) {
            // If the Entry Type exists load it so that it udpates it.
            $sectionHandle = $this->getSectionHandle($entryType->sectionId);
            $sectionEntryTypes = craft()->sections->getEntryTypesByHandle($entryType->handle);
            if ($sectionEntryTypes) {
                foreach ($sectionEntryTypes as $sectionEntryType) {
                    if ($sectionEntryType->sectionId == $entryType->sectionId) {
                        $entryType = craft()->sections->getEntryTypeById($sectionEntryType->attributes['id']);
                    }
                }
            }
        }

        $entryType->name = $jsonEntryType->name;

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

        $problemFields = $this->checkFieldLayout($jsonEntryType->fieldLayout);
        if ($problemFields !== ['handle' => []]) {
            return [false, $problemFields];
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonEntryType->fieldLayout)) {
            $requiredFields = [];
            if (isset($jsonEntryType->requiredFields) && is_array($jsonEntryType->requiredFields)) {
                foreach ($jsonEntryType->requiredFields as $requirdField) {
                    array_push($requiredFields, $this->getFieldId($requirdField));
                }
            }
            $fieldLayout = $this->assembleLayout($jsonEntryType->fieldLayout, $requiredFields);
            $fieldLayout->type = ElementType::Entry;
            $entryType->setFieldLayout($fieldLayout);
        }

        // Save Entry Type to DB
        if (craft()->sections->saveEntryType($entryType)) {
            return [true, false, $entryType];
        } else {
            return [false, $entryType->getErrors(), $entryType];
        }
    }

    /**
     * addAssetSource.
     *
     * @param ArrayObject $jsonSection
     *
     * @return bool [success]
     */
    public function addAssetSource($jsonSource, $sourceID = false)
    {
        if ($sourceID) {
            $source = $this->getAssetSourceById($sourceID);

            $assetFolder = craft()->db->createCommand()->select('id')
                 ->from('assetfolders')
                 ->where('sourceId=:sourceId', array(':sourceId' => $sourceID))
                 ->queryColumn();
            if (!$assetFolder) {
                craft()->db->createCommand()->insert('assetfolders', array(
                    'id' => $sourceID,
                    'sourceId' => $sourceID,
                    'name' => $jsonSource->name,
                ));
            }
        } else {
            $source = new AssetSourceModel();
        }

        $source->name = $jsonSource->name;

        // Set handle if it was provided
        if (isset($jsonSource->handle)) {
            $source->handle = $jsonSource->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $source->handle = $this->constructHandle($jsonSource->name);
        }

        $source->type = $jsonSource->type;

        // Convert Object to Array for saving
        $source->settings = json_decode(json_encode($jsonSource->settings), true);

        // Save Asset Source to DB
        if (craft()->assetSources->saveSource($source)) {
            return [true, null, $source];
        } else {
            return [false, $source->getErrors()];
        }
    }

    /**
     * addAssetSourceFieldLayout.
     *
     * @param ArrayObject $jsonSection
     *
     * @return bool [success]
     */
    public function setAssetSourceFieldLayout($jsonSource)
    {
        // Set handle if it was provided
        if (isset($jsonSource->handle)) {
            $handle = $jsonSource->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $handle = $this->constructHandle($jsonSource->name);
        }

        $source = $this->getSourceByHandle($handle);

        $problemFields = $this->checkFieldLayout($jsonSource->fieldLayout);
        if ($problemFields !== ['handle' => []]) {
            return [false, $problemFields];
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonSource->fieldLayout)) {
            $requiredFields = [];
            if (isset($jsonSource->requiredFields) && is_array($jsonSource->requiredFields)) {
                foreach ($jsonSource->requiredFields as $requirdField) {
                    array_push($requiredFields, $this->getFieldId($requirdField));
                }
            }
            $fieldLayout = $this->assembleLayout($jsonSource->fieldLayout, $requiredFields);
            $fieldLayout->type = ElementType::Asset;
            $source->setFieldLayout($fieldLayout);
        }

        // Save Asset Source to DB
        if (craft()->assetSources->saveSource($source)) {
            return [true, null, $source];
        } else {
            return [false, $source->getErrors(), $source];
        }
    }

    private function checkFieldLayout($fieldLayout)
    {
        $problemFields = [];
        $problemFields['handle'] = [];
        foreach ($fieldLayout as $tab => $fields) {
            foreach ($fields as $fieldHandle) {
                $field = craft()->fields->getFieldByHandle($fieldHandle);
                if ($field === null) {
                    array_push($problemFields['handle'], 'Handle "'.$fieldHandle.'" is not a valid field.');
                }
            }
        }

        return $problemFields;
    }

    /**
     * addAssetTransform.
     *
     * @param ArrayObject $jsonAssetTransform
     *
     * @return bool [success]
     */
    public function addAssetTransform($jsonAssetTransform, $transformID = false)
    {
        if ($transformID) {
            $transform = $this->getAssetTransformById($transformID);
        } else {
            $transform = new AssetTransformModel();
        }

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
        if (isset($jsonAssetTransform->width) or isset($jsonAssetTransform->height)) {
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
     * addGlobalSet.
     *
     * @param ArrayObject $jsonGlobalSet
     *
     * @return bool [success]
     */
    public function addGlobalSet($jsonGlobalSet, $globalSetID = false, $setFieldLayout = true)
    {
        if ($globalSetID) {
            $globalSet = $this->getGlobalSetById($globalSetID);
        } else {
            $globalSet = new GlobalSetModel();
        }

        $globalSet->name = $jsonGlobalSet->name;

        // Set handle if it was provided
        if (isset($jsonGlobalSet->handle)) {
            $globalSet->handle = $jsonGlobalSet->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $globalSet->handle = $this->constructHandle($jsonGlobalSet->name);
        }

        if ($setFieldLayout) {
            $problemFields = $this->checkFieldLayout($jsonGlobalSet->fieldLayout);
            if ($problemFields !== ['handle' => []]) {
                return [false, $problemFields];
            }
            // Parse & Set Field Layout if Provided
            if (isset($jsonGlobalSet->fieldLayout)) {
                $requiredFields = [];
                if (isset($jsonGlobalSet->requiredFields) && is_array($jsonGlobalSet->requiredFields)) {
                    foreach ($jsonGlobalSet->requiredFields as $requirdField) {
                        array_push($requiredFields, $this->getFieldId($requirdField));
                    }
                }
                $fieldLayout = $this->assembleLayout($jsonGlobalSet->fieldLayout, $requiredFields);
                $fieldLayout->type = ElementType::GlobalSet;
                $globalSet->setFieldLayout($fieldLayout);
            }
        }

        // Save Gobal Set to DB
        if (craft()->globals->saveSet($globalSet)) {
            return [true, false, $globalSet];
        } else {
            return [false, $globalSet->getErrors(), $globalSet];
        }
    }

    /**
     * addCategoryGroup.
     *
     * @param ArrayObject $jsonCategoryGroup
     *
     * @return bool [success]
     */
    public function addCategoryGroup($jsonCategoryGroup, $categoryGroupID = false)
    {
        if ($categoryGroupID) {
            $categoryGroup = $this->getCategoryGroupById($categoryGroupID);
        } else {
            $categoryGroup = new CategoryGroupModel();
        }

        $categoryGroup->name = $jsonCategoryGroup->name;

        // Set handle if it was provided
        if (isset($jsonCategoryGroup->handle)) {
            $categoryGroup->handle = $jsonCategoryGroup->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $categoryGroup->handle = $this->constructHandle($jsonCategoryGroup->name);
        }

        if (isset($jsonCategoryGroup->hasUrls)) {
            $categoryGroup->hasUrls = $jsonCategoryGroup->hasUrls;
        }
        if (isset($jsonCategoryGroup->template)) {
            $categoryGroup->template = $jsonCategoryGroup->template;
        }
        if (isset($jsonCategoryGroup->maxLevels)) {
            $categoryGroup->maxLevels = $jsonCategoryGroup->maxLevels;
        }

        if (isset($jsonCategoryGroup->locales)) {
            $categoryGroupLocale = [];
            $siteLocales = craft()->i18n->getSiteLocales();
            foreach ($jsonCategoryGroup->locales as $locale => $localeAttributes) {
                if (!in_array($locale, $siteLocales)) {
                    return [false, ["locale" => ['Locale "' . $locale . '" not avaialble.']], false];
                }
                $categoryGroupLocale[$locale] = new CategoryGroupLocaleModel(array(
                    'locale'          => $locale,
                    'urlFormat'       => $localeAttributes->urlFormat,
                    'nestedUrlFormat' => $localeAttributes->nestedUrlFormat,
                ));
            }
            $categoryGroup->setLocales($categoryGroupLocale);
        } else {
            return [false, ["locale" => ["Locales are missing."]], false];
        }

        // Save Category Group to DB
        if (craft()->categories->saveGroup($categoryGroup)) {
            return [true, false, $categoryGroup];
        } else {
            return [false, $categoryGroup->getErrors(), false];
        }
    }

    public function setCategoryGroupFieldLayout($jsonCategoryGroup)
    {
        // Set handle if it was provided
        if (isset($jsonCategoryGroup->handle)) {
            $categoryGroup = craft()->categories->getGroupByHandle($jsonCategoryGroup->handle);
        } else {
            $categoryGroup = craft()->categories->getGroupByHandle($this->constructHandle($jsonCategoryGroup->name));
        }

        $problemFields = $this->checkFieldLayout($jsonCategoryGroup->fieldLayout);
        if ($problemFields !== ['handle' => []]) {
            return [false, $problemFields, false];
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonCategoryGroup->fieldLayout)) {
            $requiredFields = [];
            if (isset($jsonCategoryGroup->requiredFields) && is_array($jsonCategoryGroup->requiredFields)) {
                foreach ($jsonCategoryGroup->requiredFields as $requirdField) {
                    array_push($requiredFields, $this->getFieldId($requirdField));
                }
            }
            $fieldLayout = $this->assembleLayout($jsonCategoryGroup->fieldLayout, $requiredFields);
            $fieldLayout->type = ElementType::Category;
            $categoryGroup->setFieldLayout($fieldLayout);
        }

        // Save Category Group to DB
        if (craft()->categories->saveGroup($categoryGroup)) {
            return [true, false, $categoryGroup];
        } else {
            return [false, $categoryGroup->getErrors(), false];
        }
    }

    public function addRoute($jsonRoute, $routeID = null)
    {
        if ($routeID) {
            if (!$this->getRouteById($routeID)) {
                craft()->db->createCommand()->insert('routes', array(
                    'id' => $routeID,
                ));
            }
        }

        $routeRecord = craft()->routes->saveRoute($jsonRoute->urlParts, $jsonRoute->template, $routeID, $jsonRoute->locale);

        if ($routeRecord->getErrors()) {
            return [false, $routeRecord->getErrors(), false];
        } else {
            return [true, false, $routeRecord];
        }
    }

    public function addTagGroup($jsonTag, $tagGroupID = null)
    {
        // Construct handle if one wasn't provided
        if (!isset($jsonTag->handle)) {
            $jsonTag->handle = $this->constructHandle($jsonTag->name);
        }

        if ($tagGroupID) {
            if (!craft()->tags->getTagGroupById($tagGroupID)) {
                craft()->db->createCommand()->insert('taggroups', array(
                    'id' => $tagGroupID,
                    'name' => $jsonTag->name,
                    'handle' => $jsonTag->handle,
                ));
            }
        }

        if ($tagGroupID) {
            $tagGroup = craft()->tags->getTagGroupById($tagGroupID);
            if (!$tagGroup) {
                $tagGroup = new TagGroupModel();
                $tagGroup->id = $tagGroupID;
            }
        } else {
            $tagGroup = new TagGroupModel();
        }

        $tagGroup->name = $jsonTag->name;
        $tagGroup->handle = $jsonTag->handle;

        // Save Tag Group to DB
        if (craft()->tags->saveTagGroup($tagGroup)) {
            return [true, false, $tagGroup];
        } else {
            return [false, $tagGroup->getErrors(), false];
        }
    }

    public function addTagFieldLayout($jsonTag)
    {
        // Construct handle if one wasn't provided
        if (!isset($jsonTag->handle)) {
            $jsonTag->handle = $this->constructHandle($jsonTag->name);
        }

        $tagGroup = craft()->tags->getTagGroupByHandle($jsonTag->handle);

        $problemFields = $this->checkFieldLayout($jsonTag->fieldLayout);
        if ($problemFields !== ['handle' => []]) {
            return [false, $problemFields, false];
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonTag->fieldLayout)) {
            $requiredFields = [];
            if (isset($jsonTag->requiredFields) && is_array($jsonTag->requiredFields)) {
                foreach ($jsonTag->requiredFields as $requirdField) {
                    array_push($requiredFields, $this->getFieldId($requirdField));
                }
            }
            $fieldLayout = $this->assembleLayout($jsonTag->fieldLayout, $requiredFields);
            $fieldLayout->type = ElementType::Tag;
            $tagGroup->setFieldLayout($fieldLayout);
        }

        // Save Tag Group to DB
        if (craft()->tags->saveTagGroup($tagGroup)) {
            return [true, false, $tagGroup];
        } else {
            return [false, $tagGroup->getErrors(), false];
        }
    }

    /**
     * usersExport.
     *
     * @return array []
     */
    public function usersExport($post, $includeID = false)
    {
        if (isset($post['userSelection'])) {
            $users = [];
            $allUsers = $this->getAllUsers();

            $this->sections = craft()->sections->getAllSections();

            foreach ($allUsers as $user) {
                if (in_array($user->id, $post['userSelection'])) {
                    $userJson = [
                        'enabled' => $user->enabled,
                        'archived' => $user->archived,
                        'locale' => $user->locale,
                        'localeEnabled' => $user->localeEnabled,
                        'slug' => $user->slug,
                        'uri' => $user->uri,
                        // 'dateCreated' => $user->dateCreated,
                        // 'dateUpdated' => $user->dateUpdated,
                        'root' => $user->root,
                        'lft' => $user->lft,
                        'rgt' => $user->rgt,
                        'level' => $user->level,
                        'searchScore' => $user->searchScore,
                        'username' => $user->username,
                        // 'photo' => $user->photo,
                        'firstName' => $user->firstName,
                        'lastName' => $user->lastName,
                        'email' => $user->email,
                        // 'password' => $user->password,
                        'preferredLocale' => $user->preferredLocale,
                        'weekStartDay' => $user->weekStartDay,
                        'admin' => $user->admin,
                        'client' => $user->client,
                        'locked' => $user->locked,
                        'suspended' => $user->suspended,
                        'pending' => $user->pending,
                        // 'lastLoginDate' => $user->lastLoginDate,
                        // 'invalidLoginCount' => $user->invalidLoginCount,
                        // 'lockoutDate' => $user->lockoutDate,
                        // 'passwordResetRequired' => $user->passwordResetRequired,
                        // 'passwordResetRequired' => $user->passwordResetRequired,
                        // 'lastPasswordChangeDate' => $user->lastPasswordChangeDate,
                        // 'unverifiedEmail' => $user->unverifiedEmail,
                        // 'newPassword' => $user->newPassword,
                        // 'currentPassword' => $user->currentPassword,
                        // 'verificationCodeIssuedDate' => $user->verificationCodeIssuedDate,
                        'groups' => [],
                    ];
                    if ($includeID) {
                        $userJson = array_merge(['id' => $user->id], $userJson);
                    }
                    if (craft()->getEdition() == Craft::Pro) {
                        $userPermissions = craft()->userPermissions->getPermissionsByUserId($user->id);
                        $userPermissions = $this->stripGroupPermissions($userPermissions, $user->getGroups());
                        foreach ($user->getGroups() as $userGroup) {
                            array_push($userJson['groups'], $userGroup->handle);
                        }
                        $userJson['permissions'] = $this->deconstructPermissions($userPermissions);
                    }
                    array_push($users, $userJson);
                }
            }

            return $this->arrayStripNullEmpty($users);
        }
    }

    /**
     * userGroupsExport.
     *
     * @return array []
     */
    public function userGroupsExport($post, $includeID = false)
    {
        if (isset($post['groupSelection'])) {
            $userGroups = [];
            $allUsersGroups = craft()->userGroups->allGroups;

            $this->sections = craft()->sections->getAllSections();

            $userGroups = [[], []];

            foreach ($allUsersGroups as $userGroup) {
                if (in_array($userGroup->id, $post['groupSelection'])) {
                    $userGroupPermissions = craft()->userPermissions->getPermissionsByGroupId($userGroup->id);
                    $userGroupJson = [
                        'name' => $userGroup->name,
                        'handle' => $userGroup->handle,
                    ];
                    $userGroupPermissionsJson = [
                        'handle' => $userGroup->handle,
                        'permissions' => $this->deconstructPermissions($userGroupPermissions),
                    ];
                    if ($includeID) {
                        $userGroupJson = array_merge(['id' => $userGroup->id], $userGroupJson);
                    }
                    array_push($userGroups[0], $userGroupJson);
                    array_push($userGroups[1], $userGroupPermissionsJson);
                }
            }

            return $userGroups;
        }
    }

    /**
     * deconstructPermissions.
     *
     * @return array []
     */
    private function deconstructPermissions($userPermissions)
    {
        $userPermissions = $this->fixPermissions($userPermissions);

        $global_perms = [
            'editGlobalSet',
        ];
        $assetsource_perms = [
            'createSubfoldersInAssetSource',
            'removeFromAssetSource',
            'uploadToAssetSource',
            'viewAssetSource',
        ];
        $section_perms = [
            'createEntries',
            'deleteEntries',
            'deletePeerEntries',
            'deletePeerEntryDrafts',
            'editEntries',
            'editPeerEntries',
            'editPeerEntryDrafts',
            'publishEntries',
            'publishPeerEntries',
            'publishPeerEntryDrafts',
        ];
        $category_perms = [
            'editCategories',
        ];
        $general_perms = [
            'editLocale',
        ];
        $newUserPermissions = [
            'general' => [],
            'globals' => [],
            'assetSources' => [],
            'sections' => [],
            'categories' => [],
            'unknown' => [],
        ];
        foreach ($userPermissions as $k => $userPermission) {
            $splitPermission = explode(':', $userPermission);
            if (sizeof($splitPermission) > 1) {
                if (in_array($splitPermission[0], $global_perms)) {
                    $handle = craft()->globals->getSetById($splitPermission[1])->handle;
                    if (!isset($newUserPermissions['globals'][$handle])) {
                        $newUserPermissions['globals'][$handle] = [];
                    }
                    array_push($newUserPermissions['globals'][$handle], $splitPermission[0]);
                } elseif (in_array($splitPermission[0], $assetsource_perms)) {
                    $handle = craft()->assetSources->getSourceById($splitPermission[1])->handle;
                    if (!isset($newUserPermissions['assetSources'][$handle])) {
                        $newUserPermissions['assetSources'][$handle] = [];
                    }
                    array_push($newUserPermissions['assetSources'][$handle], $splitPermission[0]);
                } elseif (in_array($splitPermission[0], $section_perms)) {
                    $handle = $this->getSectionHandle($splitPermission[1]);
                    if (!isset($newUserPermissions['sections'][$handle])) {
                        $newUserPermissions['sections'][$handle] = [];
                    }
                    array_push($newUserPermissions['sections'][$handle], $splitPermission[0]);
                } elseif (in_array($splitPermission[0], $category_perms)) {
                    $handle = craft()->categories->getGroupById($splitPermission[1])->handle;
                    if (!isset($newUserPermissions['categories'][$handle])) {
                        $newUserPermissions['categories'][$handle] = [];
                    }
                    array_push($newUserPermissions['categories'][$handle], $splitPermission[0]);
                } elseif (in_array($splitPermission[0], $general_perms)) {
                    array_push($newUserPermissions['general'], $userPermission);
                } else {
                    array_push($newUserPermissions['unknown'], $userPermission);
                }
            } else {
                array_push($newUserPermissions['general'], $userPermission);
            }
        }

        return $this->arrayStripNullEmpty($newUserPermissions);
    }

    /**
     * constructPermissions.
     *
     * @return array []
     */
    private function constructPermissions($userPermissions)
    {
        $newUserPermissions = [];
        foreach ($userPermissions as $k => $permissionGroup) {
            if ($k == 'globals') {
                foreach ($permissionGroup as $globalHandle => $permissions) {
                    $globalId = craft()->globals->getSetByHandle($globalHandle)->id;
                    foreach ($permissions as $permission) {
                        array_push($newUserPermissions, $permission.':'.$globalId);
                    }
                }
            } elseif ($k == 'assetSources') {
                foreach ($permissionGroup as $assetSourceHandle => $permissions) {
                    $assetSourceId = $this->getSourceByHandle($assetSourceHandle)->id;
                    foreach ($permissions as $permission) {
                        array_push($newUserPermissions, $permission.':'.$assetSourceId);
                    }
                }
            } elseif ($k == 'sections') {
                foreach ($permissionGroup as $sectionHandle => $permissions) {
                    $sectionId = craft()->sections->getSectionByHandle($sectionHandle)->id;
                    foreach ($permissions as $permission) {
                        array_push($newUserPermissions, $permission.':'.$sectionId);
                    }
                }
            } elseif ($k == 'categories') {
                foreach ($permissionGroup as $categoryHandle => $permissions) {
                    $sectionId = craft()->categories->getGroupByHandle($categoryHandle)->id;
                    foreach ($permissions as $permission) {
                        array_push($newUserPermissions, $permission.':'.$sectionId);
                    }
                }
            } elseif ($k == 'general') {
                foreach ($permissionGroup as $permission) {
                    array_push($newUserPermissions, $permission);
                }
            }
            // There is also a "unknown" group but those will usually contain permissions TheArchitect did not know how to process. If you end up with these please report them
        }

        return $newUserPermissions;
    }

    /**
     * stripGroupPermissions.
     *
     * @return array []
     */
    private function stripGroupPermissions($userPermissions, $userGroups)
    {
        foreach ($userGroups as $group) {
            $userGroupPermissions = craft()->userPermissions->getPermissionsByGroupId($group->id);
            foreach ($userPermissions as &$permission) {
                if (in_array($permission, $userGroupPermissions)) {
                    $permission = null;
                }
            }
        }

        return $userPermissions;
    }

    /**
     * arrayStripNullEmpty.
     *
     * @return array []
     */
    public function arrayStripNullEmpty($ary)
    {
        $newAry = [];
        foreach ($ary as $key => $value) {
            if ($value != null && $value != []) {
                if (is_array($value)) {
                    $newAry[$key] = $this->arrayStripNullEmpty($value);
                } else {
                    $newAry[$key] = $value;
                }
            }
        }

        return $newAry;
    }

    /**
     * addUser.
     *
     * @param ArrayObject $jsonUser
     *
     * @return bool [success]
     */
    public function addUser($jsonUser, $migration = false)
    {
        if ($migration) {
            list($user, $newUser) = $this->getUserById($jsonUser->id);
        } else {
            $user = new UserModel();
            $newUser = true;
        }

        foreach ($jsonUser as $key => $value) {
            if ($key != 'permissions' && $key != 'groups') {
                $user->setAttribute($key, $value);
            }
        }

        $groupIds = [];
        if (isset($jsonUser->groups)) {
            foreach ($jsonUser->groups as $groupHandle) {
                array_push($groupIds, craft()->userGroups->getGroupByHandle($groupHandle)->id);
            }
        }

        if (craft()->users->saveUser($user)) {
            if ($newUser) {
                craft()->users->sendActivationEmail($user);
            }
            if (craft()->getEdition() == 2) {
                if (craft()->userGroups->assignUserToGroups($user->id, $groupIds)) {
                    if (!isset($jsonUser->permissions) || craft()->userPermissions->saveUserPermissions($user->id, $this->constructPermissions($jsonUser->permissions))) {
                        return [true, null];
                    } else {
                        return [false, ['Permissions' => ['Failed assigning user permissions.']]];
                    }
                } else {
                    return [false, ['User Group' => ['Failed to add user to groups.']]];
                }
            } else {
                return [true, null];
            }
        } else {
            return [false, $user->getErrors()];
        }
    }

    /**
     * addUserGroup.
     *
     * @param ArrayObject $jsonUser
     *
     * @return bool [success]
     */
    public function addUserGroup($jsonUserGroup, $userGroupID = false)
    {
        if ($userGroupID) {
            $userGroup = $this->getUserGroupById($userGroupID);
        } else {
            $userGroup = new UserGroupModel();
        }

        $userGroup->name = $jsonUserGroup->name;

        // Set handle if it was provided
        if (isset($jsonUserGroup->handle)) {
            $userGroup->handle = $jsonUserGroup->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $userGroup->handle = $this->constructHandle($jsonUserGroup->name);
        }

        // Weirdly enough group handles weren't required to be unique in craft. TODO: Has been fixed will leave for a few releases though just to be sure.
        if (craft()->userGroups->getGroupByHandle($userGroup->handle) === null) {
            // Save Asset Source to DB
            if (craft()->userGroups->saveGroup($userGroup)) {
                return [true, null];
            } else {
                return [false, $userGroup->getErrors()];
            }
        } else {
            return [false, ['handle' => ['Handle "'.$userGroup->handle.'" has already been taken.']]];
        }
    }

    /**
     * addUserGroup.
     *
     * @param ArrayObject $jsonUser
     *
     * @return bool [success]
     */
    public function addUserGroupPermissions($jsonUserGroupPermissions)
    {
        if (craft()->userPermissions->saveGroupPermissions(craft()->userGroups->getGroupByHandle($jsonUserGroupPermissions->handle)->id, $this->constructPermissions($jsonUserGroupPermissions->permissions))) {
            return [true, null];
        } else {
            return [false, null];
        }
    }

    private function fixPermissions($permissions)
    {
        $newPermissions = [];
        $allPermissions = craft()->userPermissions->getAllPermissions();
        if (count($permissions) > 0) {
            foreach ($permissions as $permission) {
                array_push($newPermissions, $this->checkKeyIsInArray($permission, $allPermissions));
            }
        }

        return $newPermissions;
    }

    private function checkKeyIsInArray($dataItemName, $array)
    {
        foreach ($array as $key => $value) {
            if (strcasecmp($key, $dataItemName) == 0) {
                return $key;
            }

            if (is_array($value) && $this->checkKeyIsInArray($dataItemName, $value)) {
                return $this->checkKeyIsInArray($dataItemName, $value);
            }
        }

        return false;
    }

    /**
     * getAllUsers.
     *
     * @param ArrayObject $jsonUser
     *
     * @return array [UserModel]
     */
    public function getAllUsers()
    {
        $userRecords = UserRecord::model()->findAll();
        $users = [];

        if ($userRecords) {
            foreach ($userRecords as $userRecord) {
                array_push($users, UserModel::populateModel($userRecord));
            }

            return $users;
        }

        return;
    }

    /**
     * getGroupId.
     *
     * @param string $name [name to find ID for]
     *
     * @return int
     */
    public function getGroupId($name)
    {
        $firstId = $this->groups[0]['id'];
        foreach ($this->groups as $group) {
            if ($group->attributes['name'] == $name) {
                return $group->attributes['id'];
            }
        }

        return $firstId;
    }

    /**
     * getFieldId.
     *
     * @param string $name [name to find ID for]
     *
     * @return int
     */
    public function getFieldId($name)
    {
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
     * getSectionId.
     *
     * @param string $name [name to find ID for]
     *
     * @return int
     */
    public function getSectionId($name)
    {
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
     * getSectionHandle.
     *
     * @param int $id [ID to find handle for]
     *
     * @return string
     */
    public function getSectionHandle($id)
    {
        foreach ($this->sections as $section) {
            // Return ID if handle matches the search
            if ($section->attributes['id'] == $id) {
                return $section->attributes['handle'];
            }
        }

        return false;
    }

    /**
     * constructHandle.
     *
     * @param string $str [input string]
     *
     * @return string [the constructed handle]
     */
    public function constructHandle($string)
    {
        $string = strtolower($string);

        $words = explode(' ', $string);

        for ($i = 1; $i < count($words); ++$i) {
            $words[$i] = ucfirst($words[$i]);
        }

        return implode('', $words);
    }

    // Private Functions
    // =========================================================================

    /**
     * assembleLayout.
     *
     * @param array $fieldLayout
     * @param array $requiredFields
     *
     * @return FieldLayoutModel
     */
    private function assembleLayout($fieldLayout, $requiredFields = array())
    {
        $fieldLayoutPost = array();

        foreach ($fieldLayout as $tab => $fields) {
            $fieldLayoutPost[$tab] = array();
            foreach ($fields as $field) {
                $fieldLayoutPost[$tab][] = $this->getFieldId($field);
            }
        }

        return craft()->fields->assembleLayout($fieldLayoutPost, $requiredFields);
    }

    private function getSourceByHandle($handle)
    {
        $assetSources = craft()->assetSources->getAllSources();
        foreach ($assetSources as $key => $assetSource) {
            if ($assetSource->handle === $handle) {
                return $assetSource;
            }
        }
        foreach ($this->addedSources as $assetSource) {
            if ($assetSource->handle == $handle) {
                return $assetSource;
            }
        }
    }

    private function getTagGroupByHandle($handle)
    {
        $tagGroups = craft()->tags->getAllTagGroups();
        foreach ($tagGroups as $key => $tagGroup) {
            if ($tagGroup->handle === $handle) {
                return $tagGroup;
            }
        }
    }

    private function getUserGroupByHandle($handle)
    {
        $userGroups = craft()->userGroups->getAllGroups();
        foreach ($userGroups as $key => $userGroup) {
            if ($userGroup->handle === $handle) {
                return $userGroup;
            }
        }
    }

    private function getTransformById($id)
    {
        $transforms = craft()->assetTransforms->getAllTransforms();
        foreach ($transforms as $key => $transform) {
            if ($transform->id == $id) {
                return $transform;
            }
        }
    }

    private function stripHandleSpaces(&$object)
    {
        foreach ($object as $key => &$value) {
            if ($key === 'handle') {
                $value = preg_replace("/\s/", '', $value);
            }
            if (gettype($value) == 'array' || gettype($value) == 'object') {
                $value = $this->stripHandleSpaces($value);
            }
        }

        return $object;
    }

    private function hasHandle($handle, $fields)
    {
        foreach ($fields as $field) {
            if ($field->handle == $handle) {
                return true;
            }
        }

        return false;
    }

    private function replaceSourcesHandles(&$object)
    {
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
                            if ($field !== null) {
                                $fieldHandle = $field->id;
                            }
                        }
                    }

                    if (isset($blockType->requiredFields) && is_array($blockType->requiredFields)) {
                        foreach ($blockType->requiredFields as &$fieldHandle) {
                            $field = craft()->fields->getFieldByHandle($fieldHandle);
                            if ($field !== null) {
                                $fieldHandle = $field->id;
                            }
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
                        $v = 'section:'.$section->id;
                    }
                }
            }
        }
        if ($object->type == 'Assets') {
            if (isset($object->typesettings->sources)) {
                if (gettype($object->typesettings->sources) == 'array') {
                    foreach ($object->typesettings->sources as $k => &$v) {
                        $assetSource = $this->getSourceByHandle($v);
                        if ($assetSource) {
                            $v = 'folder:'.$assetSource->id;
                        }
                    }
                }
            }
            if (isset($object->typesettings->defaultUploadLocationSource)) {
                $assetSource = $this->getSourceByHandle($object->typesettings->defaultUploadLocationSource);
                if ($assetSource) {
                    $object->typesettings->defaultUploadLocationSource = $assetSource->id;
                }
            }
            if (isset($object->typesettings->singleUploadLocationSource)) {
                $assetSource = $this->getSourceByHandle($object->typesettings->singleUploadLocationSource);
                if ($assetSource) {
                    $object->typesettings->singleUploadLocationSource = $assetSource->id;
                }
            }
        }
        if ($object->type == 'Categories') {
            if (isset($object->typesettings->source)) {
                $category = craft()->categories->getGroupByHandle($object->typesettings->source);
                if ($category) {
                    $object->typesettings->source = 'group:'.$category->id;
                }
            }
        }
        if ($object->type == 'Tags') {
            if (isset($object->typesettings->source)) {
                $category = $this->getTagGroupByHandle($object->typesettings->source);
                if ($category) {
                    $object->typesettings->source = 'taggroup:'.$category->id;
                }
            }
        }
        if ($object->type == 'Users') {
            if (isset($object->typesettings->sources) && is_array($object->typesettings->sources)) {
                foreach ($object->typesettings->sources as $k => &$v) {
                    $userGroup = $this->getUserGroupByHandle($v);
                    if ($userGroup) {
                        $v = 'group:'.$userGroup->id;
                    }
                }
            }
        }
        if ($object->type == 'FruitLinkIt') {
            if (isset($object->typesettings->entrySources)) {
                foreach ($object->typesettings->entrySources as $k => &$v) {
                    $section = craft()->sections->getSectionByHandle($v);
                    if ($section) {
                        $v = 'section:'.$section->id;
                    }
                }
            }
        }
    }

    private function sectionExport($post, $includeID = false)
    {
        $sections = [];
        $entryTypes = [];
        if (isset($post['sectionSelection'])) {
            foreach ($post['sectionSelection'] as $id) {
                $section = craft()->sections->getSectionById($id);
                if ($section === null) {
                    continue;
                }

                $urlFormat = $section->getUrlFormat();
                $locales = $section->getLocales();
                $primaryLocale = craft()->i18n->getPrimarySiteLocaleId();

                $newSection = [
                    'name' => $section->attributes['name'],
                    'handle' => $section->attributes['handle'],
                    'type' => $section->attributes['type'],
                    'enableVersioning' => $section->attributes['enableVersioning'],
                    'typesettings' => [
                        'hasUrls' => $section->attributes['hasUrls'],
                        'template' => $section->attributes['template'],
                        'maxLevels' => $section->attributes['maxLevels'],
                    ],
                ];
                if ($section->attributes['hasUrls'] === false || $section->attributes['hasUrls'] === 0 || $section->attributes['hasUrls'] === '0') {
                    $newSection['typesettings']['locales'] = [];
                    foreach ($locales as $locale => $attributes) {
                        $newSection['typesettings']['locales'][$locale] = $attributes['enabledByDefault'];
                    }
                } else {
                    if (isset($locales[$primaryLocale])) {
                        $newSection['typesettings']['urlFormat'] = $locales[$primaryLocale]['urlFormat'];
                        $newSection['typesettings']['nestedUrlFormat'] = $locales[$primaryLocale]['nestedUrlFormat'];
                    }
                    foreach ($locales as $locale => $attributes) {
                        if ($primaryLocale != $locale) {
                            $newSection['typesettings'][$locale] = [
                                'urlFormat' => $locales[$locale]['urlFormat'],
                                'nestedUrlFormat' => $locales[$locale]['nestedUrlFormat'],
                            ];
                        }
                    }
                }
                if ($includeID) {
                    $newSection = array_merge(['id' => $section->id], $newSection);
                }
                if ($newSection['typesettings']['maxLevels'] === null) {
                    unset($newSection['typesettings']['maxLevels']);
                }
                if (isset($newSection['typesettings']['nestedUrlFormat']) && $newSection['typesettings']['nestedUrlFormat'] === null) {
                    unset($newSection['typesettings']['nestedUrlFormat']);
                }
                if ($newSection['type'] === 'single') {
                    unset($newSection['typesettings']['hasUrls']);
                }
                // Add New Section into the Sections Array
                array_push($sections, $newSection);

                $sectionEntryTypes = $section->getEntryTypes();
                foreach ($sectionEntryTypes as $entryType) {
                    $newEntryType = [
                        'sectionHandle' => $section->attributes['handle'],
                        'hasTitleField' => $entryType->attributes['hasTitleField'],
                        'titleLabel' => $entryType->attributes['titleLabel'],
                        'titleFormat' => $entryType->attributes['titleFormat'],
                        'name' => $entryType->attributes['name'],
                        'handle' => $entryType->attributes['handle'],
                        'titleLabel' => $entryType->attributes['titleLabel'],
                        'fieldLayout' => [],
                    ];
                    if ($includeID) {
                        $newEntryType = array_merge(['id' => $entryType->id, 'sectionId' => $section->id], $newEntryType);
                    }
                    if ($newEntryType['titleFormat'] === null) {
                        unset($newEntryType['titleFormat']);
                    }

                    $fieldLayout = $entryType->getFieldLayout();

                    $fieldLayoutReasons = $this->getConditionalsByFieldLayoutId($fieldLayout->id);
                    if ($fieldLayoutReasons) {
                        $newEntryType['reasons'] = $this->setReasonsLabels($fieldLayoutReasons);
                    }

                    $this->setRelabels($newEntryType, $fieldLayout);

                    foreach ($fieldLayout->getTabs() as $tab) {
                        $newEntryType['fieldLayout'][$tab->name] = [];
                        foreach ($tab->getFields() as $tabField) {
                            array_push($newEntryType['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                        }
                    }
                    // Add New EntryType into the EntryTypes Array
                    array_push($entryTypes, $newEntryType);
                }
            }
        }

        return [$sections, $entryTypes];
    }

    private function fieldExport($post, $includeID = false)
    {
        $groups = [];
        $fields = [];
        $fieldsLast = [];
        if (isset($post['fieldSelection'])) {
            foreach ($post['fieldSelection'] as $id) {
                $field = craft()->fields->getFieldById($id);
                if ($field === null) {
                    continue;
                }

                // If Field Group is not defined in Groups Array add it
                if (!in_array($field->group->name, $groups)) {
                    array_push($groups, $field->group->name);
                }

                $newField = [
                    'group' => $field->group->name,
                    'name' => $field->name,
                    'handle' => $field->handle,
                    'instructions' => $field->instructions,
                    'required' => $field->required,
                    'type' => $field->type,
                    'typesettings' => $field->settings,
                ];
                if ($includeID) {
                    $newField = array_merge(['id' => $field->id], $newField);
                }

                $this->parseFieldSources($field, $newField);

                if ($field->type == 'PositionSelect') {
                    $options = [];
                    foreach ($newField['typesettings']['options'] as $value) {
                        $options[$value] = true;
                    }
                    $newField['typesettings']['options'] = $options;
                }

                if ($field->type == 'Neo') {
                    $this->setNeoField($newField, $id, $includeID);
                }

                if ($field->type == 'Matrix') {
                    $this->setMatrixField($newField, $id, $includeID);
                }

                if ($field->type == 'SuperTable') {
                    $this->setSuperTableField($newField, $id, $includeID);
                }

                // If Field Type is Neo store it for pushing last. This is needed because Neo fields reference other fields.
                if ($field->type == 'Neo') {
                    array_push($fieldsLast, $newField);
                }
                // Add New Field into the Fields Array
                else {
                    array_push($fields, $newField);
                }
            }
        }
        // Push fields that need to be at the end of fields array
        foreach ($fieldsLast as $newField) {
            array_push($fields, $newField);
        }

        return [$groups, $fields];
    }

    private function setMatrixField(&$newField, $fieldId, $includeID = false)
    {
        $blockTypes = craft()->matrix->getBlockTypesByFieldId($fieldId);
        $blockCount = 1;
        foreach ($blockTypes as $blockType) {
            if ($includeID) {
                $blockId = $blockType->id;
            } else {
                $blockId = 'new'.$blockCount;
            }
            $newField['typesettings']['blockTypes'][$blockId] = [
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'fields' => [],
            ];
            $fieldCount = 1;
            foreach ($blockType->fields as $blockField) {
                if ($includeID) {
                    $fieldId = $blockField->id;
                } else {
                    $fieldId = 'new'.$fieldCount;
                }
                $newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId] = [
                    'name' => $blockField->name,
                    'handle' => $blockField->handle,
                    'instructions' => $blockField->instructions,
                    'required' => $blockField->required,
                    'type' => $blockField->type,
                    'typesettings' => $blockField->settings,
                ];
                if ($blockField->type == 'Neo') {
                    $this->setNeoField($newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId], $blockField->id, $includeID);
                }
                if ($blockField->type == 'SuperTable') {
                    $this->setSuperTableField($newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId], $blockField->id);
                }
                $this->parseFieldSources($blockField, $newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId]);
                ++$fieldCount;
            }
            ++$blockCount;
        }
    }

    private function setNeoField(&$newField, $fieldId, $includeID = false)
    {
        $neoGroups = craft()->neo->getGroupsByFieldId($fieldId);
        $newField['typesettings']['groups']['name'] = [];
        $newField['typesettings']['groups']['sortOrder'] = [];
        foreach ($neoGroups as $group) {
            array_push($newField['typesettings']['groups']['name'], $group->name);
            array_push($newField['typesettings']['groups']['sortOrder'], $group->sortOrder);
        }

        $blockCount = 0;

        $blockTypes = craft()->neo->getBlockTypesByFieldId($fieldId);
        foreach ($blockTypes as $blockType) {
            if ($includeID) {
                $blockId = $blockType->id;
            } else {
                $blockId = 'new'.$blockCount;
            }
            $newField['typesettings']['blockTypes'][$blockId] = [
                'sortOrder' => $blockType->sortOrder,
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'maxBlocks' => $blockType->maxBlocks,
                'childBlocks' => $blockType->childBlocks,
                'maxChildBlocks' => (isset($blockType->maxChildBlocks)) ? $blockType->maxChildBlocks : '',
                'topLevel' => $blockType->topLevel,
                'fieldLayout' => [],
            ];

            $fieldLayout = $blockType->getFieldLayout();

            $fieldLayoutReasons = $this->getConditionalsByFieldLayoutId($fieldLayout->id);
            if ($fieldLayoutReasons) {
                $newField['typesettings']['blockTypes'][$blockId]['reasons'] = $this->setReasonsLabels($fieldLayoutReasons);
            }

            $this->setRelabels($newField['typesettings']['blockTypes'][$blockId], $fieldLayout);

            foreach ($fieldLayout->getTabs() as $tab) {
                $newField['typesettings']['blockTypes'][$blockId]['fieldLayout'][$tab->name] = [];
                foreach ($tab->getFields() as $tabField) {
                    array_push($newField['typesettings']['blockTypes'][$blockId]['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                }
            }
            ++$blockCount;
        }
    }

    private function setRelabels(&$object, $fieldLayout)
    {
        if (craft()->plugins->getPlugin('relabel')) {
            $relabels = craft()->relabel->getLabels($fieldLayout->id);
            if ($relabels) {
                $object['relabel'] = [];
                foreach ($relabels as $relabel) {
                    $object['relabel'][] = [
                        'field' => craft()->fields->getFieldById($relabel->fieldId)->handle,
                        'name' => $relabel->name,
                        'instructions' => $relabel->instructions,
                    ];
                }
            }
        }
    }

    private function setSuperTableField(&$newField, $fieldId, $includeID = false)
    {
        $blockTypes = craft()->superTable->getBlockTypesByFieldId($fieldId);
        $sTFieldCount = 1;
        foreach ($blockTypes as $blockType) {
            if ($includeID) {
                $blockId = $blockType->id;
            } else {
                $blockId = 'new';
            }
            foreach ($blockType->getFields() as $sTField) {
                if ($includeID) {
                    $fieldId = $sTField->id;
                } else {
                    $fieldId = 'new'.$sTFieldCount;
                }
                $columns = array_values($newField['typesettings']['columns']);
                $newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId] = [
                    'name' => $sTField->name,
                    'handle' => $sTField->handle,
                    'instructions' => $sTField->instructions,
                    'required' => $sTField->required,
                    'type' => $sTField->type,
                    'width' => $columns[$sTFieldCount - 1]['width'],
                    'typesettings' => $sTField->settings,
                ];
                if ($sTField->type == 'Matrix') {
                    $this->setMatrixField($newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId], $sTField->id);
                }
                if ($sTField->type == 'Neo') {
                    $this->setNeoField($newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId], $sTField->id);
                }
                $this->parseFieldSources($sTField, $newField['typesettings']['blockTypes'][$blockId]['fields'][$fieldId], $sTField->id);
                ++$sTFieldCount;
            }
        }
        unset($newField['typesettings']['columns']);
    }

    private function getConditionalsByFieldLayoutId($fieldLayoutId)
    {
        if (craft()->plugins->getPlugin('reasons')) {
            $conditionalsRecord = Reasons_ConditionalsRecord::model()->findByAttributes(array('fieldLayoutId' => $fieldLayoutId));
            if ($conditionalsRecord) {
                $conditionalsModel = Reasons_ConditionalsModel::populateModel($conditionalsRecord);
                if ($conditionalsModel->conditionals && $conditionalsModel->conditionals != '') {
                    return $conditionalsModel->conditionals;
                }
            }
        }
    }

    private function setReasonsLabels($argReasons)
    {
        $newReasons = [];
        foreach ($argReasons as $fieldId => $reasons) {
            $newReasons[craft()->fields->getFieldById($fieldId)->handle] = $reasons;
            foreach ($newReasons[craft()->fields->getFieldById($fieldId)->handle] as &$reasonsOr) {
                foreach ($reasonsOr as &$reason) {
                    $reason['fieldId'] = craft()->fields->getFieldById($reason['fieldId'])->handle;
                }
            }
        }

        return $newReasons;
    }

    private function parseFieldSources(&$field, &$newField)
    {
        if ($field->type == 'Assets') {
            if ($newField['typesettings']['sources'] !== '*') {
                foreach ($newField['typesettings']['sources'] as $key => $value) {
                    if (substr($value, 0, 7) == 'folder:') {
                        $source = craft()->assetSources->getSourceById(intval(substr($value, 7)));
                        if ($source) {
                            $newField['typesettings']['sources'][$key] = $source->handle;
                        }
                    }
                }
            }
            if (isset($newField['typesettings']['defaultUploadLocationSource']) && $newField['typesettings']['defaultUploadLocationSource']) {
                $source = craft()->assetSources->getSourceById(intval($newField['typesettings']['defaultUploadLocationSource']));
                if ($source) {
                    $newField['typesettings']['defaultUploadLocationSource'] = $source->handle;
                }
            }
            if (isset($newField['typesettings']['singleUploadLocationSource']) && $newField['typesettings']['singleUploadLocationSource']) {
                $source = craft()->assetSources->getSourceById(intval($newField['typesettings']['singleUploadLocationSource']));
                if ($source) {
                    $newField['typesettings']['singleUploadLocationSource'] = $source->handle;
                }
            }
        }

        if ($field->type == 'RichText') {
            if (isset($newField['typesettings']['availableAssetSources'])) {
                if ($newField['typesettings']['availableAssetSources'] !== '*' && $newField['typesettings']['availableAssetSources'] != '') {
                    foreach ($newField['typesettings']['availableAssetSources'] as $key => $value) {
                        $source = craft()->assetSources->getSourceById($value);
                        if ($source) {
                            $newField['typesettings']['availableAssetSources'][$key] = $source->handle;
                        }
                    }
                }
            }
            if (isset($newField['typesettings']['defaultUploadLocationSource'])) {
                if (isset($newField['typesettings']['defaultUploadLocationSource']) && $newField['typesettings']['defaultUploadLocationSource']) {
                    $source = craft()->assetSources->getSourceById(intval($newField['typesettings']['defaultUploadLocationSource']));
                    if ($source) {
                        $newField['typesettings']['defaultUploadLocationSource'] = $source->handle;
                    }
                }
            }
            if (isset($newField['typesettings']['singleUploadLocationSource'])) {
                if (isset($newField['typesettings']['singleUploadLocationSource']) && $newField['typesettings']['singleUploadLocationSource']) {
                    $source = craft()->assetSources->getSourceById(intval($newField['typesettings']['singleUploadLocationSource']));
                    if ($source) {
                        $newField['typesettings']['singleUploadLocationSource'] = $source->handle;
                    }
                }
            }
        }

        if ($field->type == 'Categories') {
            if ($newField['typesettings']['source']) {
                if (substr($newField['typesettings']['source'], 0, 6) == 'group:') {
                    $category = craft()->categories->getGroupById(intval(substr($newField['typesettings']['source'], 6)));
                    if ($category) {
                        $newField['typesettings']['source'] = $category->handle;
                    }
                }
            }
        }

        if ($field->type == 'Entries') {
            if ($newField['typesettings']['sources']) {
                if (is_array($newField['typesettings']['sources'])) {
                    foreach ($newField['typesettings']['sources'] as $key => $value) {
                        if (substr($value, 0, 8) == 'section:') {
                            $section = craft()->sections->getSectionById(intval(substr($value, 8)));
                            if ($section) {
                                $newField['typesettings']['sources'][$key] = $section->handle;
                            }
                        }
                    }
                } elseif ($newField['typesettings']['sources'] == '*') {
                    $newField['typesettings']['sources'] = [];
                }
            }
        }

        if ($field->type == 'Tags') {
            if ($newField['typesettings']['source']) {
                if (substr($newField['typesettings']['source'], 0, 9) == 'taggroup:') {
                    $tag = craft()->tags->getTagGroupById(intval(substr($newField['typesettings']['source'], 9)));
                    if ($tag) {
                        $newField['typesettings']['source'] = $tag->handle;
                    }
                }
            }
        }

        if ($field->type == 'Users') {
            if ($newField['typesettings']['sources']) {
                if (is_array($newField['typesettings']['sources'])) {
                    foreach ($newField['typesettings']['sources'] as $key => $value) {
                        if (substr($value, 0, 6) == 'group:') {
                            $userGroup = craft()->userGroups->getGroupById(intval(substr($value, 6)));
                            if ($userGroup) {
                                $newField['typesettings']['sources'][$key] = $userGroup->handle;
                            }
                        }
                    }
                }
            }
        }

        if ($field->type == 'FruitLinkIt') {
            if ($newField['typesettings']['entrySources']) {
                if (is_array($newField['typesettings']['entrySources'])) {
                    foreach ($newField['typesettings']['entrySources'] as $key => $value) {
                        if (substr($value, 0, 8) == 'section:') {
                            $section = craft()->sections->getSectionById(intval(substr($value, 8)));
                            if ($section) {
                                $newField['typesettings']['entrySources'][$key] = $section->handle;
                            }
                        }
                    }
                } elseif ($newField['typesettings']['entrySources'] == '*') {
                    $newField['typesettings']['entrySources'] = [];
                }
            }
        }
    }

    private function assetSourceExport($post, $includeID = false)
    {
        $sources = [];
        if (isset($post['assetSourceSelection'])) {
            foreach ($post['assetSourceSelection'] as $id) {
                $assetSource = craft()->assetSources->getSourceById($id);
                if ($assetSource === null) {
                    continue;
                }
                $newAssetSource = [
                    'name' => $assetSource->name,
                    'handle' => $assetSource->handle,
                    'type' => $assetSource->type,
                    'settings' => $assetSource->settings,
                    'fieldLayout' => [],
                ];
                if ($includeID) {
                    $newAssetSource = array_merge(['id' => $assetSource->id], $newAssetSource);
                }

                $fieldLayout = $assetSource->getFieldLayout();

                $fieldLayoutReasons = $this->getConditionalsByFieldLayoutId($fieldLayout->id);
                if ($fieldLayoutReasons) {
                    $newAssetSource['reasons'] = $this->setReasonsLabels($fieldLayoutReasons);
                }

                $this->setRelabels($newAssetSource, $fieldLayout);

                foreach ($fieldLayout->getTabs() as $tab) {
                    $newAssetSource['fieldLayout'][$tab->name] = [];
                    foreach ($tab->getFields() as $tabField) {
                        array_push($newAssetSource['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                    }
                }
                array_push($sources, $newAssetSource);
            }
        }

        return $sources;
    }

    private function transformExport($post, $includeID = false)
    {
        $transforms = [];
        if (isset($post['assetTransformSelection'])) {
            foreach ($post['assetTransformSelection'] as $id) {
                $transform = $this->getTransformById($id);
                if ($transform === null) {
                    continue;
                }
                $newTransform = [
                    'name' => $transform->name,
                    'handle' => $transform->handle,
                    'mode' => $transform->mode,
                    'position' => $transform->position,
                    'width' => $transform->width,
                    'height' => $transform->height,
                    'quality' => $transform->quality,
                    'format' => $transform->format,
                ];
                if ($includeID) {
                    $newTransform = array_merge(['id' => $transform->id], $newTransform);
                }
                array_push($transforms, $newTransform);
            }
        }

        return $transforms;
    }

    private function globalSetExport($post, $includeID = false)
    {
        $globals = [];
        if (isset($post['globalSelection'])) {
            foreach ($post['globalSelection'] as $id) {
                $globalSet = craft()->globals->getSetById($id);
                if ($globalSet === null) {
                    continue;
                }
                $newGlobalSet = [
                    'name' => $globalSet->name,
                    'handle' => $globalSet->handle,
                    'fieldLayout' => [],
                ];
                if ($includeID) {
                    $newGlobalSet = array_merge(['id' => $globalSet->id], $newGlobalSet);
                }

                $fieldLayout = $globalSet->getFieldLayout();

                $fieldLayoutReasons = $this->getConditionalsByFieldLayoutId($fieldLayout->id);
                if ($fieldLayoutReasons) {
                    $newGlobalSet['reasons'] = $this->setReasonsLabels($fieldLayoutReasons);
                }

                $this->setRelabels($newGlobalSet, $fieldLayout);

                foreach ($fieldLayout->getTabs() as $tab) {
                    $newGlobalSet['fieldLayout'][$tab->name] = [];
                    foreach ($tab->getFields() as $tabField) {
                        array_push($newGlobalSet['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                    }
                }
                array_push($globals, $newGlobalSet);
            }
        }

        return $globals;
    }

    private function categoryGroupExport($post, $includeID = false)
    {
        $categories = [];
        if (isset($post['categorySelection'])) {
            foreach ($post['categorySelection'] as $id) {
                $categoryGroup = craft()->categories->getGroupById($id);
                $categoryGroupLocales = $categoryGroup->getLocales();
                $newCategory = [
                    'name' => $categoryGroup->name,
                    'handle' => $categoryGroup->handle,
                    'hasUrls' => $categoryGroup->hasUrls,
                    'template' => $categoryGroup->template,
                    'maxLevels' => $categoryGroup->maxLevels,
                    'locales' => [],
                    'fieldLayout' => [],
                ];
                if ($includeID) {
                    $newCategory = array_merge(['id' => $categoryGroup->id], $newCategory);
                }

                // Set Group Locales
                foreach ($categoryGroupLocales as $locale => $groupLocale) {
                    $newCategory['locales'][$locale] = [
                        'urlFormat' => $groupLocale->urlFormat,
                        'nestedUrlFormat' => $groupLocale->nestedUrlFormat,
                    ];
                }

                // Set Group fieldLayout
                $fieldLayout = $categoryGroup->getFieldLayout();

                // Set reasons
                $fieldLayoutReasons = $this->getConditionalsByFieldLayoutId($fieldLayout->id);
                if ($fieldLayoutReasons) {
                    $newCategory['reasons'] = $this->setReasonsLabels($fieldLayoutReasons);
                }

                // Set relabels
                $this->setRelabels($newCategory, $fieldLayout);

                foreach ($fieldLayout->getTabs() as $tab) {
                    $newCategory['fieldLayout'][$tab->name] = [];
                    foreach ($tab->getFields() as $tabField) {
                        array_push($newCategory['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                    }
                }
                array_push($categories, $newCategory);
            }
        }

        return $categories;
    }

    public function routeExport($post, $includeID = false)
    {
        $routes = [];

        if (isset($post['routeSelection'])) {
            foreach ($post['routeSelection'] as $id) {
                $route = $this->getRouteById($id);

                $urlParts = [];
                $urlPartsObj = json_decode($route['urlParts']);
                foreach ($urlPartsObj as $part) {
                    array_push($urlParts, $part);
                }

                $newRoute = [
                    'locale' => $route['locale'],
                    'urlParts' => $urlParts,
                    'template' => $route['template'],
                    'sortOrder' => $route['sortOrder'],
                ];
                if ($includeID) {
                    $newRoute = array_merge(['id' => $route['id']], $newRoute);
                }
                array_push($routes, $newRoute);
            }
        }

        return $routes;
    }

    public function tagExport($post, $includeID = false)
    {
        $tags = [];

        if (isset($post['tagSelection'])) {
            foreach ($post['tagSelection'] as $id) {
                $tag = craft()->tags->getTagGroupById($id);

                $newTag = [
                    'name' => $tag['name'],
                    'handle' => $tag['handle'],
                ];
                if ($includeID) {
                    $newTag = array_merge(['id' => $tag['id']], $newTag);
                }

                // Set Tag fieldLayout
                $fieldLayout = $tag->getFieldLayout();

                // Set reasons
                $fieldLayoutReasons = $this->getConditionalsByFieldLayoutId($fieldLayout->id);
                if ($fieldLayoutReasons) {
                    $newTag['reasons'] = $this->setReasonsLabels($fieldLayoutReasons);
                }

                // Set relabels
                $this->setRelabels($newTag, $fieldLayout);

                foreach ($fieldLayout->getTabs() as $tab) {
                    $newTag['fieldLayout'][$tab->name] = [];
                    foreach ($tab->getFields() as $tabField) {
                        array_push($newTag['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                    }
                }
                array_push($tags, $newTag);
            }
        }

        return $tags;
    }

    public function getAllIDs()
    {
        // Generate all IDs available for export.
        $post = [
            'fieldSelection' => [],
            'sectionSelection' => [],
            'entryTypeSelection' => [],
            'assetSourceSelection' => [],
            'assetTransformSelection' => [],
            'globalSelection' => [],
            'categorySelection' => [],
            'routeSelection' => [],
            'tagSelection' => [],
            'userSelection' => [],
        ];

        foreach (craft()->fields->getAllFields() as $field) {
            array_push($post['fieldSelection'], $field->id);
        }
        foreach (craft()->sections->getAllSections() as $section) {
            array_push($post['sectionSelection'], $section->id);
            foreach ($section->getEntryTypes() as $entryType) {
                array_push($post['entryTypeSelection'], $entryType->id);
            }
        }
        foreach (craft()->assetSources->getAllSources() as $field) {
            array_push($post['assetSourceSelection'], $field->id);
        }
        foreach (craft()->assetTransforms->getAllTransforms() as $transform) {
            array_push($post['assetTransformSelection'], $transform->id);
        }
        foreach (craft()->globals->getAllSets() as $globalSet) {
            array_push($post['globalSelection'], $globalSet->id);
        }
        foreach (craft()->categories->getAllGroups() as $categoryGroup) {
            array_push($post['categorySelection'], $categoryGroup->id);
        }
        foreach ($this->getAllRoutes() as $route) {
            array_push($post['routeSelection'], $route['id']);
        }
        foreach (craft()->tags->getAllTagGroups() as $tag) {
            array_push($post['tagSelection'], $tag['id']);
        }
        foreach (craft()->theArchitect->getAllUsers() as $user) {
            array_push($post['userSelection'], $user->id);
        }
        if (isset(craft()->userGroups)) {
            $post['groupSelection'] = [];
            foreach (craft()->userGroups->getAllGroups() as $userGroup) {
                array_push($post['groupSelection'], $userGroup->id);
            }
        }

        return $post;
    }

    public function getAllJsonIds($json)
    {
        $data = json_decode($json);

        $ids = [
            'fieldIDs' => [],
            'sectionIDs' => [],
            'entryTypeIDs' => [],
            'assetSourceIDs' => [],
            'assetTransformIDs' => [],
            'globalIDs' => [],
            'categoryIDs' => [],
            'routeIDs' => [],
            'userIDs' => [],
        ];

        if (isset($data->fields)) {
            foreach ($data->fields as $field) {
                array_push($ids['fieldIDs'], $field->id);
            }
        }

        if (isset($data->sections)) {
            foreach ($data->sections as $section) {
                array_push($ids['sectionIDs'], $section->id);
            }
        }

        if (isset($data->entryTypes)) {
            foreach ($data->entryTypes as $entryType) {
                array_push($ids['entryTypeIDs'], $entryType->id);
            }
        }

        if (isset($data->sources)) {
            foreach ($data->sources as $assetSource) {
                array_push($ids['assetSourceIDs'], $assetSource->id);
            }
        }

        if (isset($data->transforms)) {
            foreach ($data->transforms as $transform) {
                array_push($ids['assetTransformIDs'], $transform->id);
            }
        }

        if (isset($data->globals)) {
            foreach ($data->globals as $global) {
                array_push($ids['globalIDs'], $global->id);
            }
        }

        if (isset($data->categories)) {
            foreach ($data->categories as $category) {
                array_push($ids['categoryIDs'], $category->id);
            }
        }

        if (isset($data->routes)) {
            foreach ($data->routes as $route) {
                array_push($ids['routeIDs'], $route->id);
            }
        }

        if (isset($data->users)) {
            foreach ($data->users as $user) {
                array_push($ids['userIDs'], $user->id);
            }
        }

        if (isset($data->userGroups)) {
            $ids['groupIDs'] = [];
            foreach ($data->userGroups as $userGroup) {
                array_push($ids['groupIDs'], $userGroup->id);
            }
        }

        return $ids;
    }

    public function getAddedUpdatedDeletedIds($json)
    {
        $jsonPath = craft()->config->get('modelsPath', 'theArchitect');
        $masterJson = $jsonPath.'_master_.json';

        if (file_exists($masterJson)) {
            $exportTime = filemtime($masterJson);
        } else {
            $exportTime = null;
        }

        $allIds = $this->getAllIds();
        $jsonIds = $this->getAllJsonIds($json);
        $dbAddedIds = [];
        $dbUpdatedIds = [];
        $dbDeletedIds = [];
        $modelAddedIds = [];
        $modelUpdatedIds = [];
        $modelDeletedIds = [];

        $fieldUpdates = [];
        foreach (craft()->db->createCommand()->select('id, dateCreated, dateUpdated')->from('fields')->queryAll() as $fieldUpdate) {
            $date = new DateTime($fieldUpdate['dateCreated']);
            $fieldCreated[$fieldUpdate['id']] = $date->getTimestamp();
            $date = new DateTime($fieldUpdate['dateUpdated']);
            $fieldUpdates[$fieldUpdate['id']] = $date->getTimestamp();
        }
        foreach ($allIds as $type => $ids) {
            $type = str_replace('Selection', 'IDs', $type);
            $dbAddedIds[$type] = [];
            $dbUpdatedIds[$type] = [];
            $dbDeletedIds[$type] = [];
            $modelAddedIds[$type] = [];
            $modelUpdatedIds[$type] = [];
            $modelDeletedIds[$type] = [];
            foreach ($ids as $id) {
                $added = false;
                $updated = false;
                if ($type == 'fieldIDs') {
                    if ($fieldCreated[$id] > $exportTime) {
                        $added = true;
                    } elseif ($fieldUpdates[$id] > $exportTime) {
                        $updated = true;
                    }
                }
                if (!isset($jsonIds[$type]) || !in_array($id, $jsonIds[$type]) || $added || $updated) {
                    if ($added) {
                        $dbAddedIds[$type][] = $id;
                    } elseif ($updated) {
                        $dbUpdatedIds[$type][] = $id;
                    } else {
                        $modelDeletedIds[$type][] = $id;
                    }
                }
            }
        }

        return [$dbAddedIds, $dbUpdatedIds, $dbDeletedIds, $modelAddedIds, $modelUpdatedIds, $modelDeletedIds];
    }

    public function getFieldById($fieldID)
    {
        $fields = craft()->fields->getAllFields();
        foreach ($fields as $field) {
            if ($field->id == $fieldID) {
                return $field;
            }
        }
        craft()->db->createCommand()->insert('fields', array(
            'id' => $fieldID,
        ));
        $field = new FieldModel();
        $field->id = $fieldID;

        return $field;
    }

    public function getEntryTypeById($entryTypeID, $sectionID)
    {
        $entryType = craft()->sections->getEntryTypeById($entryTypeID);
        if ($entryType) {
            return $entryType;
        }
        craft()->db->createCommand()->insert('entrytypes', array(
            'id' => $entryTypeID,
            'sectionId' => $sectionID,
        ));
        $entryType = new EntryTypeModel();
        $entryType->id = $entryTypeID;

        return $entryType;
    }

    public function getSectionById($sectionID)
    {
        $sections = craft()->sections->getAllSections();
        foreach ($sections as $section) {
            if ($section->id == $sectionID) {
                return $section;
            }
        }
        craft()->db->createCommand()->insert('sections', array(
            'id' => $sectionID,
        ));
        $section = new SectionModel();
        $section->id = $sectionID;

        return $section;
    }

    public function getAssetSourceById($sourceID)
    {
        $sources = craft()->assetSources->getAllSources();
        foreach ($sources as $source) {
            if ($source->id == $sourceID) {
                return $source;
            }
        }
        foreach ($this->addedSources as $source) {
            if ($source->id == $sourceID) {
                return $source;
            }
        }
        craft()->db->createCommand()->insert('assetsources', array(
            'id' => $sourceID,
        ));
        $source = new AssetSourceModel();
        $source->id = $sourceID;

        return $source;
    }

    public function getAssetTransformById($transformID)
    {
        $assetTransforms = craft()->assetTransforms->getAllTransforms();
        foreach ($assetTransforms as $assetTransform) {
            if ($assetTransform->id == $transformID) {
                return $assetTransform;
            }
        }
        craft()->db->createCommand()->insert('assetTransforms', array(
            'id' => $transformID,
        ));
        $assetTransform = new AssetTransformModel();
        $assetTransform->id = $transformID;

        return $assetTransform;
    }

    public function getGlobalSetById($globalSetID)
    {
        $globalSet = craft()->globals->getSetById($globalSetID);
        if ($globalSet) {
            return $globalSet;
        }
        $query = craft()->db->createCommand()->select('id')
            ->from('globalsets')
            ->where('id=:id', array(':id' => $globalSetID))
            ->queryColumn();
        if (!$query) {
            craft()->db->createCommand()->insert('elements', array(
                'id' => $globalSetID,
                'type' => 'GlobalSet',
                'enabled' => 1
            ));
            craft()->db->createCommand()->insert('globalsets', array(
                'id' => $globalSetID,
            ));
        }
        $globalSet = new GlobalSetModel();
        $globalSet->id = $globalSetID;

        return $globalSet;
    }

    public function getUserById($userID)
    {
        $users = craft()->theArchitect->getAllUsers();
        foreach ($users as $user) {
            if ($user->id == $userID) {
                return array($user, false);
            }
        }
        craft()->db->createCommand()->insert('usergroups', array(
            'id' => $userID,
        ));
        $user = new UserModel();
        $user->id = $userID;

        return array($user, true);
    }

    public function getUserGroupById($userGroupID)
    {
        $userGroups = craft()->userGroups->getAllGroups();
        foreach ($userGroups as $userGroup) {
            if ($userGroup->id == $userGroupID) {
                return $userGroup;
            }
        }
        craft()->db->createCommand()->insert('usergroups', array(
            'id' => $userGroupID,
        ));
        $userGroup = new UserGroupModel();
        $userGroup->id = $userGroupID;

        return $userGroup;
    }

    public function getCategoryGroupById($categoryGroupID)
    {
        $categoryGroups = craft()->categories->getAllGroups();
        foreach ($categoryGroups as $categoryGroup) {
            if ($categoryGroup->id == $categoryGroupID) {
                return $categoryGroup;
            }
        }
        $result = craft()->db->createCommand()->insert('structures', array(
            'maxLevels' => null,
        ));
        craft()->db->createCommand()->insert('categorygroups', array(
            'id' => $categoryGroupID,
            'structureId' => craft()->db->getLastInsertID(),
        ));
        $categoryGroup = new CategoryGroupModel();
        $categoryGroup->id = $categoryGroupID;

        return $categoryGroup;
    }

    public function getAllRoutes()
    {
        return craft()->db->createCommand()
            ->select('id, locale, urlParts, template, sortOrder')
            ->from('routes')
            ->order('sortOrder')
            ->queryAll();
    }

    public function getRouteById($id)
    {
        return craft()->db->createCommand()
            ->select('id, locale, urlParts, template, sortOrder')
            ->from('routes')
            ->where('id = :id', array(':id' => $id))
            ->order('sortOrder')
            ->queryRow();
    }

    public function getAutomation()
    {
        return craft()->plugins->getPlugin('theArchitect')->getSettings()['automation'];
    }

    public function getLastImport()
    {
        return craft()->plugins->getPlugin('theArchitect')->getSettings()['lastImport'];
    }

    public function getAPIKey()
    {
        return craft()->plugins->getPlugin('theArchitect')->getSettings()['apiKey'];
    }

    public function generateUUID4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
