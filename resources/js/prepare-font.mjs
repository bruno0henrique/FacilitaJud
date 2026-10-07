import { mkdir, readFile, writeFile } from 'node:fs/promises';
const file = new URL('../../public/fonts/Satoshi-Variable.woff2', import.meta.url);
try {
    const font = await readFile(file);
    if (font.subarray(0, 4).toString() === 'wOF2') process.exit(0);
} catch {}
const css = await fetch('https://api.fontshare.com/v2/css?f[]=satoshi@variable&display=swap', { signal: AbortSignal.timeout(30000) });
if (!css.ok) throw new Error('Não foi possível carregar Satoshi da Fontshare.');
const block = (await css.text()).match(/@font-face\s*{[^}]*font-weight:\s*300 900;[^}]*font-style:\s*normal;[^}]*}/)?.[0];
const source = block?.match(/url\('([^']+\.woff2)'\)/)?.[1];
if (!source) throw new Error('Arquivo variável Satoshi não encontrado na fonte oficial.');
const response = await fetch(new URL(source, 'https://api.fontshare.com'), { signal: AbortSignal.timeout(30000) });
const bytes = Buffer.from(await response.arrayBuffer());
if (!response.ok || bytes.subarray(0, 4).toString() !== 'wOF2') throw new Error('Download Satoshi inválido.');
await mkdir(new URL('../../public/fonts/', import.meta.url), { recursive: true });
await writeFile(file, bytes);
console.log('Satoshi preparada para uso local e online.');
