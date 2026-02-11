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
      }).then(function (json) {
        var payload = (json && json.data) ? json.data : {};
        var warningText = '';
        if (payload.warnings && payload.warnings.length) {
          warningText = ' Warnings: ' + payload.warnings.map(function (w) { return w.message || w.policy_key || 'Policy warning'; }).join(' | ');
        }
        showToast((form.getAttribute('data-success') || payload.message || 'Saved.') + warningText, false);
        window.setTimeout(function () {
          window.location.reload();
        }, 400);
      }).catch(function (err) {
        showToast(err.message || 'Unable to save.', true);
      });
    });
  });
})();