import { readFileSync, writeFileSync } from 'node:fs';
// Usage and what to update by hand: README.md in this directory.
const [, , input, output, date, wallTime] = process.argv;
if (!wallTime) throw new Error('Usage: node <script> <input.json> <output.md> <date> <wall time>');
const j = JSON.parse(readFileSync(input, 'utf8'));
const cell = (t) => '`' + (t.length > 100 ? t.slice(0, 97) + '...' : t).replace(/\s+/g, ' ').replace(/\|/g, '\\|').replace(/`/g, "'") + '`';
const count = {};
const rows = { Survived: [], NoCoverage: [] };
const perFile = {};
for (const [file, f] of Object.entries(j.files)) {
  const src = f.source.replace(/\r/g, '').split('\n');
  perFile[file] = {};
  for (const m of f.mutants) {
    count[m.status] = (count[m.status] || 0) + 1;
    perFile[file][m.status] = (perFile[file][m.status] || 0) + 1;
    if (!rows[m.status]) continue;
    const { start, end } = m.location;
    const orig = start.line === end.line ? src[start.line - 1].slice(start.column - 1, end.column - 1) : src[start.line - 1].slice(start.column - 1) + ' ...';
    rows[m.status].push([file, start.line, m.mutatorName, orig, m.replacement ?? '']);
  }
}
const total = Object.values(count).reduce((a, b) => a + b, 0);
const killed = (count.Killed || 0) + (count.Timeout || 0);
const pct = (a, b) => ((100 * a) / b).toFixed(2) + '%';
const out = [];
out.push('# Landing surviving mutants', '');
out.push('**Evidence, not a backlog.** Same rule as `api-survivors.md`. Discovery only, there is no CI gate', 'on the landing side.', '');
out.push('| | |', '|---|---|');
out.push(`| Date | ${date} |`);
out.push('| Tool | Stryker 10.0.0 with the Vitest runner, Vitest 2.1 |');
out.push('| Command | `npm run mutation` in `landing/` |');
out.push('| Mutated | `src/utils/dates.ts`, `src/utils/modality.ts` |');
out.push(`| Wall time | ${wallTime} |`);
out.push(`| Mutants | ${total} |`);
out.push(`| Killed | ${count.Killed || 0} |`);
out.push(`| Survived | ${count.Survived || 0} |`);
out.push(`| No coverage | ${count.NoCoverage || 0} |`);
out.push(`| Timed out, errors | ${count.Timeout || 0}, ${(count.RuntimeError || 0) + (count.CompileError || 0)} |`);
out.push(`| Mutation score, covered only | ${pct(killed, total)}, ${pct(killed, total - (count.NoCoverage || 0))} |`);
out.push('');
out.push('Unlike Infection, Stryker also mutates code no test reaches and reports it as "no coverage".', 'Those are listed separately below.', '');
out.push('No `test-suite-hardening` ticket covers these two files, so nothing here is annotated.', '`landing/e2e/` is not measured: Vitest does not include it (ticket 16, comment of 2026-09-23).', '');
out.push('| File | Killed | Survived | No coverage |', '|---|---|---|---|');
for (const [f, c] of Object.entries(perFile)) out.push(`| \`${f}\` | ${c.Killed || 0} | ${c.Survived || 0} | ${c.NoCoverage || 0} |`);
for (const [status, title] of [['Survived', 'Survived'], ['NoCoverage', 'No coverage']]) {
  out.push('', `## ${title}`, '', '| File | Line | Mutator | Original | Replacement |', '|---|---|---|---|---|');
  rows[status].sort((a, b) => a[0].localeCompare(b[0]) || a[1] - b[1]);
  for (const [f, l, mu, o, r] of rows[status]) out.push(`| \`${f}\` | ${l} | ${mu} | ${cell(o)} | ${r === '' ? '(removed)' : cell(r)} |`);
}
out.push('');
writeFileSync(output, out.join('\n'));
console.log(count);
