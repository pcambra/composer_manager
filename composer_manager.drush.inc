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

  $items['composer-execute'] = array(
    'description' => 'Executes a composer command in the directory containing the composer.json file.',
    'allow-additional-options' => TRUE,
    'aliases' => array('composer'),
  );

  $items['composer-rebuild-file'] = array(
    'description' => 'Rebuilds the consolidated composer.json file.',
    'allow-additional-options' => TRUE,
  );

  return $items;
}

/**
 * Executes a composer command.
 */
function drush_composer_manager_composer_execute() {
  $uri = variable_get('composer_manager_file_dir', 'public://composer');
  if (!$dir = drupal_realpath($uri)) {
    return drush_set_error(dt('Error resolving path: @uri', array('@uri' => $uri)));
  }

  $cwd = getcwd();
  drush_op('chdir', $dir);

  if (!file_exists('composer.json')) {
    return drush_set_error(dt('Missing composer.json file: Run `drush composer-rebuild-file`.'));
  }

  // @todo We need to be more flexible here.
  if (!file_exists('composer.phar')) {
    if (!drush_confirm(dt('Download and install Composer?'))) {
      drush_print(dt('Composer installation aborted.'));
      return TRUE;
    }
    drush_download_file('https://getcomposer.org/installer');
    drush_op_system('php installer');
  }

  $options = drush_get_original_cli_args_and_options();
  $command = 'php composer.phar ' . implode(' ', $options);
  drush_op_system($command);

  drush_op('chdir', $cwd);
  return TRUE;
}

/**
 * Rebuilds the consolidated composer.json file.
 */
function drush_composer_manager_composer_rebuild_file() {
  $success = composer_manager_write_file();
  if ($success) {
    drush_log(dt('Completed generating composer.json file.'), 'ok');
    return TRUE;
  }
  else {
    return drush_set_error(dt('Error generating composer.json file.'));
  }
}
