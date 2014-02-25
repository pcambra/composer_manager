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

  const REGEX_PACKAGE = '@^[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]+$@';

  /**
   * The composer_manager.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The contents of the composer.lock file in the composer dir.
   *
   * @var array
   */
  private $lockFile;

  /**
   * The installed package versions from the composer.lock file.
   *
   * @var array
   */
  private $installedPackages;

  /**
   * Package requirement information.
   *
   * @var array
   */
  private $requiredPackages;

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
   * Returns TRUE if that passed name is a package.
   *
   * @param string $name
   *
   * @return bool
   */
  public function isPackage($name) {
    return preg_match(self::REGEX_PACKAGE, $name);
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
    if (!isset($this->lockFile)) {

      $filepath = $this->config->get('file_dir') . '/composer.lock';

      if (file_exists($filepath)) {
        if (!$filedata = @file_get_contents($filepath)) {
          throw new \RuntimeException(t('Error reading file: @filepath', array('@filepath' => $filepath)));
        }
        if (!$this->lockFile = Json::decode($filedata)) {
          throw new \RuntimeException(t('Error parsing file: @filepath', array('@filepath' => $filepath)));
        }
      }
      else {
        $this->lockFile = array();
      }

      if (!isset($this->lockFile['packages'])) {
        $this->lockFile['packages'] = array();
      }

    }

    return $this->lockFile;
  }

  /**
   * Unsets the property that stores the contents of the composer.lock file.
   */
  public function resetLockFile() {
    unset($this->lockFile);
  }

  /**
   * Reads installed package versions from the composer.lock file.
   *
   * NOTE: Tried using `composer show -i`, but it didn't return the versions or
   * descriptions for some reason even though it does on the command line.
   *
   * @return array
   *   An associative array of package version information.
   *
   * @throws \RuntimeException
   */
  public function getInstalledPackages() {
    if (!isset($this->installedPackages)) {

      $this->installedPackages = array();
      $lock_file = $this->loadLockFile();

      foreach ($lock_file['packages'] as $package) {
        $this->installedPackages[$package['name']] = array(
          'version' => $package['version'],
          'description' => !empty($package['description']) ? $package['description'] : '',
          'homepage' => !empty($package['homepage']) ? $package['homepage'] : '',
        );
      }

      ksort($this->installedPackages);
    }

    return $this->installedPackages;
  }

  /**
   * Unsets the property that stores the installed packages read from the
   * composer.lock file.
   */
  public function resetInstalledPackages() {
    unset($this->installedPackages);
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

    $lock_file = $this->loadLockFile();
    $lock_file += array('packages' => array());

    foreach ($lock_file['packages'] as $package) {
      if (!empty($package['require'])) {
        foreach ($package['require'] as $dependent => $version) {
          $dependents[$dependent][] = $package['name'];
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
    if (!isset($this->requiredPackages)) {

      $this->requiredPackages = array();
      \Drupal::moduleHandler()->loadInclude('composer_manager', 'writer.inc');

      // Gathers package versions.
      $data = composer_manager_fetch_data();
      foreach ($data as $module => $json) {
        $json += array('require' => array());
        foreach ($json['require'] as $package => $version) {
          if ($this->isPackage($package)) {
            if (!isset($this->requiredPackages[$package])) {
              $this->requiredPackages[$package][$version] = array();
            }
            $this->requiredPackages[$package][$version][] = $module;
          }
        }
      }

      ksort($this->requiredPackages);
    }

    return $this->requiredPackages;
  }

  /**
   * Unsets the property that stores package requirement information.
   */
  public function resetRequiredPackages() {
    unset($this->requiredPackages);
  }

}
