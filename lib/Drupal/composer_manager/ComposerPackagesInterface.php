<?php

/**
 * @file
 * Contains \Drupal\composer_manager\ComposerPackagesInterface.
 */

namespace Drupal\composer_manager;

interface ComposerPackagesInterface {

  /**
   * @return \Drupal\composer_manager\ComposerManagerInterface
   */
  public function getManager();

  /**
   * Reads installed package versions from the composer.lock file.
   *
   * @return array
   *   An associative array of package version information.
   *
   * @throws \RuntimeException
   */
  public function getInstalled();

  /**
   * Returns the packages, versions, and the modules that require them in the
   * composer.json files contained in contributed modules.
   *
   * @return array
   */
  public function getRequired();

  /**
   * Returns each installed packages dependents.
   *
   * @return array
   *   An associative array of installed packages to their dependents.
   *
   * @throws \RuntimeException
   */
  function getDependencies();

  /**
   * Returns a list of packages that need to be installed.
   *
   * @return array
   */
  function getInstallRequired();

  /**
   * Writes the consolidated composer.json file for all modules that require
   * third-party packages managed by Composer.
   *
   * @return int
   *
   * @throws \RuntimeException
   */
  public function writeComposerJson();
}
