<?php

namespace Drupal\commerce_autosku\Plugin\CommerceAutoSkuGenerator;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Places an order through a series of steps.
 *
 * Checkout flows are multi-step forms that can be configured by the store
 * administrator. This configuration is stored in the commerce_checkout_flow
 * config entity and injected into the plugin at instantiation.
 */
abstract class CommerceAutoSkuGeneratorBase extends PluginBase  implements CommerceAutoSkuGeneratorInterface, ContainerFactoryPluginInterface{

  /**
   * Entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  var $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Validate if sku is unique.
   *
   * @param ProductVariationInterface $entity
   *   Product Variation.
   * @param string $sku
   *   SKU.
   *
   * @return bool
   *   TRUE if SKU unique FALSE otherwise.
   */
  protected function isUnique(ProductVariationInterface $entity, $sku) {
    $entities = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadByProperties(['sku' => $sku]);
    if (!$entity->isNew()) {
      unset($entities[$entity->id()]);
    }

    return empty($entities);
  }

  protected function makeUnique(ProductVariationInterface $entity, $sku) {
    // Strip tags.
    $generated_sku = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags($sku));
    $output = $generated_sku;
    $i = 0;
    while (!$this->isUnique($entity, $output)) {
      $counter_length = Unicode::strlen($i) + 1;
      $un_prefixed_max_length = 255 - $counter_length;
      $sku = Unicode::substr($generated_sku, 0, $un_prefixed_max_length);
      $output = $sku . '_' . $i;
      $i++;
    };
    return $output;
  }

  /**
   * Generates the SKU according to the settings.
   *
   * @param ProductVariationInterface $entity
   *   Content entity.
   *
   * @return string
   *   A label string
   */
  public function generate(ProductVariationInterface $entity) {
    $generated_sku = $this->getSku($entity);
    if (empty($generated_sku)) {
      $generated_sku = $this->getAlternativeSku($entity);
    }
    return $this->makeUnique($entity, $generated_sku);
  }

  /**
   * Gets an alternative SKU.
   *
   * @return string
   *   Translated label string.
   */
  protected function getAlternativeSku(ProductVariationInterface $entity) {
    $content_type = $this->getBundleLabel($entity);

    if ($entity->id()) {
      $label = t('@type @id', array(
        '@type' => $content_type,
        '@id' => $entity->id(),
      ));
    }
    else {
      $label = $content_type;
    }

    return $label;
  }

  /**
   * Gets the entity bundle label or the entity label.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel(ProductVariationInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // Use the the human readable name of the bundle type. If this entity has no
    // bundle, we use the name of the content entity type.
    if ($bundle != $entity_type) {
      $bundle_entity_type = $this->entityTypeManager
        ->getDefinition($entity_type)
        ->getBundleEntityType();
      $label = $this->entityTypeManager
        ->getStorage($bundle_entity_type)
        ->load($bundle)
        ->label();
    }
    else {
      $label = $this->entityTypeManager
        ->getDefinition($entity_type)
        ->getLabel();
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  abstract protected function getSku(ProductVariationInterface $entity);

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [
      'module' => [$this->pluginDefinition['provider']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->setConfiguration($values);
    }
  }

}
