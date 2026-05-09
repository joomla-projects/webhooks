/**
 * @copyright  (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

(() => {
  'use strict';

  const options = Joomla.getOptions('com_webhooks-verify');

  if (!options) {
    return;
  }

  const btn = document.getElementById('btn-verify-endpoint');

  if (!btn) {
    return;
  }

  const container = document.getElementById('webhook-verify-container');
  const originalHtml = btn.innerHTML;

  btn.addEventListener('click', () => {
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ${options.textVerifying}`;

    Joomla.request({
      url: options.url,
      method: 'POST',
      data: `id=${options.webhookId}`,
      onSuccess: (response) => {
        let result;

        try {
          result = JSON.parse(response);
        } catch (e) {
          Joomla.renderMessages({ error: [options.textErrorGeneric] });
          btn.disabled = false;
          btn.innerHTML = originalHtml;

          return;
        }

        if (result.success) {
          Joomla.renderMessages({ message: [result.message] });
          container.innerHTML = `<span class="badge bg-success">${options.textVerified}</span>`;
        } else {
          Joomla.renderMessages({ error: [result.message] });
          btn.disabled = false;
          btn.innerHTML = originalHtml;
        }
      },
      onError: () => {
        Joomla.renderMessages({ error: [options.textErrorGeneric] });
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      },
    });
  });
})();
