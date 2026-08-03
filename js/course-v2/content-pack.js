/*
 * AI Fluency Course v2 — Content pack.
 *
 * Verbatim gamified scripts for lessons that have a designed script in the redesign zip.
 * Source: Documentation/UI/AI Fluency Course Redesign_v2.zip — AI Foundations.dc.html + AI History Journey.dc.html.
 *
 * Chapter-runner prefers these entries over the auto-gamify path. Add more entries as
 * hand-written scripts arrive; missing lessons fall back to auto-summarize from DB content.
 *
 * Keyed by lesson_id in the platform DB:
 *   1 → AI History Journey (Chapter 1.00) — 'journey' type
 *   2 → Chapter 1.01 · What is AI?
 *   3 → Chapter 1.02 · Knowledge vs Intelligence
 *   4 → Chapter 1.03 · Data Everywhere
 */

(function () {
  'use strict';

  var PACK = {

    // ───────────────────────────────────────────────────────────
    // Lesson 1 · Chapter 1.00 · AI History Journey  (linear era flow)
    // ───────────────────────────────────────────────────────────
    1: {
      type: 'journey',
      accent: '#F4B740',
      badge: { slug: 'ai_time_traveller', name: 'AI Time Traveller' },
      intro: {
        kicker: 'Chapter 1.00',
        title: 'AI HISTORY',
        tagline: 'A 70-year adventure, one quick mission',
        body: 'Travel through time with your AI buddy. Unlock memories, beat the challenges, and earn the AI Time Traveller badge along the way.',
        cta: 'Start the Journey'
      },
      eras: [
        { year: '1950',    tag: 'The Big Question',   title: 'Can Machines Think?',      line: 'Alan Turing asked one curious question that started it all: "Can machines think?"', narrate: 'It all begins with one big question…' },
        { year: '1956',    tag: 'First AI Program',   title: 'The Logic Theorist',       line: 'The very first AI program could solve problems like a person. The name "Artificial Intelligence" was born!', narrate: 'Meet the first ever thinking program.', color: '#F4B740' },
        { year: '1960s–70s', tag: 'Spotting Patterns', title: 'Learning to See',          line: 'Computers started recognising patterns — the seed of how AI "sees" the world today.', narrate: 'Now AI learns to spot patterns.', color: '#4f9bff' },
        { year: '1990s',   tag: 'The Internet Boom',  title: 'A Flood of Data',          line: 'The internet created a huge ocean of data for AI to learn from.', narrate: 'The internet changes everything.', color: '#2fd4dd' },
        { year: '2000s',   tag: 'Machine Learning',   title: 'Learning by Example',      line: 'Instead of strict rules, AI began learning from examples — just like you do!', narrate: 'AI starts learning by example.', color: '#7ee0a0' },
        { year: '2010s',   tag: 'AI Everywhere',      title: 'AI in Your Pocket',        line: 'Voice assistants like Siri and Alexa put AI into everyday life.', narrate: 'Suddenly, AI is everywhere!', color: '#ff9b54' },
        { year: '2021+',   tag: 'Generative AI',      title: 'AI That Creates',          line: 'Today AI can create text, images and audio. A brand-new era has begun!', narrate: 'And now… AI can create!', color: '#c08bff' }
      ],
      quizzes: [
        { q: 'Who asked the famous question, "Can machines think?"',
          options: ['Alan Turing', 'Albert Einstein', 'Steve Jobs', 'Charles Darwin'],
          correct: 0,
          hint: 'he’s known as the father of computer science.' },
        { q: 'What did the internet’s flood of data give AI?',
          options: ['More examples to learn from', 'Less electricity', 'Slower computers', 'Nothing at all'],
          correct: 0,
          hint: 'think about how YOU learn — from lots of examples!' }
      ],
      minigame: 'match',
      minigameData: {
        prompt: 'Match each AI model to its superpower.',
        title: 'Meet the Models',
        pairs: [
          { id: 'gpt',     label: 'GPT',     power: 'Writes human-like text' },
          { id: 'dalle',   label: 'DALL·E',  power: 'Creates images from words' },
          { id: 'whisper', label: 'Whisper', power: 'Understands the spoken word' }
        ]
      },
      // Interleave eras + quizzes + match, matching the prototype's STEPS order.
      stepOrder: [
        'intro',
        'era:0', 'era:1',
        'quiz:0',
        'era:2', 'era:3', 'era:4',
        'quiz:1',
        'era:5', 'era:6',
        'minigame',
        'reward'
      ]
    },

    // ───────────────────────────────────────────────────────────
    // Lesson 2 · Chapter 1.01 · What is AI?
    // ───────────────────────────────────────────────────────────
    2: {
      type: 'chapter',
      accent: '#4f9bff',
      badge: { slug: 'ai_mythbuster', name: 'AI Mythbuster' },
      intro: {
        kicker: 'Chapter 1.01',
        title: 'WHAT IS AI?',
        tagline: 'Bust the myths, meet the real thing',
        body: 'You’ve been using AI for years without even noticing. Let’s discover what it really is — no robots-with-feelings required.',
        cta: 'Start Chapter'
      },
      concepts: [
        { icon: 'fa-bolt',        tag: 'Not New, Not Magic', title: 'AI Is Already Around You',    body: 'AI isn’t new and it isn’t science fiction. It’s a tool that’s been quietly evolving for decades — and it’s for everyone, not just techies.' },
        { icon: 'fa-microchip',   tag: 'The Definition',     title: 'So, What Is AI?',             body: 'At its core, AI is software that learns from data to get better and better over time.',
          definition: { term: 'Artificial Intelligence', meaning: 'The ability of a computer system to learn from past data and errors, so it can make increasingly accurate predictions and decisions.' } },
        { icon: 'fa-cogs',        tag: 'Under the Hood',     title: 'What Makes It "Intelligent"?', body: 'When Siri answers you, it isn’t thinking like a human — it’s following instructions people wrote, very fast.',
          definition: { term: 'Algorithm', meaning: 'A sequence of step-by-step instructions that tells a computer exactly how to solve a problem.' } },
        { icon: 'fa-chess',       tag: 'Learning by Doing',  title: 'Getting Better Over Time',    body: 'A chess AI follows its algorithms, then improves by studying thousands of past games. Learning from examples like this is called machine learning.' },
        { icon: 'fa-mobile-alt',  tag: 'Fact vs Fiction',    title: 'AI in Everyday Life',         body: 'Instagram and streaming apps use AI to pick what you see next. But movie AIs with feelings are pure fiction — real AI has no consciousness or emotions. It is a tool, built and controlled by people.' }
      ],
      quizzes: [
        { q: 'Which sentence best describes AI?',
          options: ['Software that learns from data to make better predictions', 'A robot that has real human feelings', 'Any fast computer', 'A type of smartphone'],
          correct: 0,
          hint: 'think about learning from past data.' },
        { q: 'A chess program that improves by studying past games is using…',
          options: ['Machine learning', 'Pure magic', 'Human emotion', 'A rulebook it never changes'],
          correct: 0,
          hint: 'it learns from examples, just like you.' }
      ],
      minigame: 'turing',
      stepOrder: [
        'intro',
        'concept:0', 'concept:1',
        'quiz:0',
        'concept:2', 'concept:3',
        'quiz:1',
        'concept:4',
        'minigame',
        'reward'
      ]
    },

    // ───────────────────────────────────────────────────────────
    // Lesson 3 · Chapter 1.02 · Knowledge vs Intelligence
    // ───────────────────────────────────────────────────────────
    3: {
      type: 'chapter',
      accent: '#2fd4dd',
      badge: { slug: 'deep_thinker', name: 'Deep Thinker' },
      intro: {
        kicker: 'Chapter 1.02',
        title: 'KNOWLEDGE\nvs INTELLIGENCE',
        tagline: 'Does the machine really understand?',
        body: 'In 1980 a philosopher set a clever trap to test whether machines truly understand — or only pretend to. Let’s walk right into it.',
        cta: 'Start Chapter'
      },
      concepts: [
        { icon: 'fa-question',      tag: 'The Big Question',     title: 'Can a Machine Understand?', body: 'In 1980, philosopher John Searle asked whether a machine can ever truly understand — or only look like it does. His answer was a thought experiment called the Chinese Room.' },
        { icon: 'fa-door-open',     tag: 'Thought Experiment',   title: 'Inside the Chinese Room',   body: 'A person who speaks no Chinese sits in a room with a rulebook. Chinese notes come in; they follow the rules to slide the right symbols back out. From outside it looks fluent — but they understand nothing.' },
        { icon: 'fa-balance-scale', tag: 'The Core Difference',  title: 'Two Very Different Things', body: 'The room has every right answer but zero understanding. That gap sits at the very heart of AI.',
          definition: { term: 'Knowledge vs Intelligence', meaning: 'Knowledge is information that is stored and looked up. Intelligence is the ability to apply and adapt that knowledge to brand-new situations.' } },
        { icon: 'fa-wave-square',   tag: 'How AI Really Works',  title: 'Patterns, Not Meaning',     body: 'When you talk to Siri, it turns your words into patterns it can match — it recognises, it doesn’t comprehend. Very clever pattern-matching, no real understanding.' },
        { icon: 'fa-notes-medical', tag: 'Intelligence in Action', title: 'Applying Knowledge Smartly', body: 'At a clinic, an AI can ask the intake questions, read your vitals and prepare a preliminary diagnosis before you even see the doctor — applying stored knowledge intelligently to save everyone time.' }
      ],
      quizzes: [
        { q: 'In the Chinese Room, the person sends back correct answers but…',
          options: ['never actually understands the language', 'secretly speaks fluent Chinese', 'invents a brand-new language', 'refuses to answer'],
          correct: 0,
          hint: 'they are only following the rulebook.' },
        { q: 'Intelligence is best described as…',
          options: ['applying and adapting knowledge to new situations', 'memorising lots of facts', 'storing information safely', 'reading from a rulebook'],
          correct: 0,
          hint: 'it’s about using knowledge, not just having it.' }
      ],
      minigame: 'sort',
      stepOrder: [
        'intro',
        'concept:0', 'concept:1',
        'quiz:0',
        'concept:2', 'concept:3', 'concept:4',
        'quiz:1',
        'minigame',
        'reward'
      ]
    },

    // ───────────────────────────────────────────────────────────
    // Lesson 4 · Chapter 1.03 · Data Everywhere
    // ───────────────────────────────────────────────────────────
    4: {
      type: 'chapter',
      accent: '#ff9b54',
      badge: { slug: 'data_wrangler', name: 'Data Wrangler' },
      intro: {
        kicker: 'Chapter 1.03',
        title: 'DATA\nEVERYWHERE',
        tagline: 'The fuel that powers every AI',
        body: 'Every tap, call and click you make creates data — the raw fuel AI runs on. Let’s see just how much of it there really is.',
        cta: 'Start Chapter'
      },
      concepts: [
        { icon: 'fa-gas-pump',    tag: 'The Fuel',           title: 'Data Powers AI',            body: 'Data is the raw material AI uses to spot patterns and make predictions. You generate far more of it than you realise — just from your phone.' },
        { icon: 'fa-chart-line',  tag: 'The Explosion',      title: 'From a Trickle to a Flood', body: 'The internet boom of the 1990s opened the floodgates. Since then, the amount of data created each year has grown explosively.' },
        { icon: 'fa-th',          tag: 'Where It Comes From', title: 'You Are a Data Factory',   body: 'Gaming, phone calls, watching TV, typing, even paying at a gas pump — almost every everyday action creates data an AI can learn from.' },
        { icon: 'fa-filter',      tag: 'Cleaning It Up',     title: 'Refining Raw Data',         body: 'Raw data is messy. It has to be cleaned, because the quality of the data directly controls how accurate the AI becomes.',
          definition: { term: 'Quality Over Quantity', meaning: 'Diverse, high-quality data beats a huge pile of messy data. Good refinement removes errors, gaps and bias before training begins.' } },
        { icon: 'fa-music',       tag: 'Data to Value',      title: 'Turning Data Into Magic',   body: 'Your music app studies what you skip, repeat and save, then recommends your next favourite song. That’s data trained into a model that improves the more you use it.' }
      ],
      quizzes: [
        { q: 'Why does AI need data?',
          options: ['To learn patterns and make predictions', 'To use up more electricity', 'To slow computers down', 'It doesn’t need any data'],
          correct: 0,
          hint: 'how do YOU learn? From lots of examples.' },
        { q: 'What matters most for a strong AI model?',
          options: ['High-quality, diverse data', 'The biggest pile of any data', 'Expensive computers only', 'Bright, colourful screens'],
          correct: 0,
          hint: 'quality over quantity.' }
      ],
      minigame: 'slider',
      stepOrder: [
        'intro',
        'concept:0', 'concept:1',
        'quiz:0',
        'concept:2', 'concept:3', 'concept:4',
        'quiz:1',
        'minigame',
        'reward'
      ]
    }

  };

  function forLesson(lessonId) {
    return PACK[lessonId] || null;
  }

  function has(lessonId) {
    return !!PACK[lessonId];
  }

  window.V2ContentPack = {
    forLesson: forLesson,
    has: has,
    all: PACK
  };
})();
