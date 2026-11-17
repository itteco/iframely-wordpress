import { dispatch, select } from '@wordpress/data';
import { addIframelyString, getBlockId, isEmbedBlock } from '../utils';

/**
 * The author has changed the options of an embed in the form.
 *
 * The options go into the URL of the block. The block then asks for the preview
 * of that URL, which is the embed the published post will show. Nothing here
 * depends on the preview that is on screen, so a change made while a preview
 * loads, or after one has failed, is applied like any other.
 *
 * @param {string}  id            The block the form was built for.
 * @param {Element} formContainer The element that holds the form.
 * @param {Object}  query         All options that differ from their defaults.
 */
export function optionsChanged(id, formContainer, query) {
  const editor = select('core/block-editor');
  const blockId = id && editor.getBlock(id) ? id : getBlockId();
  const block = editor.getBlock(blockId);

  if (!block || !isEmbedBlock(block.name) || !block.attributes?.url) {
    return;
  }

  const url = addIframelyString(block.attributes.url, query || {});
  if (url !== block.attributes.url) {
    dispatch('core/block-editor').updateBlockAttributes(blockId, { url });
  }
}
