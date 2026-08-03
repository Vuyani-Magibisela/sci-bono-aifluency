/*
 * AI Fluency Course v2 — Mini-game: Sort It ("Knowledge or Intelligence?")
 * Chapter 1.02 / lesson_id = 3.
 *
 * Drag-and-drop implementation using native HTML5 DnD. Falls back to click-to-assign
 * on touch devices where dragstart isn't reliable.
 */

(function () {
  'use strict';

  var DATA = {
    prompt: 'Drag each example into the box it belongs in.',
    xpPerCorrect: 10,
    bins: [
      { id: 'knowledge', label: 'Knowledge' },
      { id: 'intel', label: 'Intelligence' }
    ],
    items: [
      { id: 'k1', label: 'Reciting a memorised fact', bin: 'knowledge' },
      { id: 'i1', label: 'Solving a brand-new problem', bin: 'intel' },
      { id: 'k2', label: 'Looking up a phone number', bin: 'knowledge' },
      { id: 'i2', label: 'Adapting a recipe to what’s in the fridge', bin: 'intel' }
    ]
  };

  function mount(container, opts) {
    opts = opts || {};
    var state = { assigned: {}, totalCorrect: 0, selectedItem: null };

    var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    container.innerHTML = ''
      + '<div class="v2-minigame-shell">'
      + '  <div class="v2-quiz-eyebrow">Mini-Game · Sort It</div>'
      + '  <h2 class="v2-minigame-shell__title">Knowledge or Intelligence?</h2>'
      + '  <p class="v2-minigame-shell__hint">' + escapeHtml(DATA.prompt) + (isTouch ? ' <em>(tap an item, then tap a box)</em>' : '') + '</p>'
      + '  <div class="v2-sort-pool" data-pool></div>'
      + '  <div class="v2-sort-bins">'
      +      DATA.bins.map(function (b) {
              return '<div class="v2-sort-bin" data-bin="' + escapeAttr(b.id) + '">'
                + '  <div class="v2-sort-bin__label">' + escapeHtml(b.label) + '</div>'
                + '  <div class="v2-sort-bin__items" data-bin-items></div>'
                + '</div>';
            }).join('')
      + '  </div>'
      + '  <div class="v2-step-footer" data-footer></div>'
      + '</div>';

    var pool = container.querySelector('[data-pool]');
    var bins = container.querySelectorAll('.v2-sort-bin');
    var footer = container.querySelector('[data-footer]');

    DATA.items.forEach(function (it) {
      var chip = document.createElement('div');
      chip.className = 'v2-sort-chip';
      chip.setAttribute('draggable', 'true');
      chip.setAttribute('data-item', it.id);
      chip.textContent = it.label;
      pool.appendChild(chip);

      chip.addEventListener('dragstart', function (e) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', it.id);
      });

      chip.addEventListener('click', function () {
        if (chip.classList.contains('is-locked')) return;
        // Tap-to-select on touch
        pool.querySelectorAll('.v2-sort-chip').forEach(function (c) { c.style.outline = ''; });
        state.selectedItem = it.id;
        chip.style.outline = '2px solid var(--v2-accent-cyan)';
      });
    });

    bins.forEach(function (bin) {
      var binId = bin.getAttribute('data-bin');
      bin.addEventListener('dragover', function (e) { e.preventDefault(); bin.classList.add('is-over'); });
      bin.addEventListener('dragleave', function () { bin.classList.remove('is-over'); });
      bin.addEventListener('drop', function (e) {
        e.preventDefault();
        bin.classList.remove('is-over');
        var itemId = e.dataTransfer.getData('text/plain');
        tryAssign(itemId, binId, bin);
      });
      bin.addEventListener('click', function () {
        if (!state.selectedItem) return;
        tryAssign(state.selectedItem, binId, bin);
        state.selectedItem = null;
      });
    });

    function tryAssign(itemId, binId, binEl) {
      var item = DATA.items.find(function (x) { return x.id === itemId; });
      if (!item) return;
      if (state.assigned[itemId]) return; // already correctly placed

      if (item.bin === binId) {
        state.assigned[itemId] = binId;
        state.totalCorrect += 1;

        // Move chip visually into the bin (locked, coloured)
        var chip = pool.querySelector('[data-item="' + itemId + '"]');
        if (chip) {
          chip.classList.add('is-locked');
          chip.setAttribute('draggable', 'false');
          chip.style.outline = '';
          var target = binEl.querySelector('[data-bin-items]');
          target.appendChild(chip);
        }

        if (opts.xpBurst) opts.xpBurst(DATA.xpPerCorrect);
        checkComplete();
      } else {
        binEl.classList.remove('is-shaking');
        void binEl.offsetWidth;
        binEl.classList.add('is-shaking');
        if (opts.mascot) opts.mascot.say('Not quite — think about which one requires adapting to new info.', { autoHideMs: 3500 });
      }
    }

    function checkComplete() {
      var allDone = DATA.items.every(function (x) { return !!state.assigned[x.id]; });
      if (!allDone) return;
      footer.innerHTML = '';
      var next = document.createElement('button');
      next.className = 'v2-btn v2-btn--primary';
      next.textContent = 'Continue';
      next.addEventListener('click', function () {
        if (opts.onComplete) opts.onComplete({ xpAwarded: state.totalCorrect * DATA.xpPerCorrect });
      });
      footer.appendChild(next);
      if (opts.mascot) opts.mascot.say('Nice sorting. Knowledge is stored; intelligence adapts.', { autoHideMs: 5000 });
    }
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function escapeAttr(s) { return escapeHtml(s); }

  window.V2Minigame_Sort = { mount: mount };
})();
