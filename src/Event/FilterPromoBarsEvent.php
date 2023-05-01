<?php

namespace Drupal\commerce_promo_bar\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Defines the event for filtering/sorting the available promo bars.
 *
 * @see \Drupal\commerce_promo_bar\Event\PromoBarEvents
 */
class FilterPromoBarsEvent extends EventBase {

  /**
   * Constructs a new FilterPromoBarsEvent object.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface[] $promo_bars
   *   The promo bars.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store.
   */
  public function __construct(protected array $promo_bars, protected StoreInterface $store) {}

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

}
