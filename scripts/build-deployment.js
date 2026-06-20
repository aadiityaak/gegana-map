import {
    chmodSync,
    cpSync,
    existsSync,
    mkdirSync,
    readdirSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'fs';
import { dirname, join, resolve } from 'path';
import JSZip from 'jszip';

const projectRoot = resolve(process.cwd());
const args = process.argv.slice(2);
const noVendor = args.includes('--no-vendor');
const publicDirName = 'public_html';
const buildDirName = noVendor
    ? 'build-deployment-novendor'
    : 'build-deployment';
const zipBaseName = noVendor
    ? 'gegana-map-deployment-novendor.zip'
    : 'gegana-map-deployment.zip';

const buildDir = join(projectRoot, buildDirName);
const zipFile = join(projectRoot, zipBaseName);

if (existsSync(buildDir)) {
    rmSync(buildDir, { recursive: true, force: true });
}
if (existsSync(zipFile)) {
    rmSync(zipFile, { force: true });
}

mkdirSync(buildDir, { recursive: true });

const laravelAppDir = join(buildDir, 'laravel-app');
mkdirSync(laravelAppDir, { recursive: true });

const publicHtmlDir = join(buildDir, publicDirName);
mkdirSync(publicHtmlDir, { recursive: true });

const includeFiles = [
    'app',
    'bootstrap',
    'config',
    'database',
    'resources',
    'routes',
    ...(noVendor ? [] : ['vendor']),
    'artisan',
    '.env.example',
    'composer.json',
    'composer.lock',
    'package.json',
];

const includePublicFiles = [
    'public/build',
    'public/maps',
    'public/branding',
    'public/favicon.ico',
    'public/favicon.svg',
    'public/apple-touch-icon.png',
    'public/robots.txt',
    'public/.htaccess',
    'public/index.php',
];

const ensureDir = (path) => {
    if (!existsSync(path)) mkdirSync(path, { recursive: true });
};

const copyPath = (srcPath, destPath) => {
    if (!existsSync(srcPath)) return;
    ensureDir(dirname(destPath));
    cpSync(srcPath, destPath, { recursive: true });
};

process.stdout.write('Copying laravel files: ');
for (const entry of includeFiles) {
    const src = join(projectRoot, entry);
    const dest = join(laravelAppDir, entry);
    if (!existsSync(src)) continue;
    copyPath(src, dest);
    process.stdout.write('.');
}
process.stdout.write('\n');

const laravelPublicExtras = [
    'public/build/manifest.json',
    'public/maps',
    'public/branding',
];

process.stdout.write('Copying public extras to laravel-app: ');
for (const entry of laravelPublicExtras) {
    const src = join(projectRoot, entry);
    const dest = join(laravelAppDir, entry);
    if (!existsSync(src)) continue;
    copyPath(src, dest);
    process.stdout.write('.');
}
process.stdout.write('\n');

process.stdout.write(`Copying public files to ${publicDirName}: `);
for (const entry of includePublicFiles) {
    const src = join(projectRoot, entry);
    if (!existsSync(src)) continue;
    const fileName = entry.replace(/^public[\\/]/, '');
    const dest = join(publicHtmlDir, fileName);
    copyPath(src, dest);
    process.stdout.write('.');
}
process.stdout.write('\n');

const indexPhpPath = join(publicHtmlDir, 'index.php');
if (existsSync(indexPhpPath)) {
    let indexContent = readFileSync(indexPhpPath, 'utf8');
    indexContent = indexContent.replace(
        "require __DIR__.'/../vendor/autoload.php';",
        "require __DIR__.'/../laravel-app/vendor/autoload.php';",
    );
    indexContent = indexContent.replace(
        "$app = require_once __DIR__.'/../bootstrap/app.php';",
        "$app = require_once __DIR__.'/../laravel-app/bootstrap/app.php';",
    );
    writeFileSync(indexPhpPath, indexContent);
}

const envProdPath = join(laravelAppDir, '.env.production');
if (!existsSync(envProdPath)) {
    const example = existsSync(join(projectRoot, '.env.production'))
        ? readFileSync(join(projectRoot, '.env.production'), 'utf8')
        : existsSync(join(projectRoot, '.env.example'))
          ? readFileSync(join(projectRoot, '.env.example'), 'utf8')
          : '';
    if (example.trim() !== '') {
        writeFileSync(envProdPath, example);
    }
}

const touchFile = (path, content = '') => {
    if (existsSync(path)) return;
    ensureDir(dirname(path));
    writeFileSync(path, content);
};

const ensureStorageScaffold = () => {
    const dirs = [
        join(laravelAppDir, 'storage', 'app', 'public'),
        join(laravelAppDir, 'storage', 'framework', 'cache', 'data'),
        join(laravelAppDir, 'storage', 'framework', 'sessions'),
        join(laravelAppDir, 'storage', 'framework', 'views'),
        join(laravelAppDir, 'storage', 'logs'),
        join(laravelAppDir, 'bootstrap', 'cache'),
    ];

    for (const dir of dirs) {
        ensureDir(dir);
        touchFile(join(dir, '.gitignore'), '*\n!.gitignore\n');
    }
};

ensureStorageScaffold();

const addDirectoryToZip = (zip, dirPath, zipFolder = '') => {
    const items = readdirSync(dirPath, { withFileTypes: true });
    for (const item of items) {
        const itemPath = join(dirPath, item.name);
        const zipPath = zipFolder ? `${zipFolder}/${item.name}` : item.name;
        if (item.isDirectory()) {
            addDirectoryToZip(zip, itemPath, zipPath);
            continue;
        }
        zip.file(zipPath, readFileSync(itemPath));
    }
};

const zip = new JSZip();
addDirectoryToZip(zip, buildDir);
const zipContent = await zip.generateAsync({
    type: 'nodebuffer',
    compression: 'DEFLATE',
    compressionOptions: { level: 6 },
});
writeFileSync(zipFile, zipContent);

try {
    chmodSync(zipFile, 0o644);
} catch {}

rmSync(buildDir, { recursive: true, force: true });

console.log(`ZIP ready: ${zipBaseName}`);
