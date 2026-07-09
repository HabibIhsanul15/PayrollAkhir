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

    // 1. Remove background circles block completely
    const bgPattern = /\s*<div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">[\s\S]*?<\/div>\s*<\/div>\s*/g;
    // Wait, the outer div might be the main wrapper `<div className="relative">`.
    // Let's just remove the inner bg block.
    const bgPattern2 = /\s*<div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">[\s\S]*?<\/div>\n/g;
    content = content.replace(bgPattern2, '');

    // Replace the main relative wrapper if it exists just to be clean
    content = content.replace(/<div className="relative">/g, '<div>');

    // 2. Replace huge Cards/Containers
    content = content.replace(/rounded-3xl border border-slate-200 bg-white\/75 backdrop-blur-xl shadow-\[0_16px_50px_rgba\(2,6,23,0\.06\)\]/g, 
        'bg-white border border-border rounded shadow-sm');
    content = content.replace(/rounded-3xl border border-slate-200 bg-white\/75 backdrop-blur shadow-\[0_16px_50px_rgba\(2,6,23,0\.06\)\]/g, 
        'bg-white border border-border rounded shadow-sm');
    content = content.replace(/rounded-2xl border border-slate-200 bg-white\/70/g, 
        'bg-white border border-border rounded shadow-sm');

    // 3. Typography
    content = content.replace(/text-3xl font-black tracking-tight text-slate-900/g, 'text-lg font-semibold text-foreground');
    content = content.replace(/text-2xl font-bold text-slate-800/g, 'text-lg font-semibold text-foreground');
    content = content.replace(/text-slate-500 mt-1/g, 'text-[10px] text-muted-foreground mt-0.5');
    content = content.replace(/text-sm font-semibold text-slate-700/g, 'text-[10px] font-semibold text-muted-foreground');
    content = content.replace(/font-semibold text-slate-900/g, 'font-medium text-foreground');

    // 4. Badges / Pills in Headers
    content = content.replace(/inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white\/70 px-4 py-2 shadow-sm/g, 'hidden');

    // 5. Buttons
    content = content.replace(/rounded-2xl bg-slate-900 text-white font-extrabold hover:bg-slate-800/g, 'bg-blue-600 rounded text-xs font-medium text-white hover:bg-blue-700 transition-colors');
    content = content.replace(/rounded-2xl bg-white\/70 backdrop-blur border-slate-200 hover:bg-white/g, 'bg-white border border-border rounded text-xs text-muted-foreground hover:text-foreground transition-colors');
    
    // 6. Padding adjustments
    content = content.replace(/p-6 relative my-8/g, 'p-4 my-4');
    content = content.replace(/px-6 py-5/g, 'px-4 py-3');
    content = content.replace(/p-7/g, 'p-4');
    content = content.replace(/p-6/g, 'p-4');

    if (content !== original) {
        fs.writeFileSync(filePath, content);
        console.log('Refactored:', filePath);
    }
}

processDirectory(path.join(__dirname, 'src/pages'));
