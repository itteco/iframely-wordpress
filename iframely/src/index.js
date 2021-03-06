/**
 * Iframely URL options form scripts
 */
const { createHigherOrderComponent } = wp.compose;
const { Fragment, RawHTML, renderToString} = wp.element;
const { InspectorControls } = wp.blockEditor;
const iEvent = new RegExp("setIframelyEmbedOptions");
const { PanelBody } = wp.components;
const admHtml = 'If your <a href="https://iframely.com/plans" target="_blank">plan</a> supports it and config allows, Iframely will show <a href="https://iframely.com/docs/options" target="_blank">edit options</a> for selected URL here, whenever  available.';
const usrHtml = 'Iframely will show edit options for selected URL here, whenever  available.';

function findIframeByContentWindow(iframes, contentWindow) {
    let foundIframe;
    for(let i = 0; i < iframes.length && !foundIframe; i++) {
        let iframe = iframes[i];
        if (iframe.contentWindow === contentWindow) {
            foundIframe = iframe;
        }
    }
    return foundIframe;
}

function getSelectedBlockID() {
    return wp.data.select('core/editor').getBlockSelectionStart();
}

function addIframelyString(url, query) {
    let newUrl = url.replace(/(?:&amp;|\?|&)?iframely=(.+)$/, '');
    newUrl += Object.keys(query).length === 0 ? '' : ((/\?/.test(newUrl) ? '&': '?') + 'iframely=' + encodeURIComponent(window.btoa(JSON.stringify(query))));

    return newUrl;
}

function loadIframelyEmbedJs($w) {
    if ($w && !$w.iframely) { // already loaded
        var ifs = $w.document.createElement('script'); ifs.type = 'text/javascript'; ifs.async = true;
        ifs.src = ('https:' == document.location.protocol ? 'https:' : 'http:') + '//if-cdn.com/embed.js';
        var s = $w.document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ifs, s);
    }
}

if (iframely) {
    // Failsafe in case of iframely name space not accessible.
    // E.g. no internet connection
    iframely.on('options-changed', function(id, formContainer, query) {

        const selector = 'div#block-' + getSelectedBlockID();
        const iframe = document.querySelector(selector + ' iframe').contentDocument.querySelector('iframe');

        const preview = jQuery(selector).find('iframe');

        if (preview && preview.data() && preview.data().data && preview.data().context) {
            const data = preview.data();

            let src = data.context;

            // wipe out old query completely
            if (data.data.query && data.data.query.length > 0) {
                data.data.query.forEach(function(key) {
                    if (src.indexOf(key) > -1) {
                        src = src.replace (new RegExp ('&?' + key.replace('-', '\\-') + '=[^\\?\\&]+'), ''); // delete old key
                    };
                });
            }
            // and add entire new query instead
            Object.keys(query).forEach(function(key) {
                src += (src.indexOf('?') > -1 ? '&' : '?') + key + '=' + query[key];
            });

            // load embed.js if it was missing to catch chaning sizes
            loadIframelyEmbedJs(document.querySelector(selector + ' iframe').contentWindow);

            iframe.src = src;

            wp.data.dispatch('core/block-editor').updateBlockAttributes(
                getSelectedBlockID(),
                {iquery: query}
            );
        }
    });
}

function updateForm () { // always single instance of form for all blocks...
    let selector = 'div#block-' + getSelectedBlockID();
    let preview = jQuery(selector).find('iframe');

    if (preview && jQuery(preview).data()) {

        iframely.buildOptionsForm(
            getSelectedBlockID(),
            jQuery('div#ifopts').get(0),
            jQuery(preview).data().data
        );
    }
}

window.addEventListener("message", function(e) {
    // Listen for messages from iframe proxy script
    if(iEvent.test(e.data)) {

        let frames = document.getElementsByTagName("iframe"),
            iframe = findIframeByContentWindow(frames, e.source);

        let data = JSON.parse(e.data);
        jQuery(iframe).data(data); // Store current state of options form in the iframe

        // update only if the form is open. If not, it will be built on render
        const block = wp.data.select('core/editor').getBlock(getSelectedBlockID());
        if (block && /^core\-?\/?embed/i.test(block.name)) {
            updateForm();
        }
    }
},false);

function addAttributes (settings) {

    if (/^embed$/i.test(settings.category) && typeof settings.attributes !== 'undefined' && !settings.attributes.iquery) {
        settings.attributes = Object.assign(settings.attributes, {
            iquery:{ 
                type: 'string',
                default: ''
            }
        });    
    }

    return settings;
}
wp.hooks.addFilter ('blocks.registerBlockType', 'iframely/add-attributes', addAttributes);


function saveQueryURL (element, blockType, attributes) {

    if (/^embed$/i.test(blockType.category), attributes.iquery && attributes.url) {
        let url = attributes.url;
        let newUrl = addIframelyString(attributes.url, attributes.iquery);
        attributes.url = newUrl; // this is to pass blocks validation

// Cache busting doesn't seem to be needed
/* 
    // bust the cache preview, so it re-renders when returning to previous options
    // also warms up cache if URL is new, as the next time getEmbedPreview will return cached value
    if (wp.data.select( 'core' ).getEmbedPreview(newUrl)) {
        wp.data.dispatch('core/data').invalidateResolution( 'core', 'getEmbedPreview', [ newUrl ] );
    }
*/        

        let s = renderToString(element).replace(/&amp;/g, '&');

        let elAsString = s.replace(url, newUrl);

        return (
            <RawHTML>{elAsString}</RawHTML>
        );
    } else {
        return element;
    }
}
wp.hooks.addFilter ('blocks.getSaveElement', 'iframely/save-query', saveQueryURL);


class IframelyOptions extends React.Component {

    updateEmptyPlaceholder() {
        // Placeholder text in case of no options exist.
        let formPlaceholder = jQuery('div#ifopts');
        if (!formPlaceholder.html()) {
            if (wp.data.select( 'core' ).canUser( 'create', 'users' )) {
                formPlaceholder.html(admHtml);
            } else {
                formPlaceholder.html(usrHtml);
            }
        }
    }

    componentDidMount() {
        updateForm();
        this.updateEmptyPlaceholder();
    }

    componentDidUpdate() {
        this.updateEmptyPlaceholder();
    }

    render() {
        return <div id="ifopts"></div>;
    }
}

const withInspectorControls = createHigherOrderComponent( (BlockEdit) => {
    return (props) => {        
        if (props.isSelected === true && /^core\-?\/?embed/i.test(props.name)) {
            return (
                <Fragment>
                    <BlockEdit { ...props } />
                    <InspectorControls>
                            <PanelBody title="Iframely options">
                                <IframelyOptions/>
                            </PanelBody>
                    </InspectorControls>
                </Fragment>
            );
        } else {
            return (<Fragment><BlockEdit { ...props } /></Fragment>);
        }
    };
}, "withInspectorControl" );

wp.hooks.addFilter( 'editor.BlockEdit', 'iframely/with-inspector-controls', withInspectorControls );
// Preload to cache User Admin permission
wp.data.select( 'core' ).canUser( 'create', 'users' );