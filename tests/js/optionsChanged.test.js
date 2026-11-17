import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import jQuery from 'jquery';
import { dispatch, select } from '@wordpress/data';
import { optionsChanged } from '../../src/js/events/optionsChanged';

vi.mock('@wordpress/data', () => ({ select: vi.fn(), dispatch: vi.fn() }));

const BASE = 'https://cdn.iframe.ly/api/iframe?url=https%3A%2F%2Fexample.com&key=abc';

let sandbox;
let embed;
let updateBlockAttributes;

function setStoredOptions(context, queryKeys) {
  jQuery(sandbox).data({ method: 'setIframelyEmbedOptions', context, data: { query: queryKeys } });
}

beforeEach(() => {
  global.jQuery = jQuery;
  document.body.innerHTML = '<div id="block-abc-123"><iframe class="sandbox"></iframe></div>';
  sandbox = document.querySelector('iframe.sandbox');
  sandbox.contentWindow.document.body.innerHTML = '<iframe class="embed"></iframe>';
  embed = sandbox.contentWindow.document.querySelector('iframe.embed');
  embed.src = BASE;

  updateBlockAttributes = vi.fn();
  select.mockReturnValue({ getBlockSelectionStart: () => 'abc-123' });
  dispatch.mockReturnValue({ updateBlockAttributes });
});

afterEach(() => {
  vi.clearAllMocks();
  document.body.innerHTML = '';
  delete global.jQuery;
});

describe('optionsChanged', () => {
  it('reloads the embed with the new options appended to its URL', () => {
    setStoredOptions(BASE, []);

    optionsChanged('abc-123', null, { theme: 'dark', _start: '70' });

    expect(embed.src).toBe(`${BASE}&theme=dark&_start=70`);
  });

  it('stores the options in the "iquery" attribute of the selected block', () => {
    setStoredOptions(BASE, []);

    optionsChanged('abc-123', null, { theme: 'dark' });

    expect(dispatch).toHaveBeenCalledWith('core/block-editor');
    expect(updateBlockAttributes).toHaveBeenCalledWith('abc-123', { iquery: { theme: 'dark' } });
  });

  it('replaces the options that were in the URL before', () => {
    setStoredOptions(`${BASE}&theme=light&_start=10`, ['theme', '_start']);

    optionsChanged('abc-123', null, { theme: 'dark' });

    expect(embed.src).toBe(`${BASE}&theme=dark`);
  });

  it('removes all previous options when the form is reset', () => {
    setStoredOptions(`${BASE}&theme=light`, ['theme']);

    optionsChanged('abc-123', null, {});

    expect(embed.src).toBe(BASE);
    expect(updateBlockAttributes).toHaveBeenCalledWith('abc-123', { iquery: {} });
  });

  it('removes previous options whose name contains a dash', () => {
    setStoredOptions(`${BASE}&max-width=300`, ['max-width']);

    optionsChanged('abc-123', null, {});

    expect(embed.src).toBe(BASE);
  });

  it('starts the query string when the embed URL has none', () => {
    setStoredOptions('https://cdn.iframe.ly/abc', []);

    optionsChanged('abc-123', null, { theme: 'dark' });

    expect(embed.src).toBe('https://cdn.iframe.ly/abc?theme=dark');
  });

  it('does nothing until the embed has reported its options', () => {
    optionsChanged('abc-123', null, { theme: 'dark' });

    expect(embed.src).toBe(BASE);
    expect(updateBlockAttributes).not.toHaveBeenCalled();
  });

  /*
   * Known issue, kept as a record of current behaviour (see the review report):
   * option values are concatenated into the URL without encoding, so a value
   * containing "&" or "#" changes the meaning of the embed URL.
   */
  it('known issue: does not encode option values', () => {
    setStoredOptions(BASE, []);

    optionsChanged('abc-123', null, { title: 'a&b=c' });

    expect(embed.src).toBe(`${BASE}&title=a&b=c`);
  });
});
