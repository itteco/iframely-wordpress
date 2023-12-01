import { select } from '@wordpress/data';
import { Component } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getBlockIframe, getBlockId } from '../utils';

class IframelyOptions extends Component {
  constructor(props) {
    super(props);
    this.state = {
      isAdmin: select('core').canUser('create', 'users'),
    };
  }

  updateEmptyPlaceholder() {
    let placeholder = document.getElementById('iframely-options');
    if (!placeholder || placeholder.innerHTML) {
      return;
    }
    placeholder.innerHTML = this.state.isAdmin
      ? sprintf(
          __(
            'If your <a href="%s" target="_blank">plan</a> supports it and config allows, Iframely will show <a href="%s" target="_blank">edit options</a> for selected URL here, whenever available.',
            'iframely',
          ),
          'https://iframely.com/plans?utm_source=wordpress-plugin',
          'https://iframely.com/docs/options?utm_source=wordpress-plugin',
        )
      : __('Iframely will show edit options for selected URL here, whenever available.', 'iframely');
  }

  componentDidMount() {
    setTimeout(() => {
      updateForm();
      this.updateEmptyPlaceholder();
    }, 10);
  }

  componentDidUpdate() {
    setTimeout(() => {
      this.updateEmptyPlaceholder();
    }, 10);
  }

  render() {
    return <div id="iframely-options" className="iframely-options" />;
  }
}

function updateForm() {
  //console.log('updateForm');
  const blockId = getBlockId();
  const iframe = getBlockIframe(blockId);
  const data = jQuery(iframe).data();
  let $options = jQuery('#iframely-options');
  let options = $options.length === 2 ? $options.get(1) : $options.get(0);
  if (iframe && data) {
    iframely.buildOptionsForm(blockId, options, data.data);
  }
}

export { IframelyOptions, updateForm };
