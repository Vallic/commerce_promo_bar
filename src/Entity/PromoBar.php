<?php

namespace Drupal\commerce_promo_bar\Entity;

use Drupal\commerce\Entity\CommerceContentEntityBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\commerce\EntityOwnerTrait;

/**
 * Defines the promo bar entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_promo_bar",
 *   label = @Translation("Promo bar"),
 *   label_collection = @Translation("Promo bars"),
 *   label_singular = @Translation("promo bar"),
 *   label_plural = @Translation("promo bars"),
 *   label_count = @PluralTranslation(
 *     singular = "@count promo bars",
 *     plural = "@count promo bars",
 *     context = "Commerce",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\commerce_promo_bar\PromoBarStorage",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\commerce_promo_bar\PromoBarListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\entity\EntityAccessControlHandler",
 *     "event" = "Drupal\commerce_promo_bar\Event\PromoBarEvent",
 *     "form" = {
 *       "default" = "Drupal\commerce_promo_bar\Form\PromoBarForm",
 *       "add" = "Drupal\commerce_promo_bar\Form\PromoBarForm",
 *       "enable" = "Drupal\commerce_promo_bar\Form\PromoBarEnableForm",
 *       "disable" = "Drupal\commerce_promo_bar\Form\PromoBarDisableForm",
 *       "edit" = "Drupal\commerce_promo_bar\Form\PromoBarForm",
 *       "duplicate" = "Drupal\commerce_promo_bar\Form\PromoBarForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\commerce_promo_bar\PromoBarRouteProvider",
 *       "delete-multiple" = "Drupal\entity\Routing\DeleteMultipleRouteProvider",
 *     },
 *   },
 *   base_table = "commerce_promo_bar",
 *   data_table = "commerce_promo_bar_field_data",
 *   translatable = TRUE,
 *   translation = {
 *     "content_translation" = {
 *       "access_callback" = "content_translation_translate_access"
 *     },
 *   },
 *   admin_permission = "administer commerce promo bar",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "status" = "status",
 *   },
 *   links = {
 *     "collection" = "/admin/commerce/promo-bars",
 *     "add-form" = "/promo-bar/add",
 *     "edit-form" = "/promo-bar/{commerce_promo_bar}/edit",
 *     "enable-form" = "/promo-bar/{commerce_promo_bar}/enable",
 *     "disable-form" = "/promo-bar/{commerce_promo_bar}/disable",
 *     "duplicate-form" = "/promo-bar/{commerce_promo_bar}/duplicate",
 *     "delete-form" = "/promo-bar/{commerce_promo_bar}/delete",
 *     "delete-multiple-form" = "/admin/commerce/promo-bars/delete",
 *     "reorder" = "/admin/commerce/promo-bars/reorder",
 *     "drupal:content-translation-overview" = "/promo-bar/{commerce_promo_bar}/translations",
 *     "drupal:content-translation-add" = "/promo-bar/{commerce_promo_bar}/translations/add/{source}/{target}",
 *     "drupal:content-translation-edit" = "/promo-bar/{commerce_promo_bar}/translations/edit/{language}",
 *     "drupal:content-translation-delete" = "/promo-bar/{commerce_promo_bar}/translations/delete/{language}",
 *   },
 *   field_ui_base_route = "entity.commerce_promo_bar.settings",
 * )
 */
class PromoBar extends CommerceContentEntityBase implements PromoBarInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel == 'canonical') {
      $rel = 'edit-form';
    }

    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('label', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPages() {
    return $this->get('pages')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('body')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->set('body', $description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStores() {
    return $this->getTranslatedReferencedEntities('stores');
  }

  /**
   * {@inheritdoc}
   */
  public function setStores(array $stores) {
    $this->set('stores', $stores);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStoreIds() {
    $store_ids = [];
    foreach ($this->get('stores') as $field_item) {
      $store_ids[] = $field_item->target_id;
    }
    return $store_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function setStoreIds(array $store_ids) {
    $this->set('stores', $store_ids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // Explicitly set the owner ID to 0 if the translation owner is anonymous
      // (This will ensure we don't store a broken reference in case the user
      // no longer exists).
      if ($translation->getOwner()->isAnonymous()) {
        $translation->setOwnerId(0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate($store_timezone = 'UTC') {
    return new DrupalDateTime($this->get('start_date')->value, $store_timezone);
  }

  /**
   * {@inheritdoc}
   */
  public function setStartDate(DrupalDateTime $start_date) {
    $this->get('start_date')->value = $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate($store_timezone = 'UTC') {
    if (!$this->get('end_date')->isEmpty()) {
      return new DrupalDateTime($this->get('end_date')->value, $store_timezone);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setEndDate(DrupalDateTime $end_date = NULL) {
    $this->get('end_date')->value = NULL;
    if ($end_date) {
      $this->get('end_date')->value = $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCountdownDate($store_timezone = 'UTC') {
    if (!$this->get('countdown_date')->isEmpty()) {
      return new DrupalDateTime($this->get('countdown_date')->value, $store_timezone);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setCountdownDate(DrupalDateTime $end_date = NULL) {
    $this->get('countdown_date')->value = NULL;
    if ($end_date) {
      $this->get('countdown_date')->value = $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled) {
    $this->set('status', (bool) $enabled);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return (int) $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPromotion() {
    return $this->getTranslatedReferencedEntity('promotion_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getPromotionId() {
    return $this->get('promotion_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerRoles() {
    $roles = [];
    foreach ($this->get('customer_roles') as $field_item) {
      $roles[] = $field_item->target_id;
    }
    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomerRoles(array $rids) {
    $this->set('customer_roles', $rids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ]);

    $fields['body'] = BaseFieldDefinition::create('text_with_summary')
      ->setLabel(t('Body'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('The main message for the promo bar. Allowed usage of tokens.'))
      ->setSettings([
        'display_description' => TRUE,
        'display_summary' => FALSE,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea_with_summary',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'hidden',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['background_color'] = BaseFieldDefinition::create('color_field_type')
      ->setLabel(t('Background color'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(['color' => '#d11515', 'opacity' => '1'])
      ->setDescription(t('The background color.'))
      ->setSettings([
        'display_description' => TRUE,
        'format' => '#HEXHEX',
      ])
      ->setDisplayOptions('form', [
        'type' => 'color_field_widget_html5',
        'weight' => -4,
      ])
      ->setDisplayOptions('view', [
        'type' => 'color_field_formatter_css',
        'label' => 'hidden',
        'weight' => 10,
        'settings' => [
          'selector' => 'article.promo-bar-wrapper-[commerce_promo_bar:id]',
          'property' => 'background-color',
          'important' => 1,
          'opacity' => TRUE,
          'advanced' => FALSE,
          'css' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['text_color'] = BaseFieldDefinition::create('color_field_type')
      ->setLabel(t('Text color'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(['color' => '#ffffff', 'opacity' => '1'])
      ->setDescription(t('The text color.'))
      ->setSettings([
        'display_description' => TRUE,
        'format' => '#HEXHEX',
      ])
      ->setDisplayOptions('form', [
        'type' => 'color_field_widget_html5',
        'weight' => -3,
      ])
      ->setDisplayOptions('view', [
        'type' => 'color_field_formatter_css',
        'label' => 'hidden',
        'weight' => 10,
        'settings' => [
          'selector' => 'article.promo-bar-wrapper-[commerce_promo_bar:id]',
          'property' => 'color',
          'important' => 1,
          'opacity' => TRUE,
          'advanced' => FALSE,
          'css' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['stores'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Stores'))
      ->setDescription(t('Limit promo bar availability to selected stores.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'commerce_store')
      ->setSetting('handler', 'default')
      ->setSetting('optional_label', t('Restrict to specific stores'))
      ->setDisplayOptions('form', [
        'type' => 'commerce_entity_select',
        'weight' => 0,
      ]);

    $fields['start_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Start date'))
      ->setDescription(t('The date the promo bar becomes valid.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDefaultValueCallback('Drupal\commerce_promo_bar\Entity\PromoBar::getDefaultStartDate')
      ->setDisplayOptions('form', [
        'type' => 'commerce_store_datetime',
        'weight' => 5,
      ]);

    $fields['end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('End date'))
      ->setDescription(t('The date after which the promo bar is invalid.'))
      ->setRequired(FALSE)
      ->setSetting('datetime_type', 'datetime')
      ->setSetting('datetime_optional_label', t('Provide an end date'))
      ->setDisplayOptions('form', [
        'type' => 'commerce_store_datetime',
        'weight' => 6,
      ]);

    $fields['countdown_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Countdown date'))
      ->setDescription(t('The date until countdown should be displayed'))
      ->setRequired(FALSE)
      ->setSettings([
        'display_description' => TRUE,
      ])
      ->setSetting('datetime_type', 'datetime')
      ->setSetting('datetime_optional_label', t('Provide an countdown date'))
      ->setDisplayOptions('form', [
        'type' => 'commerce_store_datetime',
        'weight' => 6,
      ]);

    $fields['promotion_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Promotion'))
      ->setDescription(t('Related promotion. Tokens can be used to insert coupons or other information from promotion in the promo bar body.'))
      ->setCardinality(1)
      ->setSetting('target_type', 'commerce_promotion')
      ->setSetting('handler', 'default')
      ->setSetting('optional_label', t('Related promotions'))
      ->setSetting('display_description', TRUE)
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'weight' => 15,
        'settings' => [
          'allow_existing' => TRUE,
          'override_labels' => TRUE,
          'label_singular' => t('promotion'),
          'label_plural' => t('promotions'),
          'removed_reference' => 'keep',
        ],
      ]);

    $fields['customer_roles'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Customer roles'))
      ->setDescription(t('The customer roles for which the promo bar is valid.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'user_role')
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 20,
      ]);

    $fields['pages'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Pages'))
      ->setDescription(t("Restrict display of promo bar per pages by using paths. Enter one path per line. The \'*\' character is a wildcard. Example paths are /blog for the blog page and /blog/* for every personal blog. <front> is the front page."))
      ->setTranslatable(FALSE)
      ->setRequired(FALSE)
      ->setDefaultValue('')
      ->setSettings(['display_description' => TRUE])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 25,
        'settings' => [
          'rows' => 5,
        ],
      ]);

    $fields['visibility'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Visibility'))
      ->setDescription(t('Applicable only if pages field are not empty'))
      ->setDefaultValue(0)
      ->setRequired(TRUE)
      ->setSettings([
        'on_label' => t('Show for the listed pages'),
        'off_label' => t('Hide for the listed pages'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 26,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user');

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setTranslatable(FALSE)
      ->setDescription(t('The weight of this promo bar in relation to others.'))
      ->setDefaultValue(0);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => -1,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the commerce promo bar was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the commerce promo bar was last edited.'));

    return $fields;
  }

  /**
   * Default value callback for 'start_date' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return string
   *   The default value (date string).
   */
  public static function getDefaultStartDate() {
    $timestamp = \Drupal::time()->getRequestTime();
    return date(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
  }

  /**
   * Helper callback for uasort() to sort promo_bars by weight and label.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $a
   *   The first promo_bar to sort.
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $b
   *   The second promo_bar to sort.
   *
   * @return int
   *   The comparison result for uasort().
   */
  public static function sort(PromoBarInterface $a, PromoBarInterface $b) {
    $a_weight = $a->getWeight();
    $b_weight = $b->getWeight();
    if ($a_weight == $b_weight) {
      $a_label = $a->label();
      $b_label = $b->label();
      return strnatcasecmp($a_label, $b_label);
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }

  /**
   * Determine promo bar visibility per specific page.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar
   *   The promo bar.
   *
   * @return bool
   *   True or false.
   */
  public static function evaluateVisibility(PromoBarInterface $promo_bar) {
    if (empty($promo_bar->getPages())) {
      return TRUE;
    }

    // Convert path to lowercase. This allows comparison of the same path
    // with different case. Ex: /Page, /page, /PAGE.
    $pages = mb_strtolower($promo_bar->getPages());

    // True means show, false is hide.
    $visibility = (bool) $promo_bar->get('visibility')->value;

    $request = \Drupal::requestStack()->getCurrentRequest();
    $current_path = \Drupal::service('path.current');
    $alias_manager = \Drupal::service('path_alias.manager');
    $path_matcher = \Drupal::service('path.matcher');

    // Compare the lowercase path alias (if any) and internal path.
    $path = $current_path->getPath($request);
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');
    $path_alias = mb_strtolower($alias_manager->getAliasByPath($path));

    $matches = $path_matcher->matchPath($path_alias, $pages) || (($path != $path_alias) && $path_matcher->matchPath($path, $pages));
    return $matches ? $visibility : !$visibility;
  }

}
