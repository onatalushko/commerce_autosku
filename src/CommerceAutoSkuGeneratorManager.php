<?php
/**
 * @file
 * Contains \Drupal\commerce_autosku\AutoEntityLabelManager.
 */

namespace Drupal\commerce_autosku;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;

class CommerceAutoSkuGeneratorManager extends DefaultPluginManager implements CommerceAutoSkuGeneratorManagerInterface, PluginManagerInterface {

  /**
   * Constructs a CommerceAutoSkuGeneratorManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/CommerceAutoSkuGenerator',
      $namespaces,
      $module_handler,
      'Drupal\commerce_autosku\Plugin\CommerceAutoSkuGenerator\CommerceAutoSkuGeneratorInterface',
      'Drupal\commerce_autosku\Annotation\CommerceAutoSkuGenerator');

    $this->alterInfo('commerce_autosku_generator_info');
    $this->setCacheBackend($cache_backend, 'commerce_autosku_generator_info_plugins');
  }
}