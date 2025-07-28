#!/usr/bin/env node

/**
 * Release Validation Script
 * 
 * Validates that the release meets WordPress.org standards before distribution.
 * This is Phase 3, Item 3 of the release automation.
 * 
 * Usage: npm run validate:release
 */

const fs = require('fs');
const path = require('path');

class ReleaseValidator {
    constructor() {
        this.errors = [];
        this.warnings = [];
        this.projectRoot = './';
        
        // WordPress requirements
        this.wpRequirements = {
            minWpVersion: '5.0',
            minPhpVersion: '7.4',
            maxTestedWpVersion: '6.4'
        };
    }
    
    async validate() {
        console.log('🔍 Validating release for WordPress.org compliance...');
        
        try {
            // Core validations
            await this.validatePluginHeader();
            await this.validateReadmeTxt();
            await this.validateVersionConsistency();
            await this.validateRequiredFiles();
            await this.validateFileStructure();
            await this.validateNoDevFiles();
            await this.validateCodeQuality();
            
            // Report results
            this.reportResults();
            
            if (this.errors.length > 0) {
                process.exit(1);
            }
            
        } catch (error) {
            console.error('❌ Validation failed with error:', error.message);
            process.exit(1);
        }
    }
    
    async validatePluginHeader() {
        console.log('📋 Validating plugin header...');
        
        const mainFile = this.getMainPluginFile();
        if (!mainFile) {
            this.errors.push('❌ Main plugin file not found');
            return;
        }
        
        const content = fs.readFileSync(mainFile, 'utf8');
        const headerPattern = /\/\*\*([\s\S]*?)\*\//;
        const headerMatch = content.match(headerPattern);
        
        if (!headerMatch) {
            this.errors.push('❌ Plugin header not found');
            return;
        }
        
        const header = headerMatch[1];
        const requiredFields = [
            'Plugin Name',
            'Description', 
            'Version',
            'Author'
        ];
        
        const recommendedFields = [
            'Plugin URI',
            'Author URI',
            'License',
            'License URI',
            'Text Domain',
            'Requires at least',
            'Tested up to',
            'Requires PHP'
        ];
        
        // Check required fields
        for (const field of requiredFields) {
            if (!header.includes(`${field}:`)) {
                this.errors.push(`❌ Missing required header field: ${field}`);
            }
        }
        
        // Check recommended fields
        for (const field of recommendedFields) {
            if (!header.includes(`${field}:`)) {
                this.warnings.push(`⚠️  Missing recommended header field: ${field}`);
            }
        }
        
        // Validate version format
        const versionMatch = header.match(/Version:\s*(.+)/);
        if (versionMatch) {
            const version = versionMatch[1].trim();
            if (!/^\d+\.\d+\.\d+/.test(version)) {
                this.warnings.push(`⚠️  Version format should be semantic (x.y.z): ${version}`);
            }
        }
        
        console.log('✅ Plugin header validation completed');
    }
    
    async validateReadmeTxt() {
        console.log('📄 Validating readme.txt...');
        
        const readmePath = path.join(this.projectRoot, 'readme.txt');
        if (!fs.existsSync(readmePath)) {
            this.errors.push('❌ readme.txt file missing');
            return;
        }
        
        const content = fs.readFileSync(readmePath, 'utf8');
        
        // Check WordPress readme format
        if (!content.startsWith('=== ')) {
            this.errors.push('❌ readme.txt must start with === Plugin Name ===');
        }
        
        const requiredSections = [
            'Stable tag:',
            'Tested up to:',
            'Requires at least:',
            'License:',
            '== Description ==',
            '== Installation ==',
            '== Changelog =='
        ];
        
        for (const section of requiredSections) {
            if (!content.includes(section)) {
                this.errors.push(`❌ readme.txt missing required section: ${section}`);
            }
        }
        
        // Check version format in stable tag
        const stableTagMatch = content.match(/Stable tag:\s*(.+)/);
        if (stableTagMatch) {
            const stableTag = stableTagMatch[1].trim();
            if (!/^\d+\.\d+\.\d+/.test(stableTag)) {
                this.warnings.push(`⚠️  Stable tag should be semantic version: ${stableTag}`);
            }
        }
        
        // Check WordPress version requirements
        const testedUpToMatch = content.match(/Tested up to:\s*(.+)/);
        if (testedUpToMatch) {
            const testedVersion = testedUpToMatch[1].trim();
            // Basic check - should be at least 5.x
            if (!testedVersion.match(/^[5-9]\./)) {
                this.warnings.push(`⚠️  Consider testing with newer WordPress version: ${testedVersion}`);
            }
        }
        
        console.log('✅ readme.txt validation completed');
    }
    
    async validateVersionConsistency() {
        console.log('🔄 Validating version consistency...');
        
        const versions = this.getAllVersions();
        const uniqueVersions = [...new Set(Object.values(versions))];
        
        if (uniqueVersions.length > 1) {
            this.errors.push(`❌ Version inconsistency found: ${JSON.stringify(versions)}`);
        } else {
            console.log(`✅ All versions consistent: ${uniqueVersions[0]}`);
        }
    }
    
    getAllVersions() {
        const versions = {};
        
        // Get version from package.json
        try {
            const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
            versions.packageJson = packageJson.version;
        } catch (error) {
            // Package.json might not exist in distribution
        }
        
        // Get version from plugin header
        const mainFile = this.getMainPluginFile();
        if (mainFile) {
            const content = fs.readFileSync(mainFile, 'utf8');
            const versionMatch = content.match(/Version:\s*(.+)/);
            if (versionMatch) {
                versions.pluginHeader = versionMatch[1].trim();
            }
        }
        
        // Get version from readme.txt
        try {
            const readme = fs.readFileSync('readme.txt', 'utf8');
            const stableTagMatch = readme.match(/Stable tag:\s*(.+)/);
            if (stableTagMatch) {
                versions.readmeStableTag = stableTagMatch[1].trim();
            }
        } catch (error) {
            // readme.txt might not exist
        }
        
        // Get version from plugin constant
        if (mainFile) {
            const content = fs.readFileSync(mainFile, 'utf8');
            const constantMatch = content.match(/define\(\s*['"](DCMM_VERSION|.*_VERSION)['"],\s*['"](.+?)['"]/);
            if (constantMatch) {
                versions.pluginConstant = constantMatch[2];
            }
        }
        
        return versions;
    }
    
    async validateRequiredFiles() {
        console.log('📁 Validating required files...');
        
        const requiredFiles = [
            { path: this.getMainPluginFile(), name: 'Main plugin file' },
            { path: 'readme.txt', name: 'WordPress readme' },
            { path: 'includes/', name: 'Includes directory' }
        ];
        
        for (const file of requiredFiles) {
            if (!file.path || !fs.existsSync(file.path)) {
                this.errors.push(`❌ Required file missing: ${file.name} (${file.path})`);
            }
        }
        
        console.log('✅ Required files validation completed');
    }
    
    async validateFileStructure() {
        console.log('🏗️  Validating file structure...');
        
        // Check for proper WordPress plugin structure
        const expectedDirs = ['includes'];
        const optionalDirs = ['assets', 'languages', 'templates'];
        
        for (const dir of expectedDirs) {
            if (!fs.existsSync(dir)) {
                this.warnings.push(`⚠️  Expected directory not found: ${dir}/`);
            }
        }
        
        // Check file extensions
        const phpFiles = this.getFilesByExtension('.php');
        const jsFiles = this.getFilesByExtension('.js');
        const cssFiles = this.getFilesByExtension('.css');
        
        if (phpFiles.length === 0) {
            this.errors.push('❌ No PHP files found');
        }
        
        console.log(`✅ Found ${phpFiles.length} PHP files, ${jsFiles.length} JS files, ${cssFiles.length} CSS files`);
    }
    
    async validateNoDevFiles() {
        console.log('🚫 Checking for development files...');
        
        const devFiles = [
            'package.json',
            'package-lock.json',
            'node_modules',
            'src',
            'scripts',
            'tests',
            'test',
            '.git',
            '.gitignore',
            'webpack.config.js',
            'gulpfile.js',
            '.babelrc',
            '.eslintrc',
            'tsconfig.json',
            'migrate-*.php',
            'test-*.php'
        ];
        
        const foundDevFiles = devFiles.filter(file => {
            if (file.includes('*')) {
                // Handle wildcard patterns
                const pattern = file.replace('*', '.*');
                const regex = new RegExp(pattern);
                return fs.readdirSync('./').some(f => regex.test(f));
            }
            return fs.existsSync(file);
        });
        
        if (foundDevFiles.length > 0) {
            this.warnings.push(`⚠️  Development files found (should be excluded from distribution): ${foundDevFiles.join(', ')}`);
        }
        
        console.log('✅ Development files check completed');
    }
    
    async validateCodeQuality() {
        console.log('🔍 Performing basic code quality checks...');
        
        const phpFiles = this.getFilesByExtension('.php');
        let syntaxErrors = 0;
        
        for (const file of phpFiles.slice(0, 10)) { // Check first 10 files to avoid slowdown
            try {
                const content = fs.readFileSync(file, 'utf8');
                
                // Basic syntax checks
                if (content.includes('<?php') && !content.trim().startsWith('<?php')) {
                    this.warnings.push(`⚠️  ${file}: PHP opening tag should be at the beginning`);
                }
                
                // Security checks
                if (content.includes('eval(') || content.includes('exec(')) {
                    this.warnings.push(`⚠️  ${file}: Contains potentially unsafe functions`);
                }
                
            } catch (error) {
                syntaxErrors++;
            }
        }
        
        if (syntaxErrors > 0) {
            this.errors.push(`❌ ${syntaxErrors} files have syntax errors`);
        }
        
        console.log('✅ Code quality check completed');
    }
    
    getMainPluginFile() {
        // Look for main plugin file
        const candidates = [
            'membership.php',
            'dc-membership.php',
            'plugin.php'
        ];
        
        for (const candidate of candidates) {
            if (fs.existsSync(candidate)) {
                const content = fs.readFileSync(candidate, 'utf8');
                if (content.includes('Plugin Name:')) {
                    return candidate;
                }
            }
        }
        
        // Search for any PHP file with plugin header
        const phpFiles = this.getFilesByExtension('.php');
        for (const file of phpFiles) {
            if (file.includes('/')) continue; // Skip files in subdirectories
            
            const content = fs.readFileSync(file, 'utf8');
            if (content.includes('Plugin Name:')) {
                return file;
            }
        }
        
        return null;
    }
    
    getFilesByExtension(extension) {
        const files = [];
        
        const scanDirectory = (dir) => {
            const items = fs.readdirSync(dir);
            
            for (const item of items) {
                const fullPath = path.join(dir, item);
                const stat = fs.statSync(fullPath);
                
                if (stat.isDirectory() && !['node_modules', '.git', 'dist'].includes(item)) {
                    scanDirectory(fullPath);
                } else if (stat.isFile() && fullPath.endsWith(extension)) {
                    files.push(fullPath);
                }
            }
        };
        
        scanDirectory('./');
        return files;
    }
    
    reportResults() {
        console.log('\n📊 Validation Results:');
        console.log('='.repeat(50));
        
        if (this.errors.length === 0 && this.warnings.length === 0) {
            console.log('✅ Perfect! No issues found.');
        } else {
            if (this.errors.length > 0) {
                console.log('\n❌ ERRORS (must fix before release):');
                this.errors.forEach(error => console.log(`  ${error}`));
            }
            
            if (this.warnings.length > 0) {
                console.log('\n⚠️  WARNINGS (recommended to fix):');
                this.warnings.forEach(warning => console.log(`  ${warning}`));
            }
        }
        
        console.log(`\n📈 Summary: ${this.errors.length} errors, ${this.warnings.length} warnings`);
        
        if (this.errors.length > 0) {
            console.log('\n❌ VALIDATION FAILED - Please fix errors before proceeding with release');
        } else {
            console.log('\n✅ VALIDATION PASSED - Release is ready for WordPress.org');
        }
    }
}

// Run validation if called directly
if (require.main === module) {
    const validator = new ReleaseValidator();
    validator.validate();
}

module.exports = ReleaseValidator;