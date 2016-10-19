<?php

namespace Craft;

/**
 * TheArchitect_ApiController.
 */
class TheArchitect_ApiController extends BaseController
{
    protected $allowAnonymous = true;

    public function actionExport()
    {
        $apiKey = craft()->theArchitect->getAPIKey();
        $key = craft()->request->getParam('key');

        if (!$apiKey OR $key != $apiKey) {
			die('Unauthorized key');
		}

        // Run Migration Export
        craft()->theArchitect->exportMigrationConstruct();

        // Set last import to match this export time.
        craft()->plugins->savePluginSettings(craft()->plugins->getPlugin('theArchitect'), array('lastImport' => (new DateTime())->getTimestamp()));

        die('Migration Exported!');
    }

    public function actionImport()
    {
        $apiKey = craft()->theArchitect->getAPIKey();
        $key = craft()->request->getParam('key');
        $force = craft()->request->getParam('force');

        if (!$apiKey OR $key != $apiKey) {
			die('Unauthorized key');
		}

        // Run Migration Import
        craft()->theArchitect->importMigrationConstruct($force);

        die('Migration Imported!');
    }
}
