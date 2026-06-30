@php
    $tokenId = 'api-token-'.md5($token ?? '');
@endphp
<div class="api-token-box" style="border:1px solid #334155;border-radius:0.5rem;padding:0.75rem;background:#020617">
    <div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.5rem;flex-wrap:wrap">
        <button type="button" class="btn" data-api-token-toggle="{{ $tokenId }}">Show token</button>
        <button type="button" class="btn" data-api-token-copy="{{ $tokenId }}">Copy</button>
    </div>
    <code
        id="{{ $tokenId }}"
        data-api-token-value="{{ $token }}"
        style="display:block;word-break:break-all;padding:0.5rem;background:#0f172a;border-radius:0.375rem;letter-spacing:0.02em"
    >••••••••••••••••••••••••••••••••</code>
</div>
<script>
(function () {
    const code = document.getElementById(@json($tokenId));
    if (!code) return;
    const value = code.dataset.apiTokenValue || '';
    const masked = '••••••••••••••••••••••••••••••••';
    let visible = false;

    document.querySelector('[data-api-token-toggle="{{ $tokenId }}"]')?.addEventListener('click', function () {
        visible = !visible;
        code.textContent = visible ? value : masked;
        this.textContent = visible ? 'Hide token' : 'Show token';
    });

    document.querySelector('[data-api-token-copy="{{ $tokenId }}"]')?.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(value);
            const label = this.textContent;
            this.textContent = 'Copied!';
            setTimeout(() => { this.textContent = label; }, 1500);
        } catch (e) {
            code.textContent = value;
            visible = true;
            document.querySelector('[data-api-token-toggle="{{ $tokenId }}"]').textContent = 'Hide token';
            code.focus();
            document.execCommand('copy');
        }
    });
})();
</script>
