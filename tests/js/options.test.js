import { createElement, renderToString } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

// Registers the editor.BlockEdit filter as a side effect.
import '../../src/js/options';

jest.mock('@wordpress/data', () => ({ select: jest.fn() }));
jest.mock('@wordpress/block-editor', () => ({
  InspectorControls: ({ children }) => <aside data-testid="inspector">{children}</aside>,
}));
jest.mock('@wordpress/components', () => ({
  PanelBody: ({ title, children }) => <section data-title={title}>{children}</section>,
}));
jest.mock('../../src/js/components/IframelyOptions', () => ({
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

  it('renders other blocks unchanged', () => {
    expect(render({ name: 'core/paragraph', isSelected: true })).toBe('<div data-block="core/paragraph"></div>');
  });
});
