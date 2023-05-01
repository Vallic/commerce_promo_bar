<?php

namespace Drupal\commerce_promo_bar\Plugin\Block;

use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Promo bar block'.
 *
 * @Block(
 *  id = "promo_bar_block",
 *  admin_label = @Translation("Promo bar block"),
 * )
 */
class PromoBarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The promo_bar storage.
   *
   * @var \Drupal\commerce_promo_bar\PromoBarStorageInterface
   */
  protected $promoBarStorage;

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
   */
  public function __construct(array $configuration,
  $plugin_id,
  $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
  CurrentStoreInterface $current_store,
  AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentStore = $current_store;
    $this->currentUser = $current_user;
    $this->promoBarStorage = $entity_type_manager->getStorage('commerce_promo_bar');
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
      $container->get('current_user')
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

    $promo_bars = $this->promoBarStorage->loadAvailable($this->currentStore->getStore(), $this->currentUser->getRoles());
    $timezone = $this->currentStore->getStore()->getTimezone();
    $timestamp = \Drupal::time()->getRequestTime();
    $date = DrupalDateTime::createFromTimestamp($timestamp, $timezone);
    $js_settings = [];
    foreach ($promo_bars as $promo_bar) {
      // Visibility is handled here, and not in storage, because
      // we handle page visibility here only - for display purposes.
      // All general restrictions are handled in
      // \Drupal\commerce_promo_bar\PromoBarStorage::loadAvailable
      // so that we have ability to pull all promo bars regardless
      // of per page visibility.
      if (PromoBar::evaluateVisibility($promo_bar)) {
        $visible_promo_bars[] = $this->entityTypeManager->getViewBuilder('commerce_promo_bar')->view($promo_bar);
        if ($promo_bar->getCountdownDate() && $promo_bar->getCountdownDate($timezone)->getTimestamp() > $date->getTimestamp()) {
          $js_settings[$promo_bar->id()] = $promo_bar->getCountdownDate($timezone)->format('c');
        }
      }
    }

    // If on block settings is set to show only one promo bar always
    // use the latest in the list.
    if (empty($this->configuration['stack']) && $visible_promo_bars) {
      $visible_promo_bars = [
        end($visible_promo_bars),
      ];
    }

    return [
      '#theme' => 'commerce_promo_bar_block',
      '#promo_bars' => $visible_promo_bars,
      '#attached' => [
        'library' => [
          'commerce_promo_bar/countdown',
          'commerce_promo_bar/block',
        ],
        'drupalSettings' => [
          'commercePromoBar' => [
            'countdown' => $js_settings,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.path';
    $contexts[] = 'store';
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
