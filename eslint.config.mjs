// ESLint flat config: catches real bugs (undefined names, duplicates,
// unreachable code, loose equality) in the plugin's jQuery admin scripts and
// leaves style alone.
import globals from 'globals';

export default [
	{
		ignores: ['node_modules/**', 'vendor/**', 'build/**', '**/*.min.js'],
	},
	{
		files: ['assets/js/**/*.js'],
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'script',
			globals: {
				...globals.browser,
				...globals.jquery,
				wp: 'readonly',
				ajaxurl: 'readonly',
				// Localized by the admin enqueue.
				tmq: 'readonly',
			},
		},
		rules: {
			'no-undef': 'error',
			'no-dupe-keys': 'error',
			'no-duplicate-case': 'error',
			'no-unreachable': 'error',
			'no-redeclare': 'error',
			eqeqeq: ['error', 'smart'],
			'no-unused-vars': ['warn', { args: 'none' }],
		},
	},
];
