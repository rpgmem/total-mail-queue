import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		coverage: {
			provider: 'v8',
			// Every script, so one no test loads counts as uncovered rather
			// than vanishing from the figure.
			include: [ 'assets/js/**/*.js' ],
			// The `.min.js` build outputs are the same code, minified.
			exclude: [ 'assets/js/**/*.min.js' ],
			reporter: [ 'text', 'text-summary' ],
			// The highest of 90%, the measured figure minus 5 points, and the
			// current value; never lowered (CLAUDE.md). Raised 93 -> 94 in #129,
			// measured 98.55% when set.
			thresholds: { lines: 94 },
		},
	},
} );
