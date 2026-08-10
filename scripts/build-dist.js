#!/usr/bin/env node

/**
 * Build Clean Distribution Script
 * 
 * Creates a production-ready version of the plugin without development files.
 * This is Phase 3, Item 1 of the release automation.
 * 
 * Usage: npm run build:dist
 */

const fs = require('fs-extra');
const path = require('path');
const { execSync } = require('child_process');

class DistBuilder {
    constructor() {
        this.distDir = './dist';
        this.rootDir = './';
        
        // Files and directories to include in distribution
        this.includePatterns = [
            'membership.php',              // Main plugin file
            'readme.txt',                  // WordPress readme
            'includes/**/*',               // PHP source code
            'css/**/*',             // Compiled CSS
            'js/**/*',              // Production JS
            'assets/images/**/*',          // Images
            'languages/**/*',              // Translation files
            'LICENSE',                     // License file
        ];
        
        // Files and patterns to exclude from distribution
        this.excludePatterns = [
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

            // WordPress.org repo display assets (icon, banner) — not part
            // of the plugin itself; synced separately via deploy-svn.js --assets
            '**/.wordpress-org/**',
        ];
    }
    
    async build() {
        console.log('🚀 Building clean distribution...');
        
        try {
            // Step 1: Clean previous distribution
            await this.cleanDist();
            
            // Step 2: Compile assets if needed
            await this.compileAssets();
            
            // Step 3: Copy files to distribution
            await this.copyFiles();
            
            // Step 4: Validate distribution
            await this.validateDist();
            
            console.log('✅ Clean distribution built successfully in ./dist/');
            console.log('📦 Distribution is ready for WordPress.org submission');
            
        } catch (error) {
            console.error('❌ Build failed:', error.message);
            process.exit(1);
        }
    }
    
    async cleanDist() {
        console.log('🧹 Cleaning previous distribution...');
        await fs.remove(this.distDir);
        await fs.ensureDir(this.distDir);
    }
    
    async compileAssets() {
        console.log('🎨 Compiling assets...');
        
        try {
            // Run CSS compilation
            execSync('npm run css', { stdio: 'inherit' });
            console.log('✅ Assets compiled successfully');
        } catch (error) {
            console.log('⚠️  Asset compilation skipped (script may not exist)');
        }
    }
    
    async copyFiles() {
        console.log('📋 Copying files to distribution...');
        
        // Get all files in the project
        const allFiles = await this.getAllFiles(this.rootDir);
        
        // Filter files based on include/exclude patterns
        const filesToCopy = allFiles.filter(file => {
            const relativePath = path.relative(this.rootDir, file);
            
            // Check if file matches include patterns
            const included = this.includePatterns.some(pattern => 
                this.matchesPattern(relativePath, pattern)
            );
            
            // Check if file matches exclude patterns
            const excluded = this.excludePatterns.some(pattern => 
                this.matchesPattern(relativePath, pattern)
            );
            
            return included && !excluded;
        });
        
        // Copy files to distribution
        for (const file of filesToCopy) {
            const relativePath = path.relative(this.rootDir, file);
            const destPath = path.join(this.distDir, relativePath);
            
            await fs.ensureDir(path.dirname(destPath));
            await fs.copy(file, destPath);
        }
        
        console.log(`✅ Copied ${filesToCopy.length} files to distribution`);
    }
    
    async getAllFiles(dir, fileList = []) {
        const files = await fs.readdir(dir);
        
        for (const file of files) {
            const filePath = path.join(dir, file);
            const stat = await fs.stat(filePath);
            
            if (stat.isDirectory()) {
                // Skip certain directories early
                if (['node_modules', '.git', 'dist'].includes(file)) {
                    continue;
                }
                await this.getAllFiles(filePath, fileList);
            } else {
                fileList.push(filePath);
            }
        }
        
        return fileList;
    }
    
    matchesPattern(filePath, pattern) {
        // Convert glob-like patterns to regex
        const regexPattern = pattern
            .replace(/\*\*/g, '.*')
            .replace(/\*/g, '[^/]*')
            .replace(/\?/g, '.');
        
        const regex = new RegExp(`^${regexPattern}$`);
        
        // Normalize path separators for cross-platform compatibility
        const normalizedPath = filePath.replace(/\\/g, '/');
        const normalizedPattern = pattern.replace(/\\/g, '/');
        
        return regex.test(normalizedPath) || 
               normalizedPath.startsWith(normalizedPattern.replace('/**/*', '/')) ||
               regex.test(path.basename(filePath));
    }
    
    async validateDist() {
        console.log('🔍 Validating distribution...');
        
        const requiredFiles = [
            'membership.php',
            'readme.txt',
            'includes'
        ];
        
        for (const file of requiredFiles) {
            const filePath = path.join(this.distDir, file);
            if (!(await fs.pathExists(filePath))) {
                throw new Error(`Required file missing in distribution: ${file}`);
            }
        }
        
        // Check that no development files are included
        const devFiles = [
            'package.json',
            'node_modules',
            'src',
            'scripts'
        ];
        
        for (const file of devFiles) {
            const filePath = path.join(this.distDir, file);
            if (await fs.pathExists(filePath)) {
                console.warn(`⚠️  Development file found in distribution: ${file}`);
            }
        }
        
        console.log('✅ Distribution validation passed');
    }
}

// Run the build if called directly
if (require.main === module) {
    const builder = new DistBuilder();
    builder.build();
}

module.exports = DistBuilder;