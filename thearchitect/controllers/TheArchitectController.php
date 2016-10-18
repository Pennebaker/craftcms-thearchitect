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

        craft()->templates->includeJsResource('thearchitect/js/diff.min.js');
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

        craft()->templates->includeJsResource('thearchitect/js/diff.min.js');
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

        list($newObject, $allFields, $fields, $similarFields) = craft()->theArchitect->exportMatrixAsNeo($post);

        $variables = array(
            'json' => json_encode($newObject, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT),
            'tab' => 'tab5',
            'oldFieldCount' => sizeof($allFields),
            'newFieldCount' => sizeof($fields),
            'similarFields' => $similarFields,
        );

        craft()->templates->includeJsResource('thearchitect/js/diff.min.js');
        craft()->templates->includeJsResource('thearchitect/js/thearchitect.js');
        craft()->templates->includeCssResource('thearchitect/css/thearchitect.css');

        $this->renderTemplate('thearchitect/output', $variables);
    }

    /**
     * actionMigrations [View migration file info].
     */
    public function actionMigrations()
    {
        $migrationsEnabled = craft()->theArchitect->migrationsEnabled();

        $jsonPath = craft()->config->get('modelsPath', 'theArchitect');
        $masterJson = craft()->config->get('modelsPath', 'theArchitect').'_master_.json';

        if ($migrationsEnabled) {
            craft()->theArchitect->exportMigrationConstruct();
            craft()->theArchitect->importMigrationConstruct();
        }

        $variables = array(
            'migrationsEnabled' => $migrationsEnabled,
            'exportTime' => filemtime($masterJson),
            'importTime' => fileatime($masterJson),
        );

        craft()->templates->includeCssResource('thearchitect/css/thearchitect.css');

        $this->renderTemplate('thearchitect/migrations', $variables);
    }

    /**
     * actionEnableMigrations [Enable migrations].
     */
    public function actionEnableMigrations()
    {
        craft()->plugins->getPlugin('theArchitect')->setSettings(array('enableMigrations' => true));

        $this->redirect('thearchitect/migrations');
    }

    /**
     * actionFarm [Disable migrations].
     */
    public function actionDisableMigrations()
    {
        craft()->plugins->getPlugin('theArchitect')->setSettings(array('enableMigrations' => false));

        $this->redirect('thearchitect/migrations');
    }

    /**
     * actionConstructList [list the files inside the `config.jsonPath` folder].
     */
    public function actionConstructList()
    {
        $files = array();
        $jsonPath = craft()->path->getConfigPath().'thearchitect/';

        if (file_exists($jsonPath) && is_dir($jsonPath) && $handle = opendir($jsonPath)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != '.' && $entry != '..' && $entry != '_master_.json' && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) == 'json') {
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
        $jsonPath = craft()->path->getConfigPath().'thearchitect/';

        $filePath = $jsonPath.$fileName;

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
