<?php

/**
 * @file
 * Contains Drupal\commerce_autosku\AutoEntityLabelManagerInterface.
 */

namespace Drupal\commerce_autosku;

/**
 * Provides an interface for AutoEntityLabelManager.
 */
interface CommerceAutoSkuManagerInterface {

  /**
   * Sets the automatically generated entity label.
   *
   * @return string
   *   The applied label. The entity is updated with this label.
   */
  public function setSku();

  /**
   * Determines if the entity bundle has auto entity label enabled.
   *
   * @return bool
   *   True if the entity bundle has an automatic label.
   */
  public function hasAutoSku();

  /**
   * Determines if the entity bundle has an optional automatic label.
   *
   * Optional means that if the label is empty, it will be automatically
   * generated.
   *
   * @return bool
   *   True if the entity bundle has an optional automatic label.
   */
  public function hasOptionalAutoSku();

  /**
   * Returns whether the automatic label has to be set.
   *
   * @return bool
   *   Returns true if the label should be automatically generated.
   */
  public function autoSkuNeeded();
}
