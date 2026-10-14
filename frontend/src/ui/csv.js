/**
 * Genera CSV (separador ";" para Excel en español) con escape de comillas e
 * inyección de fórmulas neutralizada (=, +, -, @).
 * @param {any[]} rows
 * @param {[string, (row: any) => any][]} columns
 */
export function toCsv(rows, columns) {
  const escape = (value) => {
    let text = String(value ?? '');
    if (/^[=+\-@]/.test(text)) text = `'${text}`;
    return /[";\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  };
  const lines = [columns.map(([label]) => escape(label)).join(';')];
  rows.forEach((row) => lines.push(columns.map(([, get]) => escape(get(row))).join(';')));
  return '﻿' + lines.join('\r\n');
}

export function download(filename, content, type = 'text/csv;charset=utf-8') {
  const url = URL.createObjectURL(new Blob([content], { type }));
  const a = Object.assign(document.createElement('a'), { href: url, download: filename });
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
