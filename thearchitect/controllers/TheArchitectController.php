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
        $jsonPath = craft()->config->get('modelsPath', 'theArchitect');
        $masterJson = $jsonPath.'_master_.json';

        if (file_exists($masterJson)) {
            $exportTime = filemtime($masterJson);
        } else {
            $exportTime = null;
        }

        if (file_exists($masterJson)) {
            list($dbAddedIDs, $dbUpdatedIDs, $dbDeleteIDs, $modelAddedIDs, $modelUpdatedIDs, $modelDeleteIDs) = craft()->theArchitect->getAddedUpdatedDeletedIds(file_get_contents($masterJson));
        }

        $variables = array(
            'automation' => craft()->theArchitect->getAutomation(),
            'exportTime' => $exportTime,
            'importTime' => craft()->theArchitect->getLastImport(),
            'apiKey' => craft()->theArchitect->getAPIKey(),
            'jsonPath' => $jsonPath,
            'mismatch' => craft()->theArchitect->compareMigrationConstruct(),
            'dbAdditions' => (isset($dbAddedIDs)) ? $dbAddedIDs : null,
            'dbUpdates' => (isset($dbUpdatedIDs)) ? $dbUpdatedIDs : null,
            'dbDeletions' => (isset($dbDeleteIDs)) ? $dbDeleteIDs : null,
            'modelAdditions' => (isset($modelAddedIDs)) ? $modelAddedIDs : null,
            'modelUpdates' => (isset($modelUpdatedIDs)) ? $modelUpdatedIDs : null,
            'modelDeletions' => (isset($modelDeleteIDs)) ? $modelDeleteIDs : null,
        );

        craft()->templates->includeCssResource('thearchitect/css/thearchitect.css');
        craft()->templates->includeJsResource('thearchitect/js/clipboard.min.js');
        craft()->templates->includeJs('var clipboard = new Clipboard("[data-clipboard-text]");clipboard.on("success",function(){Craft.cp.displayNotice("Copied to clipboard!");});clipboard.on("error",function(){Craft.cp.displayError("Error copying to clipboard.");});');

        $this->renderTemplate('thearchitect/migrations', $variables);
    }

    public function actionMigrationExport()
    {
        // Run Migration Export
        craft()->theArchitect->exportMigrationConstruct();

        // Set last import to match this export time.
        craft()->plugins->savePluginSettings(craft()->plugins->getPlugin('theArchitect'), array('lastImport' => (new DateTime())->getTimestamp()));

        $this->redirect('thearchitect/migrations');
    }

    public function actionMigrationImport()
    {
        $force = (!is_null(craft()->request->getPost('force')));

        $jsonPath = craft()->config->get('modelsPath', 'theArchitect');
        $masterJson = $jsonPath.'_master_.json';

        if (!file_exists($masterJson)) {
            craft()->userSession->setError(Craft::t('There is no migration file to import.'));
        } else {
            $result = craft()->theArchitect->importMigrationConstruct($force);

            if ($result) {
                touch($masterJson);
                craft()->userSession->setNotice(Craft::t('Migration imported successfully.'));
            } else {
                craft()->userSession->setError(Craft::t('There is some field type changes. To prevent content loss please review the field types before forcing.'));
            }
        }

        $this->redirect('thearchitect/migrations');
    }

    public function actionGenerateKey()
    {
        // Generate a new API key for import / export url calls
        craft()->plugins->savePluginSettings(craft()->plugins->getPlugin('theArchitect'), array('apiKey' => craft()->theArchitect->generateUUID4()));

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
