<?php

namespace Craft;

/**
 * Class TheArchitectPlugin.
 */
class TheArchitectPlugin extends BasePlugin
{
    /**
     * getName.
     *
     * @return null|string
     */
    public function getName()
    {
        return Craft::t('The Architect');
    }

    /**
     * getVersion.
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.5.2';
    }

    /**
     * getSchemaVersion.
     *
     * @return string
     */
    public function getSchemaVersion()
    {
        return '1.5.1';
    }

    /**
     * getDeveloper.
     *
     * @return string
     */
    public function getDeveloper()
    {
        return 'Pennebaker';
    }

    /**
     * getDeveloperUrl.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'http://pennebaker.com';
    }

    /**
     * getPluginUrl.
     *
     * @return string
     */
    public function getPluginUrl()
    {
        return 'https://github.com/Pennebaker/craftcms-thearchitect';
    }

    /**
     * getReleaseFeedUrl.
     *
     * @return string
     */
    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/Pennebaker/craftcms-thearchitect/master/releases.json';
    }

    /**
     * getDocumentationUrl.
     *
     * @return string
     */
    public function getDocumentationUrl()
    {
        return $this->getPluginUrl().'/wiki';
    }

    /**
     * hasCpSection.
     *
     * @return bool
     */
    public function hasCpSection()
    {
        if (craft()->userSession->isAdmin()) {
            return true;
        }
    }

    public function init()
    {
        $modelsPath = craft()->config->get('modelsPath', 'theArchitect');
        if (!file_exists($modelsPath)) {
            mkdir($modelsPath);
        }
    }

    protected function defineSettings()
    {
        return array(
            'enableMigrations' => array(AttributeType::Bool, 'default' => null),
            'lastImport' => array(AttributeType::Number, 'default' => null),
        );
    }

    public function registerCpRoutes()
    {
        return array(
            'thearchitect/files' => array('action' => 'theArchitect/constructList'),
            'thearchitect/blueprint' => array('action' => 'theArchitect/blueprint'),
            'thearchitect/migrations' => array('action' => 'theArchitect/migrations'),
            'thearchitect/convert' => array('action' => 'theArchitect/convert'),
        );
    }
}
