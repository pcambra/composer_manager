<?php

/**
 * @file
 * Functions related to the creation of the consolidated composer.json file.
 */

/**
 * Fetches the data in each module's composer.json file.
 *
 * @return array
 *
 * @throws \RuntimeException
 */
function composer_manager_fetch_data() {
  $data = array();
  foreach (module_list() as $module) {

    $module_dir = drupal_get_path('module', $module);
    $composer_file = $module_dir . '/composer.json';
    $args = array('@file' => $composer_file);

    if (!file_exists($composer_file)) {
      continue;
    }

    if (!$filedata = @file_get_contents($composer_file)) {
      throw new \RuntimeException(t('Error reading file: @file', $args));
    }

    if (!$json = @drupal_json_decode($filedata)) {
      throw new \UnexpectedValueException(t('Expecting contents of file to be valid JSON: @file', $args));
    }

    $data[] = $json;
  }
  return $data;
}

/**
 * Builds the JSON array ccontaining the combined requirements of each module's
 * composer.json file.
 *
 * @param array $data
 *   An array of JSON arrays parsed from composer.json files. This is usually
 *   the return value of composer_manager_fetch_data().
 *
 * @return array
 *   The consolidated JSON array that will be written to a compsoer.json file.
 *
 * @throws \RuntimeException
 */
function composer_manager_build_json(array $data) {
  $combined = array();
  foreach ($data as $json) {

    // We will only merge the module's composer.json if it has some additional
    // dependencies stated in "require".
    if (!isset($json['require'])) {
      continue;
    }

    if (!$combined) {
      $combined = array(
        'require' => array(),
        'config' => array('vendor-dir' => composer_manager_vendor_dir()),
      );
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
        $combined[$key] = array_merge($combined[$key], $json[$key]);
      }
    }

    // Take the lowest stability.
    if (isset($json['minimum-stability'])) {
      if (!isset($combined['minimum-stability']) || -1 == composer_manager_compare_stability($json['minimum-stability'], $combined['minimum-stability'])) {
        $combined['minimum-stability'] = $json['minimum-stability'];
      }
    }
  }

  drupal_alter('composer_json', $combined);
  return $combined;
}

/**
 * Writes the composer.json file in the specified directory.
 *
 * @param string $dir_uri
 *   The URI of the directory that the composer.json file is being written to.
 *   This is usually the "composer_manager_file_dir" system variable.
 * @param array $json
 *   A model of the JSON data being written. This is usually the return value of
 *   composer_manager_build_json().
 *
 * @throws \RuntimeException
 */
function composer_manager_put_file($dir_uri, array $json) {
  if (!is_dir($dir_uri)) {
    if (!drupal_mkdir($dir_uri)) {
      throw new \RuntimeException(t('Error creating directory: @dir', array('@dir' => $dir_uri)));
    }
    file_create_htaccess($dir_uri, TRUE);
  }

  $file_uri = $dir_uri . '/composer.json';
  if (!@file_put_contents($file_uri, drupal_json_encode($json))) {
    throw new \RuntimeException(t('Error writing composer.json file: @file', array('@file' => $file_uri)));
  }

  // Displays a message to admins that a file was written.
  if (user_access('administer site configuration')) {
    $command = file_exists($dir_uri . '/composer.lock') ? 'update' : 'install';
    drupal_set_message(t('A composer.json file was written to @path.', array('@path' => drupal_realpath($dir_uri))));
    drupal_set_message(t('It is strongly recommended to run a Composer !command command in the directory above.', array('!command' => $command)));
  }
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
function composer_manager_compare_stability($a, $b) {
  $number = array(
    'dev' => 0,
    'alpha' => 1,
    'beta' => 2,
    'RC' => 3,
    'rc' => 3,
    'stable' => 4,
  );

  if (!isset($number[$a]) || !isset($number[$b])) {
    throw new \UnexpectedValueException(t('Unexpected value for "minimum-stability"'));
  }

  if ($number[$a] == $number[$b]) {
    return 0;
  }
  else {
    return $number[$a] < $number[$b] ? -1 : 1;
  }
}
