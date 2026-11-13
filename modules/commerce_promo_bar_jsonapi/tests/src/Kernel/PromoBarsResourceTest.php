<?php

namespace Drupal\Tests\commerce_promo_bar_jsonapi\Kernel;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests GET /jsonapi/promo-bars.
 *
 * @coversDefaultClass \Drupal\commerce_promo_bar_jsonapi\Resource\PromoBars
 *
 * @group commerce_promo_bar
 */
class PromoBarsResourceTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'serialization',
    'jsonapi',
    'jsonapi_resources',
    'commerce_promotion',
    'commerce_promo_bar',
    'commerce_promo_bar_jsonapi',
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
    $this->config('system.site')->set('name', 'Promo site')->save();
    $this->requestTime = $this->container->get('datetime.time')->getRequestTime();
    // Use up user 1; requests are anonymous.
    $this->createUser();
  }

  /**
   * Tests the promo bars of a page and their attributes.
   *
   * @covers ::process
   * @covers ::getAttributes
   */
  public function testProcess(): void {
    $everywhere = $this->createPromoBar('Everywhere', [
      'body' => ['value' => 'Free shipping at [site:name]', 'format' => 'plain_text'],
      'background_color' => ['color' => '#000000', 'opacity' => 1],
      'dismissible' => TRUE,
    ]);
    $countdown_date = DrupalDateTime::createFromTimestamp($this->requestTime + 3600, $this->store->getTimezone());
    $everywhere->setCountdownDate($countdown_date)->save();
    $this->createPromoBar('Blog only', ['pages' => '/blog', 'visibility' => TRUE]);
    $this->createPromoBar('Disabled', ['status' => FALSE]);

    [$document, $response] = $this->request('/jsonapi/promo-bars?pagePath=/blog');
    $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    $titles = array_map(fn (array $resource): string => $resource['attributes']['title'], $document['data']);
    $this->assertEqualsCanonicalizing(['Blog only', 'Everywhere'], $titles);

    $resource = $document['data'][array_search('Everywhere', $titles, TRUE)];
    $this->assertSame('promo-bar', $resource['type']);
    $this->assertSame($everywhere->uuid(), $resource['id']);
    $this->assertSame([
      'title' => 'Everywhere',
      'background_color' => '#000000',
      'text_color' => '#FFFFFF',
      'countdown_date' => $countdown_date->format('c'),
      'dismissible' => TRUE,
    ], array_diff_key($resource['attributes'], ['message' => TRUE]));
    $this->assertStringContainsString('Free shipping at Promo site', $resource['attributes']['message']);

    // Without a page, the front page.
    [$document] = $this->request('/jsonapi/promo-bars');
    $this->assertSame(['Everywhere'], array_map(fn (array $resource): string => $resource['attributes']['title'], $document['data']));

    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    $contexts = $response->getCacheableMetadata()->getCacheContexts();
    foreach (['url.query_args:pagePath', 'languages:language_content', 'store', 'user.roles', 'country'] as $context) {
      $this->assertContains($context, $contexts);
    }
    $this->assertContains('commerce_promo_bar_list', $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * Requests a JSON:API document.
   *
   * @param string $uri
   *   The URI.
   *
   * @return array
   *   The decoded document and the response.
   */
  protected function request(string $uri): array {
    $request = Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/vnd.api+json']);
    $response = $this->container->get('http_kernel')->handle($request);
    return [json_decode((string) $response->getContent(), TRUE), $response];
  }

  /**
   * Creates a promo bar for the default store, started an hour ago.
   *
   * @param string $label
   *   The label.
   * @param array $values
   *   Additional field values.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface
   *   The saved promo bar.
   */
  protected function createPromoBar(string $label, array $values = []): PromoBarInterface {
    $promo_bar = PromoBar::create($values + [
      'label' => $label,
      'body' => ['value' => $label, 'format' => 'plain_text'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promo_bar->setStartDate(DrupalDateTime::createFromTimestamp($this->requestTime - 3600, $this->store->getTimezone()));
    $promo_bar->save();
    return $promo_bar;
  }

}
