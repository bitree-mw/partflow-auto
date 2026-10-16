// Reads the first worksheet of an .xlsx file in the browser, mirroring App\Support\SpreadsheetReader::readXlsx(),
// so the import page can check columns and rows before uploading. Uses the built-in DecompressionStream.

const MAX_UNCOMPRESSED_BYTES = 30 * 1024 * 1024;
const RELATIONSHIP_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

export class XlsxUnsupportedError extends Error {}

export const canReadXlsx = () => typeof DecompressionStream === 'function' && typeof DOMParser === 'function';

function zipEntries(bytes) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const searchStart = Math.max(0, bytes.length - 65557);
    let end = -1;

    for (let offset = bytes.length - 22; offset >= searchStart; offset--) {
        if (view.getUint32(offset, true) === 0x06054b50) {
            end = offset;
            break;
        }
    }

    if (end === -1) {
        throw new Error('Not a zip archive.');
    }

    const count = view.getUint16(end + 10, true);
    let offset = view.getUint32(end + 16, true);

    if (count === 0xffff || offset === 0xffffffff) {
        throw new XlsxUnsupportedError('ZIP64 archives are not read in the browser.');
    }

    const entries = new Map();
    const decoder = new TextDecoder();

    for (let index = 0; index < count; index++) {
        if (view.getUint32(offset, true) !== 0x02014b50) {
            throw new Error('Damaged zip directory.');
        }

        const nameLength = view.getUint16(offset + 28, true);
        const extraLength = view.getUint16(offset + 30, true);
        const commentLength = view.getUint16(offset + 32, true);
        const name = decoder.decode(bytes.subarray(offset + 46, offset + 46 + nameLength));

        entries.set(name, {
            method: view.getUint16(offset + 10, true),
            compressedSize: view.getUint32(offset + 20, true),
            size: view.getUint32(offset + 24, true),
            localOffset: view.getUint32(offset + 42, true),
        });
        offset += 46 + nameLength + extraLength + commentLength;
    }

    return { view, entries };
}

async function readEntry(bytes, zip, name) {
    const entry = zip.entries.get(name);

    if (!entry) {
        return null;
    }

    if (entry.size > MAX_UNCOMPRESSED_BYTES) {
        throw new Error('The Excel file is too large to import.');
    }

    const local = entry.localOffset;
    const dataStart = local + 30 + zip.view.getUint16(local + 26, true) + zip.view.getUint16(local + 28, true);
    const data = bytes.subarray(dataStart, dataStart + entry.compressedSize);

    if (entry.method === 0) {
        return new TextDecoder().decode(data);
    }

    if (entry.method !== 8) {
        throw new XlsxUnsupportedError(`Zip compression method ${entry.method} is not read in the browser.`);
    }

    const stream = new Blob([data]).stream().pipeThrough(new DecompressionStream('deflate-raw'));

    return new Response(stream).text();
}

async function readXml(bytes, zip, name) {
    const text = await readEntry(bytes, zip, name);

    if (text === null) {
        return null;
    }

    const xml = new DOMParser().parseFromString(text, 'application/xml');

    if (xml.getElementsByTagName('parsererror').length) {
        throw new Error(`${name} is not valid XML.`);
    }

    return xml;
}

const childrenNamed = (element, localName) => [...element.children].filter((child) => child.localName === localName);

function inlineText(item) {
    if (!item) {
        return '';
    }

    const direct = childrenNamed(item, 't')[0];

    if (direct) {
        return direct.textContent;
    }

    return childrenNamed(item, 'r').map((run) => childrenNamed(run, 't')[0]?.textContent ?? '').join('');
}

function columnIndex(reference) {
    const letters = reference.replace(/[^A-Za-z]/g, '').toUpperCase();
    let index = 0;

    for (const letter of letters) {
        index = index * 26 + (letter.charCodeAt(0) - 64);
    }

    return Math.max(0, index - 1);
}

async function firstSheetPath(bytes, zip) {
    const workbook = await readXml(bytes, zip, 'xl/workbook.xml');
    const relationships = await readXml(bytes, zip, 'xl/_rels/workbook.xml.rels');
    const fallback = 'xl/worksheets/sheet1.xml';

    if (!workbook || !relationships) {
        return fallback;
    }

    const sheet = workbook.getElementsByTagNameNS('*', 'sheet')[0];
    const relationshipId = sheet?.getAttributeNS(RELATIONSHIP_NS, 'id') ?? '';
    const relationship = [...relationships.getElementsByTagNameNS('*', 'Relationship')]
        .find((item) => item.getAttribute('Id') === relationshipId);

    if (!relationship) {
        return fallback;
    }

    const target = (relationship.getAttribute('Target') ?? '').replace(/^\/+/, '');

    return target.startsWith('xl/') ? target : `xl/${target}`;
}

/**
 * @returns {Promise<string[][]>} rows of trimmed cell text, empty rows dropped (same as the server)
 */
export async function readXlsxRows(file) {
    const bytes = new Uint8Array(await file.arrayBuffer());
    const zip = zipEntries(bytes);
    const stringsXml = await readXml(bytes, zip, 'xl/sharedStrings.xml');
    const sharedStrings = stringsXml ? childrenNamed(stringsXml.documentElement, 'si').map(inlineText) : [];
    const sheet = await readXml(bytes, zip, await firstSheetPath(bytes, zip));

    if (!sheet) {
        throw new Error('The Excel file has no readable worksheet.');
    }

    const rows = [];

    for (const row of sheet.getElementsByTagNameNS('*', 'row')) {
        const cells = [];
        let nextColumn = 0;

        for (const cell of childrenNamed(row, 'c')) {
            const reference = cell.getAttribute('r');
            const column = reference ? columnIndex(reference) : nextColumn;
            const type = cell.getAttribute('t') ?? '';
            const raw = childrenNamed(cell, 'v')[0]?.textContent ?? '';
            let value = raw;

            if (type === 's') {
                value = sharedStrings[Number.parseInt(raw, 10)] ?? '';
            } else if (type === 'inlineStr') {
                value = inlineText(childrenNamed(cell, 'is')[0]);
            } else if (type === 'b') {
                value = raw === '1' ? 'TRUE' : 'FALSE';
            }

            cells[column] = value.trim();
            nextColumn = column + 1;
        }

        if (cells.length) {
            rows.push({ index: Math.max(0, Number.parseInt(row.getAttribute('r') ?? '1', 10) - 1), cells: Array.from(cells, (value) => value ?? '') });
        }
    }

    return rows.sort((a, b) => a.index - b.index).map((row) => row.cells);
}
