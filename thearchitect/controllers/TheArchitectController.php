<?php

namespace Craft;

/**
 * TheArchitectController.
 */
class TheArchitectController extends BaseController
{
    // Public Methods
    // =========================================================================

    /**
     * {@inheritdoc} BaseController::init()
     *
     * @throws HttpException
     */
    public function init()
    {
        // All section actions require an admin
        craft()->userSession->requireAdmin();
    }

    /**
     * actionBlueprint [list the exportable items].
     */
    public function actionBlueprint()
    {
        $variables = array(
            'assetSources' => craft()->assetSources->getAllSources(),
            'assetTransforms' => craft()->assetTransforms->getAllTransforms(),
        );

        craft()->templates->includeJsResource('thearchitect/js/thearchitect.js');
        craft()->templates->includeCssResource('thearchitect/css/thearchitect.css');

        $this->renderTemplate('thearchitect/blueprint', $variables);
    }

    /**
     * actionConstructBlueprint.
     */
    public function actionConstructBlueprint()
    {
        // Prevent GET Requests
        $this->requirePostRequest();

        $post = craft()->request->getPost();

        $output = craft()->theArchitect->exportConstruct($post);

        // If the output is empty redirect back the the export page
        if ($output == []) {
            $this->redirect('thearchitect/blueprint');
        }
        // Else display the output data as json for the user to copy
        else {
            $variables = array(
                'json' => json_encode($output, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT),
                'tab' => 'tab3',
            );

            $this->renderTemplate('thearchitect/output', $variables);
        }
    }

    /**
     * actionBlueprint [list the exportable items].
     */
    public function actionConvert()
    {
        $variables = array(
            'assetSources' => craft()->assetSources->getAllSources(),
            'assetTransforms' => craft()->assetTransforms->getAllTransforms(),
        );

        craft()->templates->includeJsResource('thearchitect/js/thearchitect.js');
        craft()->templates->includeCssResource('thearchitect/css/thearchitect.css');

        $this->renderTemplate('thearchitect/convert', $variables);
    }

    /**
     * actionConvert [ TODO ].
     */
    public function actionRecode()
    {
        // Prevent GET Requests
        $this->requirePostRequest();

        $post = craft()->request->getPost();

        $export = craft()->theArchitect->exportConstruct($post);
        // Converting arrays to objects.
        $json = json_encode($export, JSON_NUMERIC_CHECK);
        $object = json_decode($json);

        $newObject = clone $object;
        $newObject->fields = [];

        foreach ($object->fields as $fieldId => $field) {
            if ($field->type == 'Matrix') {
                $fields = [];
                $allFields = [];
                $currentGroup = $field->group;
                $maxCount = 0;
                foreach ($field->typesettings->blockTypes as $blockId => $block) {
                    foreach ($block->fields as $blockFieldId => $blockField) {
                        $allFields[] = $blockField;
                        $fieldLoc = array_search($blockField, $fields);
                        for ($i=0; $i < $maxCount; $i++) {
                            if ($fieldLoc !== false) {
                                break;
                            }
                            $testBlockField = clone $blockField;
                            $testBlockField->handle = $blockField->handle . '_' . $i;
                            $fieldLoc = array_search($testBlockField, $fields);
                            if ($fieldLoc !== false) {
                                $blockField->handle = $testBlockField->handle;;
                            }
                        }
                        if ($fieldLoc === false) {
                            if ($this->hasHandle($blockField->handle, $fields)) {
                                $count = 0;
                                $originalHandle = $blockField->handle;
                                while ($this->hasHandle($blockField->handle, $fields)) {
                                    $blockField->handle = $originalHandle . '_' . $count;
                                    $count++;
                                }
                                if ($count > $maxCount) {
                                    $maxCount = $count;
                                }
                            }
                            $fields[] = $blockField;
                        }
                    }
                }
                $fieldLinks = [];
                foreach ($fields as $fAId => $fieldA) {
                    foreach ($fields as $fBId => $fieldB) {
                        if ($fieldA != $fieldB && !(isset($fieldLinks[$fBId]) && $fieldLinks[$fBId] == $fAId)) {
                            if ($fieldA->type == $fieldB->type) {
                                if ($fieldA->typesettings == $fieldB->typesettings) {
                                    $fieldLinks[$fAId] = $fBId;
                                    // print 'found similar fields' . "\n";
                                    // print '<table><tr><td><pre style="margin:0 10px;padding:5px 10px;background-color:#eee;">' . json_encode($fieldA, JSON_PRETTY_PRINT) . '</pre></td><td><pre style="margin:0 10px;padding:5px 10px;background-color:#eee;">' . json_encode($fieldB, JSON_PRETTY_PRINT) . '</pre></td></tr></table>';
                                }
                            }
                        }
                    }
                }
                // print '<pre>';
                // print 'Found ' . sizeof($allFields) . ' Fields' . "\n";
                // print 'Reduced to ' . sizeof($fields) . ' Fields';
                // print '</pre>';
                foreach ($fields as $field) {
                    $field->group = $currentGroup;
                    $newObject->fields[] = $field;
                }
                foreach ($object->fields as $fieldId => $field) {
                    if ($field->type == 'Matrix') {
                        $newField = clone $field;
                        $newField->type = 'Neo';
                        $newField->typesettings = [ "maxBlocks" => $field->typesettings->maxBlocks, "groups" => [], "blockTypes" => [] ];
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
                            $newField->typesettings['blockTypes']["new".$count] = [
                                "sortOrder" => strval($count+1),
                                "name" => $block->name,
                                "handle" => $block->handle,
                                "maxBlocks" => "",
                                "childBlocks" => [],
                                "topLevel" => true,
                                "fieldLayout" => [ "Tab" => $blockFields ],
                                "requiredFields" => $requiredFields
                            ];
                            $count++;
                        }
                        $newObject->fields[] = $newField;
                    }
                }
                $variables = array(
                    'json' => json_encode($newObject, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT),
                    'tab' => 'tab4',
                );

                $this->renderTemplate('thearchitect/output', $variables);
            }
        }
    }

    private function hasHandle($handle, $fields) {
        foreach ($fields as $field) {
            if ($field->handle == $handle) {
                return true;
            }
        }
        return false;
    }

    /**
     * actionConstructList [list the files inside the content folder].
     */
    public function actionConstructList()
    {
        $files = array();
        if ($handle = opendir(craft()->path->getPluginsPath().'thearchitect/content')) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != '.' && $entry != '..' && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) == 'json') {
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
            'files' => $files,
        );

        $this->renderTemplate('thearchitect/files', $variables);
    }

    /**
     * actionConstructLoad [load the selected file for review before processing].
     */
    public function actionConstructLoad()
    {
        // Prevent GET Requests
        $this->requirePostRequest();

        $fileName = craft()->request->getRequiredPost('fileName');

        $filePath = craft()->path->getPluginsPath().'thearchitect/content/'.$fileName;

        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);

            $variables = array(
                'json' => $json,
                'filename' => $fileName,
            );

            $this->renderTemplate('thearchitect/index', $variables);
        }
    }

    /**
     * actionConstruct.
     */
    public function actionConstruct()
    {
        // Prevent GET Requests
        $this->requirePostRequest();

        $json = craft()->request->getRequiredPost('json');

        if ($json) {
            $notice = craft()->theArchitect->parseJson($json);

            $variables = array(
                'json' => $json,
                'result' => $notice,
            );

            $this->renderTemplate('thearchitect/index', $variables);
        }
    }
}
