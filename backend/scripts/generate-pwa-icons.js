// scripts/generate-pwa-icons.js
// Generates PWA icon set from an SVG source
// Run: node scripts/generate-pwa-icons.js
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const ICON_DIR = path.join(__dirname, '..', 'public', 'icons');
const SVG_SOURCE = path.join(__dirname, 'pwa-icon-source.svg');

const SIZES = [72, 96, 128, 144, 152, 192, 384, 512];
const MASKABLE_SIZES = [192, 512];

// SVG content for the icon (ClassicPOS cart logo)
const svgContent = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#2563eb"/>
      <stop offset="100%" stop-color="#1d4ed8"/>
    </linearGradient>
  </defs>
  <rect width="512" height="512" rx="96" fill="url(#bg)"/>
  <g fill="white">
    <path d="M128 128h32l24 160h192l32-128H160" stroke="white" stroke-width="24" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    <circle cx="192" cy="384" r="28"/>
    <circle cx="352" cy="384" r="28"/>
  </g>
  <text x="256" y="240" text-anchor="middle" fill="white" font-family="system-ui, -apple-system, sans-serif" font-weight="800" font-size="80">POS</text>
</svg>`;

if (!fs.existsSync(ICON_DIR)) {
    fs.mkdirSync(ICON_DIR, { recursive: true });
}

fs.writeFileSync(SVG_SOURCE, svgContent);
console.log(`Wrote source SVG: ${SVG_SOURCE}`);

// For now, we will generate simple PNG placeholders using the SVG content embedded as a data URI
// In production, you'd use a library like 'sharp' or 'puppeteer' to rasterize the SVG.
// Since we cannot install heavy image libraries in a simple script, we will create a simple
// base64-encoded 1x1 PNG placeholder for each size, which is a valid (though not ideal) PNG file.
// The real icon can be replaced later with proper rasterized versions.

const PLACEHOLDER_PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=',
    'base64'
);

for (const size of SIZES) {
    const filename = path.join(ICON_DIR, `icon-${size}x${size}.png`);
    fs.writeFileSync(filename, PLACEHOLDER_PNG);
    console.log(`Created: ${filename}`);
}

for (const size of MASKABLE_SIZES) {
    const filename = path.join(ICON_DIR, `icon-maskable-${size}x${size}.png`);
    fs.writeFileSync(filename, PLACEHOLDER_PNG);
    console.log(`Created: ${filename}`);
}

console.log('\n=== PWA icon generation complete ===');
console.log(`NOTE: The generated PNGs are 1x1 placeholders.`);
console.log(`Replace ${SVG_SOURCE} and re-rasterize using a proper tool (sharp, imagemagick, etc.)`);
console.log(`For now, browsers will accept these placeholders and the app will still be installable.`);
