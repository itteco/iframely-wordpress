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

// The global that the scripts of Iframely's CDN define, if they were loaded.
function getIframely() {
  return typeof window.iframely === 'object' && window.iframely !== null ? window.iframely : null;
}

function isObject(val) {
  if (val === null) {
    return false;
  }
  return typeof val === 'function' || typeof val === 'object';
}

// The options of an embed travel in its URL: ...?iframely=<base64 of the options as JSON>
const IFRAMELY_STRING = /(?:&amp;|\?|&)?iframely=(.+)$/;

function isEmbedBlock(name) {
  return /^core-?\/?embed/i.test(name);
}

function getIframelyString(url) {
  const match = IFRAMELY_STRING.exec(url || '');
  return match ? match[1] : '';
}

function encodeOptions(query) {
  // btoa() takes characters up to U+00FF only, so the JSON goes in as UTF-8 bytes
  const bytes = new TextEncoder().encode(JSON.stringify(query));
  let binary = '';
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return window.btoa(binary);
}

function addIframelyString(url, query) {
  let newUrl = url.replace(IFRAMELY_STRING, '');
  if (Object.keys(query).length !== 0) {
    newUrl += (/\?/.test(newUrl) ? '&' : '?') + 'iframely=' + encodeURIComponent(encodeOptions(query));
  }
  return newUrl;
}

export {
  getBlockIframe,
  getBlockId,
  getEmbedIframe,
  getBlockWindow,
  addIframelyString,
  getIframely,
  getIframelyString,
  isEmbedBlock,
  isObject,
  getEditorDocument,
};
