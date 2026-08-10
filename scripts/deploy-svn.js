#!/usr/bin/env node

/**
 * SVN Deployment Script
 *
 * Deploys the wp-repo branch to WordPress.org SVN repository.
 * Run from any branch — exports wp-repo cleanly via git archive.
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
        this.tmpPath = '/tmp/dc-membership-wp-repo-export';
        this.svnPath = config.svn.localPath;
        this.svnTrunkPath = this.svnPath ? path.join(this.svnPath, 'trunk') : null;
        this.svnAssetsPath = this.svnPath ? path.join(this.svnPath, 'assets') : null;
        this.repoAssetsPath = path.join(process.cwd(), '.wordpress-org', 'assets');
        this.version = null;
    }

    async deploy() {
        console.log('🚀 Starting WordPress.org SVN Deployment...\n');

        try {
            // Step 1: Verify SVN local path is configured
            await this.verifySVNPath();

            // Step 2: Export wp-repo branch to temp directory
            await this.exportWpRepo();

            // Step 3: Get version number
            await this.getVersion();

            // Step 4: Verify SVN checkout exists
            await this.verifySVNCheckout();

            // Step 5: Update SVN to latest
            await this.updateSVN();

            // Step 6: Sync files from temp to SVN trunk
            await this.syncToSVNTrunk();

            // Step 7: Review changes
            await this.reviewChanges();

            // Step 8: Confirm deployment
            await this.confirmDeployment();

            // Step 9: Commit trunk
            await this.commitTrunk();

            // Step 10: Create tag
            await this.createTag();

            // Step 11: Clean up temp directory
            this.cleanup();

            console.log('\n✅ Deployment completed successfully!');
            console.log(`🎉 Version ${this.version} is now live on WordPress.org`);
            console.log(`🔗 https://wordpress.org/plugins/${config.pluginSlug}/\n`);

        } catch (error) {
            this.cleanup();
            console.error('\n❌ Deployment failed:', error.message);
            process.exit(1);
        }
    }

    async verifySVNPath() {
        console.log('📋 Step 1: Verifying SVN configuration...');

        if (!this.svnPath) {
            throw new Error(
                'SVN local path is not configured.\n' +
                'Copy scripts/release-local.example.js to scripts/release-local.js and set svnLocalPath.'
            );
        }

        console.log(`✅ SVN local path: ${this.svnPath}\n`);
    }

    async exportWpRepo() {
        console.log('📋 Step 2: Exporting wp-repo branch...');

        if (fs.existsSync(this.tmpPath)) {
            fs.removeSync(this.tmpPath);
        }
        fs.mkdirpSync(this.tmpPath);

        execSync(`git archive wp-repo | tar -x -C "${this.tmpPath}"`, { stdio: 'inherit' });

        console.log(`✅ wp-repo exported to ${this.tmpPath}\n`);
    }

    async getVersion() {
        console.log('📋 Step 3: Getting version number...');

        const mainFile = path.join(this.tmpPath, config.mainPluginFile);
        const content = fs.readFileSync(mainFile, 'utf8');
        const versionMatch = content.match(/Version:\s*(.+)/);

        if (!versionMatch) {
            throw new Error('Could not find version in plugin file');
        }

        this.version = versionMatch[1].trim();
        console.log(`✅ Version: ${this.version}\n`);
    }

    async verifySVNCheckout() {
        console.log('📋 Step 4: Verifying SVN checkout...');

        if (!fs.existsSync(this.svnPath)) {
            throw new Error(
                `SVN checkout not found at: ${this.svnPath}\n` +
                `Please checkout the SVN repository:\n` +
                `  svn checkout ${config.svn.remoteUrl} ${this.svnPath}`
            );
        }

        if (!fs.existsSync(path.join(this.svnPath, '.svn'))) {
            throw new Error(`Not a valid SVN working copy: ${this.svnPath}`);
        }

        console.log(`✅ SVN checkout found at: ${this.svnPath}\n`);
    }

    async updateSVN() {
        console.log('📋 Step 5: Updating SVN to latest revision...');

        execSync('svn update', { cwd: this.svnPath, stdio: 'inherit' });
        console.log('✅ SVN updated\n');
    }

    async syncToSVNTrunk() {
        console.log('📋 Step 6: Syncing files to SVN trunk...');

        const excludeFlags = config.svnExcludePatterns
            .map(pattern => `--exclude='${pattern}'`)
            .join(' ');

        const rsyncCommand = `rsync -av --delete ${excludeFlags} "${this.tmpPath}/" "${this.svnTrunkPath}/"`;

        console.log(`Source: ${this.tmpPath}`);
        console.log(`Destination: ${this.svnTrunkPath}`);

        execSync(rsyncCommand, { stdio: 'inherit' });
        console.log('✅ Files synced\n');
    }

    async reviewChanges() {
        console.log('📋 Step 7: Reviewing SVN changes...\n');
        console.log('='.repeat(70));

        try {
            execSync('svn add --force * --auto-props --parents --depth infinity -q', {
                cwd: this.svnTrunkPath,
                stdio: 'inherit'
            });

            console.log('\nSVN Status:');
            console.log('='.repeat(70));
            const status = execSync('svn status', { cwd: this.svnTrunkPath, encoding: 'utf8' });
            console.log(status.trim() || 'No changes detected.');
            console.log('='.repeat(70) + '\n');
        } catch (error) {
            console.log('⚠️  Could not fully review changes\n');
        }
    }

    async confirmDeployment() {
        console.log('📋 Step 8: Confirm deployment');
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
        console.log('Press Ctrl+C to cancel, or press Enter to continue...');

        await this.waitForEnter();
    }

    waitForEnter() {
        return new Promise((resolve) => {
            process.stdin.once('data', () => {
                process.stdin.pause();
                resolve();
            });
        });
    }

    async commitTrunk() {
        console.log('\n📋 Step 9: Committing to SVN trunk...');
        console.log('='.repeat(70));

        const commitMessage = `Update to version ${this.version}`;
        console.log(`Commit message: "${commitMessage}"`);
        console.log('This will prompt for your WordPress.org credentials...\n');

        execSync(`svn commit -m "${commitMessage}"`, { cwd: this.svnTrunkPath, stdio: 'inherit' });
        console.log('✅ Trunk committed\n');
    }

    async createTag() {
        console.log('📋 Step 10: Creating SVN tag...');

        const tagUrl = `${config.svn.remoteUrl}/tags/${this.version}`;
        const trunkUrl = `${config.svn.remoteUrl}/trunk`;

        console.log(`Creating tag: ${this.version}`);

        execSync(`svn copy ${trunkUrl} ${tagUrl} -m "Tagging version ${this.version}"`, {
            cwd: this.svnPath,
            stdio: 'inherit'
        });

        console.log('✅ Tag created\n');
    }

    cleanup() {
        if (fs.existsSync(this.tmpPath)) {
            fs.removeSync(this.tmpPath);
        }
    }

    // --- WordPress.org repo display assets (icon, banner) -----------------
    //
    // These are unrelated to the plugin's code: they live in the SVN repo's
    // root /assets folder (a sibling of trunk/tags), are read from
    // .wordpress-org/assets/ in the *current working tree* (not the wp-repo
    // branch export), and are versioned independently of the plugin itself —
    // no tag is created for an assets-only update.

    async deployAssets() {
        console.log('🚀 Starting WordPress.org repo assets deployment...\n');

        try {
            await this.verifySVNPath();
            await this.verifyAssetsSource();
            await this.verifySVNCheckout();
            await this.updateSVN();
            await this.syncAssetsToSVN();
            await this.reviewAssetsChanges();
            await this.confirmAssetsDeployment();
            await this.commitAssets();

            console.log('\n✅ Repo assets deployment completed successfully!');
            console.log(`🔗 https://wordpress.org/plugins/${config.pluginSlug}/\n`);
        } catch (error) {
            console.error('\n❌ Assets deployment failed:', error.message);
            process.exit(1);
        }
    }

    async verifyAssetsSource() {
        console.log('📋 Verifying .wordpress-org/assets exists...');

        if (!fs.existsSync(this.repoAssetsPath)) {
            throw new Error(
                `No assets found at: ${this.repoAssetsPath}\n` +
                `Expected icon-*.png / banner-*.png in .wordpress-org/assets/`
            );
        }

        console.log(`✅ Assets source: ${this.repoAssetsPath}\n`);
    }

    async syncAssetsToSVN() {
        console.log('📋 Syncing repo assets to SVN...');

        fs.mkdirpSync(this.svnAssetsPath);

        // Deliberately no --delete: the SVN assets folder may already hold
        // files (e.g. screenshot-*.png) that were never tracked in git.
        const rsyncCommand = `rsync -av "${this.repoAssetsPath}/" "${this.svnAssetsPath}/"`;

        console.log(`Source: ${this.repoAssetsPath}`);
        console.log(`Destination: ${this.svnAssetsPath}`);

        execSync(rsyncCommand, { stdio: 'inherit' });
        console.log('✅ Assets synced\n');
    }

    async reviewAssetsChanges() {
        console.log('📋 Reviewing SVN asset changes...\n');
        console.log('='.repeat(70));

        try {
            execSync('svn add --force * --auto-props --parents --depth infinity -q', {
                cwd: this.svnAssetsPath,
                stdio: 'inherit'
            });

            console.log('\nSVN Status (assets/):');
            console.log('='.repeat(70));
            const status = execSync('svn status', { cwd: this.svnAssetsPath, encoding: 'utf8' });
            console.log(status.trim() || 'No changes detected.');
            console.log('='.repeat(70) + '\n');
        } catch (error) {
            console.log('⚠️  Could not fully review changes\n');
        }
    }

    async confirmAssetsDeployment() {
        console.log('📋 Confirm assets deployment');
        console.log('='.repeat(70));
        console.log('⚠️  You are about to update the WordPress.org repo assets!');
        console.log(`Plugin: ${config.pluginSlug}`);
        console.log('='.repeat(70));
        console.log('\nThis will commit icon/banner changes directly to SVN.');
        console.log('No version bump or tag is involved.\n');
        console.log('Press Ctrl+C to cancel, or press Enter to continue...');

        await this.waitForEnter();
    }

    async commitAssets() {
        console.log('\n📋 Committing repo assets...');
        console.log('='.repeat(70));

        const commitMessage = 'Update WordPress.org repo assets (icon, banner)';
        console.log(`Commit message: "${commitMessage}"`);
        console.log('This will prompt for your WordPress.org credentials...\n');

        execSync(`svn commit -m "${commitMessage}"`, { cwd: this.svnAssetsPath, stdio: 'inherit' });
        console.log('✅ Assets committed\n');
    }
}

if (require.main === module) {
    const deployer = new SVNDeployer();

    if (process.argv.includes('--assets')) {
        deployer.deployAssets();
    } else {
        deployer.deploy();
    }
}

module.exports = SVNDeployer;
