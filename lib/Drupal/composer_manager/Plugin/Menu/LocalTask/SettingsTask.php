<?php

/**
 * @file
 * Contains \Drupal\composer_manager\Plugin\Menu\LocalTask\SettingsTask.
 */

namespace Drupal\composer_manager\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskBase;
use Drupal\Core\Annotation\Menu\LocalTask;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a local task for the composer manager settings page.
 *
 * @LocalTask(
 *   id = "composer_manager_settings",
 *   route_name = "composer_manager_settings",
 *   title = @Translation("Settings"),
 *   tab_root_id = "composer_manager_packages"
 * )
 */
class SettingsTask extends LocalTaskBase {

}
