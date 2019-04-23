/**
 * Creates editable block with iframely embed generator
 */
const { __ } = wp.i18n;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { PanelBody } = wp.components;

const withInspectorControls =  createHigherOrderComponent( ( BlockEdit ) => {

    return ( props ) => {
        if (props.name === "core/embed" || props.name.startsWith("core-embed")) {
            return (
                <Fragment>
                        <BlockEdit { ...props } />
                        <InspectorControls>
                                <PanelBody >
                                        <div id="ifopts">My custom control</div>
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
