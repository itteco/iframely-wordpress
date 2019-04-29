/**
 * Iramely oembed scripts
 */
const { __ } = wp.i18n;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
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

window.addEventListener("message",function(e){
    let frames = document.getElementsByTagName("iframe");
    if(new RegExp("setIframelyEmbedOptions").test(e.data)) {
        let iframe = findIframeByContentWindow(frames, e.source);
        $(iframe).data(JSON.parse(e.data));
    }
},false);

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
        return <div id="ifopts" data-id={ this.props.data }></div>;
    }

}
IframelyOptions.defaultProps = {
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