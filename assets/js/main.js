document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[data-prevent-duplicate]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
                submitButton.textContent = 'Please wait...';
            }
        });
    });

    const navbar = document.getElementById('navbarNav');
    if (navbar) {
        navbar.querySelectorAll('a.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (typeof bootstrap !== 'undefined' && window.innerWidth < 992 && navbar.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(navbar).hide();
                }
            });
        });
    }
});
