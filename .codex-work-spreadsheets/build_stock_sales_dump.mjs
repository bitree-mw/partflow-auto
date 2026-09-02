import fs from "node:fs/promises";
import path from "node:path";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const workDir = path.resolve(".codex-work-spreadsheets");
const outputDir = path.resolve("outputs/01a05c95-63e6-7e63-bbc5-46873c7d2e53");
const payload = JSON.parse(await fs.readFile(path.join(workDir, "partflow_stock_sales_data.json"), "utf8"));
const { data, validation } = payload;
const workbook = Workbook.create();
const summary = workbook.worksheets.add("Summary");
const stock = workbook.worksheets.add("Stock");
const sales = workbook.worksheets.add("Today Sales");
const saleItems = workbook.worksheets.add("Sale Items");

const navy = "#17324D";
const teal = "#0F766E";
const blue = "#2563EB";
const lightBlue = "#E8F1FB";
const lightTeal = "#E7F6F3";
const amber = "#B45309";
const lightAmber = "#FEF3C7";
const red = "#B91C1C";
const lightRed = "#FEE2E2";
const green = "#166534";
const lightGreen = "#DCFCE7";
const slate = "#475569";
const lightSlate = "#F1F5F9";
const white = "#FFFFFF";
const border = "#CBD5E1";
const currencyFormat = '"MWK" #,##0.00';
const stockEndRow = data.stock_rows.length + 1;
const salesEndRow = Math.max(2, data.sales.length + 1);
const itemEndRow = Math.max(2, data.sale_items.length + 1);

function applyTitle(sheet, range, text) {
  sheet.getRange(range).merge();
  sheet.getRange(range.split(":")[0]).values = [[text]];
  sheet.getRange(range).format = {
    fill: navy,
    font: { bold: true, color: white, size: 18 },
    verticalAlignment: "center",
    horizontalAlignment: "left",
  };
  sheet.getRange(range).format.rowHeight = 32;
}

function styleHeader(range) {
  range.format = {
    fill: teal,
    font: { bold: true, color: white },
    horizontalAlignment: "center",
    verticalAlignment: "center",
    wrapText: true,
    borders: { preset: "outside", style: "thin", color: border },
  };
  range.format.rowHeight = 28;
}

function styleCard(sheet, labelRange, valueRange, label, formula, numberFormat = "#,##0") {
  sheet.getRange(labelRange).merge();
  sheet.getRange(valueRange).merge();
  sheet.getRange(labelRange.split(":")[0]).values = [[label]];
  sheet.getRange(valueRange.split(":")[0]).formulas = [[formula]];
  sheet.getRange(labelRange).format = {
    fill: lightBlue,
    font: { bold: true, color: navy, size: 10 },
    horizontalAlignment: "center",
    verticalAlignment: "center",
    borders: { preset: "outside", style: "thin", color: border },
  };
  sheet.getRange(valueRange).format = {
    fill: white,
    font: { bold: true, color: navy, size: 16 },
    horizontalAlignment: "center",
    verticalAlignment: "center",
    numberFormat,
    borders: { preset: "outside", style: "thin", color: border },
  };
  sheet.getRange(labelRange).format.rowHeight = 22;
  sheet.getRange(valueRange).format.rowHeight = 32;
}

summary.showGridLines = false;
applyTitle(summary, "A1:K1", "PartFlow Auto — Stock & Today’s Sales");
summary.getRange("A2:K2").merge();
summary.getRange("A2").values = [[`Snapshot for ${data.meta.date} · ${data.meta.timezone} · generated ${data.meta.generated_at}`]];
summary.getRange("A2:K2").format = {
  fill: lightSlate,
  font: { color: slate, italic: true },
  verticalAlignment: "center",
};
summary.getRange("A2:K2").format.rowHeight = 22;

summary.getRange("A4:B7").values = [
  ["Reporting date", data.meta.date],
  ["Timezone", data.meta.timezone],
  ["Currency", data.meta.currency],
  ["Sales definition", "Completed or approved sale documents dated today"],
];
summary.getRange("A4:A7").format = { font: { bold: true, color: navy }, fill: lightSlate };
summary.getRange("B4:B7").format = { wrapText: true };
summary.getRange("A4:B7").format.borders = { preset: "outside", style: "thin", color: border };

styleCard(summary, "D4:E4", "D5:E6", "On-hand units", `=SUM('Stock'!$G$2:$G$${stockEndRow})`);
styleCard(summary, "G4:H4", "G5:H6", "Available units", `=SUM('Stock'!$I$2:$I$${stockEndRow})`);
styleCard(summary, "J4:K4", "J5:K6", "Stock cost value", `=SUM('Stock'!$N$2:$N$${stockEndRow})`, currencyFormat);
styleCard(summary, "D8:E8", "D9:E10", "Out-of-stock rows", `=COUNTIF('Stock'!$K$2:$K$${stockEndRow},"Out of stock")`);
styleCard(summary, "G8:H8", "G9:H10", "Sales today", `=COUNTIF('Today Sales'!$E$2:$E$${salesEndRow},"Yes")`);
styleCard(summary, "J8:K8", "J9:K10", "Gross sales today", `=SUMIF('Today Sales'!$E$2:$E$${salesEndRow},"Yes",'Today Sales'!$O$2:$O$${salesEndRow})`, currencyFormat);

const siteStart = 13;
summary.getRange(`A${siteStart}:K${siteStart}`).merge();
summary.getRange(`A${siteStart}`).values = [["Stock by site"]];
summary.getRange(`A${siteStart}:K${siteStart}`).format = {
  fill: navy,
  font: { bold: true, color: white, size: 12 },
};
const siteHeaderRow = siteStart + 1;
const siteHeaders = [["Site", "Code", "Stock Rows", "On Hand", "Reserved", "Available", "Out of Stock", "Low Stock", "Cost Value", "Retail Value", "Coverage Note"]];
summary.getRange(`A${siteHeaderRow}:K${siteHeaderRow}`).values = siteHeaders;
styleHeader(summary.getRange(`A${siteHeaderRow}:K${siteHeaderRow}`));
const siteData = data.stock_by_site.map((row) => [
  row.site_name,
  row.site_code,
  row.stock_rows,
  row.quantity_on_hand,
  row.reserved_quantity,
  row.available_quantity,
  row.out_of_stock_rows,
  row.low_stock_rows,
  row.stock_cost_value,
  row.stock_retail_value,
  "Existing site-stock rows only",
]);
if (siteData.length) {
  const siteDataStart = siteHeaderRow + 1;
  const siteDataEnd = siteHeaderRow + siteData.length;
  summary.getRange(`A${siteDataStart}:K${siteDataEnd}`).values = siteData;
  summary.getRange(`C${siteDataStart}:H${siteDataEnd}`).format.numberFormat = "#,##0";
  summary.getRange(`I${siteDataStart}:J${siteDataEnd}`).format.numberFormat = currencyFormat;
  summary.getRange(`A${siteDataStart}:K${siteDataEnd}`).format.borders = {
    insideHorizontal: { style: "thin", color: border },
    bottom: { style: "thin", color: border },
  };
}
const qaStart = siteHeaderRow + siteData.length + 3;
summary.getRange(`A${qaStart}:K${qaStart}`).merge();
summary.getRange(`A${qaStart}`).values = [["Validation & caveats"]];
summary.getRange(`A${qaStart}:K${qaStart}`).format = {
  fill: validation.stock_rows_without_movements > 0 ? lightAmber : lightGreen,
  font: { bold: true, color: validation.stock_rows_without_movements > 0 ? amber : green, size: 12 },
};
const qaRows = [
  ["Sales cross-check", `${validation.today_sale_documents_by_document_date} dated today; ${validation.sale_documents_created_today} created today; ${validation.sale_out_movements_created_today} sale-out movements; ${validation.payments_recorded_today} payments.`],
  ["Stock balance check", `${validation.latest_movement_balance_mismatches} latest movement/balance mismatches; ${data.stock_summary.negative_on_hand_rows} negative on-hand rows; ${data.stock_summary.reserved_exceeds_on_hand_rows} rows where reserved exceeds on-hand.`],
  ["Movement traceability", `${validation.stock_rows_without_movements} of ${validation.stock_rows} stock rows have no movement history. Treat those balances as current database values without an auditable movement trail.`],
  ["Coverage", `${validation.active_products} active products and ${validation.active_sites} active sites; this dump contains ${validation.stock_rows} existing site-product rows. Missing site-product combinations are not synthesized as zero rows.`],
  ["Valuation basis", "Cost value uses the latest completed/approved purchase unit cost when available, otherwise the product default purchase price. Retail value uses the current default selling price."],
];
qaRows.forEach(([label, note], index) => {
  const row = qaStart + 1 + index;
  summary.getRange(`A${row}`).values = [[label]];
  summary.getRange(`B${row}:K${row}`).merge();
  summary.getRange(`B${row}`).values = [[note]];
  summary.getRange(`A${row}`).format = { font: { bold: true, color: navy }, fill: lightSlate };
  summary.getRange(`B${row}:K${row}`).format = { wrapText: true, verticalAlignment: "center" };
  summary.getRange(`A${row}:K${row}`).format.borders = { preset: "outside", style: "thin", color: border };
  summary.getRange(`A${row}:K${row}`).format.rowHeight = index === 2 || index === 3 || index === 4 ? 34 : 26;
});

summary.freezePanes.freezeRows(2);
summary.getRange("A1:K40").format.font = { name: "Aptos" };
summary.getRange("A:K").format.columnWidth = 12;
summary.getRange("A:A").format.columnWidth = 20;
summary.getRange("B:B").format.columnWidth = 44;
summary.getRange("C:K").format.columnWidth = 15;
summary.getRange("I:J").format.columnWidth = 22;
summary.getRange("K:K").format.columnWidth = 27;

stock.showGridLines = false;
const stockHeaders = [["Site ID", "Site Code", "Site Name", "Product ID", "Product Code", "Product Name", "On Hand", "Reserved", "Available", "Low Stock Level", "Status", "Unit Cost Basis", "Unit Retail Price", "Stock Cost Value", "Stock Retail Value", "Site Active", "Product Active"]];
const stockValues = data.stock_rows.map((row) => [
  row.site_id,
  row.site_code,
  row.site_name,
  row.product_id,
  row.product_code,
  row.product_name,
  row.quantity_on_hand,
  row.reserved_quantity,
  row.available_quantity,
  row.low_stock_level,
  row.stock_status,
  row.unit_cost_basis,
  row.unit_retail_price,
  row.stock_cost_value,
  row.stock_retail_value,
  row.site_active ? "Yes" : "No",
  row.product_active ? "Yes" : "No",
]);
stock.getRange(`A1:Q${stockEndRow}`).values = [...stockHeaders, ...stockValues];
styleHeader(stock.getRange("A1:Q1"));
stock.getRange(`A2:Q${stockEndRow}`).format.font = { name: "Aptos", size: 10 };
stock.getRange(`A2:A${stockEndRow}`).format.numberFormat = "0";
stock.getRange(`D2:D${stockEndRow}`).format.numberFormat = "0";
stock.getRange(`G2:J${stockEndRow}`).format.numberFormat = "#,##0";
stock.getRange(`L2:O${stockEndRow}`).format.numberFormat = currencyFormat;
stock.getRange(`K2:K${stockEndRow}`).conditionalFormats.add("containsText", { text: "Out of stock", format: { fill: lightRed, font: { color: red, bold: true } } });
stock.getRange(`K2:K${stockEndRow}`).conditionalFormats.add("containsText", { text: "Low stock", format: { fill: lightAmber, font: { color: amber, bold: true } } });
stock.getRange(`K2:K${stockEndRow}`).conditionalFormats.add("containsText", { text: "In stock", format: { fill: lightGreen, font: { color: green, bold: true } } });
const stockTable = stock.tables.add(`A1:Q${stockEndRow}`, true, "StockDumpTable");
stockTable.style = "TableStyleMedium2";
stock.freezePanes.freezeRows(1);
stock.freezePanes.freezeColumns(3);
stock.getRange("A:Q").format.columnWidth = 13;
stock.getRange("C:C").format.columnWidth = 20;
stock.getRange("E:E").format.columnWidth = 22;
stock.getRange("F:F").format.columnWidth = 34;
stock.getRange("K:K").format.columnWidth = 17;
stock.getRange("L:O").format.columnWidth = 18;

sales.showGridLines = false;
const saleHeaders = [["Sale ID", "Invoice", "Date/Time", "Status", "Included In Sales Total", "Site Code", "Site", "Customer", "Cashier", "Line Count", "Units Sold", "Subtotal", "Discount", "Tax", "Total", "Paid", "Balance", "Payment Status", "Profit"]];
sales.getRange("A1:S1").values = saleHeaders;
styleHeader(sales.getRange("A1:S1"));
if (data.sales.length) {
  const saleValues = data.sales.map((row) => [
    row.id,
    row.document_number,
    row.document_date ? new Date(String(row.document_date).replace(" ", "T") + "+02:00") : null,
    row.status,
    row.included_in_sales_total ? "Yes" : "No",
    row.site_code,
    row.site_name,
    row.customer_name ?? "Walk-in",
    row.cashier_name,
    row.line_count,
    row.units_sold,
    row.subtotal_amount,
    row.discount_amount,
    row.tax_amount,
    row.total_amount,
    row.paid_amount,
    row.balance_amount,
    row.payment_status,
    row.profit_amount,
  ]);
  sales.getRange(`A2:S${data.sales.length + 1}`).values = saleValues;
  const salesTable = sales.tables.add(`A1:S${data.sales.length + 1}`, true, "TodaySalesTable");
  salesTable.style = "TableStyleMedium2";
} else {
  sales.getRange("A2:S2").merge();
  sales.getRange("A2").values = [[`No sale documents dated ${data.meta.date}.`]];
  sales.getRange("A2:S2").format = { fill: lightGreen, font: { color: green, italic: true }, horizontalAlignment: "left" };
}
sales.getRange(`C2:C${salesEndRow}`).format.numberFormat = "yyyy-mm-dd hh:mm";
sales.getRange(`J2:K${salesEndRow}`).format.numberFormat = "#,##0";
sales.getRange(`L2:Q${salesEndRow}`).format.numberFormat = currencyFormat;
sales.getRange(`S2:S${salesEndRow}`).format.numberFormat = currencyFormat;
sales.freezePanes.freezeRows(1);
sales.getRange("A:S").format.columnWidth = 14;
sales.getRange("B:B").format.columnWidth = 20;
sales.getRange("C:C").format.columnWidth = 20;
sales.getRange("E:E").format.columnWidth = 22;
sales.getRange("G:I").format.columnWidth = 20;

saleItems.showGridLines = false;
const itemHeaders = [["Invoice", "Date/Time", "Status", "Included In Sales Total", "Site Code", "Site", "Product Code", "Product Name", "Quantity", "Unit Cost", "Unit Price", "Discount", "Tax Rate", "Tax Amount", "Line Total", "Profit"]];
saleItems.getRange("A1:P1").values = itemHeaders;
styleHeader(saleItems.getRange("A1:P1"));
if (data.sale_items.length) {
  const itemValues = data.sale_items.map((row) => [
    row.document_number,
    row.document_date ? new Date(String(row.document_date).replace(" ", "T") + "+02:00") : null,
    row.status,
    row.included_in_sales_total ? "Yes" : "No",
    row.site_code,
    row.site_name,
    row.product_code,
    row.product_name,
    row.quantity,
    row.unit_cost,
    row.unit_price,
    row.discount_amount,
    row.tax_rate / 100,
    row.tax_amount,
    row.line_total,
    row.profit_amount,
  ]);
  saleItems.getRange(`A2:P${data.sale_items.length + 1}`).values = itemValues;
  const itemTable = saleItems.tables.add(`A1:P${data.sale_items.length + 1}`, true, "TodaySaleItemsTable");
  itemTable.style = "TableStyleMedium2";
} else {
  saleItems.getRange("A2:P2").merge();
  saleItems.getRange("A2").values = [[`No sale line items dated ${data.meta.date}.`]];
  saleItems.getRange("A2:P2").format = { fill: lightGreen, font: { color: green, italic: true }, horizontalAlignment: "left" };
}
saleItems.getRange(`B2:B${itemEndRow}`).format.numberFormat = "yyyy-mm-dd hh:mm";
saleItems.getRange(`I2:I${itemEndRow}`).format.numberFormat = "#,##0";
saleItems.getRange(`J2:L${itemEndRow}`).format.numberFormat = currencyFormat;
saleItems.getRange(`M2:M${itemEndRow}`).format.numberFormat = "0.00%";
saleItems.getRange(`N2:P${itemEndRow}`).format.numberFormat = currencyFormat;
saleItems.freezePanes.freezeRows(1);
saleItems.getRange("A:P").format.columnWidth = 15;
saleItems.getRange("A:A").format.columnWidth = 20;
saleItems.getRange("B:B").format.columnWidth = 20;
saleItems.getRange("H:H").format.columnWidth = 34;

const summaryInspect = await workbook.inspect({
  kind: "table",
  range: `Summary!A1:K${qaStart + qaRows.length}`,
  include: "values,formulas",
  tableMaxRows: 40,
  tableMaxCols: 11,
});
console.log("SUMMARY_INSPECT");
console.log(summaryInspect.ndjson);

const stockInspect = await workbook.inspect({
  kind: "table",
  range: `Stock!A1:Q8`,
  include: "values,formulas",
  tableMaxRows: 8,
  tableMaxCols: 17,
});
console.log("STOCK_SAMPLE");
console.log(stockInspect.ndjson);

const errors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A",
  options: { useRegex: true, maxResults: 100 },
  summary: "final formula error scan",
});
console.log("FORMULA_ERRORS");
console.log(errors.ndjson);

await fs.mkdir(outputDir, { recursive: true });
for (const sheetName of ["Summary", "Stock", "Today Sales", "Sale Items"]) {
  const preview = await workbook.render({ sheetName, autoCrop: "all", scale: 1, format: "png" });
  const safeName = sheetName.toLowerCase().replaceAll(" ", "_");
  await fs.writeFile(path.join(workDir, `preview_${safeName}.png`), new Uint8Array(await preview.arrayBuffer()));
}

const outputPath = path.join(outputDir, `partflow_stock_sales_${data.meta.date}.xlsx`);
const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);
console.log("OUTPUT_PATH");
console.log(outputPath);
