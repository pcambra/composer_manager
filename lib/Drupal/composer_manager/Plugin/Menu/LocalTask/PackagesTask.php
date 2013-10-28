<?php

/**
 * @file
 * Contains \Drupal\composer_manager\Plugin\Menu\LocalTask\PackagesTask.
 */

namespace Drupal\composer_manager\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskBase;
use Drupal\Core\Annotation\Menu\LocalTask;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a local task for the composer manager packages page.
 *
 * @LocalTask(
 *   id = "composer_manager_packages",
 *   route_name = "composer_manager_packages_page",
 *   title = @Translation("Packages"),
 *   tab_root_id = "composer_manager_packages"
 * )
 */
class PackagesTask extends LocalTaskBase {

}
