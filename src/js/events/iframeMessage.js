import { select } from '@wordpress/data';
import { updateForm } from '../components/IframelyOptions';
import { getEditorDocument, getBlockId, isEmbedBlock, isObject } from '../utils';

const methodName = 'setIframelyEmbedOptions';

function getCurrentIframe(source) {
  const editor = getEditorDocument();
  const iframes = editor.querySelectorAll('iframe');
  let iframe = null;
  iframes.forEach((item) => {
    if (item.contentWindow === source) {
      iframe = item;
    }
  });
  return iframe;
}

function parse(message) {
  if (isObject(message)) {
    return message;
  }
  // Other scripts post messages to the editor as well, in formats of their own
  try {
    return JSON.parse(message);
  } catch {
    return null;
  }
}

export function iframeMessage(e) {
  // The frame an embed is shown in belongs to the editor: its messages come from the editor's own origin
  if (e?.origin !== window.location.origin) {
    return;
  }
  const data = parse(e.data);
  if (!isObject(data) || data.method !== methodName || !isObject(data.data)) {
    return;
  }
  const iframe = getCurrentIframe(e.source);
  if (!iframe) {
    return;
  }

  // Store the current state of options form in the iframe
  jQuery(iframe).data(data);

  // update only if the form is open. If not, it will be built on render
  const block = select('core/block-editor').getBlock(getBlockId());

  if (block && isEmbedBlock(block.name)) {
    updateForm();
  }
}
