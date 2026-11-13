#!/usr/bin/env node
// Builds amd/build/*.min.js for the Hikmah plugins from amd/src/*.js without the full Moodle
// grunt toolchain. It handles the simple shape these modules use: no `import` statements and
// top-level `export const name = …` declarations. For anything more complex, run Moodle's own
// `grunt amd` from a full checkout instead (moodle-plugin-ci does this in CI).
//
// Usage: node scripts/build-amd.js

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const plugins = {
    'theme_hikmah': 'plugins/theme/hikmah',
    'paygw_hikmahtransfer': 'plugins/payment/gateway/hikmahtransfer',
    'local_hikmahattendance': 'plugins/local/hikmahattendance',
};

let built = 0;
for (const [component, dir] of Object.entries(plugins)) {
    const src = path.join(root, dir, 'amd', 'src');
    if (!fs.existsSync(src)) {
        continue;
    }
    const out = path.join(root, dir, 'amd', 'build');
    fs.mkdirSync(out, {recursive: true});
    for (const file of fs.readdirSync(src).filter((f) => f.endsWith('.js'))) {
        const name = file.replace(/\.js$/, '');
        const code = fs.readFileSync(path.join(src, file), 'utf8');
        if (/^\s*import\s/m.test(code)) {
            throw new Error(`${dir}/amd/src/${file} uses import; build it with grunt instead.`);
        }
        const exports = [];
        const body = code.replace(/^export const (\w+) =/gm, (match, id) => {
            exports.push(id);
            return `const ${id} = _exports.${id} =`;
        });
        if (/^\s*export\s/m.test(body)) {
            throw new Error(`${dir}/amd/src/${file} has an export this builder does not understand.`);
        }
        const wrapped = `define("${component}/${name}", ["exports"], function(_exports) {\n"use strict";\n` +
            'Object.defineProperty(_exports, "__esModule", {value: true});\n' +
            exports.map((id) => `_exports.${id} = void 0;\n`).join('') +
            body + '\n});\n';
        fs.writeFileSync(path.join(out, name + '.min.js'), wrapped);
        built++;
        console.log(`built ${component}/${name}`);
    }
}
console.log(`${built} module(s) built.`);
