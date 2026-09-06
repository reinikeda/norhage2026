/**
 * Expand / collapse cart cards on the recovery admin list.
 */
(function () {
  'use strict';

  var root = document.querySelector('.nh-cr-list');
  if (!root) {
    return;
  }

  document.querySelectorAll('[data-nh-cr-expand]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('data-nh-cr-expand') === '1';
      root.querySelectorAll('details.nh-cr-card').forEach(function (el) {
        el.open = open;
      });
    });
  });
})();
