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
// "options-changed" with the chosen values when the user edits it. This stand-in
// understands the three kinds of option the real embeds report:
//   { value: '', values: { small: 'Make it a small card' } }   a checkbox that stands for one value
//   { label: 'Closed captions', value: false }                  a plain checkbox
//   { label: 'Start from', value: '', placeholder: '...' }      a text field
const OPTIONS_JS = `
window.iframely = window.iframely || {};
window.iframely.buildOptionsForm = function (id, container, options) {
  if (!container) {
    return;
  }
  container.innerHTML = '';
  // Lets a test wait until the form was built again after an embed reloaded.
  container.dataset.builds = String((Number(container.dataset.builds) || 0) + 1);
  var inputs = [];
  Object.keys(options || {}).forEach(function (name) {
    if (name === 'query') {
      return;
    }
    var option = options[name];
    var label = document.createElement('label');
    var input = document.createElement('input');
    var text;
    label.className = 'iframely-e2e-option';
    input.name = name;
    if (option.values) {
      var only = Object.keys(option.values)[0];
      input.type = 'checkbox';
      input.checked = option.value === only;
      input.dataset.value = only;
      text = option.values[only];
    } else if (typeof option.value === 'boolean') {
      input.type = 'checkbox';
      input.checked = option.value;
      text = option.label;
    } else {
      input.type = 'text';
      input.value = option.value;
      input.placeholder = option.placeholder || '';
      text = option.label;
    }
    if (input.type === 'checkbox') {
      label.appendChild(input);
      label.appendChild(document.createTextNode(' ' + text));
    } else {
      label.appendChild(document.createTextNode(text + ' '));
      label.appendChild(input);
    }
    container.appendChild(label);
    inputs.push(input);
    input.addEventListener('change', function () {
      var query = {};
      inputs.forEach(function (item) {
        if (item.type === 'text') {
          if (item.value) {
            query[item.name] = item.value;
          }
        } else if (item.checked) {
          query[item.name] = item.dataset.value || true;
        }
      });
      window.iframely.trigger('options-changed', id, container, query);
    });
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

/**
 * The WordPress version of the tests site, for the few tests whose outcome depends on it.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils
 */
async function wordpressVersion(requestUtils) {
  return (await requestUtils.rest({ path: '/iframely-e2e/v1/env' })).wordpress;
}

/**
 * Makes the next calls to the (stubbed) Iframely API fail, as if the API timed out.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils
 * @param {number}                                                      count
 */
async function failNextApiRequests(requestUtils, count = 1) {
  return requestUtils.rest({ method: 'POST', path: '/iframely-e2e/v1/fail-next', data: { count } });
}

/**
 * Makes the next call to the (stubbed) Iframely API take the given time.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils
 * @param {number}                                                      ms
 */
async function delayNextApiRequest(requestUtils, ms) {
  return requestUtils.rest({ method: 'POST', path: '/iframely-e2e/v1/delay-next', data: { ms } });
}

/**
 * Inserts an embed block and returns locators for it and for its preview.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').Editor} editor
 * @param {string}                                                url
 */
async function insertEmbed(editor, url) {
  await editor.insertBlock({ name: 'core/embed', attributes: { url } });
  const block = editor.canvas.locator('[data-type="core/embed"]');
  const preview = block.locator('iframe').contentFrame();
  return { block, preview };
}

/**
 * Changes an option in the "Iframely options" panel and waits for what follows:
 * the plugin reloads the preview with the option and stores it in the block, the
 * editor then fetches a new preview for the changed URL, and each time the embed
 * reports its options again and the form is rebuilt from that report.
 *
 * @param {import('@playwright/test').Page}         page
 * @param {import('@playwright/test').FrameLocator} preview The preview of the embed block.
 * @param {RegExp}                                  src     What the address of the embed looks like in the new preview.
 * @param {() => Promise<void>}                     change
 */
async function changeOption(page, preview, src, change) {
  const { expect } = require('@wordpress/e2e-test-utils-playwright');
  const panel = page.locator('#iframely-options');
  const builds = async () => Number(await panel.getAttribute('data-builds'));

  await change();
  await expect(preview.locator('iframe[title="Iframely stub embed"]')).toHaveAttribute('src', src);

  // The form is only safe to use again once it is no longer being rebuilt.
  await expect(async () => {
    const before = await builds();
    await new Promise((resolve) => setTimeout(resolve, 400));
    expect(await builds()).toBe(before);
  }).toPass();
}

/**
 * The value the plugin appends to an embed URL to carry its options.
 *
 * @param {Object} options
 */
function iframelyParam(options) {
  return `iframely=${encodeURIComponent(btoa(JSON.stringify(options)))}`;
}

module.exports = {
  VALID_KEY,
  OTHER_VALID_KEY,
  REJECTED_KEY,
  stubIframelyCdn,
  resetPlugin,
  apiRequests,
  wordpressVersion,
  failNextApiRequests,
  delayNextApiRequest,
  insertEmbed,
  changeOption,
  iframelyParam,
};
