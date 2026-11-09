import { expect, test } from 'vite-plus/test';
import { scanFileNames } from './fileName';

const now = new Date(2026, 9, 25, 9, 5);

test('one PDF takes the name written, without a second .pdf and the signs a file name cannot hold', () => {
    expect(scanFileNames(' polizza  casa.PDF', 1, now)).toEqual([
        'polizza casa.pdf',
    ]);
    expect(scanFileNames('bollette 10/2026: luce', 1, now)).toEqual([
        'bollette 10 2026 luce.pdf',
    ]);
});

test('without a name, one PDF is called after the day and the time', () => {
    expect(scanFileNames('', 1, now)).toEqual(['Scanner 2026-10-25 09.05.pdf']);
    expect(scanFileNames(' / ', 1, now)).toEqual([
        'Scanner 2026-10-25 09.05.pdf',
    ]);
});

test('one PDF per side: the name with the number, of as many digits as the last one', () => {
    expect(scanFileNames('ricevute', 3, now)).toEqual([
        'ricevute 1.pdf',
        'ricevute 2.pdf',
        'ricevute 3.pdf',
    ]);
    const twelve = scanFileNames('', 12, now);
    expect(twelve[0]).toBe('Scanner 2026-10-25 09.05 01.pdf');
    expect(twelve[11]).toBe('Scanner 2026-10-25 09.05 12.pdf');
});

test('after a stop halfway the numbers go on from the PDFs already uploaded, with the digits of the last one', () => {
    expect(scanFileNames('ricevute', 2, now, 3)).toEqual([
        'ricevute 4.pdf',
        'ricevute 5.pdf',
    ]);
    expect(scanFileNames('ricevute', 1, now, 9)).toEqual(['ricevute 10.pdf']);
    expect(scanFileNames('ricevute', 3, now, 8)).toEqual([
        'ricevute 09.pdf',
        'ricevute 10.pdf',
        'ricevute 11.pdf',
    ]);
});
