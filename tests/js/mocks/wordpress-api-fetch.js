/**
 * Test-only stand-in for @wordpress/api-fetch (a WordPress script external,
 * not an npm dependency of this project). Tests replace it with jest.mock().
 */
export default jest.fn( () => Promise.resolve( {} ) );
