// Parts import: a visible file picker that checks the chosen sheet in the browser before upload,
// and a 0-100% progress overlay while it uploads and the server reviews it. The server re-validates everything.

import { submitWithProgress } from './import-progress';
import { canReadXlsx, readXlsxRows } from './xlsx-reader';

const ACCEPTED_EXTENSIONS = ['csv', 'xlsx', 'txt'];

// Same heading rules as CatalogProductImportService::fieldForHeading(). Warning Number is optional.
const REQUIRED_COLUMNS = {
    product_type_name: 'Item Name',
    product_name: 'Description',
    quantity: 'Qty',
    unit_cost: 'Cost Price',
    minimum_selling_price: 'Min Selling Price',
    maximum_selling_price: 'Max Selling Price',
};
const WARNING_COLUMN = 'low_stock_level';

function fieldForHeading(heading) {
    const normalized = String(heading).toLowerCase().replace(/[^a-z]/g, '');

    if (normalized === '') return null;
    if (normalized.startsWith('min')) return 'minimum_selling_price';
    if (normalized.startsWith('max')) return 'maximum_selling_price';
    if (['warning', 'lowstock', 'reorder'].some((word) => normalized.includes(word))) return 'low_stock_level';
    if (normalized.includes('cost')) return 'unit_cost';
    if (['qty', 'quantity'].includes(normalized) || normalized.startsWith('qty')) return 'quantity';
    if (normalized.includes('description') || normalized === 'productname') return 'product_name';
    if (normalized.includes('itemname') || ['item', 'parttype', 'producttype'].includes(normalized)) return 'product_type_name';

    return null;
}

// Minimal RFC 4180 parser, matching SpreadsheetReader::readCsv() (delimiter picked from the first line).
function parseCsv(text) {
    const source = text.replace(/^﻿/, '');
    const firstLine = source.split(/\r?\n/, 1)[0] ?? '';
    const delimiter = (firstLine.match(/;/g)?.length ?? 0) > (firstLine.match(/,/g)?.length ?? 0) ? ';' : ',';
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;

    for (let index = 0; index < source.length; index++) {
        const char = source[index];

        if (quoted) {
            if (char === '"' && source[index + 1] === '"') {
                cell += '"';
                index++;
            } else if (char === '"') {
                quoted = false;
            } else {
                cell += char;
            }
        } else if (char === '"') {
            quoted = true;
        } else if (char === delimiter) {
            row.push(cell);
            cell = '';
        } else if (char === '\n' || char === '\r') {
            if (char === '\r' && source[index + 1] === '\n') index++;
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
        } else {
            cell += char;
        }
    }

    if (cell !== '' || row.length) {
        row.push(cell);
        rows.push(row);
    }

    return rows;
}

const isBlank = (value) => String(value ?? '').trim() === '';
const isBlankRow = (cells) => cells.every(isBlank);

// Same checks as CatalogProductImportService::mapRows(): header row, required columns, row count.
// A missing or blank Warning Number is allowed and reported as a warning with the default level.
function checkRows(rows, { maxRows, defaultLowStock }) {
    const headerIndex = rows.findIndex((cells) => !isBlankRow(cells));

    if (headerIndex === -1) {
        return { ok: false, messages: ['The file is empty.'] };
    }

    const columns = {};
    rows[headerIndex].forEach((heading, index) => {
        const field = fieldForHeading(heading);
        if (field && !(field in columns)) columns[field] = index;
    });

    const missing = Object.entries(REQUIRED_COLUMNS).filter(([field]) => !(field in columns)).map(([, label]) => label);

    if (missing.length) {
        return {
            ok: false,
            messages: [`Missing column(s): ${missing.join(', ')}.`, 'Download the template to see the expected headings.'],
        };
    }

    // The server ignores rows whose recognised columns are all empty.
    const mapped = Object.values(columns);
    const partRows = rows.slice(headerIndex + 1).filter((cells) => mapped.some((index) => !isBlank(cells[index])));

    if (partRows.length === 0) {
        return { ok: false, messages: ['The file has headings but no part rows.'] };
    }

    if (partRows.length > maxRows) {
        return {
            ok: false,
            messages: [`The file has ${partRows.length.toLocaleString()} part rows. A file can contain at most ${maxRows.toLocaleString()}; split it into smaller files.`],
        };
    }

    const ready = `${partRows.length.toLocaleString()} part row(s) ready to upload.`;

    if (!(WARNING_COLUMN in columns)) {
        return {
            ok: true,
            warning: true,
            messages: [
                `There is no Warning Number column. Every part will use the default low-stock warning level of ${defaultLowStock}.`,
                ready,
            ],
        };
    }

    const blankWarnings = partRows.filter((cells) => isBlank(cells[columns[WARNING_COLUMN]])).length;

    if (blankWarnings > 0) {
        return {
            ok: true,
            warning: true,
            messages: [
                `${blankWarnings.toLocaleString()} part row(s) have no Warning Number and will use the default of ${defaultLowStock}.`,
                ready,
            ],
        };
    }

    return {
        ok: true,
        messages: ['All columns found, including Warning Number.', ready],
    };
}

// An .xlsx file is a zip archive whose entry names are stored as plain text, so a renamed .xls or .csv
// is caught first. The first worksheet is then read and checked exactly like a CSV.
async function checkXlsx(file, limits) {
    const head = new Uint8Array(await file.slice(0, 4).arrayBuffer());
    const isZip = head[0] === 0x50 && head[1] === 0x4b && head[2] === 0x03 && head[3] === 0x04;
    const hasWorkbook = isZip && new TextDecoder('latin1').decode(await file.arrayBuffer()).includes('xl/workbook.xml');

    if (!hasWorkbook) {
        return {
            ok: false,
            messages: ['This is not a valid Excel (.xlsx) workbook. Older .xls files must be saved as .xlsx (or .csv) first.'],
        };
    }

    const notCheckedHere = {
        ok: true,
        messages: ['Valid Excel workbook.', 'This browser could not read its columns, so they are checked on the next step.'],
    };

    if (!canReadXlsx()) {
        return notCheckedHere;
    }

    try {
        return checkRows(await readXlsxRows(file), limits);
    } catch {
        // Never block a file only because the browser reader failed; the server reads it again on upload.
        return notCheckedHere;
    }
}

async function checkFile(file, limits) {
    const { maxBytes } = limits;
    const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';

    if (!ACCEPTED_EXTENSIONS.includes(extension)) {
        return { ok: false, messages: ['Only .csv and .xlsx files are accepted. Older .xls files must be saved as .xlsx first.'] };
    }

    if (file.size === 0) {
        return { ok: false, messages: ['The file is empty.'] };
    }

    if (file.size > maxBytes) {
        const sizeMb = (file.size / 1024 / 1024).toFixed(1);
        return { ok: false, messages: [`The file is ${sizeMb} MB. The limit is ${Math.round(maxBytes / 1024 / 1024)} MB.`] };
    }

    if (extension === 'xlsx') {
        return checkXlsx(file, limits);
    }

    return checkRows(parseCsv(await file.text()), limits);
}

export function initImportFileCheck() {
    const form = document.querySelector('[data-import-form]');
    const input = form?.querySelector('[data-import-file]');

    if (!form || !input) {
        return;
    }

    const fileName = form.querySelector('[data-import-file-name]');
    const buttonLabel = form.querySelector('[data-import-file-button-label]');
    const panel = form.querySelector('[data-import-file-check]');
    const title = form.querySelector('[data-import-file-check-title]');
    const list = form.querySelector('[data-import-file-check-messages]');
    const submit = form.querySelector('[data-import-submit]');
    const overlay = form.querySelector('[data-import-progress]');
    const limits = {
        maxBytes: Number(form.dataset.maxBytes) || 5 * 1024 * 1024,
        maxRows: Number(form.dataset.maxRows) || 1000,
        defaultLowStock: Number(form.dataset.defaultLowStock) || 10,
    };
    let checkRun = 0;

    function showState(state, heading, messages = []) {
        panel.hidden = false;
        panel.dataset.state = state;
        title.textContent = heading;
        list.replaceChildren(...messages.map((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            return item;
        }));
    }

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        const run = ++checkRun;

        input.setCustomValidity('');

        if (!file) {
            fileName.textContent = 'No file chosen';
            buttonLabel.textContent = 'Choose file';
            panel.hidden = true;
            submit.disabled = false;
            return;
        }

        fileName.textContent = `${file.name} (${(file.size / 1024).toFixed(0)} KB)`;
        buttonLabel.textContent = 'Change file';
        submit.disabled = true;
        showState('checking', 'Checking the file...');

        let result;

        try {
            result = await checkFile(file, limits);
        } catch {
            result = { ok: false, messages: ['The file could not be read. Make sure it is not open in another program and try again.'] };
        }

        // A newer selection replaced this one while it was being read.
        if (run !== checkRun) {
            return;
        }

        if (result.ok && result.warning) {
            showState('warning', 'File accepted - check the Warning Number', result.messages);
            submit.disabled = false;
        } else if (result.ok) {
            showState('ok', 'File accepted', result.messages);
            submit.disabled = false;
        } else {
            showState('error', 'This file cannot be uploaded', result.messages);
            input.setCustomValidity(result.messages[0]);
        }
    });

    // An invalid file carries a custom validity message, so the browser blocks the submit before this runs.
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        if (submit.disabled) {
            return;
        }

        submitWithProgress(form, overlay, {
            uploadShare: 50,
            stepStarts: [0, 50, 68, 84],
            doneTitle: 'Review ready',
            failTitle: 'This file could not be uploaded',
        });
    });

    // Hide the overlay again if the browser restores this page from its back/forward cache.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            overlay.hidden = true;
            submit.disabled = false;
        }
    });
}
