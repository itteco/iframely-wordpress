import { iframeMessage } from './events/iframeMessage';
import { optionsChanged } from './events/optionsChanged';
import { getIframely } from './utils';

// The options form comes from scripts of Iframely's CDN. Without them the editor
// and the embeds work as usual, there are just no options to choose.
const iframely = getIframely();
if (iframely && typeof iframely.on === 'function') {
  iframely.on('options-changed', optionsChanged);
}

window.addEventListener('message', iframeMessage);
