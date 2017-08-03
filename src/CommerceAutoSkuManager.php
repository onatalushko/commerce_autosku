<?php
/**
 * @file
 * Contains \Drupal\commerce_autosku\AutoEntityLabelManager.
 */

namespace Drupal\commerce_autosku;

use Drupal\commerce_autosku\Plugin\CommerceAutoSkuGenerator\CommerceAutoSkuGeneratorInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Entity\ProductVariationTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class CommerceAutoSkuManager implements CommerceAutoSkuManagerInterface {
  use StringTranslationTrait;

  /**
   * Automatic label is disabled.
   */
  const DISABLED = 'disabled';

  /**
   * Automatic label is enabled. Will always be generated.
   */
  const ENABLED = 'enabled';

  /**
   * Automatic label is optional. Will only be generated if no label was given.
   */
  const OPTIONAL = 'optional';

  /**
   * The content entity.
   *
   * @var ProductVariationInterface
   */
  protected $entity;

  /**
   * The type of the entity.
   *
   * @var string
   */
  protected $entity_type;

  /**
   * The bundle of the entity.
   *
   * @var string
   */
  protected $entity_bundle;

  /**
   * The bundle of the entity.
   *
   * @var ProductVariationTypeInterface
   */
  protected $bundle_entity_type;

  /**
   * Indicates if the automatic label has been applied.
   *
   * @var bool
   */
  protected $auto_sku_applied = FALSE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Token service.
   *
   * @var CommerceAutoSkuGeneratorManagerInterface
   */
  protected $generatorManager;

  /**
   * Constructs an AutoEntityLabelManager object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add the automatic label to.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager
   * @param CommerceAutoSkuGeneratorManagerInterface $generatorManager
   *   Token manager.
   */
  public function __construct(ContentEntityInterface $entity, EntityTypeManagerInterface $entity_type_manager, CommerceAutoSkuGeneratorManagerInterface $generatorManager) {
    $this->entity = $entity;
    $this->entityTypeManager = $entity_type_manager;
    $this->generatorManager = $generatorManager;

    $entity_type_id = $entity->getEntityTypeId();
    $bundle_id = $entity->bundle();
    $bundle_entity_type_id = $entity_type_manager->getDefinition($entity_type_id)->getBundleEntityType();
    $this->bundle_entity_type = $this->entityTypeManager->getStorage($bundle_entity_type_id)->load($bundle_id);


  }

  /**
   * Checks if the entity has a label.
   *
   * @return bool
   *   True if the entity has a label property.
   */
  public function hasSku() {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    $definition = $this->entityTypeManager->getDefinition($this->entity->getEntityTypeId());
    // @todo cleanup.
    $hasKey = $definition->hasKey('sku');
    if ($hasKey) {
      return $hasKey;
    }
    $entityManager = \Drupal::service('entity_field.manager');
    $fields = $entityManager->getFieldDefinitions($this->entity->getEntityTypeId(), $this->entity->bundle());
    if (isset($fields['sku'])) {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setSku() {

    if (!$this->hasSku()) {
      throw new \Exception('This entity has no SKU.');
    }

    $configuration = $this->getConfig('configuration');
    $instance_id = $this->getConfig('plugin');
    /** @var CommerceAutoSkuGeneratorInterface $generator */
    $generator = $this->generatorManager->createInstance($instance_id, $configuration);
    $sku = $generator->generate($this->entity);

    $sku_name = $this->getSkuName();
    $this->entity->{$sku_name}->setValue($sku);

    $this->auto_sku_applied = TRUE;
    return $sku;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutoSku() {
    return $this->getConfig('mode') == self::ENABLED;
  }

  /**
   * {@inheritdoc}
   */
  public function hasOptionalAutoSku() {
    return $this->getConfig('mode') == self::OPTIONAL;
  }

  /**
   * {@inheritdoc}
   */
  public function autoSkuNeeded() {
    $not_applied = empty($this->auto_sku_applied);
    $required = $this->hasAutoSku();
    $optional = $this->hasOptionalAutoSku() && empty($this->entity->label());
    return $not_applied && ($required || $optional);
  }

  /**
   * Gets the field name of the entity label.
   *
   * @return string
   *   The entity label field name. Empty if the entity has no label.
   */
  public function getSkuName() {
    $sku_field = '';

    if ($this->hasSku()) {
      $entityManager = \Drupal::service('entity_field.manager');
      /** @var BaseFieldDefinition[] $fields */
      $fields = $entityManager->getFieldDefinitions($this->entity->getEntityTypeId(), $this->entity->bundle());
      $sku_field  = $fields['sku']->getFieldStorageDefinition()->getName();
    }

    return $sku_field;
  }

  /**
   * Returns automatic SKU configuration of the product variation type.
   *
   * @param string $key
   *   The configuration key to get.
   *
   * @return bool|mixed
   */
  protected function getConfig($key) {
    $config = $this->bundle_entity_type->getThirdPartySettings('commerce_autosku');
    return isset($config[$key]) ? $config[$key] : FALSE;
  }

  /**
   * Constructs the list of options for the given bundle.
   */
  public static function commerce_autosku_options() {
    return [
      CommerceAutoSkuManager::DISABLED => t('Disabled'),
      CommerceAutoSkuManager::ENABLED => t('Automatically generate the SKU and hide the label field'),
      CommerceAutoSkuManager::OPTIONAL => t('Automatically generate the SKU if the SKU field is left empty'),
    ];
  }

}