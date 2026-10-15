<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;

/**
 * Tests the promo bar body processing.
 *
 * @coversDefaultClass \Drupal\commerce_promo_bar\PromoBarBodyProcessor
 *
 * @group commerce_promo_bar
 */
class PromoBarBodyProcessorTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_promotion',
    'commerce_promo_bar',
    'color_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promo_bar');
    $this->installConfig(['filter', 'commerce_promo_bar']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->config('system.site')->set('name', 'Promo site')->save();
  }

  /**
   * Tests the tokens and filters outside of entity rendering.
   *
   * @covers ::process
   * @covers ::replaceTokens
   */
  public function testProcess(): void {
    $promo_bar = $this->createPromoBar('Welcome to [site:name] <b>today</b>');
    /** @var \Drupal\commerce_promo_bar\PromoBarBodyProcessorInterface $body_processor */
    $body_processor = $this->container->get('commerce_promo_bar.body_processor');

    $metadata = new BubbleableMetadata();
    $text = $body_processor->replaceTokens($promo_bar->getDescription(), $promo_bar, $metadata);
    $this->assertSame('Welcome to Promo site <b>today</b>', $text);
    $this->assertContains('config:system.site', $metadata->getCacheTags());

    // The plain text format escapes HTML and adds a paragraph.
    $metadata = new BubbleableMetadata();
    $markup = $body_processor->process($promo_bar, $metadata);
    $this->assertSame('<p>Welcome to Promo site &lt;b&gt;today&lt;/b&gt;</p>', trim($markup));
    $this->assertContains('config:system.site', $metadata->getCacheTags());
    $this->assertContains('config:filter.format.plain_text', $metadata->getCacheTags());
  }

  /**
   * Tests that rendering the promo bar bubbles the token cacheability.
   *
   * @covers ::replaceTokens
   */
  public function testRender(): void {
    $promo_bar = $this->createPromoBar('Welcome to [site:name]');
    $build = $this->container->get('entity_type.manager')->getViewBuilder('commerce_promo_bar')->view($promo_bar);
    $output = (string) $this->container->get('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Welcome to Promo site', $output);
    $this->assertContains('config:system.site', CacheableMetadata::createFromRenderArray($build)->getCacheTags());
  }

  /**
   * Creates a promo bar.
   *
   * @param string $body
   *   The body, in the plain text format.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface
   *   The saved promo bar.
   */
  protected function createPromoBar(string $body): PromoBarInterface {
    $promo_bar = PromoBar::create([
      'label' => 'Promo bar',
      'body' => ['value' => $body, 'format' => 'plain_text'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promo_bar->save();
    return $promo_bar;
  }

}
