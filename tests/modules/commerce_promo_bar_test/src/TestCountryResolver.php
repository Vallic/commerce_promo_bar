<?php

namespace Drupal\commerce_promo_bar_test;

use Drupal\Core\State\StateInterface;
use Drupal\commerce\Country;
use Drupal\commerce\Resolver\CountryResolverInterface;

/**
 * Resolves the country stored in state, for tests.
 */
class TestCountryResolver implements CountryResolverInterface {

  /**
   * The state key holding the country code.
   */
  const STATE_KEY = 'commerce_promo_bar_test.country';

  /**
   * Constructs a new TestCountryResolver object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(protected StateInterface $state) {}

  /**
   * {@inheritdoc}
   */
  public function resolve() {
    $country_code = $this->state->get(self::STATE_KEY);
    return $country_code ? new Country($country_code) : NULL;
  }

}
