/**
 * @file
 * Javascript to attach countdown on the promo bar.
 */
(function (Drupal, drupalSettings, once) {
  /**
   * Returns list of dismissible id's.
   *
   * @returns {[]}
   */
  const getDismissedPromoBars = () => {
    const saved = window.localStorage.getItem('commerce_promo_bar');
    if (saved !== null) {
      return JSON.parse(saved);
    }

    return [];
  };

  /**
   * Set promo bar to be dismissed.
   *
   * @param id
   */
  const setDismissedPromoBar = (id) => {
    const promoBars = getDismissedPromoBars();

    if (promoBars.includes(id) === false) {
      promoBars.push(id);
      window.localStorage.setItem(
        'commerce_promo_bar',
        JSON.stringify(promoBars),
      );
    }
  };

  /**
   * Remove promo bar markup.
   *
   * @param id
   */
  const removePromoBar = (id) => {
    const markup = document.querySelector(`#promo-bar-${id}`);
    if (markup) {
      markup.remove();
    }
  };

  /**
   * Dismiss promo bar by id.
   *
   * @param id
   */
  const dismissPromoBar = (id) => {
    setDismissedPromoBar(id);
    removePromoBar(id);
  };

  /**
   * Attaches the commercePromoBar behavior.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.commercePromoBar = {
    attach: function attach(context) {
      // Check if we have variable or that counter is active.
      if (!drupalSettings.commercePromoBar) {
        return;
      }

      // Fetch already dismissed items.
      const dismissedPromoBars = getDismissedPromoBars();
      const promoBars = drupalSettings.commercePromoBar;

      Object.entries(promoBars).forEach((item) => {
        const [id, promoBar] = item;

        // Determine if promo bar is dismissible.
        if (promoBar.hasOwnProperty('dismissible')) {
          const dismissible = promoBar.dismissible;
          if (dismissible) {
            // If is dismissed, remove it from DOM.
            if (dismissedPromoBars.includes(id)) {
              removePromoBar(id);
            }
            // Attach event to markup to trigger removal.
            else {
              const dismissAction = document.querySelector(
                `#promo-bar-${id} .dismiss`,
              );
              dismissAction.addEventListener('click', (event) => {
                dismissPromoBar(id);
              });
            }
          }
        }
        // Set countdown if applicable.
        if (promoBar.hasOwnProperty('countdown')) {
          const countdownSelector = document.querySelector(
            `.promo-bar-countdown-${id}`,
          );
          // We may remove it from DOM if is dismissible.
          if (countdownSelector !== null) {
            const countdown = promoBar.countdown;
            const deadline = new Date(countdown);
            const startTimer = setInterval(function () {
              const remainingTime = deadline.getTime() - Date.now();
              const remainingDays = Math.floor(
                remainingTime / (1000 * 60 * 60 * 24),
              );
              const remainingHours = Math.floor(
                (remainingTime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60),
              );
              const remainingMinutes = Math.floor(
                (remainingTime % (1000 * 60 * 60)) / (1000 * 60),
              );
              const remainingSeconds = Math.floor(
                (remainingTime % (1000 * 60)) / 1000,
              );

              const day = Drupal.formatPlural(
                remainingDays,
                '1 day',
                '@count days',
              );
              const hour = Drupal.formatPlural(
                remainingHours,
                '1 hour',
                '@count hours',
              );
              const min = Drupal.formatPlural(
                remainingMinutes,
                '1 minute',
                '@count minutes',
              );
              const sec = Drupal.formatPlural(
                remainingSeconds,
                '1 second',
                '@count seconds',
              );
              countdownSelector.textContent = `${day} / ${hour} / ${min} / ${sec}`;
              if (remainingTime < 0) {
                clearInterval(startTimer);
                // We should not reach here usually, unless someone have
                // the page opened without refreshing when timer expires.
                countdownSelector.textContent = Drupal.t('Expired');
              }
            }, 1000);
          }
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
