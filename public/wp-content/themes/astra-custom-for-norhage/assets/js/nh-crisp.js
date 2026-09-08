/**
 * Keep Crisp usable on phones. Hiding #crisp-chatbox with CSS makes Android
 * open a blank white screen — the launcher stays tappable, the panel is invisible.
 */
(function () {
  'use strict';

  function queue(fn) {
    window.$crisp = window.$crisp || [];
    try {
      fn(window.$crisp);
    } catch (e) {
      /* Crisp not present */
    }
  }

  function setOpen(on) {
    if (!document.body) {
      return;
    }
    document.body.classList.toggle('nh-crisp-open', !!on);
  }

  function hideLauncher() {
    if (document.body && document.body.classList.contains('nh-crisp-open')) {
      return;
    }
    queue(function ($c) {
      $c.push(['do', 'chat:hide']);
    });
  }

  function showLauncher() {
    queue(function ($c) {
      $c.push(['do', 'chat:show']);
    });
  }

  function openChat() {
    setOpen(true);
    queue(function ($c) {
      $c.push(['do', 'chat:show']);
    });
    window.setTimeout(function () {
      queue(function ($c) {
        $c.push(['do', 'chat:open']);
      });
    }, 180);
  }

  function bind() {
    queue(function ($c) {
      $c.push(['on', 'chat:opened', function () {
        setOpen(true);
      }]);
      $c.push(['on', 'chat:closed', function () {
        setOpen(false);
      }]);
    });
  }

  window.nhCrisp = {
    hideLauncher: hideLauncher,
    showLauncher: showLauncher,
    open: openChat
  };

  bind();
})();
