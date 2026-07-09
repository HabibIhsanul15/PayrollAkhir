const fs = require('fs');
const path = require('path');

function processDirectory(dirPath) {
    const files = fs.readdirSync(dirPath);
    for (const file of files) {
        const fullPath = path.join(dirPath, file);
        if (fs.statSync(fullPath).isDirectory()) {
            processDirectory(fullPath);
        } else if (fullPath.endsWith('.jsx')) {
            processFile(fullPath);
        }
    }
}

function processFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    let original = content;

    // Remove any huge rounded cards regardless of specific tailwind order
    content = content.replace(/className="[^"]*rounded-3xl[^"]*"/g, (match) => {
        return 'className="bg-white border border-border rounded shadow-sm p-4 my-4"';
    });

    // Inputs: w-full rounded-2xl border...
    content = content.replace(/className="[^"]*w-full rounded-2xl border[^"]*"/g, (match) => {
        return 'className="w-full border border-border rounded bg-white px-3 py-1.5 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition-all"';
    });

    // Alerts / Badges rounded-2xl
    content = content.replace(/rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700/g, 'rounded bg-rose-50 px-3 py-2 text-xs text-rose-600 border border-rose-100');
    content = content.replace(/rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700/g, 'rounded bg-emerald-50 px-3 py-2 text-xs text-emerald-600 border border-emerald-100');

    // Buttons
    content = content.replace(/className="[^"]*rounded-2xl bg-gradient-to-r from-sky-600 to-indigo-600[^"]*"/g, 'className="px-4 py-1.5 bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors"');
    content = content.replace(/className="[^"]*rounded-2xl border-slate-200 hover:bg-slate-50[^"]*"/g, 'className="px-4 py-1.5 bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors"');

    // Remaining rounded-2xl
    content = content.replace(/rounded-2xl/g, 'rounded');

    // Remaining text-sm in table cells or specific places where they should be text-xs
    content = content.replace(/text-sm text-slate-500/g, 'text-xs text-muted-foreground');
    content = content.replace(/text-sm font-semibold/g, 'text-xs font-semibold');

    // specific modal dialogs with rounded-2xl bg-white
    content = content.replace(/bg-white rounded-2xl w-full/g, 'bg-white rounded w-full border border-border');

    if (content !== original) {
        fs.writeFileSync(filePath, content);
        console.log('Aggressively Refactored:', filePath);
    }
}

processDirectory(path.join(__dirname, 'src/pages'));
