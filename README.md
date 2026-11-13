CONTENTS OF THIS FILE
---------------------
* Introduction
* Requirements
* Installation
* Configuration
* Usage
* Customization
* Maintainers

INTRODUCTION
------------

Commerce Promo Bar allows stores to display notifications and promotional information
via the bar on top pages or any positions.


REQUIREMENTS
------------
This module requires Drupal Commerce 2, and it's submodule promotion and
Color field module.


INSTALLATION
------------
Install the Commerce Promo Bar module as you would normally install
any Drupal contrib module.
Visit https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
--------------
    1. Navigate to Administration > Extend and enable the Commerce Promo Bar
       module.
    2. Navigate to Home > Administration > Structure > Block layout and
       place Commerce Promo Bar block onto region where you want to show promo bars.
       (if you want to show only one promo bar always - uncheck Stack promo bars)
    3. Navigate to Home > Administration > Commerce > Configuration > Promo Bar.
       if you want to add fields, or change display & form view modes.


USAGE
--------------
    1. Navigate to Home > Administration > Commerce > Promo Bars
       where you can create new promo bar.
    2. Promo bar should be visible on region where you placed block from
       2nd step from Configuration.

![Drag Racing](https://www.drupal.org/files/project-images/ui_promobar_1.png)

FEATURES
--------------
* adjust background color
* adjust text color
* use tokens inside promo bar body
* WYSIWYG editor to style promo bar message
* set start and end date
* set countdown date and display countdown timer
* restrict visibility per store
* restrict visibility per customer role
* restrict visibility per country (show or hide for the listed countries)
* restrict visibility per any path

CUSTOMIZATION
--------------
* If you want to use Twig, use `commerce-promo-bar.html.twig` for any changes.
* If you want to use UI for changes, it is possible trough WYSIWYG editor and/or
view mode / field formatters to achieve some customizations.
* Promo Bar entity is field-able, so you can add any field to the entity.
To use it within Promo Bar body, insert them with tokens

API
--------------
The rules the block uses are available to API resources and other front
ends:

* `PromoBarStorageInterface::loadVisible($store, $account, $path,
  $cacheability)` returns the promo bars visible to a customer on a page:
  available for the store, customer roles and country, filtered by
  `FilterPromoBarsEvent`, then by page visibility. Pass the front-end page
  path, or NULL for the current request path. The cacheability collects the
  cache tags, contexts and the max-age until the next scheduled change.
* `commerce_promo_bar.visibility` checks the page visibility of one promo bar
  for a path.
* `commerce_promo_bar.body_processor` returns the body with tokens replaced
  and the text format applied, for output that does not render the entity.
* `FilterPromoBarsEvent` subscribers get the path and the customer through
  `getPath()` and `getAccount()`, and add their cache contexts to
  `getCacheability()`.

JSON:API
--------------
The Commerce Promo Bar JSON:API submodule (`commerce_promo_bar_jsonapi`,
needs JSON:API Resources) serves the promo bars visible on a page:

    GET /jsonapi/promo-bars?pagePath=/blog

* One `promo-bar` resource per promo bar, in the order of `loadVisible()`,
  with the attributes `title`, `message` (tokens replaced, text format
  applied), `background_color`, `text_color`, `countdown_date` (ISO 8601, or
  null) and `dismissible`.
* `pagePath` is the front-end page, without the language prefix. Without it,
  the front page (`/`).
* The store, customer, country and language are the ones Drupal resolves for
  the request, as for the block. The response carries their cache contexts
  and expires at the next scheduled change.
* Anyone may request it: the promo bar rules decide what a customer sees.

To add attributes, decorate the `commerce_promo_bar_jsonapi.promo_bars`
service with a subclass of
`Drupal\commerce_promo_bar_jsonapi\Resource\PromoBars` that overrides
`getAttributeNames()` and `getAttributes()`. Add the cacheability of what
they read to the `$cacheability` they receive.

NOTES:

_The country is the one Commerce resolves for the current request
(`commerce.current_country`). Without a country resolver of your own, that
is the current store's country, then the site's default country. To use the
customer's location, add a service tagged `commerce.country_resolver`, for
example one that reads a CDN country header or GeoIP._

_Javascript which provides functionality for countdown timer expects
existence of class `promo-bar-countdown-1` where number 1 from example
represents promo bar entity ID._

_Color fields which are used for customization of color for background and text
expects existence of class `article.promo-bar-wrapper-1`
where number 1 from example represents promo bar entity ID._

MAINTAINERS
-----------

The 1.0.x branch was created by:

* Valentino Medimorec (valic) - https://www.drupal.org/u/valic

**Project page:** https://www.drupal.org/project/commerce_promo_bar
