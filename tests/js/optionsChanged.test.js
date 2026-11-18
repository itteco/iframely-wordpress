import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { dispatch, select } from '@wordpress/data';
import { optionsChanged } from '../../src/js/events/optionsChanged';

vi.mock('@wordpress/data', () => ({ select: vi.fn(), dispatch: vi.fn() }));

const URL = 'https://www.youtube.com/watch?v=abc';

let blocks;
let updateBlockAttributes;

function optionsIn(url) {
  const encoded = new window.URL(url).searchParams.get('iframely');
  return encoded === null ? null : JSON.parse(window.atob(encoded));
}

function newUrl() {
  expect(updateBlockAttributes).toHaveBeenCalledTimes(1);
  const [id, attributes] = updateBlockAttributes.mock.calls[0];
  expect(id).toBe('abc-123');
  expect(Object.keys(attributes)).toEqual(['url']);
  return attributes.url;
}

beforeEach(() => {
  blocks = { 'abc-123': { name: 'core/embed', attributes: { url: URL } } };
  updateBlockAttributes = vi.fn();
  select.mockReturnValue({
    getBlock: (id) => blocks[id] || null,
    getBlockSelectionStart: () => 'abc-123',
  });
  dispatch.mockReturnValue({ updateBlockAttributes });
});

afterEach(() => {
  vi.clearAllMocks();
  document.body.innerHTML = '';
});

describe('optionsChanged', () => {
  it('puts the options into the URL of the block', () => {
    optionsChanged('abc-123', null, { theme: 'dark', _start: '70' });

    expect(dispatch).toHaveBeenCalledWith('core/block-editor');
    expect(newUrl().startsWith(`${URL}&iframely=`)).toBe(true);
    expect(optionsIn(newUrl())).toEqual({ theme: 'dark', _start: '70' });
  });

  it('replaces the options that were in the URL before', () => {
    optionsChanged('abc-123', null, { theme: 'light', _start: '10' });
    blocks['abc-123'].attributes.url = newUrl();
    updateBlockAttributes.mockClear();

    optionsChanged('abc-123', null, { theme: 'dark' });

    expect(optionsIn(newUrl())).toEqual({ theme: 'dark' });
    expect(newUrl().match(/iframely=/g)).toHaveLength(1);
  });

  it('takes the options out of the URL when the form is back at its defaults', () => {
    blocks['abc-123'].attributes.url = `${URL}&iframely=eyJ0aGVtZSI6ImRhcmsifQ%3D%3D`;

    optionsChanged('abc-123', null, {});

    expect(newUrl()).toBe(URL);
  });

  it('keeps values with special characters intact', () => {
    optionsChanged('abc-123', null, { title: 'a&b=c #1', _lang: 'Ελληνικά' });

    const url = newUrl();
    expect(url.match(/[&#]/g)).toEqual(['&']);
    const bytes = Uint8Array.from(window.atob(new window.URL(url).searchParams.get('iframely')), (c) =>
      c.charCodeAt(0),
    );
    expect(JSON.parse(new TextDecoder().decode(bytes))).toEqual({ title: 'a&b=c #1', _lang: 'Ελληνικά' });
  });

  it('does not need the preview: it works while one loads and after one has failed', () => {
    // No preview frame of the block is on the page.
    expect(document.querySelector('iframe')).toBeNull();

    optionsChanged('abc-123', null, { card: 'small' });

    expect(optionsIn(newUrl())).toEqual({ card: 'small' });
  });

  it('leaves the block alone when the options are the ones it has', () => {
    blocks['abc-123'].attributes.url = `${URL}&iframely=eyJ0aGVtZSI6ImRhcmsifQ%3D%3D`;

    optionsChanged('abc-123', null, { theme: 'dark' });
    blocks['abc-123'].attributes.url = URL;
    optionsChanged('abc-123', null, {});

    expect(updateBlockAttributes).not.toHaveBeenCalled();
  });

  it('changes the block the form was built for, not the one that is selected', () => {
    blocks.other = { name: 'core/embed', attributes: { url: 'https://vimeo.com/1' } };
    select.mockReturnValue({ getBlock: (id) => blocks[id] || null, getBlockSelectionStart: () => 'other' });

    optionsChanged('abc-123', null, { theme: 'dark' });

    expect(optionsIn(newUrl())).toEqual({ theme: 'dark' });
  });

  it('falls back to the selected block when the form names no block', () => {
    optionsChanged(undefined, null, { theme: 'dark' });

    expect(optionsIn(newUrl())).toEqual({ theme: 'dark' });
  });

  it.each([
    ['there is no such block', () => delete blocks['abc-123']],
    ['the block is not an embed', () => (blocks['abc-123'].name = 'core/paragraph')],
    ['the block has no URL yet', () => (blocks['abc-123'].attributes.url = undefined)],
  ])('does nothing when %s', (name, arrange) => {
    arrange();

    optionsChanged('abc-123', null, { theme: 'dark' });

    expect(updateBlockAttributes).not.toHaveBeenCalled();
  });

  /*
   * Summary cards have one option, as the real card for
   * https://en.wikipedia.org/wiki/Tiramisu reports it.
   */
  describe('for a card', () => {
    const CARD_URL = 'https://en.wikipedia.org/wiki/Tiramisu';
    // tests/php/CardTest.php renders this exact URL.
    const SMALL_CARD_URL = `${CARD_URL}?iframely=eyJjYXJkIjoic21hbGwifQ%3D%3D`;

    it('makes the card small', () => {
      blocks['abc-123'].attributes.url = CARD_URL;

      optionsChanged('abc-123', null, { card: 'small' });

      expect(newUrl()).toBe(SMALL_CARD_URL);
    });

    it('makes a small card regular again', () => {
      blocks['abc-123'].attributes.url = SMALL_CARD_URL;

      optionsChanged('abc-123', null, {});

      expect(newUrl()).toBe(CARD_URL);
    });
  });
});
