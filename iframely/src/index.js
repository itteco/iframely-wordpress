/**
 * Iramely oembed scripts
 */
const { __ } = wp.i18n;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { PanelBody } = wp.components;
import parseUrl from 'url-parse'; // https://www.npmjs.com/package/url-parse

const script_text = "window.addEventListener(\"message\",function(e){\n" +
    "    if(e.data.indexOf('setIframelyEmbedOptions') >= 0) {\n" +
    "        window.parent.postMessage(e.data,'*');\n" +
    "    }\n" +
    "    if(e.data.lastIndexOf('gutenbergSetOptions:', 0) === 0){\n" +
    "        var fr=document.getElementsByTagName('iframe')[0];\n" +
    "        fr.src=e.data.replace('gutenbergSetOptions:','');}\n" +
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

function parseOptions(src, opts) {
    // Apply options to the API call url
    let parsedApiCall = parseUrl(src, true);
    parsedApiCall.query = Object.assign(parsedApiCall.query, opts);
    return parsedApiCall.toString();
}

function retrieveDataUrl(selectedIframe) {
    // Manages original URL stored without options applied
    let oldUrl = '';
    if (selectedIframe.dataUrl) {
        // getting from a dta parameter
        oldUrl = selectedIframe.dataUrl;
    } else {
        // Retrieving url from child iframe
        let contents = $(selectedIframe).contents().get(0);
        oldUrl = $(contents).find('iframe').get(0).src;
        selectedIframe.dataUrl = oldUrl;
    }
    return oldUrl;
}

iframely.on('options-changed', function(id, formContainer, query) {
    // block options interaction
    let selectedIframe = $(id).find('iframe').get(0);
    let newUrl = parseOptions(retrieveDataUrl(selectedIframe), query);
    selectedIframe.contentWindow.postMessage('gutenbergSetOptions:' + newUrl);

});

window.addEventListener("message",function(e){
    let frames = document.getElementsByTagName("iframe");
    if(new RegExp("setIframelyEmbedOptions").test(e.data)) {
        let iframe = findIframeByContentWindow(frames, e.source);
        $(iframe).data(JSON.parse(e.data));
    }
},false);

$(document).ready(function () {
    let target = document.querySelector("#editor");
    let config = {
        childList: true,
        characterData: true,
        subtree: true,
    };
    iframelyObserver.observe(target, config);
});

function injectProxy(mutation) {
    /* One or more children have been added to and/or removed
    from the tree; see mutation.addedNodes and
    mutation.removedNodes */
    let scriptProxy   = document.createElement("script");
    scriptProxy.type  = "text/javascript";
    scriptProxy.text  = script_text;
    let iframe = mutation.target.getElementsByTagName("iframe");
    let innerDoc = iframe[0].contentDocument || iframe[0].contentWindow.document;
    innerDoc.body.appendChild(scriptProxy);
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