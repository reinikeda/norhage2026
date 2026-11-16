/**
 * Custom-cut sheet price: m² (or metre) × measure + cutting fee.
 *
 * Run: node public/wp-content/themes/astra-custom-for-norhage/tests/test-custom-cut-sheet.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const file = path.join(__dirname, '../assets/js/custom-cutting.js');
const sandbox = { globalThis: {}, console };
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(file, 'utf8'), sandbox);

const paid = sandbox.NHCustomCutPrice.paidSheet;
let failures = 0;

function assert(label, ok) {
  if (ok) {
    console.log('ok  ' + label);
    return;
  }
  failures++;
  console.log('FAIL  ' + label);
}

const vat = [{ rate: 25, compound: false }];
const ctx = {
  round_gross: true,
  raw_fee: 28,
  cut_fee: 35,
  tax_rates: vat
};

assert('1 m² sheet is 171 + 35 = 206', paid(1, 136.8, 171, ctx) === 206);
assert('1 m² sheet is not the m² price with the fee taken off', paid(1, 136.8, 171, ctx) !== 136);
assert('1 m² sheet is not the material alone', paid(1, 136.8, 171, ctx) !== 171);

assert('2 m² sheet is 377', paid(2, 136.8, 171, ctx) === 377);

const noRawFee = Object.assign({}, ctx, { raw_fee: 0 });
assert('a shown cutting fee is still added when the raw fee is missing', paid(1, 136.8, 171, noRawFee) === 206);

const noRates = Object.assign({}, ctx, { tax_rates: [] });
assert('shown prices are used when VAT rates did not reach the page', paid(2, 136.8, 171, noRates) === 377);

const rateObject = Object.assign({}, ctx, { tax_rates: { 0: { rate: 25, compound: false } } });
assert('VAT rates stored as an object still add the fee', paid(1, 136.8, 171, rateObject) === 206);

const eur = { round_gross: false, raw_fee: 28, cut_fee: 35, tax_rates: vat };
const eurSheet = paid(1, 136.8, 171, eur);
assert('EUR keeps cents and still adds the fee', Math.abs(eurSheet - 206) < 0.001);

const big = paid(10, 136.48, 171, ctx);
assert('a large sheet stays above the material price', big > Math.round(136.48 * 10 * 1.25));
assert('a large sheet does not add the fee twice', big < Math.round(136.48 * 10 * 1.25) + 35 + 2);

if (failures > 0) {
  console.error(failures + ' failed');
  process.exit(1);
}
console.log('all passed');
