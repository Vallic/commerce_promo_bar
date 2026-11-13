<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Drupal\commerce_promo_bar\PromoBarStorage;
use Drupal\commerce_promo_bar\PromoBarStorageInterface;
use Drupal\commerce_promo_bar_test\EventSubscriber\FilterPromoBarsSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests loading the promo bars visible to a customer on a page.
 */
#[CoversClass(PromoBarStorage::class)]
#[Group('commerce_promo_bar')]
#[RunTestsInSeparateProcesses]
class PromoBarLoadVisibleTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_promotion',
    'commerce_promo_bar',
    'commerce_promo_bar_test',
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
    $this->requestTime = $this->container->get('datetime.time')->getRequestTime();
    // Use up user 1.
    $this->createUser();
  }

  /**
   * Tests the visible promo bars per page and customer.
   */
  public function testLoadVisible(): void {
    $this->createPromoBar('Everywhere');
    $this->createPromoBar('Blog only', ['pages' => '/blog', 'visibility' => TRUE]);
    $this->createPromoBar('Not on blog', ['pages' => '/blog', 'visibility' => FALSE]);
    $this->createPromoBar('Customers', ['customer_roles' => ['authenticated']]);
    $anonymous = new AnonymousUserSession();

    $this->assertVisible(['Blog only', 'Everywhere'], $anonymous, '/blog');
    $this->assertVisible(['Everywhere', 'Not on blog'], $anonymous, '/shop');
    $this->assertVisible(['Customers', 'Everywhere', 'Not on blog'], $this->createUser(), '/shop');

    // Without a path, the current path applies.
    $this->container->get('path.current')->setPath('/blog');
    $this->assertVisible(['Blog only', 'Everywhere'], $anonymous);
  }

  /**
   * Tests the context the filter event receives.
   */
  public function testFilterEventContext(): void {
    $this->createPromoBar('Everywhere');
    $state = $this->container->get('state');
    $customer = $this->createUser();

    $this->getStorage()->loadVisible($this->store, $customer, '/blog');
    $this->assertSame(['path' => '/blog', 'account' => $customer->id()], $state->get(FilterPromoBarsSubscriber::CONTEXT_STATE_KEY));

    $this->container->get('path.current')->setPath('/shop');
    $this->getStorage()->loadVisible($this->store, $customer);
    $this->assertSame(['path' => '/shop', 'account' => $customer->id()], $state->get(FilterPromoBarsSubscriber::CONTEXT_STATE_KEY));

    // Not loaded for a page or a customer.
    $this->getStorage()->loadAvailable($this->store);
    $this->assertSame(['path' => NULL, 'account' => NULL], $state->get(FilterPromoBarsSubscriber::CONTEXT_STATE_KEY));

    // Subscribers add to the cacheability.
    $state->set(FilterPromoBarsSubscriber::ADD_CONTEXT_STATE_KEY, TRUE);
    $cacheability = new CacheableMetadata();
    $this->getStorage()->loadVisible($this->store, $customer, '/blog', $cacheability);
    $this->assertContains('url.query_args:promo_test', $cacheability->getCacheContexts());
  }

  /**
   * Tests the cacheability of the result.
   */
  public function testCacheability(): void {
    $anonymous = new AnonymousUserSession();

    $cacheability = new CacheableMetadata();
    $this->getStorage()->loadVisible($this->store, $anonymous, '/blog', $cacheability);
    $this->assertEqualsCanonicalizing(['country', 'store', 'user.roles'], $cacheability->getCacheContexts());
    $this->assertSame(['commerce_promo_bar_list'], $cacheability->getCacheTags());
    $this->assertSame(Cache::PERMANENT, $cacheability->getCacheMaxAge());

    // Without a path, the result varies by the current path.
    $cacheability = new CacheableMetadata();
    $this->getStorage()->loadVisible($this->store, $anonymous, NULL, $cacheability);
    $this->assertContains('url.path', $cacheability->getCacheContexts());

    // An available promo bar ends in 10 minutes, even when not visible on the
    // page. A countdown only counts when the promo bar is visible.
    $this->createPromoBar('Shop only', [
      'pages' => '/shop',
      'visibility' => TRUE,
      'end_date' => $this->storeDate(600),
      'countdown_date' => $this->storeDate(60),
    ]);
    $cacheability = new CacheableMetadata();
    $this->getStorage()->loadVisible($this->store, $anonymous, '/blog', $cacheability);
    $this->assertSame(600, $cacheability->getCacheMaxAge());

    $cacheability = new CacheableMetadata();
    $this->getStorage()->loadVisible($this->store, $anonymous, '/shop', $cacheability);
    $this->assertSame(60, $cacheability->getCacheMaxAge());
  }

  /**
   * Gets the promo bar storage.
   *
   * @return \Drupal\commerce_promo_bar\PromoBarStorageInterface
   *   The promo bar storage.
   */
  protected function getStorage(): PromoBarStorageInterface {
    /** @var \Drupal\commerce_promo_bar\PromoBarStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('commerce_promo_bar');
    return $storage;
  }

  /**
   * Creates a promo bar for the default store, started an hour ago.
   *
   * @param string $label
   *   The label.
   * @param array $values
   *   Additional field values. Dates are DrupalDateTime objects.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface
   *   The saved promo bar.
   */
  protected function createPromoBar(string $label, array $values = []): PromoBarInterface {
    $end_date = $values['end_date'] ?? NULL;
    $countdown_date = $values['countdown_date'] ?? NULL;
    unset($values['end_date'], $values['countdown_date']);

    $promo_bar = PromoBar::create($values + [
      'label' => $label,
      'body' => ['value' => $label, 'format' => 'plain_text'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promo_bar->setStartDate($this->storeDate(-3600));
    $promo_bar->setEndDate($end_date);
    $promo_bar->setCountdownDate($countdown_date);
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
   * Asserts the labels of the visible promo bars.
   *
   * @param string[] $labels
   *   The expected labels, sorted.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The customer.
   * @param string|null $path
   *   The page path, or NULL for the current path.
   */
  protected function assertVisible(array $labels, $account, ?string $path = NULL): void {
    $visible = array_map(fn (PromoBarInterface $promo_bar) => $promo_bar->label(), $this->getStorage()->loadVisible($this->store, $account, $path));
    sort($visible);
    $this->assertSame($labels, $visible);
  }

}
