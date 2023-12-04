import { getBlockId, getEmbedIframe, getBlockIframe, getBlockWindow, getEditorDocument } from '../utils';
import { dispatch } from '@wordpress/data';

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

  embedIframe.src = src;

  dispatch('core/block-editor').updateBlockAttributes(blockId, { iquery: query });
}
