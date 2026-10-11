@php $siteKey = config('services.recaptcha.site_key') @endphp
@if($siteKey)
<script src="https://www.google.com/recaptcha/api.js?render={{ $siteKey }}"></script>
<script>
(function () {
    var SITE_KEY = '{{ $siteKey }}';

    function refreshToken() {
        if (typeof grecaptcha === 'undefined' || typeof Livewire === 'undefined') return;
        grecaptcha.ready(function () {
            grecaptcha.execute(SITE_KEY, {action: 'login'}).then(function (token) {
                var wireEl = document.querySelector('[wire\\:id]');
                if (wireEl) {
                    Livewire.find(wireEl.getAttribute('wire:id')).set('recaptchaToken', token);
                }
            });
        });
    }

    // Pre-generate token on page load (valid 2 min)
    document.addEventListener('DOMContentLoaded', refreshToken);

    // Refresh every 90 s so the token never expires before submission
    setInterval(refreshToken, 90000);
})();
</script>
@endif
