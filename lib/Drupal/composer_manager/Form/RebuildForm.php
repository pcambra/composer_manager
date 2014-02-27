<?php

/**
 * @file
 * Contains \Drupal\composer_manager\Form\RebuildForm.
 */

namespace Drupal\composer_manager\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes a button that forces a rebuild of the composer.json file.
 *
 * @ingroup forms
 */
class RebuildForm implements FormInterface, ContainerInjectionInterface {

  /**
   * The composer_manager.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a \Drupal\composer_manager\Form\RebuildForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->config = $config_factory->get('composer_manager.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'composer_manager_rebuild_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $file_dir = $this->config->get('file_dir');

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Rebuild composer.json file'),
      '#disabled' => 0 !== strpos($file_dir, 'public://') && (!is_dir($file_dir) || !is_writable($file_dir)),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    try {

      /* @var $packages \Drupal\composer_manager\ComposerPackagesInterface */
      $packages = \Drupal::service('composer_manager.packages');
      $packages->writeComposerJsonFile();

      $filepath = drupal_realpath($packages->getManager()->getComposerJsonFile()->getFilepath());
      drupal_set_message(t('A composer.json file was written to @filepath.', array('@filepath' => $filepath)));

    } catch (\Exception $e) {
      if (\Drupal::currentUser()->hasPermission('administer site configuration')) {
        drupal_set_message(t('Error writing composer.json file'), 'error');
      }
      watchdog_exception('composer_manager', $e);
    }
  }

}
