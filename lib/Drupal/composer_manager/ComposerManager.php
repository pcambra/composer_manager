<?php

/**
 * @file
 * Contains \Drupal\composer_manager\ComposerManager.
 */

namespace Drupal\composer_manager;

use Drupal\Component\Utility\Json;
use Drupal\Core\Config\ConfigFactory;

/**
 * Manages composer files for contrib modules.
 * @todo Find better name.
 */
class ComposerManager {

  /**
   * The composer_manager.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a \Drupal\composer_manager\ComposerManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->config = $config_factory->get('composer_manager.settings');
  }

  /**
   * Loads the composer.lock file if it exists.
   *
   * @return array
   *   The parsed JSON, and empty array if the file doesn't exist.
   *
   * @throws \RuntimeException
   *   Thrown when the file could not be read/parsed.
   */
  public function loadLockFile() {
    // @todo Do we actually need static caching
    // @todo If needed convert it to a member variable on the class.
    $json = &drupal_static(__FUNCTION__);
    if ($json === NULL) {
      $dir_uri = $this->config->get('file_dir');
      $file_uri = $dir_uri . '/composer.lock';

      if (file_exists($file_uri)) {
        if (!$filedata = @file_get_contents($file_uri)) {
          throw new \RuntimeException(t('Error reading file: @file', array('@file' => $file_uri)));
        }
        if (!$json = Json::decode($filedata)) {
          throw new \RuntimeException(t('Error parsing file: @file', array('@file' => $file_uri)));
        }
      }
      else {
        $json =  array();
      }
    }
    return $json;
  }

  /**
   * Reads installed package versions from the composer.lock file.
   *
   * NOTE: Tried using `composer show -i`, but it didn't return the versions or
   * descriptions for some strange reason even though it does on the command line.
   *
   * @return array
   *   An associative array of package version information.
   *
   * @throws \RuntimeException
   */
  public function getInstalledPackages() {
    // @todo Do we actually need static caching
    // @todo If needed convert it to a member variable on the class.
    $installed = &drupal_static(__FUNCTION__, NULL);
    if (NULL === $installed) {
      $installed = array();

      $json = $this->loadLockFile();
      if (isset($json['packages'])) {
        foreach ($json['packages'] as $package) {
          $installed[$package['name']] = array(
            'version' => $package['version'],
            'description' => !empty($package['description']) ? $package['description'] : '',
            'homepage' => !empty($package['homepage']) ? $package['homepage'] : '',
          );
        }
      }

      ksort($installed);
    }

    return $installed;
  }

  /**
   * Returns each installed packages dependents.
   *
   * @return array
   *   An associative array of installed packages to their dependents.
   *
   * @throws \RuntimeException
   */
  public function getPackageDependencies() {
    $dependents = array();

    $json = $this->loadLockFile();
    if (isset($json['packages'])) {
      foreach ($json['packages'] as $package) {
        if (!empty($package['require'])) {
          foreach ($package['require'] as $dependent => $version) {
            $dependents[$dependent][] = $package['name'];
          }
        }
      }
    }

    return $dependents;
  }

  /**
   * Returns the packages, versions, and the modules that require them in the
   * composer.json files contained in contributed modules.
   *
   * @return array
   */
  public function getRequiredPackages() {
    $required = &drupal_static(__FUNCTION__, NULL);
    if (NULL === $required) {
      \Drupal::moduleHandler()->loadInclude('composer_manager', 'writer.inc');

      // Gathers package versions.
      $required = array();
      $data = composer_manager_fetch_data();
      foreach ($data as $module => $json) {
        if (isset($json['require'])) {
          foreach ($json['require'] as $package => $version) {
            $pattern = '@^[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]+$@';
            if (preg_match($pattern, $package)) {
              if (!isset($required[$package])) {
                $required[$package][$version] = array();
              }
              $required[$package][$version][] = $module;
            }
          }
        }
      }

      ksort($required);
    }

    return $required;
  }

}
