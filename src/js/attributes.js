import { RawHTML, renderToString } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { addIframelyString } from './utils';

addFilter('blocks.registerBlockType', 'iframely/add-attributes', addAttributes);
addFilter('blocks.getSaveElement', 'iframely/save-query', saveQueryURL);

function addAttributes(settings) {
  if (/^embed$/i.test(settings.category) && typeof settings.attributes !== 'undefined' && !settings.attributes.iquery) {
    settings.attributes = Object.assign(settings.attributes, {
      iquery: {
        type: 'string',
        default: '',
      },
    });
  }

  return settings;
}

function saveQueryURL(element, blockType, attributes) {
  if (/^embed$/i.test(blockType.category) && attributes.iquery && attributes.url) {
    let url = attributes.url;
    let newUrl = addIframelyString(attributes.url, attributes.iquery);
    attributes.url = newUrl; // this is to pass blocks validation

    /*
    Cache busting doesn't seem to be needed.
    bust the cache preview, so it re-renders when returning to previous options
    also warms up cache if URL is new, as the next time getEmbedPreview will return cached value
    if (wp.data.select( 'core' ).getEmbedPreview(newUrl)) {
        wp.data.dispatch('core/data').invalidateResolution( 'core', 'getEmbedPreview', [ newUrl ] );
    }
    */

    let s = renderToString(element).replace(/&amp;/g, '&');
    let elAsString = s.replace(url, newUrl);
    return <RawHTML>{elAsString}</RawHTML>;
  } else {
    return element;
  }
}
