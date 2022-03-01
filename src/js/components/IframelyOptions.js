import { select } from '@wordpress/data';
import { Component } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { updateForm } from '../options';

class IframelyOptions extends Component {
  constructor(props) {
    super(props);
    this.state = {
      isAdmin: select('core').canUser('create', 'users'),
    };
  }

  updateEmptyPlaceholder() {
    let placeholder = document.getElementById('iframely-options');
    if (placeholder.innerHTML) {
      return;
    }
    placeholder.innerHTML = this.state.isAdmin
      ? sprintf(
          __(
            'If your <a href="%s" target="_blank">plan</a> supports it and config allows, Iframely will show <a href="%s" target="_blank">edit options</a> for selected URL here, whenever available.',
            'iframely'
          ),
          'https://iframely.com/plans?utm_source=wordpress-plugin',
          'https://iframely.com/docs/options?utm_source=wordpress-plugin'
        )
      : __('Iframely will show edit options for selected URL here, whenever available.', 'iframely');
  }

  componentDidMount() {
    updateForm();
    this.updateEmptyPlaceholder();
  }

  componentDidUpdate() {
    this.updateEmptyPlaceholder();
  }

  render() {
    return <div id="iframely-options" className="iframely-options" />;
  }
}

export { IframelyOptions };
