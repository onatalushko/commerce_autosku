<?php
/**
 * @file
 * Contains \Drupal\commerce_autosku\AutoEntityLabelManager.
 */

namespace Drupal\commerce_autosku;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;

class CommerceAutoSkuManager implements CommerceAutoSkuManagerInterface {
  use StringTranslationTrait;

  /**
   * Automatic label is disabled.
   */
  const DISABLED = 0;

  /**
   * Automatic label is enabled. Will always be generated.
   */
  const ENABLED = 1;

  /**
   * Automatic label is optional. Will only be generated if no label was given.
   */
  const OPTIONAL = 2;

  /**
   * The content entity.
   *
   * @var ContentEntityInterface
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
   * Indicates if the automatic label has been applied.
   *
   * @var bool
   */
  protected $auto_sku_applied = FALSE;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Automatic label configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs an AutoEntityLabelManager object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add the automatic label to.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager
   * @param \Drupal\Core\Utility\Token $token
   *   Token manager.
   */
  public function __construct(ContentEntityInterface $entity, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, Token $token) {
    $this->entity = $entity;
    $this->entity_type = $entity->getEntityType()->id();
    $this->entity_bundle = $entity->bundle();
    $this->bundle_entity_type = $entity_type_manager->getDefinition($this->entity_type)->getBundleEntityType();

    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
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

    $pattern = $this->getConfig('pattern') ?: '';
    $pattern = trim($pattern);

    if ($pattern) {
      $sku = $this->generateSku($pattern, $this->entity);
    }
    else {
      $sku = $this->getAlternativeSku();
    }

    $sku = substr($sku, 0, 255);
    $sku_name = $this->getSkuName();
    $this->entity->{$sku_name}->setValue($sku);

    $this->auto_sku_applied = TRUE;
    return $sku;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutoSku() {
    return $this->getConfig('status') == self::ENABLED;
  }

  /**
   * {@inheritdoc}
   */
  public function hasOptionalAutoSku() {
    return $this->getConfig('status') == self::OPTIONAL;
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
   * Gets the entity bundle label or the entity label.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel() {
    $entity_type = $this->entity->getEntityTypeId();
    $bundle = $this->entity->bundle();

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
   * Generates the SKU according to the settings.
   *
   * @param string $pattern
   *   Label pattern. May contain tokens.
   * @param ProductVariationInterface $entity
   *   Content entity.
   *
   * @return string
   *   A label string
   */
  protected function generateSku($pattern, ProductVariationInterface $entity) {
    $entity_type = $entity->getEntityType()->id();

    $generated_sku = $this->token
      ->replace($pattern, array($entity_type => $entity), array(
        'sanitize' => FALSE,
        'clear' => TRUE
      ));


    // Evaluate PHP.
    if ($this->getConfig('php')) {
      $generated_sku = $this->evalSku($generated_sku, $this->entity);
    }

    // Strip tags.
    $generated_sku = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags($generated_sku));
    $output = $generated_sku;


    $i = 0;
    while (!$this->isSkuUnique($entity, $output)) {
      $counter_length = Unicode::strlen($i) + 1;
      $un_prefixed_max_length = 255 - $counter_length;
      $sku = Unicode::substr($generated_sku, 0, $un_prefixed_max_length);
      $output = $sku . '_' . $i;
      $i++;
    };

    return $output;
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
  protected function isSkuUnique(ProductVariationInterface $entity, $sku) {
    $entities = $this->entityTypeManager->getStorage($this->entity_type)->loadByProperties(['sku' => $sku]);
    if (!$entity->isNew()) {
      unset($entities[$entity->id()]);
    }

    return empty($entities);
  }

  /**
   * Returns automatic label configuration of the content entity bundle.
   *
   * @param string $key
   *   The configuration key to get.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  protected function getConfig($key) {
    $entity_type = $this->bundle_entity_type . '_' . $this->entity_bundle;
    if (!isset($this->config)) {
      $this->config = $this->configFactory->get('commerce_autosku.entity_type.' . $entity_type);
    }
    return $this->config->get($key);
  }

  /**
   * Gets an alternative entity label.
   *
   * @return string
   *   Translated label string.
   */
  protected function getAlternativeSku() {
    $content_type = $this->getBundleLabel();

    if ($this->entity->id()) {
      $label = $this->t('@type @id', array(
        '@type' => $content_type,
        '@id' => $this->entity->id(),
      ));
    }
    else {
      $label = $content_type;
    }

    return $label;
  }

  /**
   * Evaluates php code and passes the entity to it.
   *
   * @param $code
   *   PHP code to evaluate.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity to pass through to the PHP script.
   *
   * @return string
   *   String to use as SKU.
   */
  protected function evalSku($code, $entity) {
    ob_start();
    print eval('?>' . $code);
    $output = ob_get_contents();
    ob_end_clean();

    return $output;
  }

  /**
   * Constructs the list of options for the given bundle.
   */
  public static function commerce_autosku_options($entity_type, $bundle_name) {
    $options = array(
      'commerce_autosku_disabled' => t('Disabled'),
    );
    if (self::commerce_autosku_entity_label_visible($entity_type)) {
      $options += array(
        'commerce_autosku_enabled' => t('Automatically generate the label and hide the label field'),
        'commerce_autosku_optional' => t('Automatically generate the label if the label field is left empty'),
      );
    }
    else {
      $options += array(
        'commerce_autosku_enabled' => t('Automatically generate the label'),
      );
    }
    return $options;
  }

  /**
   * Check if given entity bundle has a visible label on the entity form.
   *
   * @param $entity_type
   *   The entity type.
   * @param $bundle_name
   *   The name of the bundle.
   *
   * @return
   *   TRUE if the label is rendered in the entity form, FALSE otherwise.
   *
   * @todo
   *   Find a generic way of determining the result of this function. This
   *   will probably require access to more information about entity forms
   *   (entity api module?).
   */
  public static function commerce_autosku_entity_label_visible($entity_type) {
    $hidden = array(
      'profile2' => TRUE,
    );

    return empty($hidden[$entity_type]);
  }
}