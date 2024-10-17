<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_promo_bar\Event\FilterPromoBarsEvent;
use Drupal\commerce_promo_bar\Event\PromoBarEvents;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the promo bar storage.
 */
class PromoBarStorage extends CommerceContentEntityStorage implements PromoBarStorageInterface {

  /**
   * The time.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAvailable(StoreInterface $store, array $roles = []): array {
    $timezone = $store->getTimezone();
    $timestamp = $this->time->getRequestTime();
    $date = DrupalDateTime::createFromTimestamp($timestamp, $timezone);
    $date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = $this->getQuery();
    $query->accessCheck(FALSE);
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
      ->condition($store_condition);

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
