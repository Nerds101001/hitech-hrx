import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import html from '@rollup/plugin-html';
import { glob } from 'glob';
import fs from 'fs';
import path from 'path';

/**
 * Get Files from a directory
 * @param {string} query
 * @returns array
 */
function GetFilesArray(query) {
  return glob.sync(query);
}

function getModuleAssets() {
  const moduleAssets = [];

  try {
    // Read module statuses
    const moduleStatusesPath = path.join(process.cwd(), 'modules_statuses.json');
    const moduleStatuses = JSON.parse(fs.readFileSync(moduleStatusesPath, 'utf-8'));

    // Process each enabled module
    Object.entries(moduleStatuses).forEach(([moduleName, isEnabled]) => {
      if (isEnabled) {
        // Get JS files from module
        const moduleJsFiles = GetFilesArray(`Modules/${moduleName}/resources/assets/js/**/*.js`);
        moduleAssets.push(...moduleJsFiles);

        // Get SCSS/CSS files from module
        const moduleScssFiles = GetFilesArray(`Modules/${moduleName}/resources/assets/sass/**/*.scss`);
        const moduleCssFiles = GetFilesArray(`Modules/${moduleName}/resources/assets/css/**/*.css`);
        moduleAssets.push(...moduleScssFiles, ...moduleCssFiles);
      }
    });

    console.log(`Loaded assets from ${Object.values(moduleStatuses).filter(Boolean).length} enabled modules`);
  } catch (error) {
    console.warn('Could not load module assets:', error.message);
  }

  return moduleAssets;
}

/**
 * Js Files
 */
// Page JS Files
const pageJsFiles = GetFilesArray('resources/assets/js/*.js');

const pageAppJsFiles = GetFilesArray('resources/assets/js/app/*.js');

// Page specific JS files in pages directory
const pageDirJsFiles = GetFilesArray('resources/assets/js/pages/*.js');

// Processing Vendor JS Files
const vendorJsFiles = GetFilesArray('resources/assets/vendor/js/*.js');

// Processing Libs JS Files
const LibsJsFiles = GetFilesArray('resources/assets/vendor/libs/**/*.js');

/**
 * Scss Files
 */
// Processing Core, Themes & Pages Scss Files
const CoreScssFiles = GetFilesArray('resources/assets/vendor/scss/**/!(_)*.scss');

// Processing Libs Scss & Css Files
const LibsScssFiles = GetFilesArray('resources/assets/vendor/libs/**/!(_)*.scss');
const LibsCssFiles = GetFilesArray('resources/assets/vendor/libs/**/*.css');

const pageCssFiles = GetFilesArray('resources/assets/css/*.css');

// Processing Fonts Scss Files
const FontsScssFiles = GetFilesArray('resources/assets/vendor/fonts/!(_)*.scss');

// Get all module assets dynamically
const moduleAssets = getModuleAssets();// Get all module assets dynamically


// Processing Window Assignment for Libs like jKanban, pdfMake
function libsWindowAssignment() {
  return {
    name: 'libsWindowAssignment',

    transform(src, id) {
      if (id.includes('jkanban.js')) {
        return src.replace('this.jKanban', 'window.jKanban');
      } else if (id.includes('vfs_fonts')) {
        return src.replaceAll('this.pdfMake', 'window.pdfMake');
      } else if (id.includes('apex-charts.min.js')) {
        return src.replace('module.exports=e()', 'module.exports=(window.ApexCharts=e())');
      }
    }
  };
}

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/assets/css/demo.css',
        'resources/js/app.js',
        ...pageJsFiles,
        ...pageAppJsFiles,
        ...pageDirJsFiles,
        ...vendorJsFiles,
        ...LibsJsFiles,
        ...LibsJsFiles,
        'resources/js/main-helper.js', // Processing Main Helper JS File
        'resources/js/main-datatable.js', // Processing Main Datatable JS File
        'resources/js/main-select2.js', // Processing Main Select2 JS File
        ...CoreScssFiles,
        ...LibsScssFiles,
        ...LibsCssFiles,
        ...FontsScssFiles,
        ...pageCssFiles,
        // Include all module assets dynamically
        ...moduleAssets
      ],
      refresh: true
    }),
    html(),
    libsWindowAssignment()
  ],
  build: {
    rollupOptions: {
      external: ['laravel-echo']
    }
  },
  server: {
    port: 5174,
    watch: {
      ignored: [
        '**/node_modules/**',
        '**/vendor/**',
        '**/storage/**',
        '**/backups/**',
        '**/_deleted_archive/**',
        '**/.git/**'
      ]
    }
  }
});
