<?php

/**
 * The Architect by Pennebaker
 *
 * @author      Pennebaker <http://pennebaker.com>
 * @package     The Architect
 * @copyright   Copyright (c) 2016, Pennebaker
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 * @link        https://github.com/pennebaker/craft-thearchitect
 */


/**
 * Configuration file for The Architect
 *
 * Override this by placing a file named 'thearchitect.php' inside your config folder and override variables as needed.
 * Multi-environment settings work in this file the same way as in general.php or db.php
 */

return array(
    'modelsPath' => str_replace('plugins', 'config', __dir__.'/'),
);
