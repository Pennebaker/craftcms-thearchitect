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

        $this->renderTemplate('thearchitect/blueprint', $variables);
    }

    /**
     * actionConstructBlueprint [ TODO ].
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
            );

            $this->renderTemplate('thearchitect/output', $variables);
        }
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
