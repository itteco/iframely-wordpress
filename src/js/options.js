import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { IframelyOptions } from './components/IframelyOptions';

const withInspectorControls = createHigherOrderComponent((BlockEdit) => {
  return (props) => {
    if (props.isSelected === true && /^core-?\/?embed/i.test(props.name)) {
      return (
        <Fragment>
          <BlockEdit {...props} />
          <InspectorControls>
            <PanelBody title={__('Iframely options', 'iframely')}>
              <IframelyOptions />
            </PanelBody>
          </InspectorControls>
        </Fragment>
      );
    } else {
      return (
        <Fragment>
          <BlockEdit {...props} />
        </Fragment>
      );
    }
  };
}, 'withInspectorControl');

addFilter('editor.BlockEdit', 'iframely/with-inspector-controls', withInspectorControls);
