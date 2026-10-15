<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Defines the interface for promo bar storage.
 */
interface PromoBarStorageInterface extends ContentEntityStorageInterface {

  /**
   * Loads the available promo bars for the given store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The commerce store.
   * @param array $roles
   *   The user roles.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface[]
   *   The available promo bars.
   */
  public function loadAvailable(StoreInterface $store, array $roles = []): array;

  /**
   * Loads the enabled promo bar that starts next for the given store.
   *
   * Used to expire cached output when a scheduled promo bar becomes
   * available. Customer roles are not taken into account.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The commerce store.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface|null
   *   The promo bar with the nearest future start date, or NULL if none.
   */
  public function loadNextScheduled(StoreInterface $store): ?PromoBarInterface;

}
