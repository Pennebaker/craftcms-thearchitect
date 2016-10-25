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
			die('{"type": "export","success": false}');
		}

        // Run Migration Export
        craft()->theArchitect->exportMigrationConstruct();

        // Set last import to match this export time.
        craft()->plugins->savePluginSettings(craft()->plugins->getPlugin('theArchitect'), array('lastImport' => (new DateTime())->getTimestamp()));

        die('{"type": "export","success": true}');
    }

    public function actionImport()
    {
        $apiKey = craft()->theArchitect->getAPIKey();
        $key = craft()->request->getParam('key');
        $force = craft()->request->getParam('force');

        if (!$apiKey OR $key != $apiKey) {
			die('{"type": "export","success": false}');
		}

        // Run Migration Import
        $result = craft()->theArchitect->importMigrationConstruct($force);

        if ($result) {
            die('{"type": "import","success": true}');
        } else {
            die('{"type": "import","success": false}');
        }
    }
}
