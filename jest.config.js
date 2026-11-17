const defaultConfig = require('@wordpress/scripts/config/jest-unit.config');

module.exports = {
  ...defaultConfig,
  roots: ['<rootDir>/src', '<rootDir>/tests/js'],
  collectCoverageFrom: ['src/**/*.js'],
  coverageDirectory: 'artifacts/coverage-js',
};
