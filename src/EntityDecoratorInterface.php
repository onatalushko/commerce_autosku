<?php
/**
 * @file
 * Contains \Drupal\commerce_autosku\EntityDecoratorInterface.
 */

namespace Drupal\commerce_autosku;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for EntityDecorator.
 */
interface EntityDecoratorInterface {

  /**
   * Automatic entity label entity decorator.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return \Drupal\commerce_autosku\CommerceAutoSkuManager|\Drupal\Core\Entity\ContentEntityInterface
   */
  public function decorate(ContentEntityInterface $entity);
}
