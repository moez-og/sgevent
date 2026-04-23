(function () {
    function lockFormControls(form, submitButton) {
        var controls = form.querySelectorAll('input, select, textarea, button');

        controls.forEach(function (control) {
            if (control === submitButton) {
                return;
            }

            if (control.tagName === 'BUTTON') {
                control.disabled = true;
                return;
            }

            if (control.tagName === 'INPUT' && control.type === 'hidden') {
                return;
            }

            // Keep values submittable: do not disable named form fields.
            control.readOnly = true;
            control.setAttribute('aria-disabled', 'true');
            control.style.pointerEvents = 'none';
        });

        var links = form.querySelectorAll('a');
        links.forEach(function (link) {
            link.style.pointerEvents = 'none';
            link.style.opacity = '0.7';
        });
    }

    function startMorphSubmit(form) {
        if (form.dataset.morphing === 'true') {
            return;
        }

        var submitButton = form.querySelector('button[type="submit"].auth-submit');
        if (!submitButton) {
            form.submit();
            return;
        }

        var page = form.closest('.auth-page');
        var card = form.closest('.auth-card');

        form.dataset.morphing = 'true';
        form.setAttribute('aria-busy', 'true');
        submitButton.classList.add('is-morphing');
        submitButton.setAttribute('aria-busy', 'true');

        if (page) {
            page.classList.add('is-submitting');
        }

        if (card) {
            card.classList.add('is-submitting');
        }

        lockFormControls(form, submitButton);

        window.setTimeout(function () {
            form.submit();
        }, 680);
    }

    function attachMorphHandler(form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.morphing === 'true') {
                return;
            }

            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                if (typeof form.reportValidity === 'function') {
                    form.reportValidity();
                }
                return;
            }

            event.preventDefault();
            startMorphSubmit(form);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form[data-auth-morph]');
        forms.forEach(attachMorphHandler);
    });
})();
