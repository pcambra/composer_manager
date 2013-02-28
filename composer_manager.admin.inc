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
 * Page callback; Shows the status of all packages required by contrib.
 */
function composer_manager_packages_page() {
  $build = array();

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

  $form['placeholder'] = array(
    '#markup' => '<p><em>' . t('Placeholder for a package status UI.') . '</em></p><br/>',
  );

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
