<?php

namespace Drupal\commerce_promo_bar;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;

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
  public function loadAvailable(StoreInterface $store, array $roles = []);

}
