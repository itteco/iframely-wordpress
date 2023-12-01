import { getBlockId, getEmbedIframe, getBlockIframe, getBlockWindow } from '../utils';
import { dispatch } from '@wordpress/data';

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

export function optionsChanged(id, formContainer, query) {
  const blockId = getBlockId();
  const blockIframe = getBlockIframe(blockId);
  const embedIframe = getEmbedIframe(blockId);
  const data = jQuery(blockIframe)?.data();
  let src = data?.context;

  if (!(blockIframe && src && data?.data)) {
    return;
  }

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
  //loadIframelyEmbedJs(getBlockWindow(blockId));

  embedIframe.src = src;

  dispatch('core/block-editor').updateBlockAttributes(blockId, { iquery: query });
}
