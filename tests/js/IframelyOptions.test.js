import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import jQuery from 'jquery';
import { select } from '@wordpress/data';
import { createElement, createRoot, flushSync } from '@wordpress/element';
import { IframelyOptions, updateForm } from '../../src/js/components/IframelyOptions';

vi.mock('@wordpress/data', () => ({ select: vi.fn() }));

let canCreateUsers;
let container;
let root;

function mount() {
  container = document.createElement('div');
  document.body.appendChild(container);
  root = createRoot(container);
  flushSync(() => {
    root.render(createElement(IframelyOptions));
  });
  // The component fills the panel shortly after mounting.
  vi.advanceTimersByTime(20);
}

beforeEach(() => {
  vi.useFakeTimers();
  global.jQuery = jQuery;
  global.iframely = { buildOptionsForm: vi.fn() };
  canCreateUsers = false;
  select.mockImplementation((store) =>
    store === 'core' ? { canUser: () => canCreateUsers } : { getBlockSelectionStart: () => 'abc-123' },
  );
  document.body.innerHTML = '<div id="block-abc-123"><iframe class="sandbox"></iframe></div>';
});

afterEach(() => {
  if (root) {
    flushSync(() => root.unmount());
    root = null;
  }
  vi.useRealTimers();
  vi.clearAllMocks();
  document.body.innerHTML = '';
  delete global.jQuery;
  delete global.iframely;
});

describe('updateForm', () => {
  it('builds the options form of the selected block from the options stored on its iframe', () => {
    const options = { query: ['theme'], theme: { value: 'dark' } };
    jQuery(document.querySelector('iframe.sandbox')).data({ context: 'https://cdn.iframe.ly/x', data: options });
    const panel = document.createElement('div');
    panel.id = 'iframely-options';
    document.body.appendChild(panel);

    updateForm();

    expect(global.iframely.buildOptionsForm).toHaveBeenCalledWith('abc-123', panel, options);
  });

  it.each([
    ['is missing', undefined],
    ['has no form builder', {}],
  ])('does nothing when the Iframely script %s', (name, value) => {
    jQuery(document.querySelector('iframe.sandbox')).data({ data: { theme: { value: 'dark' } } });
    document.body.insertAdjacentHTML('beforeend', '<div id="iframely-options"></div>');
    delete global.iframely;
    if (value) {
      global.iframely = value;
    }

    expect(() => updateForm()).not.toThrow();
    expect(document.getElementById('iframely-options').innerHTML).toBe('');
  });

  it('does nothing when the selected block has no preview iframe', () => {
    document.body.innerHTML = '<div id="iframely-options"></div>';

    updateForm();

    expect(global.iframely.buildOptionsForm).not.toHaveBeenCalled();
  });
});

describe('IframelyOptions', () => {
  it('renders the container the Iframely options form is built into', () => {
    mount();

    const panel = container.querySelector('#iframely-options');
    expect(panel).not.toBeNull();
    expect(panel.className).toBe('iframely-options');
  });

  it('asks Iframely for the form of the selected block when it appears', () => {
    const options = { query: [] };
    jQuery(document.querySelector('iframe.sandbox')).data({ context: 'https://cdn.iframe.ly/x', data: options });

    mount();

    expect(global.iframely.buildOptionsForm).toHaveBeenCalledWith(
      'abc-123',
      container.querySelector('#iframely-options'),
      options,
    );
  });

  it('shows a short hint while there are no options', () => {
    mount();

    expect(container.querySelector('#iframely-options').textContent).toBe(
      'Iframely will show edit options for selected URL here, whenever available.',
    );
  });

  it('shows administrators a hint with links to plans and documentation', () => {
    canCreateUsers = true;

    mount();

    const links = [...container.querySelectorAll('#iframely-options a')].map((a) => a.getAttribute('href'));
    expect(links).toEqual([
      'https://iframely.com/plans?utm_source=wordpress-plugin',
      'https://iframely.com/docs/options?utm_source=wordpress-plugin',
    ]);
  });

  it('keeps the form that Iframely has built', () => {
    global.iframely.buildOptionsForm.mockImplementation((id, panel) => {
      panel.innerHTML = '<form class="built"></form>';
    });
    jQuery(document.querySelector('iframe.sandbox')).data({ context: 'https://cdn.iframe.ly/x', data: {} });

    mount();

    expect(container.querySelector('#iframely-options').innerHTML).toBe('<form class="built"></form>');
  });
});
