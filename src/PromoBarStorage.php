<?php

namespace Drupal\commerce_promo_bar;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_promo_bar\Event\FilterPromoBarsEvent;
use Drupal\commerce_promo_bar\Event\PromoBarEvents;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Defines the promo bar storage.
 */
class PromoBarStorage extends CommerceContentEntityStorage implements PromoBarStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function loadAvailable(StoreInterface $store, array $roles = []) {
    $timezone = $store->getTimezone();
    $timestamp = \Drupal::time()->getRequestTime();
    $date = DrupalDateTime::createFromTimestamp($timestamp, $timezone);
    $date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = $this->getQuery();
    $or_condition = $query->orConditionGroup()
      ->condition('end_date', $date, '>')
      ->notExists('end_date');
    $store_condition = $query->orConditionGroup()
      ->notExists('stores')
      ->condition('stores', $store->id());
    $query
      ->condition('start_date', $date, '<=')
      ->condition('status', TRUE)
      ->condition($or_condition)
      ->condition($store_condition)
      ->accessCheck(FALSE);

    if ($roles) {
      $roles_conditions = $query->orConditionGroup()
        ->notExists('customer_roles')
        ->condition('customer_roles', $roles, 'IN');
      $query->condition($roles_conditions);
    }
    $result = $query->execute();
    if (empty($result)) {
      return [];
    }

    $promo_bars = $this->loadMultiple($result);

    // Sort the remaining promo bars.
    uasort($promo_bars, [$this->entityType->getClass(), 'sort']);
    $event = new FilterPromoBarsEvent($promo_bars, $store);
    $this->eventDispatcher->dispatch($event, PromoBarEvents::FILTER_PROMO_BARS);

    return $event->getPromoBars();
  }

}
