/**
 * Shared helpers for the end-to-end tests.
 *
 * The tests site answers Iframely API calls itself (see
 * tests/e2e/mu-plugins/iframely-api-stub.php), and the two scripts the editor
 * loads from if-cdn.com are replaced here, so no test depends on the network.
 */

// Accepted by the API stub; any key without this prefix is rejected.
const VALID_KEY = 'e2evalidkey0123456789';
const OTHER_VALID_KEY = 'e2evalidkey9876543210';
const REJECTED_KEY = 'wrongkey0123456789';

const EMBED_JS = `
window.iframely = window.iframely || {};
(function (iframely) {
  var handlers = {};
  iframely.on = function (name, callback) {
    (handlers[name] = handlers[name] || []).push(callback);
  };
  iframely.trigger = function (name) {
    var args = Array.prototype.slice.call(arguments, 1);
    (handlers[name] || []).forEach(function (callback) {
      callback.apply(null, args);
    });
  };
})(window.iframely);
`;

// The real options.js builds a form from the options an embed reports and fires
// "options-changed" when the user edits it. One checkbox is enough for the tests.
const OPTIONS_JS = `
window.iframely = window.iframely || {};
window.iframely.buildOptionsForm = function (id, container, options) {
  if (!container) {
    return;
  }
  container.innerHTML = '<label class="iframely-e2e-option"><input type="checkbox" name="theme"> Dark theme (stub)</label>';
  var input = container.querySelector('input');
  input.checked = !!(options && options.theme && options.theme.value);
  input.addEventListener('change', function () {
    window.iframely.trigger('options-changed', id, container, input.checked ? { theme: 'dark' } : {});
  });
};
`;

/**
 * Serves local stand-ins for https://if-cdn.com/embed.js and options.js.
 *
 * @param {import('@playwright/test').BrowserContext} context
 */
async function stubIframelyCdn(context) {
  await context.route('https://if-cdn.com/**', (route) => {
    const body = route.request().url().includes('options.js') ? OPTIONS_JS : EMBED_JS;
    return route.fulfill({ status: 200, contentType: 'application/javascript', body });
  });
}

/**
 * Puts the plugin back into its freshly activated state, optionally with an API key.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils
 * @param {string}                                                      apiKey
 */
async function resetPlugin(requestUtils, apiKey = '') {
  return requestUtils.rest({ method: 'POST', path: '/iframely-e2e/v1/reset', data: { api_key: apiKey } });
}

/**
 * Query parameters of every call the site has made to the (stubbed) Iframely API.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils
 */
async function apiRequests(requestUtils) {
  return requestUtils.rest({ path: '/iframely-e2e/v1/requests' });
}

module.exports = { VALID_KEY, OTHER_VALID_KEY, REJECTED_KEY, stubIframelyCdn, resetPlugin, apiRequests };
