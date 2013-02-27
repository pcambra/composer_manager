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

  $form['composer_manager_generate_file'] = array(
    '#title' => 'Automatically generate a consolidated composer.json file',
    '#type' => 'checkbox',
    '#default_value' => variable_get('composer_manager_generate_file', 1),
    '#description' => t('Combine the requirements in all composer.json files that are included with contributed modules. Uncheck to manage a custom composer.json file in the vendor directory above.'),
  );

  $form['composer_manager_file_dir'] = array(
    '#title' => 'Composer File Firectory',
    '#type' => 'textfield',
    '#default_value' => variable_get('composer_manager_file_dir', 'public://composer'),
    '#description' => t('The URI of the directory where the composer.json file will be generated and where Composer commands should be run.'),
    '#states' => array(
      'visible' => array(
        ':input[name="composer_manager_generate_file"]' => array('checked' => TRUE),
      ),
    ),
  );

  return system_settings_form($form);
}

/**
 * Page callback; Shows the status of all packages requires by contrib.
 */
function composer_manager_packages_page() {
  $build = array();

  return $build;
}
