import { afterEach, describe, expect, it, vi } from 'vitest';
vi.mock('@wordpress/data', () => ({ select: vi.fn(), dispatch: vi.fn() }));
vi.mock('../../src/js/events/iframeMessage', () => ({ iframeMessage: vi.fn() }));
vi.mock('../../src/js/events/optionsChanged', () => ({ optionsChanged: vi.fn() }));

// The module wires its listeners while it loads, so every test loads a fresh copy.
async function loadEvents() {
  vi.resetModules();
  await import('../../src/js/events');
}

afterEach(() => {
  delete global.iframely;
  vi.restoreAllMocks();
});

describe('event wiring', () => {
  it('listens for option changes reported by the Iframely options form', async () => {
    global.iframely = { on: vi.fn() };

    await loadEvents();
    const { optionsChanged } = await import('../../src/js/events/optionsChanged');

    expect(global.iframely.on).toHaveBeenCalledWith('options-changed', optionsChanged);
  });

  it('listens for messages posted by embed iframes', async () => {
    const addEventListener = vi.spyOn(window, 'addEventListener');
    global.iframely = { on: vi.fn() };

    await loadEvents();
    const { iframeMessage } = await import('../../src/js/events/iframeMessage');

    expect(addEventListener).toHaveBeenCalledWith('message', iframeMessage);
  });

  /*
   * The script from if-cdn.com defines the global "iframely". It can be blocked,
   * or fail to load.
   */
  it.each([
    ['is missing', undefined],
    ['is not what is expected', { version: 1 }],
  ])('still listens for messages when the Iframely script %s', async (name, value) => {
    const addEventListener = vi.spyOn(window, 'addEventListener');
    if (value) {
      global.iframely = value;
    }

    await expect(loadEvents()).resolves.toBeUndefined();
    const { iframeMessage } = await import('../../src/js/events/iframeMessage');

    expect(addEventListener).toHaveBeenCalledWith('message', iframeMessage);
  });
});
