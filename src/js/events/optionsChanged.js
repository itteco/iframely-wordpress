import { getBlockId } from '../utils';
import { dispatch } from '@wordpress/data';

function loadIframelyEmbedJs($w) {
  if ($w && !$w.iframely) {
    // already loaded
    var ifs = $w.document.createElement('script');
    ifs.type = 'text/javascript';
    ifs.async = true;
    ifs.src = ('https:' === document.location.protocol ? 'https:' : 'http:') + '//if-cdn.com/embed.js';
    var s = $w.document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(ifs, s);
  }
}

export function optionsChanged(id, formContainer, query) {
  const selector = '#block-' + getBlockId();
  const iframe = document.querySelector(selector + ' iframe').contentDocument.querySelector('iframe');
  const preview = jQuery(selector).find('iframe');

  if (preview && preview.data() && preview.data().data && preview.data().context) {
    const data = preview.data();

    let src = data.context;

    // wipe out old query completely
    if (data.data.query && data.data.query.length > 0) {
      data.data.query.forEach(function (key) {
        if (src.indexOf(key) > -1) {
          src = src.replace(new RegExp('&?' + key.replace('-', '\\-') + '=[^\\?\\&]+'), ''); // delete old key
        }
      });
    }
    // and add entire new query instead
    Object.keys(query).forEach(function (key) {
      src += (src.indexOf('?') > -1 ? '&' : '?') + key + '=' + query[key];
    });

    console.log('optionsChanged', {
      query: data?.data?.query,
      src,
    });

    // load embed.js if it was missing to catch chaning sizes
    loadIframelyEmbedJs(document.querySelector(selector + ' iframe').contentWindow);

    iframe.src = src;

    dispatch('core/block-editor').updateBlockAttributes(getBlockId(), { iquery: query });
  }
}
