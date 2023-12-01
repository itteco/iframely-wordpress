import { select } from '@wordpress/data';
import { updateForm } from '../components/IframelyOptions';
import { getEditorDocument, getBlockId, isObject } from '../utils';

const methodName = 'setIframelyEmbedOptions';

function getCurrentIframe(source) {
  let editor = getEditorDocument();
  let iframes = editor.querySelectorAll('iframe');
  let iframe = null;
  iframes.forEach((item) => {
    if (item.contentWindow === source) {
      iframe = item;
    }
  });
  return iframe;
}

export function iframeMessage(e) {
  const data = isObject(e?.data) ? e.data : JSON.parse(e?.data) || {};
  if (data?.method !== methodName) {
    return;
  }
  let iframe = getCurrentIframe(e.source);
  if (!iframe) {
    return;
  }
  // console.log('messageReceived');

  // Store the current state of options form in the iframe
  jQuery(iframe).data(data);

  // update only if the form is open. If not, it will be built on render
  const block = select('core/block-editor').getBlock(getBlockId());

  if (block && /^core-?\/?embed/i.test(block.name)) {
    updateForm();
  }
}
