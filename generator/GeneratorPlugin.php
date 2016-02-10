<?php
namespace Craft;

/**
 * Class GeneratorPlugin
 *
 * @package Craft
 */
class GeneratorPlugin extends BasePlugin
{
    /**
     * getName
     * @return null|string
     */
    public function getName()
    {
        return Craft::t('Generator');
    }

    /**
     * getVersion
     * @return string
     */
    public function getVersion()
    {
        return '1.0';
    }

    /**
     * getDeveloper
     * @return string
     */
    public function getDeveloper()
    {
        return 'Pennebaker';
    }

    /**
     * getDeveloperUrl
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'http://pennebaker.com';
    }

    /**
     * hasCpSection
     * @return boolean
     */
	public function hasCpSection()
	{
		if (craft()->userSession->isAdmin())
		{
			return true;
		}
	}
}
