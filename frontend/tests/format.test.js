import { describe, expect, it } from 'vitest';
import { currentPeriod, formatCLP, isoDate } from '../src/ui/format.js';
import { toCsv } from '../src/ui/csv.js';

describe('format', () => {
  it('formatea pesos chilenos sin decimales', () => {
    expect(formatCLP(35000)).toMatch(/\$\s?35\.000/);
  });

  it('genera fecha ISO local y período', () => {
    const date = new Date(2026, 9, 5, 23, 30);
    expect(isoDate(date)).toBe('2026-10-05');
    expect(currentPeriod(date)).toBe('2026-10');
  });
});

describe('csv', () => {
  it('escapa separadores y neutraliza fórmulas', () => {
    const csv = toCsv(
      [{ name: 'Pérez; Ana', formula: '=1+1' }],
      [
        ['Nombre', (r) => r.name],
        ['Fórmula', (r) => r.formula],
      ],
    );
    expect(csv).toContain('"Pérez; Ana"');
    expect(csv).toContain("'=1+1");
  });
});
