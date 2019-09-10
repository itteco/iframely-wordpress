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
            acc[key]=obj[key].map(sortObjectKeys);
        }
        if (typeof obj[key] === 'object'){
            acc[key]=sortObjectKeys(obj[key]);
        }
        else{
            acc[key]=obj[key];
        }
        return acc;
    },{});
}

function updateIframe(id, query) {
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

    // Ensure sorted options object to make sure
    // we generating same data each time for same options.
    query = sortObject(query);
    //query.timestamp = new Date();

    // Join the url string with iframely params
    let params = iframely_key + encodeURIComponent(window.btoa(JSON.stringify(query)));
    let newUrl = url + params;

    // Update the corresponding block and get a preview if required
    let promise = wp.data.dispatch('core/block-editor').updateBlockAttributes([clientId], { url: newUrl });
    promise.then(function(value) {
        let id = value.clientId[0];
        console.log(id);
        $('div[data-block='+ id+ '] iframe').on('load', function() {
            console.log("Loaded Iframe", this);
        });
        $($('div#ifopts').get(0)).attr('data-id', id);

    });

}

if (iframely) {
    // Failsafe in case of iframely name space not accessible.
    // E.g. no internet connection
    iframely.on('options-changed', function(id, formContainer, query) {
        updateIframe(id, query);
    });
}

function initListener() {
    window.addEventListener("message",function(e){
        if(iEvent.test(e.data)) {
            let frames = document.getElementsByTagName("iframe"),
                iframe = findIframeByContentWindow(frames, e.source);
            let data = JSON.parse(e.data)
            $(iframe).data(data);
            if ($('div#ifopts').get(0)) {
                console.log('form exists');
                let id = $('div#ifopts').attr('data-id');
                iframely.buildOptionsForm(id, $('div#ifopts').get(0), data);
            }
        }
    },false);
}
initListener();

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