import { getBlockId, getEmbedIframe, getBlockIframe, getBlockWindow } from '../utils';
import { dispatch } from '@wordpress/data';

function loadIframelyEmbedJs(window) {
  console.log('embed.js loaded?');
  if (window.iframely) {
    console.log('yep');
    return;
  }
  console.log('nope, loading');

  let script = window.document.createElement('script');
  script.type = 'text/javascript';
  script.async = true;
  script.src = ('https:' === document.location.protocol ? 'https:' : 'http:') + '//if-cdn.com/embed.js';
  window.document.head.appendChild(script);

  /*
  let style = window.document.createElement('style');
  style.textContent = `.iframely-responsive {
      top: 0;
      left: 0;
      width: 100%;
      height: 0;
      position: relative;
      padding-bottom: 56.25%;
      box-sizing: border-box;
  }
  .iframely-responsive > * {
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      position: absolute;
      border: 0;
      box-sizing: border-box;
  }`;
  window.document.head.appendChild(style);
  */
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

  // load embed.js if it was missing to catch changing sizes
  loadIframelyEmbedJs(getBlockWindow(blockId));

  embedIframe.src = src;

  dispatch('core/block-editor').updateBlockAttributes(blockId, { iquery: query });
}
