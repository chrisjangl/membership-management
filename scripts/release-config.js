/**
 * Centralized Release Configuration
 *
 * This file defines what should be included/excluded at different stages
 * of the release and deployment process.
 */

module.exports = {
    // Plugin metadata
    pluginSlug: 'membership-management',
    mainPluginFile: 'membership.php',

    // Files to exclude when building distribution (from source → dist)
    buildExcludePatterns: [
        // Development files
        '**/node_modules/**',
        '**/src/**',
        '**/*.scss',
        '**/*.sass',
        '**/package*.json',
        '**/webpack.config.js',
        '**/gulpfile.js',
        '**/tsconfig.json',

        // Build tools
        '**/.babelrc',
        '**/.eslintrc*',
        '**/.prettierrc*',

        // Version control
        '**/.git/**',
        '**/.gitignore',
        '**/.gitattributes',

        // IDE files
        '**/.vscode/**',
        '**/.idea/**',
        '**/Thumbs.db',
        '**/.DS_Store',

        // Test files
        '**/tests/**',
        '**/test/**',
        '**/*.test.js',
        '**/*.spec.js',
        '**/phpunit.xml*',

        // Temporary/migration files
        '**/migrate-*.php',
        '**/test-*.php',
        '**/CLAUDE.md',
        '**/ROADMAP.md',

        // Scripts directory
        '**/scripts/**',
    ],

    // Files to exclude when creating GitHub release zip (from wp-repo)
    zipExcludePatterns: [
        '*.git*',           // Git files
        'node_modules/*',   // Dependencies (shouldn't be in wp-repo anyway)
        '.DS_Store',        // macOS files
        'Thumbs.db',        // Windows files
        '*.log',            // Log files
        '.vscode/*',        // IDE files
        '.idea/*',          // IDE files
        'package*.json',    // npm files (shouldn't be in wp-repo anyway)
        'src/*',            // Source files (shouldn't be in wp-repo anyway)
        'scripts/*',        // Build scripts (shouldn't be in wp-repo anyway)
        'tests/*',          // Tests (shouldn't be in wp-repo anyway)
        '*.config.js',      // Config files (shouldn't be in wp-repo anyway)
    ],

    // Files to exclude when deploying to WordPress.org SVN (from wp-repo)
    svnExcludePatterns: [
        '.git',                 // Git directory
        '.gitignore',           // Git ignore file
        'README-WP-REPO.md',    // Our internal README
        'node_modules',         // Dependencies (shouldn't be in wp-repo anyway)
        '*.zip',                // Zip files
        '.DS_Store',            // macOS files
        'Thumbs.db',            // Windows files
        '.vscode',              // IDE files
        '.idea',                // IDE files
        'package.json',         // npm files (shouldn't be in wp-repo anyway)
        'package-lock.json',    // npm files (shouldn't be in wp-repo anyway)
        'src',                  // Source files (shouldn't be in wp-repo anyway)
        'scripts',              // Build scripts (shouldn't be in wp-repo anyway)
        'tests',                // Tests (shouldn't be in wp-repo anyway)
        '*.config.js',          // Config files (shouldn't be in wp-repo anyway)
    ],

    // Files that MUST be present in WordPress.org distribution
    requiredFiles: [
        'membership.php',       // Main plugin file
        'readme.txt',           // WordPress readme
        'includes',             // PHP source code directory
    ],

    // SVN configuration
    svn: {
        // Local path to SVN checkout
        localPath: '/Users/chrisjangl/Developer/wordpress-plugins/membership-management',
        // WordPress.org SVN URL
        remoteUrl: 'https://plugins.svn.wordpress.org/membership-management',
    },

    // Git configuration
    git: {
        remoteName: 'github',
        stableBranch: 'stable',
        wpRepoBranch: 'wp-repo',
    }
};
