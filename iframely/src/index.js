/**
 * Creates editable block with iframely embed generator
 */
import Editor from './Editor.js';

/**
 * Required components
 */
const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;

/**
 * Registers and creates block
 *
 * Compatible with Gutenberg 2.8
 *
 * @param Name Name of the block with a required name space
 * @param ObjectArgs Block configuration {
 *      title - Title, displayed in the editor
 *      icon - Icon, from WP icons
 *      category - Block category, where the block will be added in the editor
 *      edit function - Returns the markup for the editor interface.
 *      save function - Returns the markup that will be rendered on the site page
 * }
 *
 */
registerBlockType(
    'itteco/iframely', // Name of the block with a required name space
    {
        title: __('Iframely'), // Title, displayed in the editor
        icon: 'window', // Icon, from WP icons
        category: 'embed', // Block category, where the block will be added in the editor
        description: __( 'This block creates iframely oembed block. Advanced settings can be adjusted below.' ),
        supports: {html: false},
        edit: Editor,
        save ( props ) { return null; }
    }
);