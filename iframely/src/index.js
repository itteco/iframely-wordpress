/**
 * Iramely oembed scripts
 */
const { __ } = wp.i18n;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { PanelBody } = wp.components;

const script_text = "window.addEventListener(\"message\",function(e){\n" +
    "    if(e.data.indexOf('setIframelyEmbedOptions') >= 0) {\n" +
    "        window.parent.postMessage(e.data,'*');\n" +
    "    }\n" +
    "},false);";

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
        blockAttrs = wp.data.select('core/editor').getBlockAttributes(clientId),
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

    // Join the url string with iframely params
    let params = iframely_key + encodeURIComponent(window.btoa(JSON.stringify(query)));
    let newUrl = url + params;
    wp.data.dispatch('core/editor').updateBlockAttributes([clientId], { url: newUrl });
});

window.addEventListener("message",function(e){
    let frames = document.getElementsByTagName("iframe");
    if(new RegExp("setIframelyEmbedOptions").test(e.data)) {
        let iframe = findIframeByContentWindow(frames, e.source);
        $(iframe).data(JSON.parse(e.data));
    }
},false);

function init_observer() {
    let target = document.querySelector("#editor");
    let config = {
        childList: true,
        characterData: true,
        subtree: true,
    };
    iframelyObserver.observe(target, config);
}

$(document).ready(function () {
    init_observer();
});

function injectProxy(mutation) {
    /* One or more children have been added to and/or removed
    from the tree; see mutation.addedNodes and
    mutation.removedNodes */
    let scriptProxy   = document.createElement("script");
    scriptProxy.type  = "text/javascript";
    scriptProxy.text  = script_text;
    let iframe = mutation.target.getElementsByTagName("iframe");
    if (iframe[0] !== undefined) {
        // Block in normal editing mode
        let innerDoc = iframe[0].contentDocument || iframe[0].contentWindow.document;
        innerDoc.body.appendChild(scriptProxy);
    }
}

let iframelyObserver = new MutationObserver(function (mutationRecords, iframelyObserver) {
    mutationRecords.forEach((mutation) => {
        if (
            mutation.type === 'childList' &&
            mutation.target.getElementsByClassName('wp-block-embed').length > 0 &&
            mutation.removedNodes.length === 0
        ) {injectProxy(mutation);}
    });
});

class IframelyOptions extends React.Component {

    renderForm(clientId) {
        // Rendering form for options in the Inspector
        let selector = 'div#block-' + clientId;
        let options = $(selector).find('iframe').data();
        if (options) {
            iframely.buildOptionsForm(selector, $('div#ifopts').get(0), options.data)
        }
    }

    componentDidMount() {
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
                        <PanelBody >
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