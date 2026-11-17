import { addIframelyString } from '../../src/js/utils';

/**
 * Proves the Jest harness works: source files are transpiled and importable.
 */
describe('addIframelyString', () => {
  it('appends the options as a base64-encoded "iframely" parameter', () => {
    const url = addIframelyString('https://example.com/video', { theme: 'dark' });
    const encoded = new URL(url).searchParams.get('iframely');

    expect(JSON.parse(window.atob(encoded))).toEqual({ theme: 'dark' });
  });

  it('leaves the URL untouched when there are no options', () => {
    expect(addIframelyString('https://example.com/video', {})).toBe('https://example.com/video');
  });
});
