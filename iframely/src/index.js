/**
 * Iramely oembed scripts
 */
const { __ } = wp.i18n;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.blockEditor;
const iEvent = new RegExp("setIframelyEmbedOptions");
const { PanelBody } = wp.components;

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

function sortObject(obj){
    return Object.keys(obj).sort().reduce((acc,key)=>{
        if (Array.isArray(obj[key])){
            acc[key]=obj[key].map(sortObject);
        }
        if (typeof obj[key] === 'object'){
            acc[key]=sortObject(obj[key]);
        }
        else{
            acc[key]=obj[key];
        }
        return acc;
    },{});
}

function getSelectedBlockID() {
    return wp.data.select( 'core/editor' ).getBlockSelectionStart();
}

function addIframelyString(url, query) {
    var newUrl = url.replace(/\??&?iframely=(.+)$/, '');
    newUrl += Object.keys(query).length === 0 ? '' : ((/\?/.test(newUrl) ? '&': '?') + 'iframely=' + window.btoa(JSON.stringify(query)));

    return newUrl;
}

function updatePreview(query) {
    // block options interaction
    let blockAttrs = wp.data.select('core/block-editor').getBlockAttributes(getSelectedBlockID()),
        url = blockAttrs.url;

    let newUrl = addIframelyString(url, sortObject(query));

    // bust the cache preview, so it re-renders when returning to previous options
    // also warms up cache if URL is new, as the next time getEmbedPreview will return cached value
    if (wp.data.select( 'core' ).getEmbedPreview(newUrl)) {
        wp.data.dispatch('core/data').invalidateResolution( 'core', 'getEmbedPreview', [ newUrl ] );
    }

    // Update the corresponding block and get a preview if required
    wp.data.dispatch('core/block-editor').updateBlockAttributes(getSelectedBlockID(), { url: newUrl });
}

if (iframely) {
    // Failsafe in case of iframely name space not accessible.
    // E.g. no internet connection
    iframely.on('options-changed', function(id, formContainer, query) {
        updatePreview(query);
    });
}

window.addEventListener("message",function(e){
    // Listen for messages from iframe proxy script
    if(iEvent.test(e.data)) {
        let frames = document.getElementsByTagName("iframe"),
            iframe = findIframeByContentWindow(frames, e.source);
        let data = JSON.parse(e.data);
        $(iframe).data(data);
        console.log(data);
        let selBlock = wp.data.select('core/block-editor').getSelectedBlock();
        if (!$('div#ifopts').get(0) && selBlock) {
            console.log('Form data came! with no form!');
            // TODO: render component in form.
        }
    }
},false);


class IframelyOptions extends React.Component {

    componentDidMount() {
        iframely.buildOptionsForm(this.props.selector, $('div#ifopts').get(0), this.props.options.data);
    }

    render() {
        return <div id="ifopts"
                    data-id={ this.props.clientId }
                    data-opts={JSON.stringify(this.props.options.data)}
        >{ this.body }</div>;
    }
}

IframelyOptions.defaultProps = {
    body: '',
    clientId: '',
    selector: '',
    options: '',
};
const withInspectorControls =  createHigherOrderComponent( ( BlockEdit ) => {
    return ( props ) => {
        let fragment = (<Fragment><BlockEdit { ...props } /></Fragment>);
        if (props.isSelected===true && (props.name === "core/embed" || props.name.startsWith("core-embed"))) {
            let selector = 'div#block-' + props.clientId;
            let options = $(selector).find('iframe').data();
            if (!options || !options.data) {
                return fragment;
            }
            return (
                <Fragment>
                    <BlockEdit { ...props } />
                    <InspectorControls>
                            <PanelBody title="Iframely options" >
                                <IframelyOptions selector={ selector } options={ options } clientId={ props.clientId } />
                            </PanelBody>
                    </InspectorControls>
                </Fragment>
            );
        } else {
            return fragment;
        }
    };
}, "withInspectorControl" );

wp.hooks.addFilter( 'editor.BlockEdit', 'iframely/with-inspector-controls', withInspectorControls );