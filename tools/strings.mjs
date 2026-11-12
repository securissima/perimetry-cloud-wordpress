// Lists the translatable strings of the plugin (__(), esc_html__(), _n()), in order of appearance, without duplicates.
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
const files = (d) => readdirSync(d, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? files(join(d, e.name)) : e.name.endsWith('.php') ? [join(d, e.name)] : []));
const str = String.raw`'((?:[^'\\]|\\.)*)'`;
const re = new RegExp(String.raw`\b(?:__|esc_html__|esc_attr__)\(\s*${str}\s*,\s*'perimetry-cloud'|\b_n\(\s*${str}\s*,\s*${str}\s*,[^;]*?'perimetry-cloud'`, 'g');
const out = new Map();
for (const f of files(process.argv[2])) {
  const src = readFileSync(f, 'utf8');
  for (const m of src.matchAll(re)) {
    const un = (s) => s.replace(/\\'/g, "'");
    if (m[1] !== undefined) out.set(un(m[1]), { msgid: un(m[1]), file: f });
    else out.set(un(m[2]), { msgid: un(m[2]), plural: un(m[3]), file: f });
  }
}
console.log(JSON.stringify([...out.values()].map(({ file, ...r }) => r), null, 1));
