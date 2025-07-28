#!/usr/bin/env node

/**
 * Prepare WordPress Repository Branch Script
 * 
 * Sets up the wp-repo branch with clean distribution code for WordPress.org submission.
 * This is Phase 3, Item 2 of the release automation.
 * 
 * Usage: npm run prepare:wp-repo
 */

const { execSync } = require('child_process');
const fs = require('fs-extra');
const path = require('path');

class WpRepoPreparator {
    constructor() {
        this.wpRepoBranch = 'wp-repo';
        this.distDir = './dist';
        this.originalBranch = null;
    }
    
    async prepare() {
        console.log('🚀 Preparing WordPress.org repository branch...');
        
        try {
            // Step 1: Get current branch for restoration later
            this.originalBranch = this.getCurrentBranch();
            console.log(`📍 Current branch: ${this.originalBranch}`);
            
            // Step 2: Build clean distribution first
            await this.buildDistribution();
            
            // Step 3: Ensure we have a clean wp-repo branch
            await this.setupWpRepoBranch();
            
            // Step 4: Clear wp-repo branch and copy distribution
            await this.syncDistributionToWpRepo();
            
            // Step 5: Create wp-repo specific files
            await this.createWpRepoFiles();
            
            console.log('✅ wp-repo branch prepared successfully');
            console.log('📋 Next steps:');
            console.log('   1. Review changes: git status');
            console.log('   2. Commit changes: git add . && git commit -m "Release vX.X.X for WordPress.org"');
            console.log('   3. Push to remote: git push origin wp-repo');
            console.log(`   4. Return to work: git checkout ${this.originalBranch}`);
            
        } catch (error) {
            console.error('❌ wp-repo preparation failed:', error.message);
            
            // Try to restore original branch
            if (this.originalBranch) {
                try {
                    execSync(`git checkout ${this.originalBranch}`, { stdio: 'inherit' });
                    console.log(`🔄 Restored to ${this.originalBranch} branch`);
                } catch (restoreError) {
                    console.error('⚠️  Could not restore original branch:', restoreError.message);
                }
            }
            
            process.exit(1);
        }
    }
    
    getCurrentBranch() {
        try {
            const result = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' });
            return result.trim();
        } catch (error) {
            throw new Error('Could not determine current git branch');
        }
    }
    
    async buildDistribution() {
        console.log('📦 Building distribution...');
        
        try {
            // Import and run the distribution builder
            const DistBuilder = require('./build-dist.js');
            const builder = new DistBuilder();
            await builder.build();
        } catch (error) {
            throw new Error(`Distribution build failed: ${error.message}`);
        }
    }
    
    async setupWpRepoBranch() {
        console.log(`🌿 Setting up ${this.wpRepoBranch} branch...`);
        
        try {
            // Check if wp-repo branch exists
            const branchExists = this.branchExists(this.wpRepoBranch);
            
            if (branchExists) {
                console.log(`📂 Switching to existing ${this.wpRepoBranch} branch`);
                execSync(`git checkout ${this.wpRepoBranch}`, { stdio: 'inherit' });
            } else {
                console.log(`📝 Creating new ${this.wpRepoBranch} branch`);
                execSync(`git checkout -b ${this.wpRepoBranch}`, { stdio: 'inherit' });
            }
            
        } catch (error) {
            throw new Error(`Could not setup ${this.wpRepoBranch} branch: ${error.message}`);
        }
    }
    
    branchExists(branchName) {
        try {
            execSync(`git show-ref --verify --quiet refs/heads/${branchName}`, { stdio: 'ignore' });
            return true;
        } catch (error) {
            return false;
        }
    }
    
    async syncDistributionToWpRepo() {
        console.log('🔄 Syncing distribution to wp-repo branch...');
        
        // Remove all files except .git and dist
        const items = await fs.readdir('./');
        const toRemove = items.filter(item => 
            item !== '.git' && 
            item !== 'dist' && 
            item !== 'node_modules' // Keep if exists to prevent npm issues
        );
        
        for (const item of toRemove) {
            console.log(`🗑️  Removing ${item}`);
            await fs.remove(item);
        }
        
        // Copy distribution contents to root
        console.log('📋 Copying distribution to repository root...');
        const distContents = await fs.readdir(this.distDir);
        
        for (const item of distContents) {
            const srcPath = path.join(this.distDir, item);
            const destPath = `./${item}`;
            
            await fs.copy(srcPath, destPath, { overwrite: true });
            console.log(`✅ Copied ${item}`);
        }
        
        // Remove the dist directory
        await fs.remove(this.distDir);
    }
    
    async createWpRepoFiles() {
        console.log('📄 Creating wp-repo specific files...');
        
        // Create wp-repo specific .gitignore
        const gitignoreContent = [
            '# WordPress.org repository - keep clean',
            '',
            '# System files',
            '.DS_Store',
            'Thumbs.db',
            '*.log',
            '',
            '# Temporary files',
            '*.tmp',
            '*.temp',
            '',
            '# IDE files',
            '.vscode/',
            '.idea/',
            '',
            '# Never include these in wp-repo',
            'node_modules/',
            'package*.json',
            'src/',
            'scripts/',
            'tests/',
            '*.config.js'
        ].join('\n');
        
        await fs.writeFile('.gitignore', gitignoreContent);
        console.log('✅ Created wp-repo .gitignore');
        
        // Create or update README for wp-repo branch
        const readmeBranchContent = [
            '# WordPress.org Repository Branch',
            '',
            'This branch contains the clean, production-ready version of the plugin for WordPress.org submission.',
            '',
            '## Important Notes',
            '',
            '- This branch is automatically generated - do not edit directly',
            '- All development should happen on the main/develop branches',
            '- This branch contains only files needed for WordPress.org',
            '- No development files, build tools, or test files are included',
            '',
            '## Deployment Process',
            '',
            '1. Development happens on main/develop branches',
            '2. Release preparation creates clean distribution',
            '3. This branch is automatically updated with distribution files',
            '4. WordPress.org SVN is synced from this branch',
            '',
            '## Files Included',
            '',
            '- Plugin source code (includes/)',
            '- Compiled assets (css/, js/)',
            '- WordPress readme.txt',
            '- Main plugin file (membership.php)',
            '- Translation files (languages/)',
            '',
            '---',
            '*This README is specific to the wp-repo branch and will not appear in the distributed plugin.*'
        ].join('\n');
        
        await fs.writeFile('README-WP-REPO.md', readmeBranchContent);
        console.log('✅ Created wp-repo README');
    }
}

// Run the preparation if called directly
if (require.main === module) {
    const preparator = new WpRepoPreparator();
    preparator.prepare();
}

module.exports = WpRepoPreparator;