<?php

/**
 * @file
 * Contains \Drupal\commerce_autosku\Controller\CommerceAutoSkuForm.
 */

namespace Drupal\commerce_autosku\Form;

use Drupal\commerce_autosku\CommerceAutoSkuGeneratorManager;
use Drupal\commerce_autosku\CommerceAutoSkuManager;
use Drupal\commerce_product\Entity\ProductVariationTypeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
class CommerceAutoSkuForm extends FormBase {

  /**
   * The commerce autoSku generator plugin manager.
   *
   * @var \Drupal\commerce_payment\PaymentGatewayManager
   */
  protected $pluginManager;

  /**
   * The commerce autoSku generator plugin manager.
   *
   * @var ProductVariationTypeInterface
   */
  protected $entity;

  /**
   * AutoEntityLabelController constructor.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\commerce_autosku\CommerceAutoSkuManager $plugin_manager
   */
  public function __construct(ContainerInterface $container, CommerceAutoSkuGeneratorManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
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
      $container,
      $container->get('plugin.manager.commerce_autosku_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $commerce_product_variation_type = NULL) {
    /** @var ProductVariationTypeInterface entity */
    $this->entity = $commerce_product_variation_type;
    $configuration = $this->entity->getThirdPartySettings('commerce_autosku');
    $form['mode'] = [
      '#type' => 'radios',
      '#default_value' => isset($configuration['mode']) ? $configuration['mode'] : CommerceAutoSkuManager::DISABLED,
      '#options' => CommerceAutoSkuManager::commerce_autosku_options(),
    ];
    $definition = $this->pluginManager->getDefinitions();
    $plugins = array_column($definition, 'label', 'id');
    asort($plugins);
//    $plugin = $this->getget('');
//
//    // Use the first available plugin as the default value.
//    if (!$gateway->getPluginId()) {
//      $plugin_ids = array_keys($plugins);
//      $plugin = reset($plugin_ids);
//      $gateway->setPluginId($plugin);
//    }
    // The form state will have a plugin value if #ajax was used.
//    $plugin = $form_state->getValue('plugin');
    // Pass the plugin configuration only if the plugin hasn't been changed via #ajax.

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    if (!is_null($configuration['plugin']) && !isset($plugins[$configuration['plugin']])) {
      return $form;
    }

    $wrapper_id = Html::getUniqueId('commerce-autosku-plugin-form');

    $form['plugin'] = [
      '#type' => 'radios',
      '#title' => t('Plugin'),
      '#options' => $plugins,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
    ];
    if (!is_null($configuration['plugin']) && isset($plugins[$configuration['plugin']])) {
      $form['plugin']['#default_value'] = $configuration['plugin'];
      $form['configuration'] = [
        '#type' => 'commerce_plugin_configuration',
        '#plugin_type' => 'commerce_autosku_generator',
        '#plugin_id' => $configuration['plugin'],
      ];
      if (!is_null($configuration['configuration'])) {
        $form['configuration']['#default_value'] = $configuration['configuration'];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mode = $form_state->getValue('mode');
    $plugin = $form_state->getValue('plugin');
    $configuration = $form_state->getValue('configuration');
    $this->entity->setThirdPartySetting('commerce_autosku', 'mode', $mode);
    $this->entity->setThirdPartySetting('commerce_autosku', 'plugin', $plugin);
    $this->entity->setThirdPartySetting('commerce_autosku', 'configuration', $configuration);
    $this->entity->save();
  }

}
