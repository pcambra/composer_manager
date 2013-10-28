<?php

/**
 * @file
 * Administrative settings for the Composer Manager module.
 */

/**
 * Loads the composer.lock file if it exists.
 *
 * @return array
 *   The parsed JSON, and empty array if the file doesn't exist.
 *
 * @throws \RuntimeException
 */
function composer_manager_lockfile_load() {
  return Drupal::service('composer_manager')->loadLockFile();
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
function composer_manager_installed_packages() {
  return Drupal::service('composer_manager')->getInstalledPackages();
}

/**
 * Returns each installed packages dependents.
 *
 * @return array
 *   An associative array of installed packages to their dependents.
 *
 * @throws \RuntimeException
 */
function composer_manager_package_dependents() {
  return Drupal::service('composer_manager')->getPackageDependencies();
}

/**
 * Returns the packages, versions, and the modules that require them in the
 * composer.json files contained in contributed modules.
 *
 * @return array
 */
function composer_manager_required_packages() {
  return Drupal::service('composer_manager')->getRequiredPackages();
}
