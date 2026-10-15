<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce\CurrentCountryInterface;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
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
   * The current country.
   */
  protected CurrentCountryInterface $currentCountry;

  /**
   * The current path.
   */
  protected CurrentPathStack $currentPath;

  /**
   * The promo bar visibility.
   */
  protected PromoBarVisibilityInterface $visibility;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->time = $container->get('datetime.time');
    $instance->currentCountry = $container->get('commerce.current_country');
    $instance->currentPath = $container->get('path.current');
    $instance->visibility = $container->get('commerce_promo_bar.visibility');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAvailable(StoreInterface $store, array $roles = []): array {
    return $this->doLoadAvailable($store, $roles);
  }

  /**
   * {@inheritdoc}
   */
  public function loadVisible(StoreInterface $store, AccountInterface $account, ?string $path = NULL, ?CacheableMetadata $cacheability = NULL): array {
    $cacheability ??= new CacheableMetadata();
    $cacheability->addCacheTags($this->entityType->getListCacheTags());
    $cacheability->addCacheContexts(['store', 'user.roles', 'country']);
    if ($path === NULL) {
      $path = $this->currentPath->getPath();
      $cacheability->addCacheContexts(['url.path']);
    }

    $promo_bars = $this->doLoadAvailable($store, $account->getRoles(), $path, $account, $cacheability);

    $timezone = $store->getTimezone();
    $now = $this->time->getRequestTime();
    // The result is only valid until the next scheduled promo bar starts,
    // an available one ends, or a visible countdown runs out.
    $changes = [];
    if ($next_promo_bar = $this->loadNextScheduled($store)) {
      $changes[] = $next_promo_bar->getStartDate($timezone)->getTimestamp();
    }
    foreach ($promo_bars as $id => $promo_bar) {
      if ($end_date = $promo_bar->getEndDate($timezone)) {
        $changes[] = $end_date->getTimestamp();
      }
      if (!$this->visibility->isVisible($promo_bar, $path)) {
        unset($promo_bars[$id]);
        continue;
      }
      $countdown_date = $promo_bar->getCountdownDate($timezone);
      if ($countdown_date && $countdown_date->getTimestamp() > $now) {
        $changes[] = $countdown_date->getTimestamp();
      }
    }
    if ($changes) {
      $cacheability->mergeCacheMaxAge(max(0, min($changes) - $now));
    }

    return $promo_bars;
  }

  /**
   * Loads the available promo bars and lets subscribers filter them.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The commerce store.
   * @param array $roles
   *   The user roles. An empty array skips the customer roles check.
   * @param string|null $path
   *   The page path, or NULL if not loaded for a page.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The customer, or NULL if not known.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   The cacheability, which event subscribers add to.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface[]
   *   The available promo bars.
   */
  protected function doLoadAvailable(StoreInterface $store, array $roles, ?string $path = NULL, ?AccountInterface $account = NULL, ?CacheableMetadata $cacheability = NULL): array {
    $date = $this->getStoreDate($store);

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

    /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface[] $promo_bars */
    $promo_bars = $this->loadMultiple($result);
    $country_code = $this->currentCountry->getCountry()?->getCountryCode();
    $promo_bars = array_filter($promo_bars, function (PromoBarInterface $promo_bar) use ($country_code) {
      return $this->isAvailableForCountry($promo_bar, $country_code);
    });
    if (empty($promo_bars)) {
      return [];
    }

    // Sort the remaining promo bars.
    uasort($promo_bars, [$this->entityType->getClass(), 'sort']);
    $event = new FilterPromoBarsEvent($promo_bars, $store, $path, $account, $cacheability);
    $this->eventDispatcher->dispatch($event, PromoBarEvents::FILTER_PROMO_BARS);

    return $event->getPromoBars();
  }

  /**
   * {@inheritdoc}
   */
  public function loadNextScheduled(StoreInterface $store): ?PromoBarInterface {
    $query = $this->getQuery();
    $query->accessCheck(FALSE);
    $store_condition = $query->orConditionGroup()
      ->notExists('stores')
      ->condition('stores', $store->id());
    $result = $query
      ->condition('start_date', $this->getStoreDate($store), '>')
      ->condition('status', TRUE)
      ->condition($store_condition)
      ->sort('start_date')
      ->range(0, 1)
      ->execute();

    if (!$result) {
      return NULL;
    }

    /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface|null $promo_bar */
    $promo_bar = $this->load(reset($result));
    return $promo_bar;
  }

  /**
   * Checks whether a promo bar is available for the given country.
   *
   * Filtered here rather than in the query: an entity query cannot express
   * "the multi-value field does not contain this country".
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar
   *   The promo bar.
   * @param string|null $country_code
   *   The country code, or NULL if the current country is unknown.
   *
   * @return bool
   *   TRUE if the promo bar is available, FALSE otherwise.
   */
  protected function isAvailableForCountry(PromoBarInterface $promo_bar, ?string $country_code): bool {
    $country_codes = $promo_bar->getCountries();
    if (!$country_codes) {
      return TRUE;
    }

    $listed = $country_code !== NULL && in_array($country_code, $country_codes, TRUE);
    return $promo_bar->isCountryVisibility() ? $listed : !$listed;
  }

  /**
   * Gets the request time as a storage date in the store's timezone.
   *
   * Promo bar dates are entered and stored in the store's timezone.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The commerce store.
   *
   * @return string
   *   The date, in the datetime storage format.
   */
  protected function getStoreDate(StoreInterface $store): string {
    $date = DrupalDateTime::createFromTimestamp($this->time->getRequestTime(), $store->getTimezone());
    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

}
