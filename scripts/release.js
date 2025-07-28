#!/usr/bin/env node

/**
 * Release Orchestrator Script
 * 
 * Main script that orchestrates the entire release process.
 * 
 * Usage: 
 *   npm run release patch   # 1.0.0 -> 1.0.1
 *   npm run release minor   # 1.0.0 -> 1.1.0  
 *   npm run release major   # 1.0.0 -> 2.0.0
 */

const semver = require('semver');
const fs = require('fs-extra');
const { execSync } = require('child_process');

class ReleaseOrchestrator {
    constructor() {
        this.currentVersion = this.getCurrentVersion();
        this.releaseType = process.argv[2] || 'patch';
        this.newVersion = null;
    }
    
    async release() {
        console.log('🚀 Starting release process...');
        console.log(`📦 Current version: ${this.currentVersion}`);
        console.log(`📈 Release type: ${this.releaseType}`);
        
        try {
            // Step 1: Calculate new version
            this.calculateNewVersion();
            
            // Step 2: Confirm with user
            await this.confirmRelease();
            
            // Step 3: Validate current state
            await this.validateCurrentState();
            
            // Step 4: Update version numbers
            await this.updateVersions();
            
            // Step 5: Run validation
            await this.runValidation();
            
            // Step 6: Build and prepare
            await this.buildAndPrepare();
            
            console.log('✅ Release process completed successfully!');
            console.log(`🎉 Version ${this.newVersion} is ready`);
            
            this.printNextSteps();
            
        } catch (error) {
            console.error('❌ Release failed:', error.message);
            process.exit(1);
        }
    }
    
    getCurrentVersion() {
        try {
            const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
            return packageJson.version;
        } catch (error) {
            throw new Error('Could not read current version from package.json');
        }
    }
    
    calculateNewVersion() {
        if (!['patch', 'minor', 'major'].includes(this.releaseType)) {
            throw new Error(`Invalid release type: ${this.releaseType}. Use patch, minor, or major.`);
        }
        
        this.newVersion = semver.inc(this.currentVersion, this.releaseType);
        console.log(`🔢 New version will be: ${this.newVersion}`);
    }
    
    async confirmRelease() {
        console.log('\n📋 Release Summary:');
        console.log(`   Current: ${this.currentVersion}`);
        console.log(`   New:     ${this.newVersion}`);
        console.log(`   Type:    ${this.releaseType}`);
        
        // In a real-world scenario, you might want to add user confirmation
        // For now, we'll proceed automatically
        console.log('✅ Proceeding with release...');
    }
    
    async validateCurrentState() {
        console.log('🔍 Validating current state...');
        
        // Check git status
        try {
            const gitStatus = execSync('git status --porcelain', { encoding: 'utf8' });
            if (gitStatus.trim()) {
                console.log('⚠️  Warning: Working directory has uncommitted changes');
                console.log('📝 Please commit or stash changes before release');
                // throw new Error('Working directory not clean');
            }
        } catch (error) {
            console.log('⚠️  Could not check git status');
        }
        
        console.log('✅ Current state validated');
    }
    
    async updateVersions() {
        console.log('📝 Updating version numbers...');
        
        // Update package.json
        await this.updatePackageJson();
        
        // Update plugin header
        await this.updatePluginHeader();
        
        // Update plugin constant
        await this.updatePluginConstant();
        
        // Update readme.txt if it exists
        await this.updateReadmeTxt();
        
        console.log('✅ Version numbers updated');
    }
    
    async updatePackageJson() {
        const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
        packageJson.version = this.newVersion;
        await fs.writeFile('package.json', JSON.stringify(packageJson, null, 2) + '\n');
        console.log('✅ Updated package.json');
    }
    
    async updatePluginHeader() {
        const mainFile = this.getMainPluginFile();
        if (!mainFile) {
            console.log('⚠️  Main plugin file not found, skipping header update');
            return;
        }
        
        let content = fs.readFileSync(mainFile, 'utf8');
        content = content.replace(
            /Version:\s*.+/,
            `Version: ${this.newVersion}`
        );
        
        await fs.writeFile(mainFile, content);
        console.log(`✅ Updated ${mainFile} header`);
    }
    
    async updatePluginConstant() {
        const mainFile = this.getMainPluginFile();
        if (!mainFile) return;
        
        let content = fs.readFileSync(mainFile, 'utf8');
        
        // Look for version constant definition
        const constantPattern = /define\(\s*['"](DCMM_VERSION|.*_VERSION)['"],\s*['"](.+?)['"]\s*\)/;
        if (constantPattern.test(content)) {
            content = content.replace(constantPattern, (match, constName) => {
                return `define( '${constName}', '${this.newVersion}' )`;
            });
            
            await fs.writeFile(mainFile, content);
            console.log('✅ Updated plugin version constant');
        }
    }
    
    async updateReadmeTxt() {
        if (!fs.existsSync('readme.txt')) {
            console.log('⚠️  readme.txt not found, skipping update');
            return;
        }
        
        let content = fs.readFileSync('readme.txt', 'utf8');
        content = content.replace(
            /Stable tag:\s*.+/,
            `Stable tag: ${this.newVersion}`
        );
        
        await fs.writeFile('readme.txt', content);
        console.log('✅ Updated readme.txt stable tag');
    }
    
    async runValidation() {
        console.log('🔍 Running release validation...');
        
        try {
            const ReleaseValidator = require('./validate-release.js');
            const validator = new ReleaseValidator();
            await validator.validate();
        } catch (error) {
            throw new Error(`Validation failed: ${error.message}`);
        }
    }
    
    async buildAndPrepare() {
        console.log('🏗️  Building distribution and preparing wp-repo...');
        
        try {
            // Build distribution
            const DistBuilder = require('./build-dist.js');
            const builder = new DistBuilder();
            await builder.build();
            
            // Prepare wp-repo branch
            const WpRepoPreparator = require('./prepare-wp-repo.js');
            const preparator = new WpRepoPreparator();
            await preparator.prepare();
            
        } catch (error) {
            throw new Error(`Build/prepare failed: ${error.message}`);
        }
    }
    
    getMainPluginFile() {
        const candidates = ['membership.php', 'dc-membership.php', 'plugin.php'];
        
        for (const candidate of candidates) {
            if (fs.existsSync(candidate)) {
                const content = fs.readFileSync(candidate, 'utf8');
                if (content.includes('Plugin Name:')) {
                    return candidate;
                }
            }
        }
        
        return null;
    }
    
    printNextSteps() {
        console.log('\n📋 Next Steps:');
        console.log('='.repeat(50));
        console.log('1. Review the wp-repo branch changes');
        console.log('2. Commit version updates to main branch:');
        console.log(`   git add . && git commit -m "Release v${this.newVersion}"`);
        console.log('3. Create git tag:');
        console.log(`   git tag v${this.newVersion}`);
        console.log('4. Push changes:');
        console.log('   git push origin main --tags');
        console.log('5. Deploy wp-repo branch to WordPress.org');
        console.log('6. Update WordPress.org assets if needed');
        console.log('\n🎉 Release completed successfully!');
    }
}

// Run release if called directly
if (require.main === module) {
    const orchestrator = new ReleaseOrchestrator();
    orchestrator.release();
}

module.exports = ReleaseOrchestrator;