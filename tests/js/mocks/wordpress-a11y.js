/**
 * Test-only stand-in for @wordpress/a11y (a WordPress script external, not an
 * npm dependency of this project): speak() is a jest.fn so tests can assert
 * the live-region announcements.
 */
export const speak = jest.fn();
