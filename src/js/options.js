import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { getSelectedBlockID } from './utils';
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

function updateForm() {
  let selector = '#block-' + getSelectedBlockID();
  let preview = jQuery(selector).find('iframe');
  let previewData = jQuery(preview).data();
  let blockId = getSelectedBlockID();
  let $options = jQuery('#iframely-options');
  let options = $options.length === 2 ? $options.get(1) : $options.get(0);
  if (preview && previewData) {
    iframely.buildOptionsForm(blockId, options, previewData.data);
  }
}

export { updateForm };
