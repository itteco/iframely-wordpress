import { select } from '@wordpress/data';

// Preload to cache User Admin permission
select('core').canUser('create', 'users');

import './js/attributes';
import './js/options';
import './js/events';

import './index.scss';
