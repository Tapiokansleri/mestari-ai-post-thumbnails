(function () {
  'use strict';

  function parseJsonResponse(res, fallbackMessage) {
    return res.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error(fallbackMessage || maptAdmin.error);
      }
    });
  }

  document.querySelectorAll('.mapt-generate-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var postId = btn.getAttribute('data-post-id');
      var row = btn.closest('tr');
      var statusCell = row ? row.querySelector('.mapt-status') : null;

      if (!postId || btn.disabled) {
        return;
      }

      btn.disabled = true;
      btn.textContent = maptAdmin.generating;

      if (statusCell) {
        statusCell.textContent = '';
        statusCell.className = 'mapt-status mapt-status--loading';
      }

      var body = new URLSearchParams();
      body.append('action', 'mapt_generate_thumbnail');
      body.append('nonce', maptAdmin.nonce);
      body.append('post_id', postId);

      fetch(maptAdmin.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
      })
        .then(function (res) {
          return parseJsonResponse(res, maptAdmin.invalidResponse);
        })
        .then(function (json) {
          if (!json.success) {
            throw new Error((json.data && json.data.message) || maptAdmin.error);
          }

          if (statusCell) {
            statusCell.className = 'mapt-status mapt-status--success';
            if (json.data.thumbnail_url) {
              statusCell.innerHTML =
                '<span class="mapt-done-label">' +
                maptAdmin.done +
                '</span> <img src="' +
                json.data.thumbnail_url +
                '" alt="" class="mapt-preview" width="120" height="75" />';
            } else {
              statusCell.textContent = maptAdmin.done;
            }
          }

          btn.textContent = maptAdmin.done;
        })
        .catch(function (err) {
          if (statusCell) {
            statusCell.className = 'mapt-status mapt-status--error';
            statusCell.textContent = err.message || maptAdmin.error;
          }

          btn.disabled = false;
          btn.textContent = maptAdmin.generate;
        });
    });
  });

  var checkBtn = document.getElementById('mapt-check-updates');
  var updateStatus = document.getElementById('mapt-update-status');

  if (checkBtn && updateStatus) {
    checkBtn.addEventListener('click', function () {
      checkBtn.disabled = true;
      checkBtn.textContent = maptAdmin.checkingUpdates;
      updateStatus.className = 'mapt-update-status mapt-update-status--loading';
      updateStatus.textContent = maptAdmin.checkingUpdates;

      var body = new URLSearchParams();
      body.append('action', 'mapt_check_updates');
      body.append('nonce', maptAdmin.nonce);

      fetch(maptAdmin.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
      })
        .then(function (res) {
          return parseJsonResponse(res, maptAdmin.error);
        })
        .then(function (json) {
          if (!json.success) {
            throw new Error((json.data && json.data.message) || maptAdmin.error);
          }

          updateStatus.className = 'mapt-update-status mapt-update-status--success';
          var html = json.data.message;

          if (json.data.has_update && json.data.update_url) {
            html +=
              ' <a class="button button-primary" href="' +
              json.data.update_url +
              '">' +
              maptAdmin.installUpdate +
              '</a>';
            html +=
              ' <a href="' +
              json.data.updates_url +
              '">' +
              json.data.updates_url +
              '</a>';
          }

          updateStatus.innerHTML = html;
        })
        .catch(function (err) {
          updateStatus.className = 'mapt-update-status mapt-update-status--error';
          updateStatus.textContent = err.message || maptAdmin.error;
        })
        .finally(function () {
          checkBtn.disabled = false;
          checkBtn.textContent = maptAdmin.checkUpdates;
        });
    });
  }
})();
