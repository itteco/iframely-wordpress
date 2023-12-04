import { select } from '@wordpress/data';

function getEditorDocument() {
  let iframe = document.querySelector('[name=editor-canvas]');
  return iframe ? iframe.contentWindow.document : document;
}

function getBlockId() {
  return select('core/block-editor').getBlockSelectionStart();
}

function getBlockIframe(id) {
  let document = getEditorDocument();
  return document.querySelector(`#block-${id} iframe`);
}

function getBlockWindow(id) {
  let block = getBlockIframe(id);
  return block.contentWindow;
}

function getEmbedIframe(id) {
  let block = getBlockIframe(id);
  let document = block.contentWindow.document;
  return document.querySelector('iframe');
}

function isObject(val) {
  if (val === null) {
    return false;
  }
  return typeof val === 'function' || typeof val === 'object';
}

function addIframelyString(url, query) {
  let newUrl = url.replace(/(?:&amp;|\?|&)?iframely=(.+)$/, '');
  if (Object.keys(query).length !== 0) {
    newUrl += (/\?/.test(newUrl) ? '&' : '?') + 'iframely=' + encodeURIComponent(window.btoa(JSON.stringify(query)));
  }
  return newUrl;
}

export { getBlockIframe, getBlockId, getEmbedIframe, getBlockWindow, addIframelyString, isObject, getEditorDocument };
