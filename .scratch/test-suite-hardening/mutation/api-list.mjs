import { readFileSync, writeFileSync } from 'node:fs';

// Usage and what to update by hand: README.md in this directory.
const [, , input, output, date, wallTime] = process.argv;
if (!wallTime) throw new Error('Usage: node <script> <input.json> <output.md> <date> <wall time>');
const j = JSON.parse(readFileSync(input, 'utf8'));
const s = j.stats;

const tickets = [
  [/Domain\/Appointment\/Service\/AvailabilityComputer/, ['01']],
  [/Domain\/Appointment\/Service\/SlotGenerationRules/, ['01', '17']],
  [/Domain\/User\/Entity\/(InvitationToken|PasswordResetToken)/, ['02']],
  [/Domain\/Appointment\/Entity\/SlotLock/, ['02']],
  [/Infrastructure\/Security\/(SecureTokenGenerator|JwtCookieManager)/, ['04']],
  [/Infrastructure\/Http\/Validation\/PasswordStrengthValidator/, ['04', '21']],
  [/Infrastructure\/Persistence\/Doctrine\/Type\//, ['05']],
  [/Infrastructure\/Console\//, ['06']],
  [/Handler\/GetNextAvailableWeekHandler/, ['13']],
  [/Handler\/LockSlotHandler|Service\/AppointmentRequestService/, ['17']],
  [/Repository\/Doctrine(Appointment|SlotLock)Repository/, ['18']],
];
const ticketsFor = (f) => tickets.filter(([re]) => re.test(f)).flatMap(([, t]) => t);

const rel = (p) => p.replace(/.*\/src\//, 'src/');
const short = (diff) => {
  const lines = diff.replace(/\r/g, '').split('\n');
  const pick = (c) => lines.filter((l) => l.startsWith(c) && !l.startsWith(c.repeat(3))).map((l) => l.slice(1).trim()).join(' ');
  const clip = (t) => (t.length > 100 ? t.slice(0, 97) + '...' : t).replace(/\|/g, '\\|').replace(/`/g, "'");
  const minus = pick('-');
  const plus = pick('+');
  if (minus && plus) return '`' + clip(minus) + '` became `' + clip(plus) + '`';
  if (minus) return 'removed `' + clip(minus) + '`';
  return 'added `' + clip(plus) + '`';
};

const byFile = new Map();
for (const e of j.escaped) {
  const f = rel(e.mutator.originalFilePath);
  if (!byFile.has(f)) byFile.set(f, []);
  byFile.get(f).push(e);
}
const files = [...byFile.entries()].sort((a, b) => b[1].length - a[1].length || a[0].localeCompare(b[0]));

const out = [];
out.push('# API surviving mutants');
out.push('');
out.push('**Evidence, not a backlog.** Emptying this list on request produces assertions pinned to');
out.push('internal state, which break on the next honest refactor. It is here so the next reader starts');
out.push('from the list and not from a rerun, and so tickets can be aimed. Gating is on new work only.');
out.push('');
out.push('| | |');
out.push('|---|---|');
out.push(`| Date | ${date} |`);
out.push('| Tree | the ticket 16 branch as merged, on top of `f52f608` |');
out.push('| Tool | Infection 0.35.6, PHPUnit 10.5.63, PHPStan 2.2.16, pcov 1.0.12, PHP 8.4.26 |');
out.push('| Command | `vendor/bin/infection --threads=8 --only-covering-test-cases` |');
out.push('| Config | `API/infection.json5` (timeout 300s, PHPStan on escaped mutants) |');
out.push(`| Wall time | ${wallTime}, 8 threads, one test database per thread |`);
out.push(`| Mutants | ${s.totalMutantsCount} |`);
out.push(`| Ignored | ${s.ignoredCount} |`);
out.push(`| Killed by tests | ${s.killedCount} |`);
out.push(`| Killed by PHPStan | ${s.killedByStaticAnalysisCount} |`);
out.push(`| Escaped | ${s.escapedCount} |`);
out.push(`| Timed out | ${s.timeOutCount} |`);
out.push(`| Errors, skipped | ${s.errorCount}, ${s.skippedCount} |`);
out.push(`| Uncovered | not generated (covered-only is the default, no \`--with-uncovered\`) |`);
out.push(`| MSI, covered MSI | ${s.msi}%, ${s.coveredCodeMsi}% |`);
out.push('');
out.push('The two MSI figures are equal because uncovered code was never mutated. Code no test reaches');
out.push('is absent from this list, not vouched for by it.');
out.push('');
out.push('Not mutated, by `source.excludes`: `Infrastructure/Http/Controller`, `Infrastructure/Config`,');
out.push('and the three `Application/*/DTO` directories. Reasons in ADR-0010.');
out.push('');
out.push('Ignored mutants sit on a line carrying a Doctrine mapping attribute: a column length, a nullable');
out.push('flag. No test reads the schema, so they could only survive. They count in no figure above but');
out.push('the total.');
out.push('');
out.push('Five survivors are an artefact of a cold cache: the `getSubscribedEvents()` lines of the two HTTP');
out.push('subscribers. They are only covered when the container is compiled during the initial run, and');
out.push('every mutant then runs against the compiled container, so nothing can kill them. A run on a');
out.push('warm `var/cache/test` does not generate them (1370 mutants, not 1375).');
out.push('');
out.push('The Ticket column names the `test-suite-hardening` ticket whose scope the file falls in.');
out.push('Empty means no ticket covers it.');
out.push('');
out.push('## Timed out');
out.push('');
for (const e of j.timeouted) {
  out.push(`- \`${rel(e.mutator.originalFilePath)}\` line ${e.mutator.originalStartLine}, ${e.mutator.mutatorName}: ${short(e.diff)}`);
}
out.push('');
out.push('## Survivors per file');
out.push('');
out.push('| Survivors | File | Ticket |');
out.push('|---|---|---|');
for (const [f, list] of files) out.push(`| ${list.length} | \`${f}\` | ${ticketsFor(f).join(', ')} |`);
out.push('');
out.push('## Survivors');
for (const [f, list] of files) {
  const t = ticketsFor(f);
  out.push('');
  out.push(`### \`${f}\``);
  out.push('');
  out.push(t.length ? `Ticket: ${t.join(', ')}` : 'Ticket: none');
  out.push('');
  out.push('| Line | Mutator | Change |');
  out.push('|---|---|---|');
  list.sort((a, b) => a.mutator.originalStartLine - b.mutator.originalStartLine);
  for (const e of list) out.push(`| ${e.mutator.originalStartLine} | ${e.mutator.mutatorName} | ${short(e.diff)} |`);
}
out.push('');
writeFileSync(output, out.join('\n'));
console.log('files', files.length, 'survivors', j.escaped.length, 'annotated files', files.filter(([f]) => ticketsFor(f).length).length);
