import { select, dispatch } from '@wordpress/data';
import { getSelectedBlockID } from './utils';
import { updateForm } from './options';

const iEvent = new RegExp('setIframelyEmbedOptions');

function findIframeByContentWindow(iframes, contentWindow) {
  let foundIframe;
  for (let i = 0; i < iframes.length && !foundIframe; i++) {
    let iframe = iframes[i];
    if (iframe.contentWindow === contentWindow) {
      foundIframe = iframe;
    }
  }
  return foundIframe;
}

function loadIframelyEmbedJs($w) {
  if ($w && !$w.iframely) {
    // already loaded
    var ifs = $w.document.createElement('script');
    ifs.type = 'text/javascript';
    ifs.async = true;
    ifs.src = ('https:' === document.location.protocol ? 'https:' : 'http:') + '//if-cdn.com/embed.js';
    var s = $w.document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(ifs, s);
  }
}

if (iframely) {
  // Failsafe in case of iframely name space not accessible.
  // E.g. no internet connection
  iframely.on('options-changed', function (id, formContainer, query) {
    const selector = '#block-' + getSelectedBlockID();
    const iframe = document.querySelector(selector + ' iframe').contentDocument.querySelector('iframe');
    const preview = jQuery(selector).find('iframe');

    if (preview && preview.data() && preview.data().data && preview.data().context) {
      const data = preview.data();

      let src = data.context;

      // wipe out old query completely
      if (data.data.query && data.data.query.length > 0) {
        data.data.query.forEach(function (key) {
          if (src.indexOf(key) > -1) {
            src = src.replace(new RegExp('&?' + key.replace('-', '\\-') + '=[^\\?\\&]+'), ''); // delete old key
          }
        });
      }
      // and add entire new query instead
      Object.keys(query).forEach(function (key) {
        src += (src.indexOf('?') > -1 ? '&' : '?') + key + '=' + query[key];
      });

      // load embed.js if it was missing to catch chaning sizes
      loadIframelyEmbedJs(document.querySelector(selector + ' iframe').contentWindow);

      iframe.src = src;

      dispatch('core/block-editor').updateBlockAttributes(getSelectedBlockID(), { iquery: query });
    }
  });
}

window.addEventListener(
  'message',
  function (e) {
    // Listen for messages from iframe proxy script
    if (iEvent.test(e.data)) {
      let frames = document.getElementsByTagName('iframe'),
        iframe = findIframeByContentWindow(frames, e.source);

      let data = JSON.parse(e.data);
      jQuery(iframe).data(data); // Store current state of options form in the iframe

      // update only if the form is open. If not, it will be built on render
      const block = select('core/block-editor').getBlock(getSelectedBlockID());

      if (block && /^core-?\/?embed/i.test(block.name)) {
        updateForm();
      }
    }
  },
  false
);
