<?php

/**
 * @file
 * Hooks provided by the Composer Manager module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow modules to alter the json data in the composer file.
 *
 * @param array &$data
 *   The array that will be converted to JSON, which is the contents of the
 *   composer.json file.
 */
function hook_composer_json_alter(&$data) {
  $data['minimum-stability'] = 'dev';
}

/**
 * @} End of "addtogroup hooks".
 */
