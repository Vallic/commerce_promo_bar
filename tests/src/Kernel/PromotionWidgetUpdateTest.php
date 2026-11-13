<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests replacing the inline entity form promotion widget.
 */
#[Group('commerce_promo_bar')]
#[RunTestsInSeparateProcesses]
class PromotionWidgetUpdateTest extends OrderKernelTestBase {

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
    $this->installConfig(['commerce_promo_bar']);
  }

  /**
   * Tests commerce_promo_bar_post_update_promotion_widget().
   */
  public function testPostUpdate(): void {
    $form_display = EntityFormDisplay::create([
      'targetEntityType' => 'commerce_promo_bar',
      'bundle' => 'commerce_promo_bar',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $form_display->setComponent('promotion_id', [
      'type' => 'inline_entity_form_complex',
      'weight' => 7,
      'settings' => [
        'form_mode' => 'default',
        'allow_existing' => TRUE,
      ],
    ]);
    $form_display->setComponent('label', [
      'type' => 'string_textfield',
    ]);
    $form_display->save();

    $this->container->get('module_handler')->loadInclude('commerce_promo_bar', 'php', 'commerce_promo_bar.post_update');
    commerce_promo_bar_post_update_promotion_widget();

    $component = EntityFormDisplay::load('commerce_promo_bar.commerce_promo_bar.default')->getComponent('promotion_id');
    $this->assertSame('commerce_promo_bar_promotion', $component['type']);
    $this->assertSame(7, $component['weight']);
    $this->assertSame(['form_mode' => 'default'], $component['settings']);
    $this->assertNotContains('inline_entity_form', EntityFormDisplay::load('commerce_promo_bar.commerce_promo_bar.default')->getDependencies()['module'] ?? []);
  }

}
