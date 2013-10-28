<?php

/**
 * @file
 * Contains \Drupal\composer_manager\Form\SettingsForm.
 */

namespace Drupal\composer_manager\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\Context\ContextInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\system\SystemConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides administrative settings for the Composer Manager module.
 *
 * @ingroup forms
 */
class SettingsForm extends SystemConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a \Drupal\composer_manager\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\Context\ContextInterface $context
   *   The configuration context to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactory $config_factory, ContextInterface $context, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory, $context);

    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.context.free'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'composer_manager_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->configFactory->get('composer_manager.settings');
    $form['composer_manager_vendor_dir'] = array(
      '#title' => 'Vendor Directory',
      '#type' => 'textfield',
      '#default_value' => $config->get('vendor_dir'),
      '#description' => t('The relative or absolute path to the vendor directory containing the Composer packages and autoload.php file.'),
    );

    $form['composer_manager_file_dir'] = array(
      '#title' => 'Composer File Directory',
      '#type' => 'textfield',
      '#default_value' => $config->get('file_dir'),
      '#description' => t('The directory containing the composer.json file and where Composer commands are run.'),
    );

    $form['composer_manager_autobuild_file'] = array(
      '#title' => 'Automatically build the composer.json file when enabling or disabling modules in the Drupal UI',
      '#type' => 'checkbox',
      '#default_value' => $config->get('autobuild_file'),
      '#description' => t('Automatically build the consolidated composer.json for all contributed modules file in the vendor directory above when modules are enabled or disabled in the Drupal UI. Disable this setting if you want to maintain the composer.json file manually.'),
    );

    $form['composer_manager_autobuild_packages'] = array(
      '#title' => 'Automatically update Composer dependencies when enabling or disabling modules with Drush',
      '#type' => 'checkbox',
      '#default_value' => $config->get('autobuild_packages'),
      '#description' => t('Automatically build the consolidated composer.json file and run Composer\'s <code>!command</code> command when enabling or disabling modules with Drush. Disable this setting to manage the composer.json and dependencies manually.', array('!command' => 'update')),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    parent::validateForm($form, $form_state);

    $this->moduleHandler->loadInclude('composer_manager', 'inc', 'composer_manager.writer');

    $autobuild_file = $form_state['values']['composer_manager_autobuild_file'];
    $file_dir = $form_state['values']['composer_manager_file_dir'];
    if ($autobuild_file && !composer_manager_prepare_directory($file_dir)) {
      form_set_error('composer_manager_file_dir', t('Conposer file directory must be writable'));
    }
  }

}
