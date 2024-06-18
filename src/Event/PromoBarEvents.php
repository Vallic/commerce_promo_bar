<?php

namespace Drupal\commerce_promo_bar\Event;

/**
 * List all available promo bar events.
 */
final class PromoBarEvents {

  /**
   * Name of the event fired when available promo bars are loaded for a store.
   *
   * @Event
   *
   * @see \Drupal\commerce_promo_bar\Event\FilterPromoBarsEvent
   */
  const FILTER_PROMO_BARS = 'commerce_promo_bar.filter_promo_bars';

}
