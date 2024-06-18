/**
 * @file
 * Javascript to attach countdown on the promo bar.
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  /**
   * Returns list of dismissible id's.
   *
   * @returns {[]}
   */
  const getDismissedAlerts = () => {
    let saved =  window.localStorage.getItem('commerce_promo_bar');
    if (saved !== null) {
      return  JSON.parse(saved);
    }

    return [];
  };

  /**
   * Set promo bar to be dismissed.
   *
   * @param id
   */
  const setDismissedAlert = (id) => {
    let alerts = getDismissedAlerts();

    if (alerts.includes(id) === false) {
      alerts.push(id);
      window.localStorage.setItem(
        'commerce_promo_bar',
        JSON.stringify(alerts)
      );
    }
  };

  const dismissAlert= (id) => {
    setDismissedAlert(id);
    removeAlert(id);
  };

  const removeAlert = (id) => {
    let markup = document.querySelector('#promo-bar-' + id);
    if (markup) {
      markup.remove();
    }
  };


  /**
   * Attaches the commercePromoBar behavior.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.commercePromoBar = {
    attach: function (context) {
      // Check if we have variable or that counter is active.
      if (!drupalSettings.commercePromoBar) {
        return;
      }


      // Fetch already dismissed items.
      const dismissedAlerts = getDismissedAlerts();
      const promobars = drupalSettings.commercePromoBar;

      for (const id in promobars) {

        let countdown = promobars[id].countdown;

        // Set countdown if applicable.
        if (countdown !== undefined) {
          let countdown_selector = document.querySelector('.promo-bar-countdown-' + id);
          let deadline = new Date(countdown);

          const start_timer = setInterval(function () {
            const remaining_time = deadline.getTime() - Date.now();
            let remaining_days = Math.floor(remaining_time / (1000 * 60 * 60 * 24));
            let remaining_hours = Math.floor((remaining_time % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            let remaining_minutes = Math.floor((remaining_time % (1000 * 60 * 60)) / (1000 * 60));
            let remaining_seconds = Math.floor((remaining_time % (1000 * 60)) / 1000);

            const day = Drupal.formatPlural(remaining_days, '1 day', '@count days');
            const hour = Drupal.formatPlural(remaining_hours, '1 hour', '@count hours');
            const min = Drupal.formatPlural(remaining_minutes, '1 minute', '@count minutes');
            const sec = Drupal.formatPlural(remaining_seconds, '1 second', '@count seconds');
            countdown_selector.textContent = (`${day} / ${hour} / ${min} / ${sec}`);
            if (remaining_time < 0) {
              clearInterval(start_timer);
              // We should not reach here usually, unless someone have
              // the page opened without refreshing when timer expires.
              countdown_selector.textContent = Drupal.t('Expired');
            }
          }, 1000);
        }

        // Determine if promo bar is dismissible.
        let dismissible = promobars[id].dismissible;

        if (dismissible) {
          // If is dismissed, remove it from DOM.
          if (dismissedAlerts.includes(id)) {
            removeAlert(id);
          }
          // Attach event to markup to trigger removal.
          else {
            const dismiss_action = document.querySelector('#promo-bar-' + id + ' .dismiss');
            dismiss_action.addEventListener('click', (event) => {
              dismissAlert(id);
            });
          }
        }
      }
    },
  };

})(Drupal, drupalSettings, once);
