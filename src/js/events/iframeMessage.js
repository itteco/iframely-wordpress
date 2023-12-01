import { select } from '@wordpress/data';
import { getBlockId } from '../utils';
import { updateForm } from '../components/IframelyOptions';

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

export function iframeMessage(e) {
  // Listen for messages from iframe proxy script
  if (iEvent.test(e.data)) {
    let frames = document.getElementsByTagName('iframe'),
      iframe = findIframeByContentWindow(frames, e.source);

    let data = JSON.parse(e.data);

    console.log('messageReceived', data?.data);

    jQuery(iframe).data(data); // Store current state of options form in the iframe

    // update only if the form is open. If not, it will be built on render
    const block = select('core/block-editor').getBlock(getBlockId());

    if (block && /^core-?\/?embed/i.test(block.name)) {
      updateForm();
    }
  }
}
