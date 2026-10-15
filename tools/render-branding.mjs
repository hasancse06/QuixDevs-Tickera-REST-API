#!/usr/bin/env node
/** Render editable branding; adds no runtime dependency. */
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const require = createRequire(import.meta.url);
const sharp = require(process.env.QTRA_SHARP_MODULE || 'sharp');
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outputs = [
 ['docs/branding/github-social-preview.svg', 'docs/branding/github-social-preview.png', 1280, 640],
 ['docs/branding/wordpress-banner.svg', 'wordpress-org-assets/banner-772x250.png', 772, 250],
 ['docs/branding/wordpress-banner.svg', 'wordpress-org-assets/banner-1544x500.png', 1544, 500],
];
for (const [input, output, width, height] of outputs) {
 await sharp(path.join(root, input), { density: 288 }).resize(width, height).png({ compressionLevel: 9 }).toFile(path.join(root, output));
 console.log(`${output}: ${width} x ${height}`);
}
