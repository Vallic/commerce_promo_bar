<?php

namespace Drupal\commerce_promo_bar\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a commerce promo bar entity type.
 */
interface PromoBarInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the promo bar name.
   *
   * This name is admin-facing.
   *
   * @return string
   *   The promo bar name.
   */
  public function getName();

  /**
   * Sets the promo bar name.
   *
   * @param string $name
   *   The promo bar name.
   *
   * @return $this
   */
  public function setName($name);

  /**
   * Gets the promo bar description.
   *
   * @return string
   *   The promo bar description.
   */
  public function getDescription();

  /**
   * Sets the promo bar description.
   *
   * @param string $description
   *   The promo bar description.
   *
   * @return $this
   */
  public function setDescription($description);

  /**
   * The path or routes for visibility logic.
   *
   * @return string
   *   All paths added.
   */
  public function getPages();

  /**
   * Gets the promo bar start date/time.
   *
   * The start date/time should always be used in the store timezone.
   * Since the promo bar can belong to multiple stores, the timezone
   * isn't known at load/save time, and is provided by the caller instead.
   *
   * Note that the returned date/time value is the same in any timezone,
   * the "2019-10-17 10:00" stored value is returned as "2019-10-17 10:00 CET"
   * for "Europe/Berlin" and "2019-10-17 10:00 ET" for "America/New_York".
   *
   * @param string $store_timezone
   *   The store timezone. E.g. "Europe/Berlin".
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The promo bar start date/time.
   */
  public function getStartDate($store_timezone = 'UTC');

  /**
   * Sets the promo bar start date/time.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The promo bar start date/time.
   *
   * @return $this
   */
  public function setStartDate(DrupalDateTime $start_date);

  /**
   * Gets the promo bar end date/time.
   *
   * The end date/time should always be used in the store timezone.
   * Since the promo bar can belong to multiple stores, the timezone
   * isn't known at load/save time, and is provided by the caller instead.
   *
   * Note that the returned date/time value is the same in any timezone,
   * the "2019-10-17 11:00" stored value is returned as "2019-10-17 11:00 CET"
   * for "Europe/Berlin" and "2019-10-17 11:00 ET" for "America/New_York".
   *
   * @param string $store_timezone
   *   The store timezone. E.g. "Europe/Berlin".
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The promo bar end date/time.
   */
  public function getEndDate($store_timezone = 'UTC');

  /**
   * Sets the promo bar end date/time.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The promo bar end date/time.
   *
   * @return $this
   */
  public function setEndDate(DrupalDateTime $end_date = NULL);

  /**
   * Gets the promo bar countdown date/time.
   *
   * @param string $store_timezone
   *   The store timezone. E.g. "Europe/Berlin".
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The promo bar countdown date/time.
   */
  public function getCountdownDate($store_timezone = 'UTC');

  /**
   * Sets the promo bar countdown date/time.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The promo bar countdown date/time.
   *
   * @return $this
   */
  public function setCountdownDate(DrupalDateTime $end_date = NULL);

  /**
   * Get whether the promo bar is enabled.
   *
   * @return bool
   *   TRUE if the promo bar is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets whether the promo bar is enabled.
   *
   * @param bool $enabled
   *   Whether the promo bar is enabled.
   *
   * @return $this
   */
  public function setEnabled($enabled);

  /**
   * Gets the weight.
   *
   * @return int
   *   The weight.
   */
  public function getWeight();

  /**
   * Sets the weight.
   *
   * @param int $weight
   *   The weight.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Gets the promo bar creation timestamp.
   *
   * @return int
   *   Creation timestamp of the promo bar.
   */
  public function getCreatedTime();

  /**
   * Sets the promo bar creation timestamp.
   *
   * @param int $timestamp
   *   The promo bar creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the related promotion.
   *
   * @return \Drupal\commerce_promotion\Entity\PromotionInterface|null
   *   The promotion entity, or null.
   */
  public function getPromotion();

  /**
   * Gets the related promotion ID.
   *
   * @return int|null
   *   The promotion ID, or null.
   */
  public function getPromotionId();

  /**
   * Gets the customer roles.
   *
   * @return string[]|null
   *   The customer role IDs, or NULL if the promo bar is not limited to
   *   specific customer roles.
   */
  public function getCustomerRoles();

  /**
   * Sets the customer roles.
   *
   * @param string[] $rids
   *   The role IDs.
   *
   * @return $this
   */
  public function setCustomerRoles(array $rids);

}
