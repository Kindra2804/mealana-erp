let vertreterIndex = window.LIEFERANT_VERTRETER_INDEX;
const vertreterTemplate = window.LIEFERANT_VERTRETER_TEMPLATE;

function addVertreterRow() {
    const html = vertreterTemplate.replaceAll('__INDEX__', vertreterIndex);
    document.getElementById('vertreter-rows').insertAdjacentHTML('beforeend', html);
    vertreterIndex++;
}
