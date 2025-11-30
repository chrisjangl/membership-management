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
        console.log('[RELEASE] Starting release process...');
        console.log(`[PACKAGE] Current version: ${this.currentVersion}`);
        console.log(`[RELEASE] Release type: ${this.releaseType}`);
        
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
            
            // Step 6: Commit version changes before building
            await this.commitVersionChanges();

            // Step 7: Build and prepare
            await this.buildAndPrepare();

            // Step 8: Create GitHub release
            await this.createGitHubRelease();

            console.log('[SUCCESS] Release process completed successfully!');
            console.log(`[SUCCESS] Version ${this.newVersion} is ready`);

            this.printNextSteps();
            
        } catch (error) {
            console.error('[ERROR] Release failed:', error.message);
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
        console.log(`[PACKAGE] Release: ${this.currentVersion} → ${this.newVersion} (${this.releaseType})`);
    }
    
    async confirmRelease() {
        console.log('\n' + '='.repeat(50));
        console.log('[SUMMARY] RELEASE SUMMARY');
        console.log('='.repeat(50));
        console.log(`Current Version: ${this.currentVersion}`);
        console.log(`New Version:     ${this.newVersion}`);
        console.log(`Release Type:    ${this.releaseType}`);
        console.log('='.repeat(50));
        console.log('[OK] Proceeding with release...\n');
    }
    
    async validateCurrentState() {
        console.log('[VALIDATE] VALIDATING CURRENT STATE');
        console.log('-'.repeat(30));

        // Check git status
        try {
            const gitStatus = execSync('git status --porcelain', { encoding: 'utf8' });
            if (gitStatus.trim()) {
                console.log('[WARNING] Warning: Working directory has uncommitted changes');
                console.log('[INFO] These will be committed as part of the release process');
            } else {
                console.log('[OK] Working directory is clean');
            }
        } catch (error) {
            console.log('[WARNING] Could not check git status');
        }

        console.log('[OK] Validation complete\n');
    }
    
    async updateVersions() {
        console.log('[UPDATE] UPDATING VERSION NUMBERS');
        console.log('-'.repeat(30));

        // Update package.json
        await this.updatePackageJson();

        // Update plugin header
        await this.updatePluginHeader();

        // Update plugin constant
        await this.updatePluginConstant();

        // Update readme.txt if it exists
        await this.updateReadmeTxt();

        console.log('[OK] All version numbers updated\n');
    }
    
    async updatePackageJson() {
        const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
        packageJson.version = this.newVersion;
        await fs.writeFile('package.json', JSON.stringify(packageJson, null, 2) + '\n');
        console.log('[OK] Updated package.json');
    }
    
    async updatePluginHeader() {
        const mainFile = this.getMainPluginFile();
        if (!mainFile) {
            console.log('[WARNING] Main plugin file not found, skipping header update');
            return;
        }

        let content = fs.readFileSync(mainFile, 'utf8');

        // Update version
        content = content.replace(
            /Version:\s*.+/,
            `Version: ${this.newVersion}`
        );

        // Ensure all required headers are present
        content = this.ensurePluginHeaders(content);

        await fs.writeFile(mainFile, content);
        console.log(`[OK] Updated ${mainFile} header`);
    }

    ensurePluginHeaders(content) {
        const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));

        // Define required headers with defaults
        const requiredHeaders = {
            'Plugin URI': packageJson.homepage || 'https://github.com/chrisjangl/membership-management',
            'Text Domain': 'dcmm-membership',
            'Requires at least': '5.0',
            'Tested up to': '6.5',
            'Requires PHP': '7.4'
        };

        // Extract existing header block
        const headerMatch = content.match(/(\/\*\*[\s\S]*?\*\/)/);
        if (!headerMatch) return content;

        let headerBlock = headerMatch[1];

        // Add missing headers
        for (const [headerName, defaultValue] of Object.entries(requiredHeaders)) {
            const pattern = new RegExp(`\\*\\s*${headerName}:\\s*.+`, 'i');
            if (!pattern.test(headerBlock)) {
                // Find where to insert (before closing */)
                headerBlock = headerBlock.replace(
                    /(\s*\*\/)/,
                    ` * ${headerName}: ${defaultValue}\n$1`
                );
                console.log(`[OK] Added missing header: ${headerName}`);
            }
        }

        return content.replace(headerMatch[1], headerBlock);
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
            console.log('[OK] Updated plugin version constant');
        }
    }
    
    async updateReadmeTxt() {
        if (!fs.existsSync('readme.txt')) {
            console.log('[CREATE] Creating readme.txt...');
            await this.createReadmeTxt();
            return;
        }

        console.log('[UPDATE] Updating readme.txt...');
        let content = fs.readFileSync('readme.txt', 'utf8');

        // Update stable tag
        content = content.replace(
            /Stable tag:\s*.+/,
            `Stable tag: ${this.newVersion}`
        );

        // Ensure "Requires at least" is present
        if (!/Requires at least:/i.test(content)) {
            content = content.replace(
                /(Tags:.*?\n)/,
                '$1Requires at least: 5.0\n'
            );
        }

        // Ensure required sections exist
        content = await this.ensureReadmeSections(content);

        await fs.writeFile('readme.txt', content);
        console.log('[OK] Updated readme.txt');
    }

    async createReadmeTxt() {
        const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));

        const template = `=== Membership Management ===
Contributors: digitally-cultured
Tags: membership management, CRM
Requires at least: 5.0
Tested up to: 6.5
Stable tag: ${this.newVersion}
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

${packageJson.description || 'Empower your organization with our Membership Management Plugin for WordPress.'}

== Description ==

Unlock the full potential of your organization with our Membership Management Plugin. Designed for professional organizations and non-profits, this feature-rich tool allows you to easily manage and organize your membership list. Keep track of member status, contact information, and more, all within the familiar WordPress environment.

== Installation ==

1. Upload the plugin files to the \`/wp-content/plugins/membership-management\` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Members menu in your WordPress admin to start managing your membership.
4. Configure settings under Members > Settings to customize the plugin for your organization.

== FAQ ==

= How does the plugin track membership status? =
The plugin provides a user-friendly interface within the WordPress dashboard to mark members as active or inactive based on your organization's criteria.

= Can members update their own information? =
The plugin includes member dashboard functionality where members can view and update their information.

= Does the plugin integrate with PayPal? =
Yes, the plugin includes PayPal integration for membership dues collection and renewal payments.

== Changelog ==

${await this.generateChangelog()}
`;

        await fs.writeFile('readme.txt', template);
        console.log('[OK] Created readme.txt');
    }

    async ensureReadmeSections(content) {
        const requiredSections = ['Installation', 'Changelog'];

        for (const section of requiredSections) {
            const sectionPattern = new RegExp(`== ${section} ==`, 'i');
            if (!sectionPattern.test(content)) {
                content += await this.generateSection(section);
                console.log(`[OK] Added missing section: ${section}`);
            }
        }

        // Update changelog if it exists
        if (/== Changelog ==/i.test(content)) {
            const newChangelog = await this.generateChangelog();
            content = content.replace(
                /(== Changelog ==[\s\S]*?)(?=== |$)/i,
                `== Changelog ==\n\n${newChangelog}\n\n`
            );
            console.log('[OK] Updated changelog');
        }

        return content;
    }

    async generateSection(sectionName) {
        switch (sectionName.toLowerCase()) {
            case 'installation':
                return `\n== Installation ==\n\n1. Upload the plugin files to the \`/wp-content/plugins/membership-management\` directory, or install the plugin through the WordPress plugins screen directly.\n2. Activate the plugin through the 'Plugins' screen in WordPress.\n3. Use the Members menu in your WordPress admin to start managing your membership.\n4. Configure settings under Members > Settings to customize the plugin for your organization.\n\n`;
            case 'changelog':
                return `\n== Changelog ==\n\n${await this.generateChangelog()}\n\n`;
            default:
                return '';
        }
    }

    async generateChangelog() {
        try {
            // Get commits since last tag
            const lastTag = this.getLastTag();
            const gitRange = lastTag ? `${lastTag}..HEAD` : 'HEAD';

            const commits = execSync(`git log ${gitRange} --pretty=format:"%h|%s|%an|%ad" --date=short`, { encoding: 'utf8' })
                .split('\n')
                .filter(line => line.trim())
                .map(line => {
                    const [hash, subject, author, date] = line.split('|');
                    return { hash, subject, author, date };
                });

            if (commits.length === 0) {
                return `= ${this.newVersion} =\n* Minor updates and improvements`;
            }

            // Group commits by type
            const features = commits.filter(c => c.subject.match(/^feat/i));
            const fixes = commits.filter(c => c.subject.match(/^fix|bugfix/i));
            const others = commits.filter(c => !c.subject.match(/^(feat|fix|bugfix)/i));

            let changelog = `= ${this.newVersion} =\n`;

            if (features.length > 0) {
                features.forEach(commit => {
                    const cleanSubject = commit.subject.replace(/^feat[^:]*:\s*/i, '');
                    changelog += `* ${cleanSubject}\n`;
                });
            }

            if (fixes.length > 0) {
                fixes.forEach(commit => {
                    const cleanSubject = commit.subject.replace(/^(fix|bugfix)[^:]*:\s*/i, '');
                    changelog += `* Fix: ${cleanSubject}\n`;
                });
            }

            if (others.length > 0) {
                others.forEach(commit => {
                    let cleanSubject = commit.subject.replace(/^[^:]*:\s*/, '');
                    cleanSubject = cleanSubject.charAt(0).toUpperCase() + cleanSubject.slice(1);
                    changelog += `* ${cleanSubject}\n`;
                });
            }

            return changelog;

        } catch (error) {
            console.log('[WARNING] Could not generate changelog from git history');
            return `= ${this.newVersion} =\n* Updates and improvements`;
        }
    }

    getLastTag() {
        try {
            const result = execSync('git describe --tags --abbrev=0', { encoding: 'utf8' });
            return result.trim();
        } catch (error) {
            return null;
        }
    }
    
    async commitVersionChanges() {
        console.log('[COMMIT] COMMITTING RELEASE');
        console.log('-'.repeat(30));

        try {
            // Add only the version-updated files, not dist/
            console.log('[GIT] Adding version files to git...');
            execSync('git add package.json membership.php readme.txt', { stdio: 'inherit' });

            console.log(`[GIT] Committing release v${this.newVersion}...`);
            execSync(`git commit -m "Release v${this.newVersion}"`, { stdio: 'inherit' });

            // Create git tag
            console.log(`[TAG] Creating git tag v${this.newVersion}...`);
            execSync(`git tag v${this.newVersion}`, { stdio: 'inherit' });

            console.log('[OK] Release committed and tagged\n');
        } catch (error) {
            // If commit fails (e.g., no changes), that's ok
            console.log('[INFO] No version changes to commit or already committed\n');
        }
    }

    async runValidation() {
        console.log('[VALIDATE] RUNNING RELEASE VALIDATION');
        console.log('-'.repeat(30));

        try {
            const ReleaseValidator = require('./validate-release.js');
            const validator = new ReleaseValidator();
            await validator.validate();
            console.log('');
        } catch (error) {
            throw new Error(`Validation failed: ${error.message}`);
        }
    }
    
    async buildAndPrepare() {
        console.log('[BUILD] BUILDING & PREPARING WP-REPO');
        console.log('-'.repeat(30));

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

    async createGitHubRelease() {
        console.log('[GITHUB] CREATING GITHUB RELEASE');
        console.log('-'.repeat(30));

        try {
            // Get current branch to return to it
            const currentBranch = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();

            // Switch to wp-repo branch
            console.log('[GIT] Switching to wp-repo branch...');
            execSync('git checkout wp-repo', { stdio: 'inherit' });

            // Create distribution zip
            const zipName = `membership-management-${this.newVersion}.zip`;
            console.log(`[ZIP] Creating ${zipName}...`);

            // Remove old zip if it exists
            if (fs.existsSync(zipName)) {
                fs.removeSync(zipName);
            }

            // Get exclude patterns - use same patterns as wp-repo .gitignore
            const excludePatterns = this.getZipExcludePatterns();

            // Create zip with exclusions
            execSync(`zip -r ${zipName} . ${excludePatterns}`, { stdio: 'inherit' });

            // Generate release notes from changelog
            const releaseNotes = await this.generateReleaseNotes();

            // Create GitHub release
            console.log('[GITHUB] Creating release...');
            execSync(
                `gh release create v${this.newVersion} ${zipName} --title "Version ${this.newVersion}" --notes "${releaseNotes}"`,
                { stdio: 'inherit' }
            );

            // Clean up zip file
            fs.removeSync(zipName);

            // Return to original branch
            console.log(`[GIT] Returning to ${currentBranch} branch...`);
            execSync(`git checkout ${currentBranch}`, { stdio: 'inherit' });

            console.log('[OK] GitHub release created successfully\n');

        } catch (error) {
            console.log('[WARNING] Could not create GitHub release:', error.message);
            console.log('[INFO] You can create it manually later with:');
            console.log(`[INFO]   gh release create v${this.newVersion} membership-management-${this.newVersion}.zip`);

            // Try to return to original branch
            try {
                const currentBranch = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();
                if (currentBranch === 'wp-repo') {
                    execSync('git checkout stable', { stdio: 'inherit' });
                }
            } catch (restoreError) {
                // Ignore restoration errors
            }
        }
    }

    getZipExcludePatterns() {
        // These should match what we don't want in WordPress.org releases
        // This is what should be in wp-repo branch after prepare-wp-repo.js runs
        const excludes = [
            '*.git*',           // Git files
            'node_modules/*',   // Dependencies
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
        ];

        // Convert to zip -x format
        return excludes.map(pattern => `-x "${pattern}"`).join(' ');
    }

    async generateReleaseNotes() {
        try {
            const changelog = await this.generateChangelog();

            // Format for GitHub release
            const notes = changelog
                .replace(/^= .+ =$/, '') // Remove version header
                .trim();

            return notes || 'Updates and improvements';
        } catch (error) {
            return 'See readme.txt for changelog details.';
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
        console.log('\n[NEXT] Next Steps:');
        console.log('='.repeat(50));
        console.log('1. Review the wp-repo branch changes');
        console.log('2. Push changes and tags:');
        console.log('   git push origin main --tags');
        console.log('3. Deploy wp-repo branch to WordPress.org');
        console.log('4. Update WordPress.org assets if needed');
        console.log('\n[SUCCESS] Release completed successfully!');
    }
}

// Run release if called directly
if (require.main === module) {
    const orchestrator = new ReleaseOrchestrator();
    orchestrator.release();
}

module.exports = ReleaseOrchestrator;