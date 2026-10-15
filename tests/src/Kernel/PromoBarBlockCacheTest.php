<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\block\Entity\Block;
use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;

/**
 * Tests the cacheability of the promo bar block.
 *
 * @coversDefaultClass \Drupal\commerce_promo_bar\Plugin\Block\PromoBarBlock
 *
 * @group commerce_promo_bar
 */
class PromoBarBlockCacheTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
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
    $this->installConfig(['filter', 'commerce_promo_bar']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    // Promo bar dates are stored in the store's timezone, so use one that
    // differs from UTC to catch conversion mistakes.
    $this->store->setTimezone('America/New_York');
    $this->store->save();

    $this->requestTime = $this->container->get('datetime.time')->getRequestTime();

    // Use up user 1: as the super user its permission hash differs from
    // other users', which would vary the render cache on its own.
    $this->createUser();
  }

  /**
   * Tests that the block varies by customer roles.
   *
   * @covers ::getCacheContexts
   */
  public function testRolesCacheContext(): void {
    $this->createPromoBar('Customers only', -3600, NULL, [
      'customer_roles' => ['authenticated'],
    ]);
    $block = $this->placeBlock();

    $this->assertStringNotContainsString('Customers only', $this->renderBlock($block));

    $this->setCurrentUser($this->createUser());
    $this->assertStringContainsString('Customers only', $this->renderBlock($block));
  }

  /**
   * Tests that the block expires when the available promo bars change.
   *
   * @covers ::build
   */
  public function testMaxAge(): void {
    $block = $this->placeBlock();
    $this->assertSame(Cache::PERMANENT, $this->getMaxAge($block));

    // An available promo bar without an end date does not expire.
    $promo_bar = $this->createPromoBar('Running', -3600);
    $this->assertSame(Cache::PERMANENT, $this->getMaxAge($block));

    // Disabled promo bars and promo bars of other stores never start here.
    $this->createPromoBar('Disabled', 30, NULL, ['status' => FALSE]);
    $other_store = $this->createStore('Other store', 'other@example.com', 'online', FALSE);
    $this->createPromoBar('Other store', 30, NULL, ['stores' => [$other_store->id()]]);
    $this->assertSame(Cache::PERMANENT, $this->getMaxAge($block));

    // The next scheduled promo bar starts in 5 minutes.
    $this->createPromoBar('Scheduled', 300);
    $this->createPromoBar('Scheduled later', 900);
    $this->assertSame(300, $this->getMaxAge($block));

    // The running promo bar ends sooner.
    $promo_bar->setEndDate($this->storeDate(120));
    $promo_bar->save();
    $this->assertSame(120, $this->getMaxAge($block));

    // Its countdown runs out sooner still.
    $promo_bar->setCountdownDate($this->storeDate(60));
    $promo_bar->save();
    $this->assertSame(60, $this->getMaxAge($block));
  }

  /**
   * Creates a promo bar for the default store.
   *
   * @param string $label
   *   The label, which is also used as the body.
   * @param int $start
   *   The start date, in seconds relative to the request time.
   * @param int|null $end
   *   The end date, in seconds relative to the request time.
   * @param array $values
   *   Additional field values.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface
   *   The saved promo bar.
   */
  protected function createPromoBar(string $label, int $start, ?int $end = NULL, array $values = []): PromoBarInterface {
    $promo_bar = PromoBar::create($values + [
      'label' => $label,
      'body' => ['value' => $label, 'format' => 'plain_text'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promo_bar->setStartDate($this->storeDate($start));
    if ($end !== NULL) {
      $promo_bar->setEndDate($this->storeDate($end));
    }
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
   * Places the promo bar block in the default theme.
   *
   * @return \Drupal\block\Entity\Block
   *   The block.
   */
  protected function placeBlock(): Block {
    $block = Block::create([
      'id' => 'promo_bar',
      'theme' => 'stark',
      'region' => 'content',
      'plugin' => 'promo_bar_block',
    ]);
    $block->save();
    return $block;
  }

  /**
   * Renders the block through the render cache.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   *
   * @return string
   *   The markup.
   */
  protected function renderBlock(Block $block): string {
    $build = $this->container->get('entity_type.manager')->getViewBuilder('block')->view($block);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * Gets the max-age the rendered block bubbles up.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   *
   * @return int
   *   The max-age.
   */
  protected function getMaxAge(Block $block): int {
    $build = $this->container->get('entity_type.manager')->getViewBuilder('block')->view($block);
    $this->container->get('renderer')->renderRoot($build);
    return CacheableMetadata::createFromRenderArray($build)->getCacheMaxAge();
  }

}
