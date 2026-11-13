<?php

namespace Drupal\commerce_promo_bar_jsonapi\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Drupal\commerce_promo_bar\PromoBarBodyProcessorInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * GET /jsonapi/promo-bars: lists the promo bars visible on a page.
 *
 * The same promo bars the promo bar block shows, from
 * PromoBarStorageInterface::loadVisible(), for the page in the "pagePath"
 * query parameter, the current store, the current customer and the country
 * Commerce resolves. The message is ready to output: tokens replaced and the
 * text format applied.
 *
 * A module adds attributes by decorating the
 * "commerce_promo_bar_jsonapi.promo_bars" service with a subclass that
 * overrides getAttributeNames() and getAttributes().
 */
class PromoBars extends ResourceBase {

  /**
   * The resource type of a promo bar.
   */
  const RESOURCE_TYPE = 'promo-bar';

  /**
   * Constructs a new PromoBars object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\commerce_store\CurrentStoreInterface $currentStore
   *   The current store.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\commerce_promo_bar\PromoBarBodyProcessorInterface $bodyProcessor
   *   The promo bar body processor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected CurrentStoreInterface $currentStore,
    protected AccountInterface $currentUser,
    protected PromoBarBodyProcessorInterface $bodyProcessor,
  ) {}

  /**
   * Lists the promo bars visible on a page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with the page path in "pagePath", such as "/blog".
   *   Without it, the front page.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The promo bars, in the order of loadVisible().
   */
  public function process(Request $request): ResourceResponse {
    $path = (string) $request->query->get('pagePath', '') ?: '/';
    $cacheability = (new CacheableMetadata())->addCacheContexts([
      'url.query_args:pagePath',
      'languages:language_content',
    ]);

    $data = [];
    $store = $this->currentStore->getStore();
    if ($store) {
      /** @var \Drupal\commerce_promo_bar\PromoBarStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('commerce_promo_bar');
      $resource_type = $this->getPromoBarResourceType();
      foreach ($storage->loadVisible($store, $this->currentUser, $path, $cacheability) as $promo_bar) {
        /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar */
        $promo_bar = $this->entityRepository->getTranslationFromContext($promo_bar);
        $cacheability->addCacheableDependency($promo_bar);
        $attributes = $this->getAttributes($promo_bar, $store, $cacheability);
        $data[] = new ResourceObject($cacheability, $resource_type, $promo_bar->uuid(), NULL, $attributes, new LinkCollection([]), $promo_bar->language());
      }
    }

    $response = $this->createJsonapiResponse(new ResourceObjectData($data), $request);
    if ($response instanceof CacheableResponseInterface) {
      $response->addCacheableDependency($cacheability);
    }
    return $response;
  }

  /**
   * Gets the attribute names of a promo bar resource.
   *
   * @return string[]
   *   The attribute names.
   */
  protected function getAttributeNames(): array {
    return [
      'title',
      'message',
      'background_color',
      'text_color',
      'countdown_date',
      'dismissible',
    ];
  }

  /**
   * Gets the attributes of a promo bar.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar
   *   The promo bar, in the content language.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The current store.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cacheability of the response.
   *
   * @return array
   *   The attributes, keyed by the names getAttributeNames() returns.
   */
  protected function getAttributes(PromoBarInterface $promo_bar, StoreInterface $store, CacheableMetadata $cacheability): array {
    $metadata = new BubbleableMetadata();
    $message = $this->bodyProcessor->process($promo_bar, $metadata);
    $cacheability->addCacheableDependency($metadata);
    $countdown_date = $promo_bar->getCountdownDate($store->getTimezone());

    return [
      'title' => $promo_bar->label(),
      'message' => $message,
      'background_color' => $promo_bar->get('background_color')->color ?: NULL,
      'text_color' => $promo_bar->get('text_color')->color ?: NULL,
      'countdown_date' => $countdown_date?->format('c'),
      'dismissible' => $promo_bar->isDismissible(),
    ];
  }

  /**
   * Gets the promo bar resource type.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type.
   */
  protected function getPromoBarResourceType(): ResourceType {
    $fields = [];
    foreach ($this->getAttributeNames() as $name) {
      $fields[$name] = new ResourceTypeAttribute($name);
    }
    $resource_type = new ResourceType(static::RESOURCE_TYPE, static::RESOURCE_TYPE, \stdClass::class, FALSE, FALSE, FALSE, FALSE, $fields, static::RESOURCE_TYPE);
    $resource_type->setRelatableResourceTypes([]);
    return $resource_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return [$this->getPromoBarResourceType()];
  }

}
