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
       (if you want to show only one promobar always - uncheck Stack promo bars)
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
* restrict visibility per any path

CUSTOMIZATION
--------------
* If you want to use Twig, use `commerce-promo-bar.html.twig` for any changes.
* If you want to use UI for changes, it is possible trough WYSIWYG editor and/or
view mode / field formatters to achieve some customizations.
* Promo Bar entity is field-able, so you can add any field to the entity.
To use it within Promo Bar body, insert them with tokens

NOTES:

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
