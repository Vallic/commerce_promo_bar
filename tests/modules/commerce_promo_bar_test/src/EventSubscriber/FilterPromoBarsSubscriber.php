<?php

namespace Drupal\commerce_promo_bar_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\commerce_promo_bar\Event\FilterPromoBarsEvent;
use Drupal\commerce_promo_bar\Event\PromoBarEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records the promo bars the filter event receives, for tests.
 */
class FilterPromoBarsSubscriber implements EventSubscriberInterface {

  /**
   * The state key holding the received promo bar labels.
   */
  const STATE_KEY = 'commerce_promo_bar_test.filtered';

  /**
   * Constructs a new FilterPromoBarsSubscriber object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(protected StateInterface $state) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [PromoBarEvents::FILTER_PROMO_BARS => 'onFilter'];
  }

  /**
   * Records the labels of the promo bars.
   *
   * @param \Drupal\commerce_promo_bar\Event\FilterPromoBarsEvent $event
   *   The event.
   */
  public function onFilter(FilterPromoBarsEvent $event): void {
    $labels = [];
    foreach ($event->getPromoBars() as $promo_bar) {
      $labels[] = $promo_bar->label();
    }
    $this->state->set(self::STATE_KEY, $labels);
  }

}
