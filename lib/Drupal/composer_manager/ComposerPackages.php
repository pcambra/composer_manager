<?php

/**
 * @file
 * Contains \Drupal\composer_manager\ComposerPackages.
 */

namespace Drupal\composer_manager;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;

class ComposerPackages implements ComposerPackagesInterface {

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface.
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\composer_manager\FilesystemInterface
   */
  protected $filesystem;

  /**
   * @var \Drupal\composer_manager\ComposerManagerInterface
   */
  protected $manager;

  /**
   * The composer.lock file data parsed as a PHP array.
   *
   * @var array
   */
  private $composerLockFiledata;

  /**
   * Whether the composer.json file was written during this request.
   *
   * @var bool
   */
  protected $composerJsonWritten = FALSE;

  /**
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\composer_manager\FilesystemInterface $filesystem
   * @param \Drupal\composer_manager\ComposerManagerInterface $manager
   */
  public function __construct(LockBackendInterface $lock, ModuleHandlerInterface $module_handler, FilesystemInterface $filesystem, ComposerManagerInterface $manager) {
    $this->lock = $lock;
    $this->moduleHandler = $module_handler;
    $this->filesystem = $filesystem;
    $this->manager = $manager;
  }

  /**
   * @return \Drupal\composer_manager\ComposerManagerInterface
   */
  public function getManager() {
    return $this->manager;
  }

  /**
   * Returns the composer.lock file data parsed as a PHP array.
   *
   * @return array
   */
  public function getComposerLockFiledata() {
    if (!isset($this->composerLockFiledata)) {
      $this->composerLockFiledata = $this->manager->readComposerLockFile();
    }
    return $this->composerLockFiledata;
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
  public function getInstalled() {
    $packages = array();

    $filedata = $this->getComposerLockFiledata();
    foreach ($filedata['packages'] as $package) {
      $packages[$package['name']] = array(
        'version' => $package['version'],
        'description' => !empty($package['description']) ? $package['description'] : '',
        'homepage' => !empty($package['homepage']) ? $package['homepage'] : '',
      );
    }

    ksort($packages);
    return $packages;
  }

  /**
   * Returns the packages, versions, and the modules that require them in the
   * composer.json files contained in contributed modules.
   *
   * @return array
   */
  public function getRequired() {
    $packages = array();

    $filedata = $this->getComposerJsonFiledata();
    foreach ($filedata as $module => $json) {
      $json += array('require' => array());
      foreach ($json['require'] as $package_name => $version) {
        if ($this->manager->isValidPackageName($package_name)) {
          if (!isset($packages[$package_name])) {
            $packages[$package_name][$version] = array();
          }
          $packages[$package_name][$version][] = $module;
        }
      }
    }

    ksort($packages);
    return $packages;
  }

  /**
   * Returns each installed packages dependents.
   *
   * @return array
   *   An associative array of installed packages to their dependents.
   *
   * @throws \RuntimeException
   */
  function getDependencies() {
    $packages = array();

    $filedata = $this->getComposerLockFiledata();
    foreach ($filedata['packages'] as $package) {
      if (!empty($package['require'])) {
        foreach ($package['require'] as $dependent => $version) {
          $packages[$dependent][] = $package['name'];
        }
      }
    }

    return $packages;
  }

  /**
   * Returns a list of packages that need to be installed.
   *
   * @return array
   */
  function getInstallRequired() {
    $packages = array();

    $required = $this->getRequired();
    $installed = $this->getInstalled();
    $combined = array_unique(array_merge(array_keys($required), array_keys($installed)));

    foreach ($combined as $package_name) {
      if (!isset($installed[$package_name])) {
        $packages[] = $package_name;
      }
    }

    return $packages;
  }

  /**
   * Returns the vendor directory relative to the composer file directory.
   *
   * @return string
   *
   * @throws \RuntimeException
   */
  public function getRelativeVendorDirectory() {
    return $this->filesystem->makePathRelative(
      $this->manager->getVendorDirectory(),
      $this->manager->getComposerFileDirectory()
    );
  }

  /**
   * Writes the consolidated composer.json file for all modules that require
   * third-party packages managed by Composer.
   *
   * @return int
   *
   * @throws \RuntimeException
   */
  public function writeComposerJsonFile() {
    $bytes = $this->composerJsonWritten = FALSE;

    // Ensure only one process runs at a time. 10 seconds is more than enough.
    // It is rare that a conflict will happen, and it isn't mission critical
    // that we wait for the lock to release and regenerate the file again.
    if (!$this->lock->acquire(__FUNCTION__, 10)) {
      throw new \RuntimeException('Timeout waiting for lock');
    }

    try {
      $composer_json = $this->manager->getComposerJsonFile();
      $filedata = $this->getComposerJsonFiledata();

      $bytes = $composer_json->write($this->mergeComposerJsonFiledata($filedata));
      $this->composerJsonWritten = ($bytes !== FALSE);

      $this->lock->release(__FUNCTION__);
    }
    catch (\RuntimeException $e) {
      $this->lock->release(__FUNCTION__);
      throw $e;
    }

    return $bytes;
  }

  /**
   * Returns TRUE if the composer.json file was written in this request.
   *
   * @return bool
   *
   * @throws \RuntimeException
   */
  public function composerJsonFileWritten() {
    return $this->composerJsonWritten;
  }

  /**
   * Fetches the data in each module's composer.json file.
   *
   * @return array
   *
   * @throws \RuntimeException
   */
  function getComposerJsonFiledata() {
    $filedata = array();

    $module_list = $this->moduleHandler->getModuleList();
    foreach ($module_list as $module_name => $filename) {
      $filepath = drupal_get_path('module', $module_name) . '/composer.json';
      $composer_json = new ComposerFile($filepath);
      if ($composer_json->exists()) {
        $filedata[$module_name] = $composer_json->read();
      }
    }

    return $filedata;
  }

  /**
   * Builds the JSON array containing the combined requirements of each module's
   * composer.json file.
   *
   * @param array $filedata
   *   An array of JSON arrays parsed from composer.json files.
   *
   * @return array
   *   The consolidated JSON array that will be written to a compsoer.json file.
   *
   * @throws \RuntimeException
   */
  public function mergeComposerJsonFiledata(array $filedata) {
    $merged = array();
    foreach ($filedata as $module => $json) {

      if (!$merged) {
        $merged = array('require' => array());
        $directory = $this->getRelativeVendorDirectory();
        if (0 !== strlen($directory) && 'vendor' != $directory) {
          $merged['config']['vendor-dir'] = $directory;
        }
      }

      // @todo Detect duplicates, maybe add an "ignore" list. Figure out if this
      // encompases all keys that should be merged.
      $to_merge = array(
        'require',
        'require-dev',
        'conflict',
        'replace',
        'provide',
        'suggest',
        'repositories',
      );

      foreach ($to_merge as $key) {
        if (isset($json[$key])) {
          if (isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = array_merge($merged[$key], $json[$key]);
          }
          else {
            $merged[$key] = $json[$key];
          }
        }
      }

      // Merge in the "psr-0" autoload options.
      if (isset($json['autoload']['psr-0'])) {
        $namespaces = (array) $json['autoload']['psr-0'];
        foreach ($json['autoload']['psr-0'] as $namesapce => $dirs) {
          $dirs = (array) $dirs;
          array_walk($dirs, 'composer_manager_relative_autoload_path', $module);
          if (!isset($merged['autoload']['psr-0'][$namesapce])) {
            $merged['autoload']['psr-0'][$namesapce] = array();
          }
          $merged['autoload']['psr-0'][$namesapce] = array_merge(
            $merged['autoload']['psr-0'][$namesapce], $dirs
          );
        }
      }

      // Merge in the "classmap" and "files" autoload options.
      $autoload_options = array('classmap', 'files');
      foreach ($autoload_options as $option) {
        if (isset($json['autoload'][$option])) {
          $dirs = (array) $json['autoload'][$option];
          array_walk($dirs, 'composer_manager_relative_autoload_path', $module);
          if (!isset($merged['autoload'][$option])) {
            $merged['autoload'][$option] = array();
          }
          $merged['autoload'][$option] = array_merge(
            $merged['autoload'][$option], $dirs
          );
        }
      }

      // Take the lowest stability.
      if (isset($json['minimum-stability'])) {
        if (!isset($merged['minimum-stability']) || -1 == $this->manager->compareStability($json['minimum-stability'], $merged['minimum-stability'])) {
          $merged['minimum-stability'] = $json['minimum-stability'];
        }
      }
    }

    $this->moduleHandler->alter('composer_json', $merged);
    return $merged;
  }

  /**
   * Returns TRUE if at least one passed modules has a composer.json file,
   * which flags that the list of packages managed by Composer Manager have
   * changed.
   *
   * @param array $modules
   *   The list of modules being scanned for composer.json files, usually a list
   *   of modules that were installed or uninstalled.
   *
   * @return bool
   */
  public function haveChanges(array $modules) {
    foreach ($modules as $module) {
      $filepath = drupal_get_path('module', $module) . '/composer.json';
      $composer_json = new ComposerFile($filepath);
      if ($composer_json->exists()) {
        return TRUE;
      }
    }
    return FALSE;
  }
}
