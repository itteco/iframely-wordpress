jest.mock('@wordpress/data', () => ({ select: jest.fn(), dispatch: jest.fn() }));
jest.mock('../../src/js/events/iframeMessage', () => ({ iframeMessage: jest.fn() }));
jest.mock('../../src/js/events/optionsChanged', () => ({ optionsChanged: jest.fn() }));

function loadEvents() {
  jest.isolateModules(() => {
    require('../../src/js/events');
  });
}

afterEach(() => {
  delete global.iframely;
  jest.restoreAllMocks();
});

describe('event wiring', () => {
  it('listens for option changes reported by the Iframely options form', () => {
    const { optionsChanged } = require('../../src/js/events/optionsChanged');
    global.iframely = { on: jest.fn() };

    loadEvents();

    expect(global.iframely.on).toHaveBeenCalledWith('options-changed', optionsChanged);
  });

  it('listens for messages posted by embed iframes', () => {
    const { iframeMessage } = require('../../src/js/events/iframeMessage');
    const addEventListener = jest.spyOn(window, 'addEventListener');
    global.iframely = { on: jest.fn() };

    loadEvents();

    expect(addEventListener).toHaveBeenCalledWith('message', iframeMessage);
  });

  /*
   * Known issue, kept as a record of current behaviour (see the review report):
   * the script from if-cdn.com defines the global "iframely". When that script is
   * blocked or fails to load, the bare reference throws and the rest of the
   * plugin's editor script never runs.
   */
  it('known issue: throws when the Iframely CDN script did not load', () => {
    const addEventListener = jest.spyOn(window, 'addEventListener');

    expect(loadEvents).toThrow(ReferenceError);
    expect(addEventListener).not.toHaveBeenCalledWith('message', expect.anything());
  });
});
