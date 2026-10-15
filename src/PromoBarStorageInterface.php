<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
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
   * Loads the promo bars visible to a customer on a page.
   *
   * Runs the same rules as the promo bar block, so that API resources and
   * other front ends can reuse them: the available promo bars for the
   * customer's roles and country, then the filter event, which receives the
   * path and the customer, then the page visibility.
   *
   * The cacheability assumes that the store is the current store and the
   * account is the current user. The max-age expires the result when the next
   * scheduled promo bar starts, an available one ends, or a visible countdown
   * runs out.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The commerce store.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The customer.
   * @param string|null $path
   *   The page path, without the language prefix. NULL for the current
   *   request path, which adds the "url.path" cache context.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   Collects the cacheability of the result.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface[]
   *   The visible promo bars.
   *
   * @see \Drupal\commerce_promo_bar\PromoBarVisibilityInterface::isVisible()
   */
  public function loadVisible(StoreInterface $store, AccountInterface $account, ?string $path = NULL, ?CacheableMetadata $cacheability = NULL): array;

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
