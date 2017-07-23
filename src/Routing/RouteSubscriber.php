<?php

/**
 * @file
 * Contains \Drupal\commerce_autosku\Routing\RouteSubscriber.
 */

namespace Drupal\commerce_autosku\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for commerce_autosku routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $entity_type_id = 'commerce_product_variation_type';
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if ($route = $this->getEntityAutoSkuRoute($entity_type)) {
      $collection->add("entity.$entity_type_id.auto_sku", $route);
    }
  }

  /**
   * Gets the Entity Auto Label route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityAutoSkuRoute(EntityTypeInterface $entity_type) {
    if ($route_load = $entity_type->getLinkTemplate('auto-sku')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($route_load);
      $route
        ->addDefaults([
          '_form' => '\Drupal\commerce_autosku\Form\CommerceAutoSkuForm',
          '_title' => 'Automatic SKU',
        ])
        ->addRequirements([
          '_permission' => 'administer ' . $entity_type_id . ' SKU',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = array('onAlterRoutes', -100);
    return $events;
  }

}
