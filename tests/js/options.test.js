import { describe, expect, it, vi } from 'vitest';
import { createElement, createRoot, flushSync, renderToString, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

// Registers the editor.BlockEdit filter as a side effect.
import '../../src/js/options';

vi.mock('@wordpress/data', () => ({ select: vi.fn() }));
vi.mock('@wordpress/block-editor', () => ({
  InspectorControls: ({ children }) => <aside data-testid="inspector">{children}</aside>,
}));
vi.mock('@wordpress/components', () => ({
  PanelBody: ({ title, children }) => <section data-title={title}>{children}</section>,
}));
vi.mock('../../src/js/components/IframelyOptions', () => ({
  IframelyOptions: () => <div id="iframely-options" />,
}));

const BlockEdit = ({ name }) => <div data-block={name} />;

function render(props) {
  const Wrapped = applyFilters('editor.BlockEdit', BlockEdit);
  return renderToString(createElement(Wrapped, props));
}

describe('editor.BlockEdit filter', () => {
  it.each([['core/embed'], ['core-embed/youtube']])(
    'adds the Iframely options panel to a selected %s block',
    (name) => {
      const html = render({ name, isSelected: true });

      expect(html).toBe(
        `<div data-block="${name}"></div>` +
          '<aside data-testid="inspector"><section data-title="Iframely options">' +
          '<div id="iframely-options"></div></section></aside>',
      );
    },
  );

  it('renders embed blocks that are not selected unchanged', () => {
    expect(render({ name: 'core/embed', isSelected: false })).toBe('<div data-block="core/embed"></div>');
  });

  /*
   * The embed block of WordPress keeps a copy of its URL from the moment it was
   * first shown, and uses it for "Try again" and for the URL field.
   */
  describe('when the URL of an embed block changes', () => {
    const Remembering = ({ attributes }) => {
      const [first] = useState(attributes.url);
      return <div data-first-url={first} />;
    };

    function firstUrlAfter(name, urls) {
      const Wrapped = applyFilters('editor.BlockEdit', Remembering);
      const container = document.createElement('div');
      const root = createRoot(container);
      urls.forEach((url) => {
        flushSync(() => root.render(createElement(Wrapped, { name, isSelected: false, attributes: { url } })));
      });
      const first = container.firstChild.dataset.firstUrl;
      root.unmount();
      return first;
    }

    it('starts the block again when its options change', () => {
      const urls = [
        'https://example.com/a',
        'https://example.com/a?iframely=b25l',
        'https://example.com/a?iframely=dHdv',
      ];

      expect(firstUrlAfter('core/embed', urls)).toBe('https://example.com/a?iframely=dHdv');
      expect(firstUrlAfter('core/embed', [...urls, 'https://example.com/a'])).toBe('https://example.com/a');
    });

    it('lets the block run on when something else in the URL changes', () => {
      const urls = ['https://example.com/a?iframely=b25l', 'https://example.com/b?iframely=b25l'];

      expect(firstUrlAfter('core/embed', urls)).toBe('https://example.com/a?iframely=b25l');
      expect(firstUrlAfter('core/embed', ['https://example.com/a', 'https://example.com/b'])).toBe(
        'https://example.com/a',
      );
    });

    it('lets other blocks run on', () => {
      const urls = ['https://example.com/a', 'https://example.com/a?iframely=b25l'];

      expect(firstUrlAfter('core/image', urls)).toBe('https://example.com/a');
    });
  });

  it('renders other blocks unchanged', () => {
    expect(render({ name: 'core/paragraph', isSelected: true })).toBe('<div data-block="core/paragraph"></div>');
  });
});
