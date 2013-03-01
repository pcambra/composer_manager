<?php

/**
 * @file
 * Administrative settings for the Composer Manager module.
 */

/**
 * Administrative settings for the Composer Manager module.
 *
 * @ingroup forms
 */
function composer_manager_settings_form($form, &$form_state) {

  $form['composer_manager_vendor_dir'] = array(
    '#title' => 'Vendor Directory',
    '#type' => 'textfield',
    '#default_value' => variable_get('composer_manager_vendor_dir', 'sites/all/libraries/composer'),
    '#description' => t('The relative or absolute path to the vendor directory containing the Composer packages and autoload.php file.'),
  );

  $form['composer_manager_file_dir'] = array(
    '#title' => 'Composer File Directory',
    '#type' => 'textfield',
    '#default_value' => variable_get('composer_manager_file_dir', 'public://composer'),
    '#description' => t('The directory containing the composer.json file and where Composer commands are run.'),
  );

  $form['composer_manager_generate_file'] = array(
    '#title' => 'Automatically generate a consolidated composer.json file',
    '#type' => 'checkbox',
    '#default_value' => variable_get('composer_manager_generate_file', 1),
    '#description' => t('Combine the requirements in all composer.json files that are included with contributed modules. Uncheck to manage a custom composer.json file in the vendor directory above.'),
  );

  return system_settings_form($form);
}

/**
 * Page callback; Shows the status of all packages required by contrib.
 */
function composer_manager_packages_page() {
  $build = array();
  $error = FALSE;

  $header = array(
    'package' => t('Package'),
    'version' => t('Version'),
  );

  try {
    $packages = composer_manager_show_packages();
  }
  catch (\RuntimeException $e) {
    $error = TRUE;
    drupal_set_message(filter_xss_admin($e->getMessage()));
    watchdog_exception('composer_manager', $e);
    $packages = array();
  }

  $rows = array();
  foreach ($packages as $package_name => $package) {

    // @todo Use a theme function.
    if (!empty($package['homepage'])) {
      $options = array('attributes' => array('target' => '_blank'));
      $name = l($package_name, $package['homepage'], $options);
    }
    else {
      $name = check_plain($package_name);
    }
    if (!empty($package['description'])) {
      $name .= '<div class="description">' . check_plain($package['description']) . '</div>';
    }

    $rows[] = array(
      'package' => $name,
      'version' => check_plain($package['version']),
    );
  }

  $build['packages'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );

  if (!$error) {
    $generate_file = variable_get('composer_manager_generate_file', 1);
    $uri = variable_get('composer_manager_file_dir', 'public://composer');

    if (file_exists($uri . '/composer.lock') || !$generate_file) {
      $build['packages']['#empty'] = t('No packages are installed.');
    }
    elseif (file_exists($uri . '/composer.json')) {
      $build['packages']['#empty'] = t('No packages are installed. Run <code>drush composer install</code> to install the third-party libraries.');
    }
    else {
      $build['packages']['#empty'] = t('No packages are installed. Generate a composer.json file by clicking the "Rebuild composer.json file" button below.');
    }
  }
  else {
    $build['packages']['#empty'] = t('Error loading package information.');
  }

  if (variable_get('composer_manager_generate_file', 1)) {
    $build['refresh_form'] = drupal_get_form('composer_manager_rebuild_form');
  }

  return $build;
}

/**
 * Exposes a button that forces a rebuild of the composer.json file.
 *
 * @see composer_manager_rebuild_form_submit()
 *
 * @ingroup forms
 */
function composer_manager_rebuild_form($form, &$form_state) {

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Rebuild composer.json file'),
  );

  $form['#submit'] = array('composer_manager_rebuild_form_submit');

  return $form;
}

/**
 * Form submission handler for composer_manager_rebuild_form().
 */
function composer_manager_rebuild_form_submit($form, &$form_state) {
  composer_manager_write_file();
}

/**
 * Loads the composer.lock file if it exists.
 *
 * @return array
 *   The parsed JSON, and empty array if the file doesn't exist.
 *
 * @throws \RuntimeException
 */
function composer_manager_lockfile_load() {
  $json = &drupal_static(__FUNCTION__);
  if ($json === NULL) {
    $dir_uri = variable_get('composer_manager_file_dir', 'public://composer');
    $file_uri = $dir_uri . '/composer.lock';

    if (file_exists($file_uri)) {
      if (!$filedata = @file_get_contents($file_uri)) {
        throw new \RuntimeException(t('Error reading file: @file', array('@file' => $file_uri)));
      }
      if (!$json = drupal_json_decode($filedata)) {
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
 * Reads package versions from the composer.lock file.
 *
 * NOTE: Tried using `composer show -i`, but it didn't return the versions or
 * descriptions for some strange reason even though it does on the command line.
 *
 * @return array|FALSE
 *   An associative array of package version information, FALSE if the
 *   composer.lock file is not written or readable.
 *
 * @throws \RuntimeException
 */
function composer_manager_show_packages() {
  $package_info = array();

  $json = composer_manager_lockfile_load();
  if (isset($json['packages'])) {
    foreach ($json['packages'] as $package) {
      $package_info[$package['name']] = array(
        'version' => $package['version'],
        'description' => !empty($package['description']) ? $package['description'] : '',
        'homepage' => !empty($package['homepage']) ? $package['homepage'] : '',
      );
    }
  }

  ksort($package_info);
  return $package_info;
}

/**
 * Executes a composer command and captures the output.
 *
 * @param string $options
 *   The command args and options without the "composer" argument, for exmaple
 *   `update --dry-run` or `show -i`.
 *
 * @return string
 *   The output returned by the command.
 *
 * @throws \RuntimeException
 */
function composer_manager_run_command($options, &$stderr = NULL) {
  $descriptors = array(
    1 => array("pipe", "w"),
    2 => array("pipe", "w"),
  );

  $uri = variable_get('composer_manager_file_dir', 'public://composer');

  if (!$cwd = drupal_realpath($uri)) {
    throw new \RuntimeException(t('Error resolving path: @uri', array('@uri' => $uri)));
  }

  $command = 'php composer.phar ' . $options;
  $process = proc_open($command, $descriptors, $pipes, $cwd);
  if (!is_resource($process)) {
    throw new \RuntimeException(t('Error creating process.'));
  }

  $stdout = stream_get_contents($pipes[1]);
  fclose($pipes[1]);

  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[2]);

  proc_close($process);
  return $stdout;
}
