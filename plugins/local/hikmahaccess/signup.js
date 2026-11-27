// Sign-up page (lib.php, local_hikmahaccess_extend_signup_form): ticks the password rules while typing, shows a
// strength bar, says whether the two passwords match, and moves "or / Continue with Google" under Create account.
// Plain script run by $PAGE->requires->js_init_code(); the server checks everything again on submit.
(function() {
    var box = document.querySelector('.hk-pwcheck');
    var pw = document.getElementById('id_password');
    var pw2 = document.getElementById('id_hkpassword2');
    var idp = document.querySelector('.hk-signup-idp');
    var buttons = document.getElementById('fgroup_id_buttonar');
    if (idp && buttons) {
        buttons.parentNode.insertBefore(idp, buttons.nextSibling);
    }
    if (!box || !pw) {
        return;
    }
    var min = parseInt(box.getAttribute('data-minlength'), 10) || 0;
    var tests = {
        length: function(v) {
            return v.length >= min;
        },
        upper: function(v) {
            return /[A-Z]/.test(v);
        },
        lower: function(v) {
            return /[a-z]/.test(v);
        },
        digit: function(v) {
            return /[0-9]/.test(v);
        }
    };
    var items = box.querySelectorAll('[data-rule]');
    var label = box.querySelector('.hk-pwmeter__label');
    var words = (box.getAttribute('data-words') || '').split('|');
    var match = document.createElement('div');
    match.className = 'hk-pwmatch';
    match.setAttribute('aria-live', 'polite');
    if (pw2) {
        pw2.parentNode.appendChild(match);
    }

    var update = function() {
        var v = pw.value;
        var met = 0;
        items.forEach(function(li) {
            var ok = tests[li.getAttribute('data-rule')](v);
            li.classList.toggle('is-met', ok);
            met += ok ? 1 : 0;
        });
        // 0 = empty, 1 = weak, 2 = fair, 3 = strong (every rule met; 14+ characters also counts as strong).
        var level = 0;
        if (v.length) {
            level = met === items.length ? 3 : (met >= items.length - 1 ? 2 : 1);
        }
        box.setAttribute('data-level', level);
        label.textContent = level ? words[level - 1] || '' : '';
        if (pw2) {
            var same = pw2.value === v;
            match.textContent = pw2.value ? (same ? box.getAttribute('data-match') : box.getAttribute('data-nomatch')) : '';
            match.classList.toggle('is-met', same);
        }
    };
    pw.addEventListener('input', update);
    if (pw2) {
        pw2.addEventListener('input', update);
    }
    update();
})();
