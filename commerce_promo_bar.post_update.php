<?php

/**
 * @file
 * Post update functions for Commerce Promo Bar.
 */

/**
 * Replace the inline entity form promotion widget.
 */
function commerce_promo_bar_post_update_promotion_widget() {
  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface[] $form_displays */
  $form_displays = \Drupal::entityTypeManager()->getStorage('entity_form_display')->loadByProperties([
    'targetEntityType' => 'commerce_promo_bar',
  ]);
  foreach ($form_displays as $form_display) {
    $component = $form_display->getComponent('promotion_id');
    if ($component && str_starts_with($component['type'] ?? '', 'inline_entity_form')) {
      $form_display->setComponent('promotion_id', [
        'type' => 'commerce_promo_bar_promotion',
        'weight' => $component['weight'] ?? 15,
        'region' => $component['region'] ?? 'content',
        'settings' => [
          'form_mode' => $component['settings']['form_mode'] ?? 'default',
        ],
      ]);
      $form_display->save();
    }
  }
}
