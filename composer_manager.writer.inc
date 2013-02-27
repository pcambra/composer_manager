<?php

/**
 * @file
 * Functions related to the creation of the consolidated composer.json file.
 */

/**
 * Builds the JSON array ccontaining the combined requirements of each module's
 * composer.json file.
 *
 * @return array
 *
 * @throws \RuntimeException
 */
function composer_manager_build_json() {
  $data = array();
  foreach (module_list() as $module) {

    $module_dir = drupal_get_path('module', $module);
    $composer_file = $module_dir . '/composer.json';
    $args = array('@file' => $composer_file);

    if (!file_exists($composer_file)) {
      continue;
    }

    if (!$json = @file_get_contents($composer_file)) {
      throw new \RuntimeException(t('Error reading file: @file', $args));
    }

    if (!$parsed = @drupal_json_decode($json)) {
      throw new \UnexpectedValueException(t('Expecting contents of file to be valid JSON: @file', $args));
    }

    if (!isset($parsed['require'])) {
      throw new \UnexpectedValueException(t('Expecting JSON to contain the "require" property: @file', $args));
    }

    if (!$data) {
      $data = array(
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
      if (isset($parsed[$key])) {
        $data[$key] = array_merge($data[$key], $parsed[$key]);
      }
    }

    // Take the lowest stability.
    if (isset($parsed['minimum-stability'])) {
      if (!isset($data['minimum-stability']) || -1 == composer_manager_compare_stability($parsed['minimum-stability'], $data['minimum-stability'])) {
        $data['minimum-stability'] = $parsed['minimum-stability'];
      }
    }
  }

  return $data;
}

/**
 * Writes the composer.json file in the specified directory.
 *
 * @param string $uri
 *   The URI of the directory that the composer.json file is being written to.
 * @param array $data
 *   A model of the JSON data being written.
 *
 * @throws \RuntimeException
 */
function composer_manager_put_file($uri, array $data) {
  if (!is_dir($uri)) {
    if (!drupal_mkdir($uri)) {
      throw new \RuntimeException(t('Error creating directory: @dir', array('@dir' => $uri)));
    }
    file_create_htaccess($uri, TRUE);
  }

  $file_uri = $uri . '/composer.json';
  if (!@file_put_contents($file_uri, drupal_json_encode($data))) {
    throw new \RuntimeException(t('Error writing composer.json file: @file', array('@file' => $file_uri)));
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
