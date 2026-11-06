/* tbef-protect.js - proof of work for Gravity Forms and Contact Form 7 forms
 * that send newsletter signups to theblue.email.
 *
 * theblue.email accepts a signup only with a solved puzzle: a number that makes
 * sha256("{salt}:{email}:{number}") start with `bits` zero bits, where the salt
 * comes from the platform and is bound to the theblue.email form. Its own embed
 * script solves it for its own forms; this script does the same for forms this
 * site renders, so a signup relayed from them is checked exactly the same way.
 *
 * The puzzle is fetched from this site (/wp-json/tbef/v1/challenge?target=..),
 * which asks theblue.email with the site's key. The page carries only the
 * target name, never a key.
 *
 * Work starts as soon as an address is typed, so it is normally done before
 * the visitor presses the button. If not, the submission waits for it (at
 * most a few seconds) and then goes on, solved or not: the form itself always
 * submits, only the newsletter signup depends on the work.
 *
 * powSearch and solve are copied from theblue.email's tbe-embed.js and must hash
 * the same bytes; tests/js/pow.test.js checks the result against PHP.
 */
(function () {
    'use strict';

    var config  = window.tbefProtect || {};
    var restUrl = config.restUrl || '/wp-json/tbef/v1/';

    // A salt is good for an hour on the platform; start again before that.
    var MAX_AGE = 50 * 60 * 1000;

    // How long a submission may wait for the work before it goes on anyway.
    var WAIT = 8000;

    /* pow:start */
    function powSearch(prefix, bits, from, count) {
        var K = new Int32Array([
            0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
            0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
            0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
            0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
            0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
            0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
            0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
            0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
        ]);
        var W = new Int32Array(64);

        // One SHA-256 round over the 64 bytes of buf starting at off.
        function compress(H, buf, off) {
            var a = H[0], b = H[1], c = H[2], d = H[3], e = H[4], f = H[5], g = H[6], h = H[7];
            var t, t1, t2, s0, s1;

            for (t = 0; t < 16; t++) {
                W[t] = (buf[off + t * 4] << 24) | (buf[off + t * 4 + 1] << 16) | (buf[off + t * 4 + 2] << 8) | buf[off + t * 4 + 3];
            }
            for (t = 16; t < 64; t++) {
                s0 = W[t - 15];
                s0 = ((s0 >>> 7) | (s0 << 25)) ^ ((s0 >>> 18) | (s0 << 14)) ^ (s0 >>> 3);
                s1 = W[t - 2];
                s1 = ((s1 >>> 17) | (s1 << 15)) ^ ((s1 >>> 19) | (s1 << 13)) ^ (s1 >>> 10);
                W[t] = (W[t - 16] + s0 + W[t - 7] + s1) | 0;
            }
            for (t = 0; t < 64; t++) {
                s1 = ((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7));
                t1 = (h + s1 + ((e & f) ^ (~e & g)) + K[t] + W[t]) | 0;
                s0 = ((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10));
                t2 = (s0 + ((a & b) ^ (a & c) ^ (b & c))) | 0;
                h = g; g = f; f = e; e = (d + t1) | 0;
                d = c; c = b; b = a; a = (t1 + t2) | 0;
            }

            H[0] = (H[0] + a) | 0; H[1] = (H[1] + b) | 0; H[2] = (H[2] + c) | 0; H[3] = (H[3] + d) | 0;
            H[4] = (H[4] + e) | 0; H[5] = (H[5] + f) | 0; H[6] = (H[6] + g) | 0; H[7] = (H[7] + h) | 0;
        }

        if (bits < 1) { return from; }

        // The prefix as UTF-8 bytes, one character of `raw` per byte.
        var raw  = unescape(encodeURIComponent(prefix));
        var full = raw.length - (raw.length % 64);
        var i, j;

        // Its whole blocks are the same for every candidate: hash them once.
        var mid  = new Int32Array([0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19]);
        var head = new Uint8Array(full);
        for (i = 0; i < full; i++) { head[i] = raw.charCodeAt(i); }
        for (i = 0; i < full; i += 64) { compress(mid, head, i); }

        // What is left of the prefix, then the candidate, then SHA-256's padding.
        var tail  = raw.length - full;
        var block = new Uint8Array(128);
        for (i = 0; i < tail; i++) { block[i] = raw.charCodeAt(full + i); }

        var H = new Int32Array(8);
        var n, digits, len, end, bitLen;

        for (n = from; n < from + count; n++) {
            digits = String(n);
            len    = tail;
            for (j = 0; j < digits.length; j++) { block[len++] = digits.charCodeAt(j); }

            bitLen       = (full + len) * 8;
            block[len++] = 0x80;
            end          = len > 56 ? 128 : 64;
            for (j = len; j < end - 4; j++) { block[j] = 0; }
            block[end - 4] = (bitLen >>> 24) & 255;
            block[end - 3] = (bitLen >>> 16) & 255;
            block[end - 2] = (bitLen >>> 8) & 255;
            block[end - 1] = bitLen & 255;

            H.set(mid);
            compress(H, block, 0);
            if (end === 128) { compress(H, block, 64); }

            if ((H[0] >>> (32 - bits)) === 0) { return n; }
        }

        return -1;
    }
    /* pow:end */

    // Runs powSearch until it finds the number, off the main thread when the
    // page allows a worker and in short slices on it when it does not (a strict
    // Content-Security-Policy can refuse a blob: worker).
    function solve(prefix, bits) {
        return new Promise(function (resolve) {
            var STEP = 20000;

            function slices(from) {
                var found = powSearch(prefix, bits, from, STEP);
                if (found >= 0) { resolve(found); return; }
                setTimeout(function () { slices(from + STEP); }, 0);
            }

            var worker = null;
            try {
                var source = 'var search=' + powSearch.toString() + ';'
                    + 'onmessage=function(e){var n=-1,from=0;'
                    + 'while(n<0){n=search(e.data.prefix,e.data.bits,from,' + STEP + ');from+=' + STEP + ';}'
                    + 'postMessage(n);};';
                worker = new Worker(URL.createObjectURL(new Blob([source], { type: 'application/javascript' })));
            } catch (err) {
                worker = null;
            }

            if (!worker) { slices(0); return; }

            worker.onmessage = function (e) { worker.terminate(); resolve(e.data); };
            worker.onerror   = function () { worker.terminate(); slices(0); };
            worker.postMessage({ prefix: prefix, bits: bits });
        });
    }

    // ------------------------------------------------------------------
    // Forms on this page
    // ------------------------------------------------------------------

    function targetsOf(form) {
        if (!form || !form.querySelector) { return null; }
        var el = form.querySelector('input[name="tbef_targets"]');
        if (!el) { return null; }
        try {
            var list = JSON.parse(el.getAttribute('data-tbef') || '[]');
            return Array.isArray(list) && list.length ? list : null;
        } catch (e) {
            return null;
        }
    }

    function field(form, name) {
        var all = form.querySelectorAll('[name]');
        for (var i = 0; i < all.length; i++) {
            if (all[i].getAttribute('name') === name) { return all[i]; }
        }
        return null;
    }

    // Trimmed and nothing else: the site relays the address the same way, and
    // the platform hashes exactly what it is sent.
    function emailOf(form, name) {
        var el = field(form, name);
        return el ? String(el.value || '').trim() : '';
    }

    var cache = {};

    function challenge(target) {
        var url = restUrl + 'challenge?target=' + encodeURIComponent(target);
        return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (json) { return json && json.pow && json.pow.salt ? json.pow : null; })
            .catch(function () { return null; });
    }

    function work(target, email) {
        var id = target + '|' + email;
        var hit = cache[id];
        if (hit && Date.now() - hit.at < MAX_AGE) { return hit.promise; }

        var promise = challenge(target).then(function (pow) {
            if (!pow) { return null; }
            return solve(pow.salt + ':' + email + ':', pow.bits).then(function (nonce) {
                return { salt: pow.salt, nonce: String(nonce), email: email };
            });
        });

        cache[id] = { at: Date.now(), promise: promise };
        return promise;
    }

    function fill(form, target, result) {
        var salt  = field(form, 'tbef_pow[' + target + '][salt]');
        var nonce = field(form, 'tbef_pow[' + target + '][nonce]');
        if (!salt || !nonce) { return; }
        salt.value  = result ? result.salt : '';
        nonce.value = result ? result.nonce : '';
        salt.setAttribute('data-email', result ? result.email : '');
    }

    function prepare(form) {
        var targets = targetsOf(form);
        if (!targets) { return Promise.resolve(); }

        return Promise.all(targets.map(function (t) {
            var email = emailOf(form, t.e);
            if (email.indexOf('@') < 1) {
                fill(form, t.t, null);
                return null;
            }
            return work(t.t, email).then(function (result) { fill(form, t.t, result); });
        }));
    }

    function ready(form) {
        var targets = targetsOf(form);
        if (!targets) { return true; }

        return targets.every(function (t) {
            var email = emailOf(form, t.e);
            if (email.indexOf('@') < 1) { return true; }
            var salt = field(form, 'tbef_pow[' + t.t + '][salt]');
            return !!(salt && salt.value && salt.getAttribute('data-email') === email);
        });
    }

    function waitFor(form) {
        return Promise.race([
            prepare(form),
            new Promise(function (resolve) { setTimeout(resolve, WAIT); })
        ]);
    }

    // ------------------------------------------------------------------
    // Start early: when the address changes
    // ------------------------------------------------------------------

    var timers = [];

    function onEdit(e) {
        var form = e.target && e.target.form;
        if (!form || !targetsOf(form)) { return; }
        clearTimeout(timers[0]);
        timers[0] = setTimeout(function () { prepare(form); }, 400);
    }

    document.addEventListener('change', onEdit, true);
    document.addEventListener('input', onEdit, true);

    // ------------------------------------------------------------------
    // Gravity Forms 2.9+: its documented async hook before submission
    // ------------------------------------------------------------------

    var gfHooked = false;

    function hookGravityForms() {
        var gf = window.gform;
        if (gfHooked || !gf || !gf.utils || typeof gf.utils.addAsyncFilter !== 'function') { return; }
        gfHooked = true;
        gf.utils.addAsyncFilter('gform/submission/pre_submission', function (data) {
            var form = data && data.form;
            if (!form || !targetsOf(form) || ready(form)) { return Promise.resolve(data); }
            return waitFor(form).then(function () { return data; });
        });
    }

    hookGravityForms();
    document.addEventListener('gform/theme/scripts_loaded', hookGravityForms);

    // ------------------------------------------------------------------
    // Everything else: hold the submission until the work is done
    // ------------------------------------------------------------------

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || !targetsOf(form)) { return; }

        // Gravity Forms with the async hook waits on its own.
        if (gfHooked && /^gform_\d+$/.test(form.id || '')) { return; }

        if (form.getAttribute('data-tbef-pass') === '1') {
            form.removeAttribute('data-tbef-pass');
            return;
        }

        if (ready(form)) { return; }

        e.preventDefault();
        e.stopImmediatePropagation();

        var submitter = e.submitter || null;

        waitFor(form).then(function () {
            form.setAttribute('data-tbef-pass', '1');
            if (typeof form.requestSubmit === 'function') {
                if (submitter) { form.requestSubmit(submitter); } else { form.requestSubmit(); }
            } else {
                form.submit();
            }
        });
    }, true);
})();
