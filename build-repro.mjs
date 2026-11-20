import {compileModule} from 'svelte/compiler';
import fs from 'node:fs';
const out = compileModule(fs.readFileSync(process.argv[2], 'utf8'), {filename: 'repro.svelte.js', generate: 'client'});
fs.writeFileSync(process.argv[3], out.js.code);
console.log('compiled ok');
