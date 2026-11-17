/**
 * Reads .distignore: the list of paths that are not part of the plugin package.
 *
 * The file is shared with other tools (wp dist-archive, the wordpress.org deploy
 * action), so only the forms they all understand are supported: one path per line,
 * "#" comments, a leading "/" for a path from the project root, and "*" for any
 * run of characters within one name. A path that names a directory covers
 * everything in it.
 */

const escape = (text) => text.replace(/[.+^${}()|[\]\\]/g, '\\$&');

/**
 * @param {string} content The content of .distignore.
 * @return {RegExp[]} One expression per entry, for paths relative to the project root with "/" separators.
 */
function parseDistignore(content) {
  return content
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'))
    .map((line) => {
      const anchored = line.startsWith('/');
      const pattern = line.replace(/^\//, '').replace(/\/$/, '').split('*').map(escape).join('[^/]*');
      // Without a leading slash an entry matches at any depth, like in .gitignore.
      return new RegExp(`^${anchored ? '' : '(?:.*/)?'}${pattern}(?:/.*)?$`);
    });
}

/**
 * @param {string[]} paths    Paths relative to the project root.
 * @param {RegExp[]} excluded The result of parseDistignore().
 * @return {string[]} The paths that are part of the package.
 */
function packaged(paths, excluded) {
  return paths.filter((file) => !excluded.some((pattern) => pattern.test(file)));
}

module.exports = { parseDistignore, packaged };
