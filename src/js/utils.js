import { select } from '@wordpress/data';

function getSelectedBlockID() {
  return select('core/block-editor').getBlockSelectionStart();
}

function addIframelyString(url, query) {
  let newUrl = url.replace(/(?:&amp;|\?|&)?iframely=(.+)$/, '');
  if (Object.keys(query).length !== 0) {
    newUrl += (/\?/.test(newUrl) ? '&' : '?') + 'iframely=' + encodeURIComponent(window.btoa(JSON.stringify(query)));
  }
  return newUrl;
}

export { getSelectedBlockID, addIframelyString };
