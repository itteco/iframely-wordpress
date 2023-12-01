import { iframeMessage } from './events/iframeMessage';
import { optionsChanged } from './events/optionsChanged';

if (iframely) {
  iframely.on('options-changed', optionsChanged);
}

window.addEventListener('message', iframeMessage);
