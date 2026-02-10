(function () {
  'use strict';

  function showToast(message, isError) {
    var el = document.getElementById('toast');
    if (!el) return;
    el.hidden = false;
    el.textContent = message;
    el.className = isError ? 'toast error' : 'toast';
    window.setTimeout(function () {
      el.hidden = true;
    }, 2500);
  }

  var main = document.querySelector('main');
  var csrf = main ? main.getAttribute('data-csrf-token') : '';

  document.querySelectorAll('form.api-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var confirmMsg = form.getAttribute('data-confirm');
      if (confirmMsg && !window.confirm(confirmMsg)) {
        return;
      }

      var data = new FormData(form);
      if (!data.get('csrf_token')) {
        data.append('csrf_token', csrf || '');
      }

      fetch(form.getAttribute('action') || '/api.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
      }).then(function (resp) {
        return resp.json().then(function (json) {
          if (!resp.ok || json.error) {
            throw new Error((json && json.error) || 'Request failed');
          }
          return json;
        });
      }).then(function () {
        showToast(form.getAttribute('data-success') || 'Saved.', false);
        window.setTimeout(function () {
          window.location.reload();
        }, 400);
      }).catch(function (err) {
        showToast(err.message || 'Unable to save.', true);
      });
    });
  });
})();
