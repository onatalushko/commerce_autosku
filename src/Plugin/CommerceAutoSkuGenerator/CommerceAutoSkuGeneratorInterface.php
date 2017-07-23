<?php

namespace Drupal\commerce_autosku\Plugin\CommerceAutoSkuGenerator;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Places an order through a series of steps.
 *
 * Checkout flows are multi-step forms that can be configured by the store
 * administrator. This configuration is stored in the commerce_checkout_flow
 * config entity and injected into the plugin at instantiation.
 */
interface CommerceAutoSkuGeneratorInterface extends  ConfigurablePluginInterface, PluginFormInterface, PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Generated SKU getter.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $entity
   *   Entity SKU generated for.
   *
   * @return string
   *   Generated SKU for giv.
   */
  public function generate(ProductVariationInterface $entity);

}
