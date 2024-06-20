<?php

namespace Drupal\commerce_promo_bar\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for a commerce promo bar entity type.
 */
class PromoBarSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_promo_bar_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_promo_bar.settings');

    $form['settings'] = [
      '#markup' => $this->t('Settings form for a commerce promo bar entity type.'),
    ];

    $form['background_color'] = [
      '#title' => $this->t('Default background color'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['background_color']['default'] = [
      '#type' => 'color_field_widget_html5',
      'color' => [
        '#type' => 'color',
        '#default_value' => $config->get('background_color.color') ?? "#000023",
      ],
      'opacity' => [
        '#title' => $this->t('Opacity'),
        '#type' => 'number',
        '#min' => 0,
        '#max' => 1,
        '#step' => 0.01,
        '#required' => TRUE,
        '#default_value' => $config->get('background_color.opacity') ?? 1,
        '#placeholder' => '',
        '#error_no_message' => TRUE,
      ],
      '#title' => $this->t('Background color'),
    ];

    $form['text_color'] = [
      '#title' => $this->t('Default text color'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['text_color']['default'] = [
      '#type' => 'color_field_widget_html5',
      'color' => [
        '#type' => 'color',
        '#default_value' => $config->get('text_color.color') ?? "#FFFFFF",
      ],
      'opacity' => [
        '#title' => $this->t('Opacity'),
        '#type' => 'number',
        '#min' => 0,
        '#max' => 1,
        '#step' => 0.01,
        '#required' => TRUE,
        '#default_value' => $config->get('text_color.opacity') ?? 1,
        '#placeholder' => '',
        '#error_no_message' => TRUE,
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $background_color = $form_state->getValue(['background_color', 'default']);
    $text_color = $form_state->getValue(['text_color', 'default']);

    $this->config('commerce_promo_bar.settings')
      ->set('background_color', $background_color)
      ->set('text_color', $text_color)
      ->save();

    // We need to clear this cache tag, so that
    // commerce_promo_bar_entity_base_field_info_alter can pickup changes.
    Cache::invalidateTags(['entity_field_info']);

    $this->messenger()->addStatus($this->t('The configuration has been updated.'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_promo_bar.settings'];
  }

}
