/**
 * @file
 * Javascript to attach countdown on the promo bar.
 */

(($, Drupal, drupalSettings) => {

  /**
   * Attaches the commercePromoBar behavior.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.commercePromoBar = {

    attach(context) {
      // Check if we have variable or that counter is active.
      if (!drupalSettings.commercePromoBar.countdown || !(drupalSettings.commercePromoBar.countdown instanceof Object)) {
        return;
      }

      let countdown = drupalSettings.commercePromoBar.countdown;

      for (const promo_bar_id in countdown) {
        let countdown_selector = $('.promo-bar-countdown-' + promo_bar_id);
        let deadline = new Date(countdown[promo_bar_id]);

        const start_timer = setInterval(function () {
          const remaining_time = deadline.getTime() - Date.now();
          let remaining_days = Math.floor(remaining_time / (1000 * 60 * 60 * 24));
          let remaining_hours = Math.floor((remaining_time % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
          let remaining_minutes = Math.floor((remaining_time % (1000 * 60 * 60)) / (1000 * 60))
          let remaining_seconds = Math.floor((remaining_time % (1000 * 60)) / 1000)

          const day = Drupal.formatPlural(remaining_days, '1 day', '@count days');
          const hour = Drupal.formatPlural(remaining_hours, '1 hour', '@count hours');
          const min = Drupal.formatPlural(remaining_minutes, '1 minute', '@count minutes');
          const sec = Drupal.formatPlural(remaining_seconds, '1 second', '@count seconds');
          countdown_selector.html(`${day} / ${hour} / ${min} / ${sec}`);
          if (remaining_time < 0) {
            clearInterval(start_timer);
            // We should not reach here usually, unless someone have
            // the page opened without refreshing when timer expires.
            countdown_selector.html(Drupal.t('Expired'));
          }
        }, 1000);
      }
    },
  };

})(jQuery, Drupal, drupalSettings);
