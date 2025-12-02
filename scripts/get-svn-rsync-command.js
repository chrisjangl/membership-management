#!/usr/bin/env node

/**
 * Generate rsync command for SVN deployment
 *
 * Usage: node scripts/get-svn-rsync-command.js
 */

const config = require('./release-config.js');
const path = require('path');

// Get the wp-repo path (assuming we're running from plugin root)
const wpRepoPath = process.cwd();
const svnTrunkPath = path.join(config.svn.localPath, 'trunk');

// Build exclude flags for rsync
const excludeFlags = config.svnExcludePatterns
    .map(pattern => `--exclude='${pattern}'`)
    .join(' ');

// Build the full rsync command
const rsyncCommand = `rsync -av --delete ${excludeFlags} "${wpRepoPath}/" "${svnTrunkPath}/"`;

console.log('\n' + '='.repeat(70));
console.log('RSYNC COMMAND FOR SVN DEPLOYMENT');
console.log('='.repeat(70));
console.log('\nThis command will:');
console.log('  1. Copy files from wp-repo branch to SVN trunk');
console.log('  2. Delete files in trunk that are not in wp-repo');
console.log('  3. Exclude files that should not go to WordPress.org');
console.log('\nSource (wp-repo):');
console.log(`  ${wpRepoPath}`);
console.log('\nDestination (SVN trunk):');
console.log(`  ${svnTrunkPath}`);
console.log('\nExcluding:');
config.svnExcludePatterns.forEach(pattern => {
    console.log(`  - ${pattern}`);
});
console.log('\n' + '='.repeat(70));
console.log('COMMAND:');
console.log('='.repeat(70));
console.log(rsyncCommand);
console.log('\n' + '='.repeat(70));
console.log('\nTo run this command:');
console.log('  1. Make sure you are on the wp-repo branch');
console.log('  2. Copy the command above');
console.log('  3. Run it in your terminal');
console.log('='.repeat(70) + '\n');
