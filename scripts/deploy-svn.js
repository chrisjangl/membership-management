#!/usr/bin/env node

/**
 * SVN Deployment Script
 *
 * Deploys the wp-repo branch to WordPress.org SVN repository.
 *
 * IMPORTANT: This script requires manual confirmation before committing!
 *
 * Usage: npm run deploy:svn
 */

const { execSync } = require('child_process');
const fs = require('fs-extra');
const path = require('path');
const config = require('./release-config.js');

class SVNDeployer {
    constructor() {
        this.wpRepoPath = process.cwd();
        this.svnPath = config.svn.localPath;
        this.svnTrunkPath = path.join(this.svnPath, 'trunk');
        this.version = null;
    }

    async deploy() {
        console.log('🚀 Starting WordPress.org SVN Deployment...\n');

        try {
            // Step 1: Verify we're on wp-repo branch
            await this.verifyWpRepoBranch();

            // Step 2: Get version number
            await this.getVersion();

            // Step 3: Verify SVN checkout exists
            await this.verifySVNCheckout();

            // Step 4: Update SVN to latest
            await this.updateSVN();

            // Step 5: Sync files from wp-repo to SVN trunk
            await this.syncToSVNTrunk();

            // Step 6: Review changes
            await this.reviewChanges();

            // Step 7: Confirm deployment
            await this.confirmDeployment();

            // Step 8: Commit trunk
            await this.commitTrunk();

            // Step 9: Create tag
            await this.createTag();

            console.log('\n✅ Deployment completed successfully!');
            console.log(`🎉 Version ${this.version} is now live on WordPress.org`);
            console.log(`🔗 https://wordpress.org/plugins/${config.pluginSlug}/\n`);

        } catch (error) {
            console.error('\n❌ Deployment failed:', error.message);
            process.exit(1);
        }
    }

    async verifyWpRepoBranch() {
        console.log('📋 Step 1: Verifying branch...');

        try {
            const currentBranch = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();

            if (currentBranch !== config.git.wpRepoBranch) {
                throw new Error(`Not on ${config.git.wpRepoBranch} branch. Current branch: ${currentBranch}\nPlease run: git checkout ${config.git.wpRepoBranch}`);
            }

            console.log(`✅ On ${config.git.wpRepoBranch} branch\n`);
        } catch (error) {
            throw new Error(`Could not verify git branch: ${error.message}`);
        }
    }

    async getVersion() {
        console.log('📋 Step 2: Getting version number...');

        try {
            const mainFile = path.join(this.wpRepoPath, config.mainPluginFile);
            const content = fs.readFileSync(mainFile, 'utf8');

            const versionMatch = content.match(/Version:\s*(.+)/);
            if (!versionMatch) {
                throw new Error('Could not find version in plugin file');
            }

            this.version = versionMatch[1].trim();
            console.log(`✅ Version: ${this.version}\n`);

        } catch (error) {
            throw new Error(`Could not read version: ${error.message}`);
        }
    }

    async verifySVNCheckout() {
        console.log('📋 Step 3: Verifying SVN checkout...');

        if (!fs.existsSync(this.svnPath)) {
            throw new Error(`SVN checkout not found at: ${this.svnPath}\nPlease checkout the SVN repository first.`);
        }

        if (!fs.existsSync(path.join(this.svnPath, '.svn'))) {
            throw new Error(`Not a valid SVN working copy: ${this.svnPath}`);
        }

        console.log(`✅ SVN checkout found at: ${this.svnPath}\n`);
    }

    async updateSVN() {
        console.log('📋 Step 4: Updating SVN to latest revision...');

        try {
            execSync('svn update', {
                cwd: this.svnPath,
                stdio: 'inherit'
            });
            console.log('✅ SVN updated\n');
        } catch (error) {
            throw new Error(`SVN update failed: ${error.message}`);
        }
    }

    async syncToSVNTrunk() {
        console.log('📋 Step 5: Syncing files to SVN trunk...');

        // Build exclude flags for rsync
        const excludeFlags = config.svnExcludePatterns
            .map(pattern => `--exclude='${pattern}'`)
            .join(' ');

        const rsyncCommand = `rsync -av --delete ${excludeFlags} "${this.wpRepoPath}/" "${this.svnTrunkPath}/"`;

        console.log('Running rsync...');
        console.log(`Source: ${this.wpRepoPath}`);
        console.log(`Destination: ${this.svnTrunkPath}`);

        try {
            execSync(rsyncCommand, { stdio: 'inherit' });
            console.log('✅ Files synced\n');
        } catch (error) {
            throw new Error(`Rsync failed: ${error.message}`);
        }
    }

    async reviewChanges() {
        console.log('📋 Step 6: Reviewing SVN changes...\n');
        console.log('='.repeat(70));

        try {
            // Add new files
            console.log('Adding new files...');
            execSync('svn add --force * --auto-props --parents --depth infinity -q', {
                cwd: this.svnTrunkPath,
                stdio: 'inherit'
            });

            // Show status
            console.log('\nSVN Status:');
            console.log('='.repeat(70));
            const status = execSync('svn status', {
                cwd: this.svnTrunkPath,
                encoding: 'utf8'
            });

            if (status.trim()) {
                console.log(status);
            } else {
                console.log('No changes detected.');
            }

            console.log('='.repeat(70) + '\n');

        } catch (error) {
            // Non-fatal error for showing status
            console.log('⚠️  Could not fully review changes\n');
        }
    }

    async confirmDeployment() {
        console.log('📋 Step 7: Confirm deployment');
        console.log('='.repeat(70));
        console.log('⚠️  You are about to deploy to WordPress.org!');
        console.log(`Version: ${this.version}`);
        console.log(`Plugin: ${config.pluginSlug}`);
        console.log('='.repeat(70));
        console.log('\nThis will:');
        console.log('  1. Commit changes to SVN trunk');
        console.log('  2. Create SVN tag for version ' + this.version);
        console.log('  3. Make the plugin live on WordPress.org');
        console.log('\n⚠️  This action cannot be easily undone!\n');
        console.log('To proceed, you must run the following commands manually:\n');
        console.log('1. Review changes above');
        console.log('2. If everything looks good, continue with the deployment\n');

        // We'll pause here and require manual confirmation via stdin
        console.log('Press Ctrl+C to cancel, or press Enter to continue...');

        // Wait for user input
        await this.waitForEnter();
    }

    waitForEnter() {
        return new Promise((resolve) => {
            process.stdin.once('data', () => {
                resolve();
            });
        });
    }

    async commitTrunk() {
        console.log('\n📋 Step 8: Committing to SVN trunk...');
        console.log('='.repeat(70));

        const commitMessage = `Update to version ${this.version}`;

        console.log(`Commit message: "${commitMessage}"`);
        console.log('This will prompt for your WordPress.org credentials...\n');

        try {
            execSync(`svn commit -m "${commitMessage}"`, {
                cwd: this.svnTrunkPath,
                stdio: 'inherit'
            });

            console.log('✅ Trunk committed\n');
        } catch (error) {
            throw new Error(`SVN commit failed: ${error.message}`);
        }
    }

    async createTag() {
        console.log('📋 Step 9: Creating SVN tag...');

        const tagUrl = `${config.svn.remoteUrl}/tags/${this.version}`;
        const trunkUrl = `${config.svn.remoteUrl}/trunk`;
        const tagMessage = `Tagging version ${this.version}`;

        console.log(`Creating tag: ${this.version}`);
        console.log(`From: ${trunkUrl}`);
        console.log(`To: ${tagUrl}\n`);

        try {
            execSync(`svn copy ${trunkUrl} ${tagUrl} -m "${tagMessage}"`, {
                cwd: this.svnPath,
                stdio: 'inherit'
            });

            console.log('✅ Tag created\n');
        } catch (error) {
            throw new Error(`SVN tag creation failed: ${error.message}`);
        }
    }
}

// Run deployment if called directly
if (require.main === module) {
    const deployer = new SVNDeployer();
    deployer.deploy();
}

module.exports = SVNDeployer;
