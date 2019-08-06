/**
 * Iramely oembed scripts
 */
const { __ } = wp.i18n;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.blockEditor;
const iEvent = new RegExp("setIframelyEmbedOptions");
import { Panel, PanelBody, PanelRow } from '@wordpress/components';

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

iframely.on('options-changed', function(id, formContainer, query) {
    // block options interaction
    let clientId = id.split("div#block-")[1],
        blockAttrs = wp.data.select('core/block-editor').getBlockAttributes(clientId),
        url = blockAttrs.url,
        iframely_key = '&iframely=';

    // Parse url and make sure we are replacing an url query string properly
    if(url.indexOf('iframely=') > 0) {
        let durl = url.split('iframely=')[0];
        url = durl.substr(0, durl.length-1);
    }
    if(url.indexOf('?') === -1) {
        iframely_key = '?iframely=';
    }

    // Fix the UI caching issue
    query.timestamp = new Date().getTime();
    // Join the url string with iframely params
    let params = iframely_key + encodeURIComponent(window.btoa(JSON.stringify(query)));
    let newUrl = url + params;
    wp.data.dispatch('core/block-editor').updateBlockAttributes([clientId], { url: newUrl });

});

function initListener() {
    window.addEventListener("message",function(e){
        let frames = document.getElementsByTagName("iframe");
        if(iEvent.test(e.data)) {
            let iframe = findIframeByContentWindow(frames, e.source);
            $(iframe).data(JSON.parse(e.data));
        }
    },false);
}
initListener();

class IframelyOptions extends React.Component {

    renderForm(clientId) {
        // Rendering form for options in the Inspector
        let selector = 'div#block-' + clientId;
        let options = $(selector).find('iframe').data();
        if (options) {
            iframely.buildOptionsForm(selector, $('div#ifopts').get(0), options.data);
        }
    }

    componentDidMount() {
        this.renderForm(this.props.data);
    }

    componentDidUpdate() {
        this.renderForm(this.props.data);
    }

    render() {
        return <div id="ifopts" data-id={ this.props.data }>{ this.body }</div>;
    }

}

IframelyOptions.defaultProps = {
    body: '',
    data: '',
};
const withInspectorControls =  createHigherOrderComponent( ( BlockEdit ) => {
    return ( props ) => {
        if (props.isSelected===true && (props.name === "core/embed" || props.name.startsWith("core-embed"))) {
            return (
                <Fragment>
                    <BlockEdit { ...props } />
                    <InspectorControls>
                            <PanelBody
                                title="Iframely options"
                                className={'iframelySettingsPanel'}
                            >
                                <IframelyOptions data={ props.clientId }/>
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