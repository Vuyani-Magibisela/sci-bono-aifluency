/*
 * AI Fluency Course v2 — Mini-game: Match
 *
 * Two columns of chips: left = models (GPT, DALL·E, Whisper), right = powers.
 * User drags (or tap-selects) a model chip onto its matching power chip.
 * Correct pair → both chips lock green + XP burst. Wrong → shake.
 *
 * Content comes from opts.data.pairs = [ {id, label, power} ].
 * Falls back to a built-in default (AI models from AI History Journey) if data is missing.
 */

(function () {
  'use strict';

  var DEFAULT = {
    prompt: 'Match each AI model to its superpower.',
    title: 'Meet the Models',
    pairs: [
      { id: 'gpt',     label: 'GPT',     power: 'Writes human-like text' },
      { id: 'dalle',   label: 'DALL·E',  power: 'Creates images from words' },
      { id: 'whisper', label: 'Whisper', power: 'Understands the spoken word' }
    ]
  };
  var XP_PER_MATCH = 10;

  function mount(container, opts) {
    opts = opts || {};
    var data = Object.assign({}, DEFAULT, opts.data || {});
    var pairs = data.pairs;

    // Shuffle powers into their own display order so students can't just pair top-to-top.
    var powerOrder = shuffle(pairs.map(function (p) { return p.id; }));
    var state = { matched: {}, selectedModel: null };

    container.innerHTML = ''
      + '<div class="v2-minigame-shell">'
      + '  <div class="v2-quiz-eyebrow">Mini-Game · Match</div>'
      + '  <h2 class="v2-minigame-shell__title">' + esc(data.title) + '</h2>'
      + '  <p class="v2-minigame-shell__hint">' + esc(data.prompt) + '</p>'
      + '  <div class="v2-match-grid">'
      + '    <div class="v2-match-col" data-col="models"><div class="v2-match-col__label">Models</div></div>'
      + '    <div class="v2-match-col" data-col="powers"><div class="v2-match-col__label">Powers</div></div>'
      + '  </div>'
      + '  <div class="v2-step-footer" data-footer></div>'
      + '</div>';

    var modelsCol = container.querySelector('[data-col="models"]');
    var powersCol = container.querySelector('[data-col="powers"]');
    var footer = container.querySelector('[data-footer]');

    // Model chips (left)
    pairs.forEach(function (p) {
      var chip = document.createElement('div');
      chip.className = 'v2-match-chip v2-match-chip--model';
      chip.setAttribute('data-model', p.id);
      chip.setAttribute('draggable', 'true');
      chip.innerHTML = '<div class="v2-match-chip__label">' + esc(p.label) + '</div>';
      modelsCol.appendChild(chip);

      chip.addEventListener('dragstart', function (e) {
        if (chip.classList.contains('is-locked')) { e.preventDefault(); return; }
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', p.id);
      });

      chip.addEventListener('click', function () {
        if (chip.classList.contains('is-locked')) return;
        modelsCol.querySelectorAll('.v2-match-chip--model').forEach(function (c) { c.classList.remove('is-selected'); });
        state.selectedModel = p.id;
        chip.classList.add('is-selected');
      });
    });

    // Power chips (right, shuffled)
    powerOrder.forEach(function (id) {
      var pair = pairs.find(function (x) { return x.id === id; });
      if (!pair) return;
      var chip = document.createElement('div');
      chip.className = 'v2-match-chip v2-match-chip--power';
      chip.setAttribute('data-power', pair.id);
      chip.innerHTML = '<div class="v2-match-chip__label">' + esc(pair.power) + '</div>';
      powersCol.appendChild(chip);

      chip.addEventListener('dragover', function (e) { e.preventDefault(); chip.classList.add('is-over'); });
      chip.addEventListener('dragleave', function () { chip.classList.remove('is-over'); });
      chip.addEventListener('drop', function (e) {
        e.preventDefault();
        chip.classList.remove('is-over');
        var modelId = e.dataTransfer.getData('text/plain');
        tryMatch(modelId, pair.id, chip);
      });
      chip.addEventListener('click', function () {
        if (chip.classList.contains('is-locked')) return;
        if (!state.selectedModel) return;
        tryMatch(state.selectedModel, pair.id, chip);
        state.selectedModel = null;
      });
    });

    function tryMatch(modelId, powerId, powerChip) {
      if (state.matched[modelId]) return;
      var modelChip = modelsCol.querySelector('[data-model="' + cssEscape(modelId) + '"]');
      if (!modelChip) return;

      if (modelId === powerId) {
        state.matched[modelId] = true;
        modelChip.classList.remove('is-selected');
        modelChip.classList.add('is-locked');
        modelChip.setAttribute('draggable', 'false');
        powerChip.classList.add('is-locked');
        if (opts.xpBurst) opts.xpBurst(XP_PER_MATCH);
        checkComplete();
      } else {
        powerChip.classList.remove('is-shaking');
        void powerChip.offsetWidth;
        powerChip.classList.add('is-shaking');
        if (opts.mascot) opts.mascot.say('Not quite — try another pairing.', { autoHideMs: 3200 });
      }
    }

    function checkComplete() {
      if (pairs.every(function (p) { return !!state.matched[p.id]; })) {
        var btn = document.createElement('button');
        btn.className = 'v2-btn v2-btn--primary';
        btn.textContent = 'Continue';
        btn.addEventListener('click', function () {
          if (opts.onComplete) opts.onComplete({ xpAwarded: pairs.length * XP_PER_MATCH });
        });
        footer.innerHTML = '';
        footer.appendChild(btn);
        if (opts.mascot) opts.mascot.say('Nailed it — you know your models!', { autoHideMs: 4500 });
      }
    }
  }

  function shuffle(a) {
    var out = a.slice();
    for (var i = out.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = out[i]; out[i] = out[j]; out[j] = t;
    }
    return out;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function cssEscape(s) { return String(s).replace(/[^\w-]/g, '\\$&'); }

  window.V2Minigame_Match = { mount: mount };
})();
