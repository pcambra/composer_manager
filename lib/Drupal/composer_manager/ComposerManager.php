<?php

/**
 * @file
 * Contains \Drupal\composer_manager\ComposerManager.
 */

namespace Drupal\composer_manager;

use Drupal\Component\Utility\String;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Gets configuration settings and installed / required packages.
 */
class ComposerManager implements ComposerManagerInterface {

  const REGEX_PACKAGE = '@^[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]+$@';

  /**
   * The composer_manager.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface.
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\composer_manager\FilesystemInterface
   */
  protected $filesystem;

  /**
   * @var bool
   */
  protected $autoloaderRegistered = false;

  /**
   * Constructs a \Drupal\composer_manager\ComposerManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\composer_manager\FilesystemInterface $filesystem
   */
  public function __construct(ConfigFactory $config_factory, ModuleHandlerInterface $module_handler, FilesystemInterface $filesystem) {
    $this->config = $config_factory->get('composer_manager.settings');
    $this->moduleHandler = $module_handler;
    $this->filesystem = $filesystem;
  }

  /**
   * Returns TRUE if the passed name is a valid Composer package name.
   *
   * @param string $package_name
   *
   * @return bool
   */
  public function isValidPackageName($package_name) {
    return preg_match(self::REGEX_PACKAGE, $package_name);
  }

  /**
   * Compares the passed minimum stability requirements.
   *
   * @return int
   *   Returns -1 if the first version is lower than the second, 0 if they are
   *   equal, and 1 if the second is lower.
   *
   * @throws \UnexpectedValueException
   */
  public function compareStability($a, $b) {
    $number = array(
      'dev' => 0,
      'alpha' => 1,
      'beta' => 2,
      'RC' => 3,
      'rc' => 3,
      'stable' => 4,
    );

    if (!isset($number[$a]) || !isset($number[$b])) {
      throw new \UnexpectedValueException('Unexpected value for "minimum-stability"');
    }

    if ($number[$a] == $number[$b]) {
      return 0;
    }
    else {
      return $number[$a] < $number[$b] ? -1 : 1;
    }
  }

  /**
   * Prepares and returns the realpath to the Composer file directory.
   *
   * @return string
   *
   * @throws \RuntimeException
   */
  public function getComposerFileDirectory() {
    $directory = $this->config->get('file_dir');
    if (!$this->filesystem->prepareDirectory($directory)) {
      throw new \RuntimeException(String::format('Error creating directory: @directory', array('@directory' => $directory)));
    }
    if (!$realpath = drupal_realpath($directory)) {
      throw new \RuntimeException(String::format('Error resolving directory: @directory', array('@directory' => $directory)));
    }
    return $realpath;
  }

  /**
   * Returns the consolidated composer.json file.
   *
   * @return \Drupal\composer_manager\ComposerFileInterface
   */
  public function getComposerJsonFile() {
    return new ComposerFile($this->config->get('file_dir') . '/composer.json');
  }

  /**
   * Returns the consolidated composer.lock file.
   *
   * @return \Drupal\composer_manager\ComposerFileInterface
   */
  public function getComposerLockFile() {
    return new ComposerFile($this->config->get('file_dir') . '/composer.lock');
  }

  /**
   * Reads the consolidated composer.lock file and parses in to a PHP array.
   *
   * @return array
   *
   * @throws \RuntimeException
   */
  public function readComposerLockFile() {
    $lock_file = $this->getComposerLockFile();
    $filedata = $lock_file->exists() ? $lock_file->read() : array();
    return $filedata + array('packages' => array());
  }

  /**
   * Returns the absolute path to the vendor directory.
   *
   * @return string
   */
  public function getVendorDirectory() {
    $directory = $this->config->get('vendor_dir');
    if (!$this->filesystem->isAbsolutePath($directory)) {
      $directory = DRUPAL_ROOT . '/' . $directory;
    }
    return $directory;
  }

  /**
   * Returns the absolute path to the autoload.php file.
   *
   * @return string
   */
  public function getAutoloadFilepath() {
    return $this->getVendorDirectory() . '/autoload.php';
  }

  /**
   * Registers the autoloader.
   *
   * @throws \RuntimeException
   */
  public function registerAutolaoder() {
    if (!$this->autoloaderRegistered) {

      $filepath = $this->getAutoloadFilepath();
      if (!file_exists($filepath)) {
        throw new \RuntimeException(String::format('Autoloader not found: @filepath', array('@filepath' => $filepath)));
      }

      $this->autoloaderRegistered = TRUE;
      require_once $filepath;
    }
  }
}
