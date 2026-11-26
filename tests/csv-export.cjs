const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');

let click;
let rows;
let csv;
const noop = () => {};
const $ = () => ({ length: 0, click: (handler) => { click = handler; }, on: noop });
$.ajax = ({ success }) => success({ success: true, data: { rows } });

vm.runInNewContext(readFileSync(resolve(__dirname, '../admin/js/npcink-pay-refund-settings.js'), 'utf8'), {
  jQuery: () => ({ ready: (init) => init($) }),
  window: {},
  document: { createElement: () => ({ click: noop }), body: { appendChild: noop, removeChild: noop } },
  Blob: class { constructor(parts) { csv = parts.join(''); } },
  URL: { createObjectURL: () => 'blob:test', revokeObjectURL: noop },
});

const cells = ['=100', '+100', '-100', '@text', '\ttext', '\rtext', '\ntext', '  =100', '\uFF1D100', '中文,"引号"\n下一行', null];
// The reason comes first in the JSON object but must stay in the last CSV column.
rows = cells.map((cell) => ({ n_reason: cell, id: 1, n_order: 'ORDER' }));
click();
const expectedRows = cells.map((cell, index) => {
  const expected = index < 9 ? "'" + cell : (cell ?? '');
  return '"1","","","ORDER","","","' + expected.replace(/"/g, '""') + '"\r\n';
});
assert.equal(csv, '\uFEFF"退款编号","退款金额","退款时间","订单号","操作员工","类型","退款原因"\r\n' + expectedRows.join(''));
assert.equal(Buffer.from(csv).subarray(0, 3).toString('hex'), 'efbbbf');
console.log('CSV download passed: UTF-8 BOM, column order, quoting and defensive cell escaping.');
