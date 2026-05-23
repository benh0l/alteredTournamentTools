/**
 * Convert SVG files to PNG for social media sharing
 * Usage: node scripts/convert-svg-to-png.js
 */

const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const conversions = [
    {
        input: 'public/images/social/og-default.svg',
        output: 'public/images/social/og-default.png',
        width: 1200,
        height: 630
    },
    {
        input: 'public/logo/alteredTT_logo.svg',
        output: 'public/logo/alteredTT_logo.png',
        width: 512,
        height: 512
    }
];

async function convert() {
    for (const { input, output, width, height } of conversions) {
        const inputPath = path.join(__dirname, '..', input);
        const outputPath = path.join(__dirname, '..', output);

        if (!fs.existsSync(inputPath)) {
            console.log(`⚠️  Skipping ${input} (file not found)`);
            continue;
        }

        try {
            await sharp(inputPath)
                .resize(width, height)
                .png()
                .toFile(outputPath);

            const stats = fs.statSync(outputPath);
            const sizeKB = (stats.size / 1024).toFixed(1);
            console.log(`✅ ${input} → ${output} (${sizeKB} KB)`);
        } catch (error) {
            console.error(`❌ Error converting ${input}:`, error.message);
        }
    }
}

convert().then(() => {
    console.log('\n🎉 Conversion complete!');
});
