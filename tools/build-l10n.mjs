// Builds the translation files of the plugin from tools/translations.json and the strings found in the code:
// languages/perimetry-cloud.pot, then for each language a .po (for translators), a .mo and a .l10n.php (WordPress 6.5+).
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
const root = new URL('..', import.meta.url).pathname;
const strings = JSON.parse(execFileSync('node', [`${root}tools/strings.mjs`, `${root}perimetry-cloud`]).toString());
const tr = JSON.parse(readFileSync(`${root}tools/translations.json`, 'utf8'));
const PLURAL = { fr_FR: 'nplurals=2; plural=(n > 1);', de_DE: 'nplurals=2; plural=(n != 1);' };
const q = (s) => `"${s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n')}"`;
const head = (lang) => [
  'Project-Id-Version: Perimetry Cloud 1.0.0', 'Report-Msgid-Bugs-To: https://perimetry.cloud/contact/', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8',
  'Content-Transfer-Encoding: 8bit', ...(lang ? [`Language: ${lang}`, `Plural-Forms: ${PLURAL[lang]}`] : []), 'X-Domain: perimetry-cloud',
].map((l) => `${l}\n`).join('');
const po = (lang) => {
  let out = `msgid ""\nmsgstr ${q(head(lang))}\n\n`;
  for (const s of strings) {
    const t = lang ? tr[lang][s.msgid] : null;
    if (s.plural) out += `msgid ${q(s.msgid)}\nmsgid_plural ${q(s.plural)}\nmsgstr[0] ${q(t ? t[0] : '')}\nmsgstr[1] ${q(t ? t[1] : '')}\n\n`;
    else out += `msgid ${q(s.msgid)}\nmsgstr ${q(t ?? '')}\n\n`;
  }
  return out;
};
const mo = (lang) => {
  const entries = [['', head(lang)], ...strings.map((s) => {
    const t = tr[lang][s.msgid];
    return s.plural ? [`${s.msgid}\0${s.plural}`, t.join('\0')] : [s.msgid, t];
  })].sort((a, b) => Buffer.compare(Buffer.from(a[0]), Buffer.from(b[0])));
  const n = entries.length, H = 28, origT = H, transT = H + n * 8, data0 = H + n * 16;
  const keys = entries.map((e) => Buffer.from(e[0])), vals = entries.map((e) => Buffer.from(e[1]));
  const tables = Buffer.alloc(n * 16); let off = data0; const chunks = [];
  keys.forEach((k, i) => { tables.writeUInt32LE(k.length, i * 8); tables.writeUInt32LE(off, i * 8 + 4); chunks.push(k, Buffer.from([0])); off += k.length + 1; });
  vals.forEach((v, i) => { tables.writeUInt32LE(v.length, n * 8 + i * 8); tables.writeUInt32LE(off, n * 8 + i * 8 + 4); chunks.push(v, Buffer.from([0])); off += v.length + 1; });
  const h = Buffer.alloc(H);
  [0x950412de, 0, n, origT, transT, 0, data0].forEach((v, i) => h.writeUInt32LE(v, i * 4));
  return Buffer.concat([h, tables, ...chunks]);
};
const l10n = (lang) => {
  const messages = Object.fromEntries(strings.map((s) => {
    const t = tr[lang][s.msgid];
    return s.plural ? [`${s.msgid}\0${s.plural}`, t.join('\0')] : [s.msgid, t];
  }));
  const data = { 'project-id-version': 'Perimetry Cloud 1.0.0', 'x-generator': 'perimetry-cloud tools/build-l10n.mjs', language: lang, 'plural-forms': PLURAL[lang], messages };
  return execFileSync('php', ['-r', 'echo "<?php\\nreturn " . var_export(json_decode(stream_get_contents(STDIN), true), true) . ";\\n";'], { input: JSON.stringify(data) }).toString();
};
const L = `${root}perimetry-cloud/languages/`;
writeFileSync(`${L}perimetry-cloud.pot`, po(null));
for (const lang of Object.keys(PLURAL)) {
  writeFileSync(`${L}perimetry-cloud-${lang}.po`, po(lang));
  writeFileSync(`${L}perimetry-cloud-${lang}.mo`, mo(lang));
  writeFileSync(`${L}perimetry-cloud-${lang}.l10n.php`, l10n(lang));
}
console.log(`${strings.length} strings, ${Object.keys(PLURAL).length} languages`);
