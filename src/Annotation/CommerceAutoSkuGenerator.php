<?php

namespace Drupal\commerce_autosku\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the commerce_autosku Generator plugin annotation object.
 *
 * Plugin namespace: Plugin\CommerceAutoSkuGenerator.
 *
 * @Annotation
 */
class CommerceAutoSkuGenerator extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

}
