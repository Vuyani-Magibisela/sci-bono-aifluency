/*
 * AI Fluency Course v2 — Animation helpers (confetti, XP burst, transitions).
 * All animations run in CSS; JS just triggers them by adding/removing classes and mounting DOM.
 */

(function () {
  'use strict';

  var CONFETTI_COLORS = ['#F4B740', '#2fd4dd', '#4f9bff', '#ff7a8a', '#7ee0a0', '#c08bff'];

  /**
   * Fire a confetti burst — creates ~60 falling pieces, cleans them up after the fall.
   */
  function confetti(count) {
    count = count || 60;
    for (var i = 0; i < count; i++) {
      var piece = document.createElement('div');
      piece.className = 'v2-confetti-piece';
      var left = Math.random() * 100;
      var duration = 1.6 + Math.random() * 2.4;
      var delay = Math.random() * 0.4;
      var color = CONFETTI_COLORS[Math.floor(Math.random() * CONFETTI_COLORS.length)];
      piece.style.left = left + 'vw';
      piece.style.background = color;
      piece.style.animationDuration = duration + 's';
      piece.style.animationDelay = delay + 's';
      piece.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
      piece.addEventListener('animationend', function () {
        if (this.parentNode) this.parentNode.removeChild(this);
      });
      document.body.appendChild(piece);
    }
  }

  /**
   * Play a "+N XP" burst floating up from the XP pill.
   * @param {number} amount
   * @param {HTMLElement} anchor  the .v2-xp-wrap element to attach the burst to
   */
  function xpBurst(amount, anchor) {
    if (!anchor || !amount) return;
    var burst = document.createElement('div');
    burst.className = 'v2-xp-burst';
    burst.textContent = '+' + amount + ' XP';
    anchor.appendChild(burst);
    setTimeout(function () {
      if (burst.parentNode) burst.parentNode.removeChild(burst);
    }, 1300);
  }

  /**
   * Trigger a shake animation on an element.
   */
  function shake(el) {
    if (!el) return;
    el.classList.remove('v2-anim-shake');
    // Force reflow so the animation restarts.
    void el.offsetWidth;
    el.classList.add('v2-anim-shake');
  }

  /**
   * Apply a fresh "pop" animation to a step container when it becomes visible.
   */
  function stepEnter(el) {
    if (!el) return;
    el.classList.remove('v2-anim-pop');
    void el.offsetWidth;
    el.classList.add('v2-anim-pop');
  }

  window.V2Anim = {
    confetti: confetti,
    xpBurst: xpBurst,
    shake: shake,
    stepEnter: stepEnter
  };
})();
