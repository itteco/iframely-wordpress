import { afterEach, describe, expect, it, vi } from 'vitest';
import { select } from '@wordpress/data';
import {
  addIframelyString,
  getBlockId,
  getBlockIframe,
  getBlockWindow,
  getEditorDocument,
  getEmbedIframe,
  getIframelyString,
  isEmbedBlock,
  isObject,
} from '../../src/js/utils';

vi.mock('@wordpress/data', () => ({ select: vi.fn() }));

function decodeOptions(url) {
  const encoded = new URL(url).searchParams.get('iframely');
  return JSON.parse(window.atob(encoded));
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('addIframelyString', () => {
  it('appends the options as a base64-encoded "iframely" parameter', () => {
    const url = addIframelyString('https://example.com/video', { theme: 'dark' });

    expect(url).toBe('https://example.com/video?iframely=eyJ0aGVtZSI6ImRhcmsifQ%3D%3D');
    expect(decodeOptions(url)).toEqual({ theme: 'dark' });
  });

  it('carries the small card option the way the PHP side expects it', () => {
    // tests/php/CardTest.php renders this exact URL.
    const url = addIframelyString('https://en.wikipedia.org/wiki/Tiramisu', { card: 'small' });

    expect(url).toBe('https://en.wikipedia.org/wiki/Tiramisu?iframely=eyJjYXJkIjoic21hbGwifQ%3D%3D');
    expect(addIframelyString(url, {})).toBe('https://en.wikipedia.org/wiki/Tiramisu');
  });

  it('uses "&" when the URL already has a query string', () => {
    const url = addIframelyString('https://example.com/video?v=42', { theme: 'dark' });

    expect(url).toMatch(/^https:\/\/example\.com\/video\?v=42&iframely=/);
    expect(decodeOptions(url)).toEqual({ theme: 'dark' });
  });

  it.each([
    ['as the only parameter', 'https://example.com/video?iframely=b2xk', 'https://example.com/video?iframely='],
    ['after "&"', 'https://example.com/video?v=42&iframely=b2xk', 'https://example.com/video?v=42&iframely='],
    ['after "&amp;"', 'https://example.com/video?v=42&amp;iframely=b2xk', 'https://example.com/video?v=42&iframely='],
  ])('replaces options that are already in the URL (%s)', (name, url, expectedStart) => {
    const result = addIframelyString(url, { theme: 'light' });

    expect(result.startsWith(expectedStart)).toBe(true);
    expect(result.match(/iframely=/g)).toHaveLength(1);
    expect(decodeOptions(result)).toEqual({ theme: 'light' });
  });

  it('leaves the URL untouched when there are no options', () => {
    expect(addIframelyString('https://example.com/video?v=42', {})).toBe('https://example.com/video?v=42');
  });

  it('removes the options from the URL when they are cleared', () => {
    expect(addIframelyString('https://example.com/video?v=42&iframely=b2xk', {})).toBe(
      'https://example.com/video?v=42',
    );
    expect(addIframelyString('https://example.com/video?iframely=b2xk', {})).toBe('https://example.com/video');
  });

  it('accepts values in any script', () => {
    const url = addIframelyString('https://example.com/video', { _lang: 'Ελληνικά', title: '日本' });

    // What PHP does with it: base64 to bytes, bytes as UTF-8 JSON.
    const bytes = Uint8Array.from(window.atob(new URL(url).searchParams.get('iframely')), (c) => c.charCodeAt(0));
    expect(JSON.parse(new TextDecoder().decode(bytes))).toEqual({ _lang: 'Ελληνικά', title: '日本' });
  });

  it('percent-encodes base64 characters that are special in a query string', () => {
    // The base64 form of this value contains "+", which must not reach the URL as is.
    const url = addIframelyString('https://example.com/video', { t: 'xy>' });

    expect(url).toBe('https://example.com/video?iframely=eyJ0IjoieHk%2BIn0%3D');
  });
});

describe('isObject', () => {
  it.each([[{}], [[]], [() => {}], [new Date()]])('is true for %p', (value) => {
    expect(isObject(value)).toBe(true);
  });

  it.each([[null], [undefined], ['{"a":1}'], [42], [true]])('is false for %p', (value) => {
    expect(isObject(value)).toBe(false);
  });
});

describe('editor DOM helpers', () => {
  function addBlock(doc, id) {
    doc.body.innerHTML = `<div id="block-${id}"><iframe class="sandbox"></iframe></div>`;
    return doc.querySelector('iframe.sandbox');
  }

  it('reads the selected block from the block editor store', () => {
    select.mockReturnValue({ getBlockSelectionStart: () => 'abc-123' });

    expect(getBlockId()).toBe('abc-123');
    expect(select).toHaveBeenCalledWith('core/block-editor');
  });

  it('uses the page document when the editor canvas is not iframed', () => {
    expect(getEditorDocument()).toBe(document);
  });

  it('uses the document of the editor canvas iframe when there is one', () => {
    document.body.innerHTML = '<iframe name="editor-canvas"></iframe>';
    const canvas = document.querySelector('iframe');

    expect(getEditorDocument()).toBe(canvas.contentWindow.document);
    expect(getEditorDocument()).not.toBe(document);
  });

  it('finds the preview iframe of a block', () => {
    const sandbox = addBlock(document, 'abc-123');

    expect(getBlockIframe('abc-123')).toBe(sandbox);
    expect(getBlockWindow('abc-123')).toBe(sandbox.contentWindow);
    expect(getBlockIframe('other')).toBeNull();
  });

  it('finds the preview iframe of a block inside the editor canvas iframe', () => {
    document.body.innerHTML = '<iframe name="editor-canvas"></iframe>';
    const canvasDocument = document.querySelector('iframe').contentWindow.document;
    const sandbox = addBlock(canvasDocument, 'abc-123');

    expect(getBlockIframe('abc-123')).toBe(sandbox);
  });

  it('finds the Iframely iframe inside the preview iframe of a block', () => {
    const sandbox = addBlock(document, 'abc-123');
    const sandboxDocument = sandbox.contentWindow.document;
    sandboxDocument.body.innerHTML = '<div><iframe class="embed"></iframe></div>';

    expect(getEmbedIframe('abc-123')).toBe(sandboxDocument.querySelector('iframe.embed'));
  });
});

describe('getIframelyString', () => {
  it.each([
    ['https://example.com/video?iframely=b25l', 'b25l'],
    ['https://example.com/video?v=42&iframely=b25l%3D', 'b25l%3D'],
    ['https://example.com/video?v=42&amp;iframely=b25l', 'b25l'],
    ['https://example.com/video?v=42', ''],
    ['https://example.com/video', ''],
    ['', ''],
    [undefined, ''],
  ])('finds the options in %s', (url, expected) => {
    expect(getIframelyString(url)).toBe(expected);
  });

  it('returns what addIframelyString has added', () => {
    const url = addIframelyString('https://example.com/video?v=42', { theme: 'dark' });

    expect(getIframelyString(url)).toBe('eyJ0aGVtZSI6ImRhcmsifQ%3D%3D');
  });
});

describe('isEmbedBlock', () => {
  it.each([
    ['core/embed', true],
    ['core-embed/youtube', true],
    ['core/paragraph', false],
    ['acme/embed', false],
    [undefined, false],
  ])('%s: %s', (name, expected) => {
    expect(isEmbedBlock(name)).toBe(expected);
  });
});
