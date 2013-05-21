<?php

/**
 * @file
 * Drush hook implementations for the Composer Manager module.
 */

/**
 * Implements hook_drush_command().
 */
function composer_manager_drush_command() {
  $items = array();

  $items['composer-manager'] = array(
    'description' => 'Executes a composer command in the directory containing the composer.json file.',
    'allow-additional-options' => TRUE,
    'aliases' => array('composer-execute'),
    'arguments' => array(
      'command' => 'The command to run through the Composer Manager composer.json file. Defaults to "install".',
    ),
  );

  $items['composer-json-rebuild'] = array(
    'description' => 'Rebuilds the consolidated composer.json file.',
    'allow-additional-options' => TRUE,
    'aliases' => array(
      'composer-rebuild',
      'composer-rebuild-file',
    ),
  );

  return $items;
}

/**
 * Executes a Composer command on Composer Manager's composer.json file.
 *
 * @param $command
 *   Which command to execute on Composer Manager's composer.json file.
 */
function drush_composer_manager($command = 'install') {
  $uri = variable_get('composer_manager_file_dir', file_default_scheme() . '://composer');
  if (!$dir = drupal_realpath($uri)) {
    return drush_set_error(dt('Error resolving path: @uri', array('@uri' => $uri)));
  }

  // Check to see if the composer.json file is available.
  if (!file_exists($dir . '/composer.json')) {
    // Ask the user would like to install Drush Composer.
    if (drush_confirm(dt('The composer.json file is missing. Would you like to re-build it?'))) {
      if (drush_invoke('composer-rebuild-file') === FALSE) {
        return FALSE;
      }
    }
    else {
      return drush_set_error(dt('Missing composer.json file: Run `drush composer-rebuild-file`.'));
    }
  }

  // Check if Drush Composer is available.
  if (!drush_is_command('composer')) {
    // Ask the user would like to install Drush Composer.
    if (!drush_confirm(dt('Download and install Composer?'))) {
      drush_print(dt('Drush Composer installation aborted.'));
      return TRUE;
    }

    // Download the Drush extension.
    if (drush_invoke('dl', array('composer-8.x-1.0')) === FALSE) {
      return drush_set_error(dt('Failed installing Drush Composer. Install manually by visiting http://drupal.org/project/composer .'));
    }

    // Clear the caches by reloading the available command files.
    _drush_find_commandfiles(DRUSH_BOOTSTRAP_DRUSH);
  }

  // Mock the arguments passed to Composer.
  $workingDir = '--working-dir=' . escapeshellcmd($dir);
  $_SERVER['argv'] = array();
  return drush_invoke('composer', array($command, $workingDir));
}

/**
 * Rebuilds the consolidated composer.json file.
 */
function drush_composer_manager_composer_json_rebuild() {
  $success = composer_manager_write_file();
  if ($success) {
    drush_log(dt('Completed building composer.json file.'), 'ok');
    return TRUE;
  }
  else {
    return drush_set_error(dt('Error building composer.json file.'));
  }
}

/**
 * Drush hook; Called after a project is enabled.
 *
 * Rebuild and re-install the Composer dependencies.
 */
function drush_composer_manager_post_pm_enable() {
  // Ask whether to rebuild the Composer dependencies.
  if (drush_confirm(dt('Build and install Composer dependencies?'))) {
    // Rebuild the composer.json file.
    if (drush_composer_manager_composer_json_rebuild()) {
      // Run the Composer installation.
      drush_composer_manager('update');
    }
  }
}
