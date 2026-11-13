<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promo_bar\Cron;
use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the cache invalidation on cron.
 */
#[CoversClass(Cron::class)]
#[Group('commerce_promo_bar')]
#[RunTestsInSeparateProcesses]
class CronTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_promotion',
    'commerce_promo_bar',
    'color_field',
  ];

  /**
   * The request time.
   */
  protected int $requestTime;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promo_bar');
    $this->installConfig(['commerce_promo_bar']);

    // Promo bar dates are stored in the store's timezone, so use one that
    // differs from UTC to catch conversion mistakes.
    $this->store->setTimezone('America/New_York');
    $this->store->save();

    $this->requestTime = $this->container->get('datetime.time')->getRequestTime();
  }

  /**
   * Tests that the first run only records the run time.
   */
  public function testFirstRun(): void {
    $this->createPromoBar(-300);
    $this->cacheItem();

    $this->runCron();
    $this->assertCacheItemValid(TRUE);
    $this->assertSame($this->requestTime, $this->getLastRun());
  }

  /**
   * Tests that a promo bar starting since the previous run invalidates.
   */
  public function testStart(): void {
    $this->createPromoBar(-300);
    $this->cacheItem();

    $this->runCron(-600);
    $this->assertCacheItemValid(FALSE);
    $this->assertSame($this->requestTime, $this->getLastRun());
  }

  /**
   * Tests that hook_cron() runs the job.
   */
  public function testHookCron(): void {
    $this->createPromoBar(-300);
    $this->cacheItem();
    $this->container->get('commerce_promo_bar.cron')->getKeyValueStore()->set(Cron::LAST_RUN_KEY, $this->requestTime - 600);

    $this->container->get('cron')->run();
    $this->assertCacheItemValid(FALSE);
    $this->assertSame($this->requestTime, $this->getLastRun());
  }

  /**
   * Tests that an end or countdown since the previous run invalidates.
   */
  public function testEndAndCountdown(): void {
    $promo_bar = $this->createPromoBar(-3600);
    $promo_bar->setEndDate($this->storeDate(-60));
    $promo_bar->save();
    $this->cacheItem();
    $this->runCron(-600);
    $this->assertCacheItemValid(FALSE);

    $promo_bar->setEndDate();
    $promo_bar->setCountdownDate($this->storeDate(-60));
    $promo_bar->save();
    $this->cacheItem();
    $this->runCron(-600);
    $this->assertCacheItemValid(FALSE);
  }

  /**
   * Tests the promo bars that must not invalidate.
   */
  public function testNoChange(): void {
    // Started before the previous run.
    $this->createPromoBar(-900);
    // Not started yet.
    $this->createPromoBar(300);
    // Disabled.
    $this->createPromoBar(-300, ['status' => FALSE]);
    $this->cacheItem();

    $this->runCron(-600);
    $this->assertCacheItemValid(TRUE);
  }

  /**
   * Tests that each store's promo bars are checked in its own timezone.
   */
  public function testStoreTimezone(): void {
    $other_store = $this->createStore('Other store', 'other@example.com', 'online', FALSE);
    $other_store->setTimezone('Asia/Tokyo');
    $other_store->save();

    // Started 5 minutes ago in Tokyo. Read as New York time, the same stored
    // date would be 13 hours in the future.
    $promo_bar = PromoBar::create([
      'label' => 'Tokyo',
      'stores' => [$other_store->id()],
      'status' => TRUE,
    ]);
    $promo_bar->setStartDate(DrupalDateTime::createFromTimestamp($this->requestTime - 300, 'Asia/Tokyo'));
    $promo_bar->save();
    $this->cacheItem();

    $this->runCron(-600);
    $this->assertCacheItemValid(FALSE);
  }

  /**
   * Creates a promo bar for the default store.
   *
   * @param int $start
   *   The start date, in seconds relative to the request time.
   * @param array $values
   *   Additional field values.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface
   *   The saved promo bar.
   */
  protected function createPromoBar(int $start, array $values = []): PromoBarInterface {
    $promo_bar = PromoBar::create($values + [
      'label' => 'Promo bar ' . $start,
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promo_bar->setStartDate($this->storeDate($start));
    $promo_bar->save();
    return $promo_bar;
  }

  /**
   * Gets a date relative to the request time, in the store's timezone.
   *
   * @param int $offset
   *   The offset in seconds.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The date.
   */
  protected function storeDate(int $offset): DrupalDateTime {
    return DrupalDateTime::createFromTimestamp($this->requestTime + $offset, $this->store->getTimezone());
  }

  /**
   * Runs the cron job.
   *
   * @param int|null $last_run
   *   The previous run, in seconds relative to the request time, or NULL for
   *   the first run.
   */
  protected function runCron(?int $last_run = NULL): void {
    $key_value = $this->container->get('commerce_promo_bar.cron')->getKeyValueStore();
    if ($last_run === NULL) {
      $key_value->delete(Cron::LAST_RUN_KEY);
    }
    else {
      $key_value->set(Cron::LAST_RUN_KEY, $this->requestTime + $last_run);
    }
    $this->container->get('commerce_promo_bar.cron')->run();
  }

  /**
   * Gets the recorded previous run.
   *
   * @return int|null
   *   The timestamp, or NULL if never run.
   */
  protected function getLastRun(): ?int {
    return $this->container->get('commerce_promo_bar.cron')->getKeyValueStore()->get(Cron::LAST_RUN_KEY);
  }

  /**
   * Caches an item tagged with the promo bar list cache tag.
   *
   * Saving promo bars invalidates the tag, so call this after the last save.
   */
  protected function cacheItem(): void {
    $this->container->get('cache.default')->set('promo_bar_test', 'cached', tags: ['commerce_promo_bar_list']);
  }

  /**
   * Asserts whether the cached item is still valid.
   *
   * @param bool $valid
   *   TRUE if the item must still be valid.
   */
  protected function assertCacheItemValid(bool $valid): void {
    $this->assertSame($valid, (bool) $this->container->get('cache.default')->get('promo_bar_test'));
  }

}
