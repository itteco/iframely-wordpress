import { getBlockId, getEmbedIframe, getBlockIframe, getBlockWindow, getEditorDocument } from '../utils';
import { dispatch } from '@wordpress/data';

function maybeFixIframe(window) {
  setTimeout(() => {
    if (window.document.getElementById('iframely-styles')) {
      return;
    }
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
  }, 50);
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

  // wipe out the old query completely
  if (data.data.query && data.data.query.length > 0) {
    data.data.query.forEach(function (key) {
      if (src.indexOf(key) > -1) {
        src = src.replace(new RegExp('&?' + key.replace('-', '\\-') + '=[^\\?\\&]+'), ''); // delete old key
      }
    });
  }

  // and add an entire new query instead
  Object.keys(query).forEach(function (key) {
    src += (src.indexOf('?') > -1 ? '&' : '?') + key + '=' + query[key];
  });

  maybeFixIframe(getBlockWindow(getBlockId()));

  embedIframe.src = src;

  dispatch('core/block-editor').updateBlockAttributes(blockId, { iquery: query });
}
