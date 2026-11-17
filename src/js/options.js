import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { IframelyOptions } from './components/IframelyOptions';
import { getIframelyString, isEmbedBlock } from './utils';

const withInspectorControls = createHigherOrderComponent((BlockEdit) => {
  return (props) => {
    if (!isEmbedBlock(props.name)) {
      return <BlockEdit {...props} />;
    }

    // The embed block keeps a copy of its URL from the moment it was first shown,
    // and uses it for "Try again" and for the URL field. When the options in the
    // URL change, a new key makes the block start again with the URL it has now.
    return (
      <Fragment>
        <BlockEdit key={getIframelyString(props.attributes?.url)} {...props} />
        {props.isSelected === true && (
          <InspectorControls>
            <PanelBody title={__('Iframely options', 'iframely')}>
              <IframelyOptions />
            </PanelBody>
          </InspectorControls>
        )}
      </Fragment>
    );
  };
}, 'withInspectorControl');

addFilter('editor.BlockEdit', 'iframely/with-inspector-controls', withInspectorControls);
