import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import jQuery from 'jquery';
import { select } from '@wordpress/data';
import { updateForm } from '../../src/js/components/IframelyOptions';
import { iframeMessage } from '../../src/js/events/iframeMessage';

vi.mock('@wordpress/data', () => ({ select: vi.fn() }));
vi.mock('../../src/js/components/IframelyOptions', () => ({ updateForm: vi.fn() }));

const message = {
  method: 'setIframelyEmbedOptions',
  context: 'https://cdn.iframe.ly/api/iframe?url=https%3A%2F%2Fexample.com&key=abc',
  data: { query: ['theme'], theme: { value: 'dark' } },
};

let sandbox;
let selectedBlock;

beforeEach(() => {
  global.jQuery = jQuery;
  document.body.innerHTML =
    '<div id="block-abc-123"><iframe class="sandbox"></iframe></div><iframe class="other"></iframe>';
  sandbox = document.querySelector('iframe.sandbox');
  selectedBlock = { name: 'core/embed' };
  select.mockReturnValue({
    getBlockSelectionStart: () => 'abc-123',
    getBlock: () => selectedBlock,
  });
});

afterEach(() => {
  vi.clearAllMocks();
  document.body.innerHTML = '';
  delete global.jQuery;
});

describe('iframeMessage', () => {
  it('stores the options sent as a JSON string on the iframe they came from', () => {
    iframeMessage({ data: JSON.stringify(message), source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
    expect(jQuery(document.querySelector('iframe.other')).data()).toEqual({});
  });

  it('accepts options sent as an object', () => {
    iframeMessage({ data: message, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
  });

  it('rebuilds the options form when the selected block is an embed', () => {
    iframeMessage({ data: message, source: sandbox.contentWindow });

    expect(updateForm).toHaveBeenCalledTimes(1);
  });

  it.each([['core-embed/youtube'], ['core/embed']])('treats "%s" as an embed block', (name) => {
    selectedBlock = { name };

    iframeMessage({ data: message, source: sandbox.contentWindow });

    expect(updateForm).toHaveBeenCalledTimes(1);
  });

  it('stores the options but leaves the form alone when another block is selected', () => {
    selectedBlock = { name: 'core/paragraph' };

    iframeMessage({ data: message, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
    expect(updateForm).not.toHaveBeenCalled();
  });

  it('leaves the form alone when no block is selected', () => {
    selectedBlock = null;

    iframeMessage({ data: message, source: sandbox.contentWindow });

    expect(updateForm).not.toHaveBeenCalled();
  });

  it('ignores messages with another method', () => {
    iframeMessage({ data: { ...message, method: 'resize' }, source: sandbox.contentWindow });
    iframeMessage({ data: JSON.stringify({ height: 300 }), source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual({});
    expect(updateForm).not.toHaveBeenCalled();
  });

  it('ignores messages that do not come from an iframe of the editor', () => {
    iframeMessage({ data: message, source: window });

    expect(jQuery(sandbox).data()).toEqual({});
    expect(updateForm).not.toHaveBeenCalled();
  });

  it('finds the source iframe inside the editor canvas iframe', () => {
    document.body.innerHTML = '<iframe name="editor-canvas"></iframe>';
    const canvasDocument = document.querySelector('iframe').contentWindow.document;
    canvasDocument.body.innerHTML = '<div id="block-abc-123"><iframe class="sandbox"></iframe></div>';
    sandbox = canvasDocument.querySelector('iframe.sandbox');

    iframeMessage({ data: message, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
  });

  it('ignores JSON messages without a payload', () => {
    expect(() => iframeMessage({ data: 'null', source: sandbox.contentWindow })).not.toThrow();
    expect(jQuery(sandbox).data()).toEqual({});
  });

  /*
   * Known issue, kept as a record of current behaviour (see the review report):
   * every "message" event of the editor window is parsed as JSON without a guard,
   * so any other script that posts a plain string makes the handler throw.
   */
  it('known issue: throws on string messages that are not JSON', () => {
    expect(() => iframeMessage({ data: 'webpackHotUpdate', source: window })).toThrow(SyntaxError);
  });

  /*
   * Known issue, kept as a record of current behaviour (see the review report):
   * the origin of the message is not checked; any window that is embedded in the
   * editor can set the stored options.
   */
  it('known issue: accepts messages regardless of their origin', () => {
    iframeMessage({ data: message, source: sandbox.contentWindow, origin: 'https://attacker.example' });

    expect(jQuery(sandbox).data()).toEqual(message);
  });
});
