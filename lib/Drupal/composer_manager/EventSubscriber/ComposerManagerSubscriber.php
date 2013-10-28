<?php
namespace Drupal\composer_manager\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ComposerManagerSubscriber implements EventSubscriberInterface {

  public function addAutoload(GetResponseEvent $event) {
    composer_manager_register_autoloader();
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('addAutoload');
    return $events;
  }
}
?>