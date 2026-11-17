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
   * Known issue, kept as a record of current behaviour (see the review report):
   * the script from if-cdn.com defines the global "iframely". When that script is
   * blocked or fails to load, the bare reference throws and the rest of the
   * plugin's editor script never runs.
   */
  it('known issue: throws when the Iframely CDN script did not load', async () => {
    const addEventListener = vi.spyOn(window, 'addEventListener');

    await expect(loadEvents()).rejects.toThrow(ReferenceError);
    expect(addEventListener).not.toHaveBeenCalledWith('message', expect.anything());
  });
});
