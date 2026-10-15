<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Invalidates cached promo bars when a schedule date passes.
 *
 * The promo bar block expires itself through its max-age, but caches that
 * ignore max-age, such as the anonymous page cache or a reverse proxy, only
 * react to cache tags. When a promo bar has started, ended or finished its
 * countdown since the previous cron run, the promo bar list cache tag is
 * invalidated.
 */
class Cron {

  /**
   * The key-value collection.
   */
  const COLLECTION = 'commerce_promo_bar';

  /**
   * The key holding the previous run's timestamp.
   *
   * Key-value rather than state: state is cached as a whole, and writing it
   * on every cron run would keep invalidating that cache.
   */
  const LAST_RUN_KEY = 'cron_last_run';

  /**
   * The schedule fields whose dates change what promo bars display.
   */
  const DATE_FIELDS = ['start_date', 'end_date', 'countdown_date'];

  /**
   * Constructs a new Cron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The key-value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected KeyValueFactoryInterface $keyValueFactory,
    protected TimeInterface $time,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Runs the cron job.
   */
  public function run(): void {
    $now = $this->time->getRequestTime();
    $key_value = $this->getKeyValueStore();
    $last_run = $key_value->get(self::LAST_RUN_KEY);
    $key_value->set(self::LAST_RUN_KEY, $now);

    // On the first run there is no window to check yet.
    if ($last_run === NULL || $last_run >= $now) {
      return;
    }

    /** @var \Drupal\commerce_store\Entity\StoreInterface[] $stores */
    $stores = $this->entityTypeManager->getStorage('commerce_store')->loadMultiple();
    foreach ($stores as $store) {
      if ($this->hasScheduleChange($store, (int) $last_run, $now)) {
        $this->cacheTagsInvalidator->invalidateTags($this->entityTypeManager->getDefinition('commerce_promo_bar')->getListCacheTags());
        return;
      }
    }
  }

  /**
   * Gets the key-value store.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   The key-value store.
   */
  public function getKeyValueStore(): KeyValueStoreInterface {
    return $this->keyValueFactory->get(self::COLLECTION);
  }

  /**
   * Checks whether a store's promo bar schedule changed in a time window.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store.
   * @param int $from
   *   The window start timestamp, exclusive.
   * @param int $to
   *   The window end timestamp, inclusive.
   *
   * @return bool
   *   TRUE if an enabled promo bar of the store started, ended or finished
   *   its countdown within the window.
   */
  protected function hasScheduleChange(StoreInterface $store, int $from, int $to): bool {
    // Promo bar dates are stored in the store's timezone.
    $timezone = $store->getTimezone();
    $from = DrupalDateTime::createFromTimestamp($from, $timezone)->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $to = DrupalDateTime::createFromTimestamp($to, $timezone)->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = $this->entityTypeManager->getStorage('commerce_promo_bar')->getQuery();
    $query->accessCheck(FALSE);
    $store_condition = $query->orConditionGroup()
      ->notExists('stores')
      ->condition('stores', $store->id());
    $date_condition = $query->orConditionGroup();
    foreach (self::DATE_FIELDS as $field_name) {
      $date_condition->condition($query->andConditionGroup()
        ->condition($field_name, $from, '>')
        ->condition($field_name, $to, '<='));
    }

    return (bool) $query
      ->condition('status', TRUE)
      ->condition($store_condition)
      ->condition($date_condition)
      ->count()
      ->execute();
  }

}
