// Dropshipping-Karte (Auftrag): Werte fürs Händlerportal per Klick in die Zwischenablage
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.ds-kopier-btn');
    if (!btn) return;
    navigator.clipboard.writeText(btn.dataset.wert).then(function () {
        const alt = btn.textContent;
        btn.textContent = '✓';
        setTimeout(function () { btn.textContent = alt; }, 1200);
    });
});
