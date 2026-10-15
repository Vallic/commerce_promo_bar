<?php

namespace Drupal\commerce_promo_bar\Event;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce\EventBase;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Defines the event for filtering/sorting the available promo bars.
 *
 * @see \Drupal\commerce_promo_bar\Event\PromoBarEvents
 */
class FilterPromoBarsEvent extends EventBase {

  /**
   * The cacheability of the filtering.
   */
  protected CacheableMetadata $cacheability;

  /**
   * Constructs a new FilterPromoBarsEvent object.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface[] $promo_bars
   *   The promo bars.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store.
   * @param string|null $path
   *   The page path the promo bars are loaded for, or NULL if they are not
   *   loaded for a page.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The customer the promo bars are loaded for, or NULL if not known.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   The cacheability of the result, which subscribers add to.
   */
  public function __construct(
    protected array $promo_bars,
    protected StoreInterface $store,
    protected ?string $path = NULL,
    protected ?AccountInterface $account = NULL,
    ?CacheableMetadata $cacheability = NULL,
  ) {
    $this->cacheability = $cacheability ?? new CacheableMetadata();
  }

  /**
   * Gets the promo_bars.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface[]
   *   The promo_bars.
   */
  public function getPromoBars(): array {
    return $this->promo_bars;
  }

  /**
   * Sets the promo_bars.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface[] $promo_bars
   *   The promo_bars.
   *
   * @return $this
   */
  public function setPromoBars(array $promo_bars): static {
    $this->promo_bars = $promo_bars;
    return $this;
  }

  /**
   * Gets the store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The order.
   */
  public function getStore(): StoreInterface {
    return $this->store;
  }

  /**
   * Gets the page path the promo bars are loaded for.
   *
   * @return string|null
   *   The page path, or NULL if the promo bars are not loaded for a page.
   */
  public function getPath(): ?string {
    return $this->path;
  }

  /**
   * Gets the customer the promo bars are loaded for.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The customer, or NULL if not known.
   */
  public function getAccount(): ?AccountInterface {
    return $this->account;
  }

  /**
   * Gets the cacheability of the result.
   *
   * Subscribers that filter by anything else than the promo bars themselves,
   * such as the path, the customer or a request header, add the matching
   * cache contexts here.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability.
   */
  public function getCacheability(): CacheableMetadata {
    return $this->cacheability;
  }

}
