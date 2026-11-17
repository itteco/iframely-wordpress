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

// The frame of an embed in the editor has the origin of the editor.
const ORIGIN = window.location.origin;

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
    iframeMessage({ origin: ORIGIN, data: JSON.stringify(message), source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
    expect(jQuery(document.querySelector('iframe.other')).data()).toEqual({});
  });

  it('accepts options sent as an object', () => {
    iframeMessage({ origin: ORIGIN, data: message, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
  });

  it('stores the options a card reports, as the real card sends them', () => {
    const card = {
      method: 'setIframelyEmbedOptions',
      data: { card: { value: '', values: { small: 'Make it a small card' } } },
      context:
        'https://iframely.net/api/iframe?maxheight=750&app=1&url=https%3A%2F%2Fen.wikipedia.org%2Fwiki%2FTiramisu&key=abc',
      url: 'https://en.wikipedia.org/wiki/Tiramisu',
    };

    iframeMessage({ origin: ORIGIN, data: JSON.stringify(card), source: sandbox.contentWindow });

    expect(jQuery(sandbox).data('data')).toEqual({ card: { value: '', values: { small: 'Make it a small card' } } });
    expect(jQuery(sandbox).data('context')).toBe(card.context);
    expect(updateForm).toHaveBeenCalledTimes(1);
  });

  it('rebuilds the options form when the selected block is an embed', () => {
    iframeMessage({ origin: ORIGIN, data: message, source: sandbox.contentWindow });

    expect(updateForm).toHaveBeenCalledTimes(1);
  });

  it.each([['core-embed/youtube'], ['core/embed']])('treats "%s" as an embed block', (name) => {
    selectedBlock = { name };

    iframeMessage({ origin: ORIGIN, data: message, source: sandbox.contentWindow });

    expect(updateForm).toHaveBeenCalledTimes(1);
  });

  it('stores the options but leaves the form alone when another block is selected', () => {
    selectedBlock = { name: 'core/paragraph' };

    iframeMessage({ origin: ORIGIN, data: message, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
    expect(updateForm).not.toHaveBeenCalled();
  });

  it('leaves the form alone when no block is selected', () => {
    selectedBlock = null;

    iframeMessage({ origin: ORIGIN, data: message, source: sandbox.contentWindow });

    expect(updateForm).not.toHaveBeenCalled();
  });

  it('ignores messages with another method', () => {
    iframeMessage({ origin: ORIGIN, data: { ...message, method: 'resize' }, source: sandbox.contentWindow });
    iframeMessage({ origin: ORIGIN, data: JSON.stringify({ height: 300 }), source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual({});
    expect(updateForm).not.toHaveBeenCalled();
  });

  it('ignores messages that do not come from an iframe of the editor', () => {
    iframeMessage({ origin: ORIGIN, data: message, source: window });

    expect(jQuery(sandbox).data()).toEqual({});
    expect(updateForm).not.toHaveBeenCalled();
  });

  it('finds the source iframe inside the editor canvas iframe', () => {
    document.body.innerHTML = '<iframe name="editor-canvas"></iframe>';
    const canvasDocument = document.querySelector('iframe').contentWindow.document;
    canvasDocument.body.innerHTML = '<div id="block-abc-123"><iframe class="sandbox"></iframe></div>';
    sandbox = canvasDocument.querySelector('iframe.sandbox');

    iframeMessage({ origin: ORIGIN, data: message, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual(message);
  });

  it('ignores JSON messages without a payload', () => {
    expect(() => iframeMessage({ origin: ORIGIN, data: 'null', source: sandbox.contentWindow })).not.toThrow();
    expect(jQuery(sandbox).data()).toEqual({});
  });

  it.each([['webpackHotUpdate'], ['{broken'], [''], [42], [undefined]])(
    'ignores a message in another format: %s',
    (data) => {
      expect(() => iframeMessage({ origin: ORIGIN, data, source: sandbox.contentWindow })).not.toThrow();
      expect(jQuery(sandbox).data()).toEqual({});
      expect(updateForm).not.toHaveBeenCalled();
    },
  );

  it('ignores options that are not a set of options', () => {
    iframeMessage({ origin: ORIGIN, data: { ...message, data: 'theme=dark' }, source: sandbox.contentWindow });
    iframeMessage({ origin: ORIGIN, data: { ...message, data: undefined }, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual({});
    expect(updateForm).not.toHaveBeenCalled();
  });

  it('ignores messages of a frame that has left the editor for another site', () => {
    iframeMessage({ data: message, source: sandbox.contentWindow, origin: 'https://attacker.example' });
    iframeMessage({ data: message, source: sandbox.contentWindow, origin: 'null' });
    iframeMessage({ data: message, source: sandbox.contentWindow });

    expect(jQuery(sandbox).data()).toEqual({});
    expect(updateForm).not.toHaveBeenCalled();
  });
});
