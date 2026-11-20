(function () {
    'use strict';

    var forms = Array.prototype.slice.call(document.querySelectorAll('form[data-nxs-postsocial-login]'));
    if (!forms.length) return;
    var busy = false;

    function showError(message) {
        var wrap = document.querySelector('#wpbody-content .wrap') || document.querySelector('.wrap');
        if (!wrap) {
            window.alert(message);
            return;
        }
        var notice = document.getElementById('nxs-postsocial-login-notice');
        if (!notice) {
            notice = document.createElement('div');
            notice.id = 'nxs-postsocial-login-notice';
            notice.className = 'notice notice-error';
            notice.setAttribute('role', 'alert');
            var paragraph = document.createElement('p');
            notice.appendChild(paragraph);
            wrap.insertBefore(notice, wrap.firstChild);
        }
        notice.firstChild.textContent = message;
    }

    function submit(event) {
        event.preventDefault();
        if (busy) return;
        busy = true;
        var form = event.currentTarget;
        var buttons = Array.prototype.slice.call(document.querySelectorAll('button[form="nxs-postsocial-billing-form"],button[form="nxs-postsocial-dashboard-form"]'));
        buttons.forEach(function (button) { button.disabled = true; });
        var popup = window.open('about:blank', '_blank');
        if (popup) popup.opener = null;

        fetch(form.dataset.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
            body: new FormData(form)
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload.success || !payload.data || typeof payload.data.login_url !== 'string' || Number(payload.data.expires_in) <= 0) {
                throw new Error(payload.data && payload.data.message ? payload.data.message : 'Could not open Post.Social.');
            }
            if (popup && !popup.closed) popup.location.replace(payload.data.login_url);
            else window.location.assign(payload.data.login_url);
        }).catch(function (error) {
            if (popup && !popup.closed) popup.close();
            showError(error && error.message ? error.message : 'Could not open Post.Social.');
        }).finally(function () {
            busy = false;
            buttons.forEach(function (button) { button.disabled = false; });
        });
    }
    forms.forEach(function (form) { form.addEventListener('submit', submit); });
}());
