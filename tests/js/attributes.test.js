import { describe, expect, it, vi } from 'vitest';
import { createElement, renderToString } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

// Registers the block filters as a side effect.
import '../../src/js/attributes';

vi.mock('@wordpress/data', () => ({ select: vi.fn() }));

const embedBlock = { name: 'core/embed', category: 'embed' };

function savedEmbed(url) {
  return createElement(
    'figure',
    { className: 'wp-block-embed' },
    createElement('div', { className: 'wp-block-embed__wrapper' }, `\n${url}\n`),
  );
}

describe('blocks.registerBlockType filter', () => {
  it('adds the "iquery" attribute to embed blocks', () => {
    const settings = applyFilters(
      'blocks.registerBlockType',
      { category: 'embed', attributes: { url: { type: 'string' } } },
      'core/embed',
    );

    expect(settings.attributes).toEqual({
      url: { type: 'string' },
      iquery: { type: 'string', default: '' },
    });
  });

  it('matches the category regardless of case', () => {
    const settings = applyFilters('blocks.registerBlockType', { category: 'Embed', attributes: {} }, 'acme/embed');

    expect(settings.attributes.iquery).toBeDefined();
  });

  it('leaves other blocks alone', () => {
    const original = { category: 'text', attributes: { content: { type: 'string' } } };

    const settings = applyFilters('blocks.registerBlockType', original, 'core/paragraph');

    expect(settings.attributes).toEqual({ content: { type: 'string' } });
  });

  it('leaves embed blocks without attributes alone', () => {
    const settings = applyFilters('blocks.registerBlockType', { category: 'embed' }, 'acme/embed');

    expect(settings.attributes).toBeUndefined();
  });

  it('does not overwrite an existing "iquery" attribute', () => {
    const iquery = { type: 'object', default: {} };

    const settings = applyFilters('blocks.registerBlockType', { category: 'embed', attributes: { iquery } }, 'x/y');

    expect(settings.attributes.iquery).toBe(iquery);
  });
});

describe('blocks.getSaveElement filter', () => {
  it('saves the embed URL with the Iframely options appended', () => {
    const attributes = { url: 'https://example.com/video', iquery: { theme: 'dark' } };

    const element = applyFilters('blocks.getSaveElement', savedEmbed(attributes.url), embedBlock, attributes);

    expect(renderToString(element)).toBe(
      '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">\n' +
        'https://example.com/video?iframely=eyJ0aGVtZSI6ImRhcmsifQ%3D%3D\n' +
        '</div></figure>',
    );
  });

  it('updates the url attribute as well, so the saved block stays valid', () => {
    const attributes = { url: 'https://example.com/video', iquery: { theme: 'dark' } };

    applyFilters('blocks.getSaveElement', savedEmbed(attributes.url), embedBlock, attributes);

    expect(attributes.url).toBe('https://example.com/video?iframely=eyJ0aGVtZSI6ImRhcmsifQ%3D%3D');
  });

  it('handles URLs with several query parameters', () => {
    const attributes = { url: 'https://example.com/video?a=1&b=2', iquery: { theme: 'dark' } };

    const element = applyFilters('blocks.getSaveElement', savedEmbed(attributes.url), embedBlock, attributes);

    expect(renderToString(element)).toContain(
      '\nhttps://example.com/video?a=1&b=2&iframely=eyJ0aGVtZSI6ImRhcmsifQ%3D%3D\n',
    );
  });

  it('replaces options saved earlier instead of stacking them', () => {
    const attributes = {
      url: 'https://example.com/video?iframely=eyJ0aGVtZSI6ImRhcmsifQ%3D%3D',
      iquery: { theme: 'light' },
    };

    const html = renderToString(
      applyFilters('blocks.getSaveElement', savedEmbed(attributes.url), embedBlock, attributes),
    );

    expect(html.match(/iframely=/g)).toHaveLength(1);
    expect(html).toContain('?iframely=eyJ0aGVtZSI6ImxpZ2h0In0%3D');
  });

  it.each([
    ['embed blocks without options', embedBlock, { url: 'https://example.com/video', iquery: '' }],
    ['embed blocks without a URL', embedBlock, { url: '', iquery: { theme: 'dark' } }],
    [
      'other blocks',
      { name: 'core/paragraph', category: 'text' },
      { url: 'https://example.com/video', iquery: { theme: 'dark' } },
    ],
  ])('returns the original element for %s', (name, blockType, attributes) => {
    const element = savedEmbed('https://example.com/video');

    expect(applyFilters('blocks.getSaveElement', element, blockType, { ...attributes })).toBe(element);
  });
});
