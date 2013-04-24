<?php

/**
 * @file
 * Administrative settings for the Composer Manager module.
 */

/**
 * Administrative settings for the Composer Manager module.
 *
 * @see composer_manager_settings_form_validate()
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
    '#default_value' => variable_get('composer_manager_file_dir', file_default_scheme() . '://composer'),
    '#description' => t('The directory containing the composer.json file and where Composer commands are run.'),
  );

  $form['composer_manager_autobuild_file'] = array(
    '#title' => 'Automatically build the consolidated composer.json file',
    '#type' => 'checkbox',
    '#default_value' => variable_get('composer_manager_autobuild_file', 1),
    '#description' => t('Combine the requirements in all composer.json files that are included with contributed modules. Uncheck to manage a custom composer.json file in the vendor directory above.'),
  );

  $form['#validate'] = array('composer_manager_settings_form_validate');

  return system_settings_form($form);
}

/**
 * Form validation handler for composer_manager_settings_form().
 */
function composer_manager_settings_form_validate($form, &$form_state) {
  require_once __DIR__ . '/composer_manager.writer.inc';
  $autobuild_file = $form_state['values']['composer_manager_autobuild_file'];
  $file_dir = $form_state['values']['composer_manager_file_dir'];
  if ($autobuild_file && !composer_manager_prepare_directory($file_dir)) {
    form_set_error('composer_manager_file_dir', t('Conposer file directory must be writable'));
  }
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
    '#caption' => t('Installed Packages'),
  );

  $autobuild = variable_get('composer_manager_autobuild_file', 1);

  if (!$error) {
    $uri = variable_get('composer_manager_file_dir', file_default_scheme() . '://composer');
    if (file_exists($uri . '/composer.lock') || !$autobuild) {
      $build['packages']['#empty'] = t('No packages are installed.');
    }
    elseif (file_exists($uri . '/composer.json')) {
      $build['packages']['#empty'] = t('No packages are installed. Run <code>drush composer-manager install</code> to install the third-party libraries.');
    }
    else {
      $build['packages']['#empty'] = t('No packages are installed. Generate a composer.json file by clicking the "Rebuild composer.json file" button below.');
    }
  }
  else {
    $build['packages']['#empty'] = t('Error loading package information.');
  }

  $build['refresh_form'] = drupal_get_form('composer_manager_rebuild_form');

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
  $file_dir = variable_get('composer_manager_file_dir', file_default_scheme() . '://composer');

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Rebuild composer.json file'),
    '#disabled' => !is_dir($file_dir) || !is_writable($file_dir),
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
    $dir_uri = variable_get('composer_manager_file_dir', file_default_scheme() . '://composer');
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
