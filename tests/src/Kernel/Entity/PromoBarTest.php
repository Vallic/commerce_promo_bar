<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel\Entity;

use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests the PromoBar entity.
 *
 * @coversDefaultClass \Drupal\commerce_promo_bar\Entity\PromoBar
 *
 * @group commerce
 */
class PromoBarTest extends OrderKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
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
    $this->installConfig(['commerce_promo_bar']);
  }

  /**
   * @covers ::getName
   * @covers ::setName
   * @covers ::getDescription
   * @covers ::setDescription
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getStores
   * @covers ::setStores
   * @covers ::setStoreIds
   * @covers ::getStoreIds
   * @covers ::getStartDate
   * @covers ::setStartDate
   * @covers ::getEndDate
   * @covers ::setEndDate
   * @covers ::getCountdownDate
   * @covers ::setCountdownDate
   * @covers ::isEnabled
   * @covers ::setEnabled
   * @covers ::getOwner
   * @covers ::setOwner
   * @covers ::getOwnerId
   * @covers ::setOwnerId
   */
  public function testPromoBar() {
    $promo_bar = PromoBar::create([
      'status' => FALSE,
    ]);

    $promo_bar->setName('My PromoBar');
    $this->assertEquals('My PromoBar', $promo_bar->getName());

    $promo_bar->setDescription('My PromoBar Description');
    $this->assertEquals('My PromoBar Description', $promo_bar->getDescription());

    $promo_bar->setCreatedTime(635879700);
    $this->assertEquals(635879700, $promo_bar->getCreatedTime());

    $promo_bar->setStores([$this->store]);
    $this->assertEquals([$this->store], $promo_bar->getStores());

    $promo_bar->setStoreIds([$this->store->id()]);
    $this->assertEquals([$this->store->id()], $promo_bar->getStoreIds());
    $promo_bar->save();

    /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar */
    $promo_bar = $this->reloadEntity($promo_bar);
    $this->assertEquals($promo_bar->id(), 1);

    $date_pattern = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $time = $this->container->get('datetime.time');
    $default_start_date = date($date_pattern, $time->getRequestTime());
    $this->assertEquals($default_start_date, $promo_bar->getStartDate()->format($date_pattern));
    $promo_bar->setStartDate(new DrupalDateTime('2017-01-01 12:12:12'));
    $this->assertEquals('2017-01-01 12:12:12 UTC', $promo_bar->getStartDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-01 12:12:12 CET', $promo_bar->getStartDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $this->assertNull($promo_bar->getEndDate());
    $promo_bar->setEndDate(new DrupalDateTime('2017-01-31 17:15:00'));
    $this->assertEquals('2017-01-31 17:15:00 UTC', $promo_bar->getEndDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-31 17:15:00 CET', $promo_bar->getEndDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $this->assertNull($promo_bar->getCountdownDate());
    $promo_bar->setEndDate(new DrupalDateTime('2017-01-31 17:15:00'));
    $this->assertEquals('2017-01-31 17:15:00 UTC', $promo_bar->getEndDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-31 17:15:00 CET', $promo_bar->getEndDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $promo_bar->setEnabled(TRUE);
    $this->assertEquals(TRUE, $promo_bar->isEnabled());

    $promo_bar->setOwnerId(900);
    $this->assertEquals(900, $promo_bar->getOwnerId());
    $this->assertTrue($promo_bar->getOwner()->isAnonymous());
    $promo_bar->save();
    $this->assertEquals(0, $promo_bar->getOwnerId());

    $this->assertEmpty($promo_bar->getCustomerRoles());
    $promo_bar->setCustomerRoles(['anonymous']);
    $this->assertEquals(['anonymous'], $promo_bar->getCustomerRoles());
  }

  /**
   * @covers ::createDuplicate
   */
  public function testDuplicate() {
    $promo_bar = PromoBar::create([
      'label' => 'New promo bar',
      'status' => FALSE,
    ]);
    $promo_bar->save();

    $duplicate_promo_bar = $promo_bar->createDuplicate();
    $this->assertEquals('New promo bar', $duplicate_promo_bar->label());
  }

}
