<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel;

use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Drupal\commerce_promo_bar\PromoBarStorage;
use Drupal\commerce_promo_bar_test\EventSubscriber\FilterPromoBarsSubscriber;
use Drupal\commerce_promo_bar_test\TestCountryResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests restricting promo bars by country.
 */
#[CoversClass(PromoBarStorage::class)]
#[CoversClass(PromoBar::class)]
#[Group('commerce_promo_bar')]
#[RunTestsInSeparateProcesses]
class PromoBarCountryTest extends OrderKernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promo_bar');
    $this->installConfig(['commerce_promo_bar']);
  }

  /**
   * Tests the country fields.
   */
  public function testFields(): void {
    $promo_bar = $this->createPromoBar('All countries');
    $this->assertSame([], $promo_bar->getCountries());
    $this->assertTrue($promo_bar->isCountryVisibility());

    $promo_bar->setCountries(['FR', 'DE']);
    $promo_bar->set('country_visibility', FALSE);
    $promo_bar->save();
    /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar */
    $promo_bar = $this->reloadEntity($promo_bar);
    $this->assertSame(['FR', 'DE'], $promo_bar->getCountries());
    $this->assertFalse($promo_bar->isCountryVisibility());
  }

  /**
   * Tests the available promo bars per country.
   */
  public function testLoadAvailable(): void {
    $this->createPromoBar('All countries');
    $this->createPromoBar('France and Germany', [
      'countries' => ['FR', 'DE'],
    ]);
    $this->createPromoBar('United States', [
      'countries' => ['US'],
    ]);
    $this->createPromoBar('Not in France', [
      'countries' => ['FR'],
      'country_visibility' => FALSE,
    ]);

    // Without a resolved country of the site's own, the store's country, US,
    // applies.
    $this->assertAvailable(['All countries', 'Not in France', 'United States']);

    // A resolver that returns a different country.
    $this->setCountry('FR');
    $this->assertAvailable(['All countries', 'France and Germany']);

    $this->setCountry('DE');
    $this->assertAvailable(['All countries', 'France and Germany', 'Not in France']);
  }

  /**
   * Tests that the filter event only receives promo bars for the country.
   */
  public function testFilterEvent(): void {
    $this->createPromoBar('All countries');
    $this->createPromoBar('France', [
      'countries' => ['FR'],
    ]);

    $this->assertAvailable(['All countries']);
    $this->assertSame(['All countries'], $this->container->get('state')->get(FilterPromoBarsSubscriber::STATE_KEY));
  }

  /**
   * Tests the update that installs the country fields.
   */
  public function testUpdate(): void {
    $promo_bar = $this->createPromoBar('Existing');
    $update_manager = $this->container->get('entity.definition_update_manager');
    foreach (['countries', 'country_visibility'] as $field_name) {
      $update_manager->uninstallFieldStorageDefinition($update_manager->getFieldStorageDefinition($field_name, 'commerce_promo_bar'));
    }
    // Kernel tests leave unrelated entity types uninstalled, so only look at
    // the promo bar changes.
    $this->assertArrayHasKey('commerce_promo_bar', $update_manager->getChangeSummary());

    $this->container->get('module_handler')->loadInclude('commerce_promo_bar', 'install');
    commerce_promo_bar_update_10002();
    $this->assertArrayNotHasKey('commerce_promo_bar', $update_manager->getChangeSummary());

    // Existing promo bars are shown for the listed countries.
    /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar */
    $promo_bar = $this->reloadEntity($promo_bar);
    $this->assertTrue($promo_bar->isCountryVisibility());
    $this->assertSame([], $promo_bar->getCountries());
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
      'start_date' => gmdate('Y-m-d\TH:i:s', $this->container->get('datetime.time')->getRequestTime() - 86400),
      'status' => TRUE,
    ]);
    $promo_bar->save();
    return $promo_bar;
  }

  /**
   * Sets the country the test resolver returns.
   *
   * The current country is cached per request, so a new request is pushed.
   *
   * @param string $country_code
   *   The country code.
   */
  protected function setCountry(string $country_code): void {
    $this->container->get('state')->set(TestCountryResolver::STATE_KEY, $country_code);
    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Asserts the labels of the available promo bars.
   *
   * @param string[] $labels
   *   The expected labels, sorted.
   */
  protected function assertAvailable(array $labels): void {
    /** @var \Drupal\commerce_promo_bar\PromoBarStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('commerce_promo_bar');
    $available = array_map(fn (PromoBarInterface $promo_bar) => $promo_bar->label(), $storage->loadAvailable($this->store));
    sort($available);
    $this->assertSame($labels, $available);
  }

}
