<?php

namespace Drupal\commerce_promo_bar\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\InlineFormManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_promo_bar_promotion' widget.
 *
 * References an existing promotion, or creates a new one through the
 * Commerce "content_entity" inline form.
 */
#[FieldWidget(
  id: "commerce_promo_bar_promotion",
  label: new TranslatableMarkup("Existing or new promotion"),
  field_types: ["entity_reference"],
)]
class PromotionWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The mode without a promotion.
   */
  const MODE_NONE = 'none';

  /**
   * The mode referencing an existing promotion.
   */
  const MODE_EXISTING = 'existing';

  /**
   * The mode creating a new promotion.
   */
  const MODE_NEW = 'new';

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The inline form manager.
   */
  protected InlineFormManager $inlineFormManager;

  /**
   * The entity display repository.
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $element['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode of new promotions'),
      '#default_value' => $this->getSetting('form_mode'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions('commerce_promotion'),
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $form_modes = $this->entityDisplayRepository->getFormModeOptions('commerce_promotion');
    $form_mode = $this->getSetting('form_mode');
    return [
      $this->t('Form mode of new promotions: @mode', ['@mode' => $form_modes[$form_mode] ?? $form_mode]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface|null $promotion */
    $promotion = $items[$delta]->entity ?? NULL;
    $parents = array_merge($element['#field_parents'], [$items->getName(), $delta]);
    $wrapper_id = Html::getId(implode('-', $parents) . '-promotion-wrapper');
    $modes = [
      self::MODE_NONE => $this->t('None'),
      self::MODE_EXISTING => $this->t('Existing promotion'),
      self::MODE_NEW => $this->t('New promotion'),
    ];
    $mode = $this->getMode($parents, $promotion, $form_state);

    $element += [
      '#type' => 'fieldset',
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    $element['#rendered_mode'] = $mode;
    $element['#element_validate'][] = [static::class, 'validateMode'];
    $element['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Promotion'),
      '#title_display' => 'invisible',
      '#options' => $modes,
      '#default_value' => $mode,
      '#ajax' => [
        'callback' => [static::class, 'ajaxRefresh'],
        'wrapper' => $wrapper_id,
      ],
    ];
    // Without JavaScript, the mode is applied with this button.
    $element['change_mode'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change promotion'),
      '#name' => implode('_', $parents) . '_change_mode',
      '#limit_validation_errors' => [array_merge($parents, ['mode'])],
      '#submit' => [[static::class, 'changeModeSubmit']],
      '#attributes' => ['class' => ['js-hide']],
    ];

    if ($mode === self::MODE_EXISTING) {
      $element['target_id'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Promotion'),
        '#target_type' => 'commerce_promotion',
        '#selection_handler' => $this->getFieldSetting('handler'),
        '#selection_settings' => $this->getFieldSetting('handler_settings') ?: [],
        '#default_value' => $promotion,
        '#maxlength' => 1024,
        '#required' => TRUE,
      ];
    }
    elseif ($mode === self::MODE_NEW) {
      $new_promotion = $this->entityTypeManager->getStorage('commerce_promotion')->create([
        'langcode' => $items->getEntity()->language()->getId(),
      ]);
      $inline_form = $this->inlineFormManager->createInstance('content_entity', [
        'form_mode' => $this->getSetting('form_mode'),
      ], $new_promotion);

      $element['entity'] = [
        '#type' => 'container',
        '#parents' => array_merge($parents, ['entity']),
        '#inline_form' => $inline_form,
      ];
      $element['entity'] = $inline_form->buildInlineForm($element['entity'], $form_state);
    }

    return $element;
  }

  /**
   * Gets the mode to build the element for.
   *
   * A submit is processed against the elements that were rendered: building
   * other elements at that point would treat their missing input as empty
   * values, and lose their defaults. The selected mode is applied when the
   * form is rebuilt, after the mode radios or the "Change promotion" button.
   *
   * @param array $parents
   *   The parents of the element.
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface|null $promotion
   *   The referenced promotion.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The mode.
   */
  protected function getMode(array $parents, $promotion, FormStateInterface $form_state): string {
    $modes = [self::MODE_NONE, self::MODE_EXISTING, self::MODE_NEW];
    if ($form_state->isRebuilding()) {
      $mode = $form_state->getValue(array_merge($parents, ['mode']));
      if (in_array($mode, $modes, TRUE)) {
        return $mode;
      }
    }

    $input = NestedArray::getValue($form_state->getUserInput(), $parents);
    if (is_array($input)) {
      // The elements that were rendered.
      if (array_key_exists('entity', $input)) {
        return self::MODE_NEW;
      }
      if (array_key_exists('target_id', $input)) {
        return self::MODE_EXISTING;
      }
      if (array_key_exists('mode', $input)) {
        return self::MODE_NONE;
      }
    }

    return $promotion ? self::MODE_EXISTING : self::MODE_NONE;
  }

  /**
   * Validates that the selected mode was applied before saving.
   *
   * Only happens without JavaScript, when a different mode is selected and
   * the form is saved without pressing "Change promotion".
   */
  public static function validateMode(array &$element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    // Elements of the widget, such as the mode radios, the "Change promotion"
    // button or Ajax elements of the new promotion form, rebuild the form.
    if ($triggering_element && array_slice($triggering_element['#parents'], 0, count($element['#parents'])) === $element['#parents']) {
      return;
    }
    $mode = $form_state->getValue(array_merge($element['#parents'], ['mode']));
    if ($mode !== $element['#rendered_mode']) {
      $form_state->setError($element['mode'], t('Press "Change promotion" to apply the selected promotion option.'));
    }
  }

  /**
   * Submit callback for the "Change promotion" button.
   */
  public static function changeModeSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for switching the mode.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    // The triggering element is one of the mode radios.
    return NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $element = NestedArray::getValue($form, array_merge($form['#parents'], [$field_name, 'widget', 0]));
    $values = $form_state->getValue(array_merge($form['#parents'], [$field_name, 0])) ?: [];

    $items->setValue([]);
    switch ($values['mode'] ?? self::MODE_NONE) {
      case self::MODE_EXISTING:
        if (!empty($values['target_id'])) {
          $items->setValue([['target_id' => $values['target_id']]]);
        }
        break;

      case self::MODE_NEW:
        /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface|null $inline_form */
        $inline_form = $element['entity']['#inline_form'] ?? NULL;
        if ($inline_form) {
          // The inline form saves the promotion before the promo bar is
          // built for saving. During validation, it is still new.
          $items->setValue([['entity' => $inline_form->getEntity()]]);
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getSetting('target_type') === 'commerce_promotion'
      && $field_definition->getFieldStorageDefinition()->getCardinality() === 1;
  }

}
