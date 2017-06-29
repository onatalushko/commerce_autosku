<?php

/**
 * @file
 * Contains \Drupal\commerce_autosku\Controller\CommerceAutoSkuForm.
 */

namespace Drupal\commerce_autosku\Form;

use Drupal\commerce_autosku\CommerceAutoSkuManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceAutoSkuForm.
 *
 * @property \Drupal\Core\Config\ConfigFactoryInterface config_factory
 * @property \Drupal\Core\Entity\EntityTypeManagerInterface entity_manager
 * @property  String entity_type_parameter
 * @property  String entity_type_id
 * @property \Drupal\commerce_autosku\CommerceAutoSkuManager auto_entity_label_manager
 * @package Drupal\commerce_autosku\Controller
 */
class CommerceAutoSkuForm extends ConfigFormBase {
  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  protected $entitymanager;

  protected $route_match;

  /**
   * AutoEntityLabelController constructor.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_manager, RouteMatchInterface $route_match) {
    parent::__construct($config_factory);
    $this->entitymanager = $entity_manager;
    $this->route_match = $route_match;
    $route_options = $this->route_match->getRouteObject()->getOptions();
    $array_keys = array_keys($route_options['parameters']);
    $this->entity_type_parameter = array_shift($array_keys);
    $entity_type = $this->route_match->getParameter($this->entity_type_parameter);
    $this->entity_type_id = $entity_type->id();
    $this->entity_type_provider =  $entity_type->getEntityType()->getProvider();
  }


  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_autosku.entity_type.' . $this->entity_type_parameter . '_' . $this->entity_type_id,
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'commerce_autosku_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('config.factory'),
      $container->get('entity.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $key = $this->entity_type_parameter . '_' . $this->entity_type_id;
    $config = $this->config('commerce_autosku.entity_type.' . $key);

    /*
     * @todo
     *  Find a generic way of determining if the label is rendered on the
     *  entity form. If not, don't show 'commerce_autosku_optional' option.
     */
    $options = [
      CommerceAutoSkuManager::DISABLED => $this->t('Disabled'),
      CommerceAutoSkuManager::ENABLED => $this->t('Automatically generate the SKU and hide the label field'),
      CommerceAutoSkuManager::OPTIONAL => $this->t('Automatically generate the SKU if the SKU field is left empty'),
    ];

    $form['commerce_autosku'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Automatic SKU generation for @type', ['@type' => $this->entity_type_id]),
      '#weight' => 0,
      '#tree' => TRUE,
    ];

    $form['commerce_autosku']['status'] = [
      '#type' => 'radios',
      '#default_value' => $config->get('status'),
      '#options' => $options,
    ];

    $form['commerce_autosku']['pattern'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pattern for the SKU'),
      '#description' => $this->t('Leave blank for using the per default generated SKU. Otherwise this string will be used as SKU. Use the syntax [token] if you want to insert a replacement pattern.'),
      '#default_value' => $config->get('pattern'),
    ];

    // Don't allow editing of the pattern if PHP is used, but the users lacks
    // permission for PHP.
    if ($config->get('php') && !\Drupal::currentUser()->hasPermission('use PHP for auto SKUs')) {
      $form['commerce_autosku']['pattern']['#disabled'] = TRUE;
      $form['commerce_autosku']['pattern']['#description'] = $this->t('You are not allowed the configure the pattern for the SKU, because you do not have the %permission permission.', ['%permission' => $this->t('Use PHP for auto SKUs')]);
    }

    // Display the list of available placeholders if token module is installed.
    $module_handler = \Drupal::moduleHandler();
    if ($module_handler->moduleExists('token')) {
      $token_info = $module_handler->invoke($this->entity_type_provider, 'token_info');
      $token_types = isset($token_info['types']) ? array_keys($token_info['types']) : [];
      $form['commerce_autosku']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => $token_types,
        '#dialog' => TRUE,
      ];
    }

    $form['commerce_autosku']['php'] = [
      '#access' => \Drupal::currentUser()->hasPermission('use PHP for auto entity labels'),
      '#type' => 'checkbox',
      '#title' => $this->t('Evaluate PHP in pattern.'),
      '#description' => $this->t('Put PHP code above that returns your string, but make sure you surround code in <code>&lt;?php</code> and <code>?&gt;</code>. Note that <code>$entity</code> and <code>$language</code> are available and can be used by your code.'),
      '#default_value' => $config->get('php'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type_key = $this->entity_type_parameter . '_' . $this->entity_type_id;
    $config = $this->configFactory->getEditable('commerce_autosku.entity_type.' . $entity_type_key);
    foreach ($form_state->getValue('commerce_autosku') as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }
}
