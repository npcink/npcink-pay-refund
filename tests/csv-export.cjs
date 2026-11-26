const assert = require('node:assert/strict');
const { readFileSync, writeFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');

let click;
let rows;
let csv;
const strings = {
  csvId: '退款编号', csvAmount: '退款金额', csvTime: '退款时间', csvOrder: '订单号',
  csvUser: '操作员工', csvType: '类型', csvReason: '退款原因', csvFilename: '退款订单记录表.csv',
};
const noop = () => {};
const $ = () => ({ length: 0, click: (handler) => { click = handler; }, on: noop });
$.ajax = ({ success }) => success({ success: true, data: { rows } });

vm.runInNewContext(readFileSync(resolve(__dirname, '../admin/js/npcink-pay-refund-settings.js'), 'utf8'), {
  jQuery: () => ({ ready: (init) => init($) }),
  window: { npcinkPayRefundSettings: { strings } },
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

strings.csvOrder = 'Order number';
click();
assert.equal(csv.split('\r\n')[0], '\uFEFF"退款编号","退款金额","退款时间","Order number","操作员工","类型","退款原因"');
strings.csvOrder = '订单号';

if (process.argv[2]) {
  const fixture = { id: 1, n_amount: '7.05', n_time: '2026-11-26 10:00:00', n_user: '合成操作员', n_type: '微信' };
  rows = [
    { ...fixture, n_order: '00001234', n_reason: '中文,"引号"\n下一行' },
    { ...fixture, id: 2, n_order: '12345678901234567890', n_reason: '=1+1' },
    { ...fixture, id: 3, n_order: 'SYNTHETIC-03', n_reason: '+1+1' },
    { ...fixture, id: 4, n_order: 'SYNTHETIC-04', n_reason: '-1+1' },
    { ...fixture, id: 5, n_order: 'SYNTHETIC-05', n_reason: '@SUM(1,1)' },
  ];
  click();
  writeFileSync(process.argv[2], csv, { flag: 'wx' });
}
