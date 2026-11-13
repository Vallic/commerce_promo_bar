<?php

namespace Drupal\Tests\commerce_promo_bar\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Drupal\commerce_promo_bar\Plugin\Field\FieldWidget\PromotionWidget;
use Drupal\commerce_promotion\Entity\Promotion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the promotion widget of the promo bar form.
 *
 * Runs without JavaScript, so the mode is applied with the "Change promotion"
 * button.
 */
#[CoversClass(PromotionWidget::class)]
#[Group('commerce_promo_bar')]
#[RunTestsInSeparateProcesses]
class PromotionWidgetTest extends CommerceBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_order',
    'commerce_promotion',
    'commerce_promo_bar',
    'commerce_promo_bar_test',
    'color_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce promo bar',
      'administer commerce_promotion',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests referencing an existing promotion and removing it.
   */
  public function testExistingPromotion(): void {
    $promotion = Promotion::create([
      'name' => 'Ten off',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => ['percentage' => '0.10'],
      ],
      'status' => TRUE,
    ]);
    $promotion->save();

    $this->drupalGet('promo-bar/add');
    $this->assertSession()->checkboxChecked('edit-promotion-id-0-mode-none');
    $this->assertSession()->fieldNotExists('promotion_id[0][target_id]');
    $this->submitForm([
      'label[0][value]' => 'Existing',
      'body[0][value]' => 'Ten percent off',
      'promotion_id[0][mode]' => 'existing',
    ], 'Change promotion');
    // The autocomplete is added, and the other values are kept.
    $this->assertSession()->fieldValueEquals('label[0][value]', 'Existing');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Promotion field is required.');

    $this->submitForm([
      'promotion_id[0][target_id]' => 'Ten off (' . $promotion->id() . ')',
    ], 'Save');
    $this->assertSession()->pageTextContains('New promo bar Existing has been created.');
    $promo_bar = $this->loadPromoBar('Existing');
    $this->assertEquals($promotion->id(), $promo_bar->getPromotionId());

    $this->drupalGet($promo_bar->toUrl('edit-form'));
    $this->assertSession()->checkboxChecked('edit-promotion-id-0-mode-existing');
    $this->assertSession()->fieldValueEquals('promotion_id[0][target_id]', 'Ten off (' . $promotion->id() . ')');

    // Saving a mode that was not applied is refused.
    $this->submitForm(['promotion_id[0][mode]' => 'none'], 'Save');
    $this->assertSession()->pageTextContains('Press "Change promotion" to apply the selected promotion option.');
    $this->assertEquals($promotion->id(), $this->loadPromoBar('Existing')->getPromotionId());

    $this->submitForm(['promotion_id[0][mode]' => 'none'], 'Change promotion');
    $this->assertSession()->fieldNotExists('promotion_id[0][target_id]');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The promo bar Existing has been updated.');
    $this->assertNull($this->loadPromoBar('Existing')->getPromotionId());
    // The promotion itself is kept.
    $this->assertNotNull(Promotion::load($promotion->id()));
  }

  /**
   * Tests creating a new promotion with the promo bar.
   */
  public function testNewPromotion(): void {
    $this->drupalGet('promo-bar/add');
    $this->submitForm([
      'label[0][value]' => 'New',
      'body[0][value]' => 'Fifteen percent off',
      'promotion_id[0][mode]' => 'new',
    ], 'Change promotion');
    // The promotion form is added with its default values.
    $this->assertSession()->fieldExists('promotion_id[0][entity][name][0][value]');
    $this->assertSession()->fieldValueNotEquals('promotion_id[0][entity][start_date][0][value][date]', '');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Name field is required.');

    // The test module leaves only the order percentage offer.
    $this->submitForm([
      'promotion_id[0][entity][name][0][value]' => 'Fifteen off',
      'promotion_id[0][entity][offer][0][target_plugin_configuration][order_percentage_off][percentage]' => '15',
    ], 'Save');
    $this->assertSession()->pageTextContains('New promo bar New has been created.');

    $promotions = Promotion::loadMultiple();
    $this->assertCount(1, $promotions);
    $promotion = reset($promotions);
    $this->assertEquals('Fifteen off', $promotion->getName());
    $this->assertEquals('0.15', $promotion->getOffer()->getConfiguration()['percentage']);
    $this->assertEquals($promotion->id(), $this->loadPromoBar('New')->getPromotionId());
  }

  /**
   * Loads a promo bar by label, bypassing the static cache.
   *
   * @param string $label
   *   The label.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface
   *   The promo bar.
   */
  protected function loadPromoBar(string $label): PromoBarInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('commerce_promo_bar');
    $storage->resetCache();
    /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface[] $promo_bars */
    $promo_bars = $storage->loadByProperties(['label' => $label]);
    $this->assertCount(1, $promo_bars);
    return reset($promo_bars);
  }

}
