<?php

namespace Drupal\commerce_promo_bar\Plugin\Block;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_store\CurrentStoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Promo bar block'.
 */
#[Block(
    id: "promo_bar_block",
    admin_label: new TranslatableMarkup("Promo bar block"),
)]
class PromoBarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current store.
   */
  protected CurrentStoreInterface $currentStore;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The time.
   */
  protected TimeInterface $time;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrentStoreInterface $current_store, AccountProxyInterface $current_user, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentStore = $current_store;
    $this->currentUser = $current_user;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('commerce_store.current_store'),
      $container->get('current_user'),
      $container->get('datetime.time')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'stack' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['stack'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stack promo bars'),
      '#description' => $this->t('Disabling this is going to take the promo bar with highest weight only. Leaving enabled is going to render all available promo bars for the current store.'),
      '#default_value' => $this->configuration['stack'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['stack'] = $form_state->getValue('stack');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $visible_promo_bars = [];

    /** @var \Drupal\commerce_promo_bar\PromoBarStorageInterface $promo_bar_storage */
    $promo_bar_storage = $this->entityTypeManager->getStorage('commerce_promo_bar');
    $store = $this->currentStore->getStore();
    $cacheability = new CacheableMetadata();
    $promo_bars = $promo_bar_storage->loadVisible($store, $this->currentUser, NULL, $cacheability);
    $timezone = $store->getTimezone();
    $timestamp = $this->time->getRequestTime();
    $js_settings = [];

    $view_builder = $this->entityTypeManager->getViewBuilder('commerce_promo_bar');
    foreach ($promo_bars as $promo_bar) {
      $visible_promo_bars[] = $view_builder->view($promo_bar);
      $countdown_date = $promo_bar->getCountdownDate($timezone);
      if ($countdown_date && $countdown_date->getTimestamp() > $timestamp) {
        $js_settings[$promo_bar->id()]['countdown'] = $countdown_date->format('c');
      }
      $js_settings[$promo_bar->id()]['dismissible'] = $promo_bar->isDismissible();
    }

    // If on block settings is set to show only one promo bar always
    // use the latest in the list.
    if (empty($this->configuration['stack']) && $visible_promo_bars) {
      $visible_promo_bars = [
        end($visible_promo_bars),
      ];
    }

    $build = [
      '#theme' => 'commerce_promo_bar_block',
      '#promo_bars' => $visible_promo_bars,
      '#attached' => [
        'library' => [
          'commerce_promo_bar/countdown',
          'commerce_promo_bar/block',
        ],
        'drupalSettings' => [
          'commercePromoBar' => $js_settings,
        ],
      ],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.path';
    $contexts[] = 'store';
    // Promo bars can be restricted by customer roles.
    $contexts[] = 'user.roles';
    // Promo bars can be restricted by countries.
    $contexts[] = 'country';
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    return Cache::mergeTags($cache_tags, $this->entityTypeManager->getDefinition('commerce_promo_bar')->getListCacheTags());
  }

}
