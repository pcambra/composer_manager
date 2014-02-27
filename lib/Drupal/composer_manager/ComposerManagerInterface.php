<?php

/**
 * @file
 * Contains \Drupal\composer_manager\ComposerManager.
 */

namespace Drupal\composer_manager;

/**
 * Interface for manager objects.
 */
interface ComposerManagerInterface {

  /**
   * Returns TRUE if the passed name is a valid Composer package name.
   *
   * @param string $package_name
   *
   * @return bool
   */
  public function isValidPackageName($package_name);

  /**
   * Compares the passed minimum stability requirements.
   *
   * @return int
   *   Returns -1 if the first version is lower than the second, 0 if they are
   *   equal, and 1 if the second is lower.
   *
   * @throws \UnexpectedValueException
   */
  public function compareStability($a, $b);

  /**
   * Prepares and returns the realpath to the Composer file directory.
   *
   * @return string
   *
   * @throws \RuntimeException
   */
  public function getComposerFileDirectory();

  /**
   * Returns the consolidated composer.json file.
   *
   * @return \Drupal\composer_manager\ComposerFileInterface
   */
  public function getComposerJsonFile();

  /**
   * Returns consolidated composer.lock file.
   *
   * @return \Drupal\composer_manager\ComposerFileInterface
   */
  public function getComposerLockFile();

  /**
   * Reads the consolidated composer.lock file and parses in to a PHP array.
   *
   * @return array
   *
   * @throws \RuntimeException
   */
  public function readComposerLockFile();

  /**
   * Returns the absolute path to the vendor directory.
   *
   * @return string
   */
  public function getVendorDirectory();

  /**
   * Returns the absolute path to the autoload.php file.
   *
   * @return string
   */
  public function getAutoloadFilepath();

  /**
   * Registers the autoloader.
   *
   * @throws \RuntimeException
   */
  public function registerAutolaoder();
}
