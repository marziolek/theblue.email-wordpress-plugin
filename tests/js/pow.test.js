// Checks that the solver in tbef-protect.js finds numbers theblue.email accepts:
// sha256("{salt}:{email}:{nonce}") must start with `bits` zero bits, over the
// UTF-8 bytes of the string. Run with: node --test tests/js/pow.test.js
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const crypto = require('node:crypto');
const fs     = require('node:fs');
const path   = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../assets/js/tbef-protect.js'), 'utf8');
const block  = source.match(/\/\* pow:start \*\/([\s\S]*?)\/\* pow:end \*\//);
assert.ok(block, 'the pow:start / pow:end block is in tbef-protect.js');

// eslint-disable-next-line no-new-func
const powSearch = new Function(block[1] + '; return powSearch;')();

function leadingZeroBits(buf) {
    let bits = 0;
    for (const byte of buf) {
        if (byte === 0) { bits += 8; continue; }
        return bits + Math.clz32(byte) - 24;
    }
    return bits;
}

function solve(prefix, bits) {
    for (let from = 0; ; from += 20000) {
        const n = powSearch(prefix, bits, from, 20000);
        if (n >= 0) { return n; }
    }
}

const cases = [
    ['1767225600.12.abcdef0123456789.0123456789abcdef0123456789abcdef', 'ada@example.com'],
    ['1767225600.12.abcdef0123456789.0123456789abcdef0123456789abcdef', 'zażółć@przykład.pl'],
    ['1767225600.12.' + 'f'.repeat(16) + '.' + 'a'.repeat(32), 'a.very.long.address.that.crosses.a.block.boundary@sub.example.co.uk'],
];

for (const [salt, email] of cases) {
    test('a solution for ' + email + ' has the zero bits the platform asks for', () => {
        const prefix = salt + ':' + email + ':';
        const nonce  = solve(prefix, 12);
        const digest = crypto.createHash('sha256').update(prefix + nonce, 'utf8').digest();
        assert.ok(leadingZeroBits(digest) >= 12);
    });
}

test('it returns -1 when no number in the range solves it', () => {
    assert.strictEqual(powSearch('x:y:', 30, 0, 10), -1);
});
