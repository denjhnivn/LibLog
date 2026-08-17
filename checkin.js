const checkinForm    = document.getElementById('checkin-form');
const idInput        = document.getElementById('id-number');
const idField        = document.getElementById('id-field');
const pcField        = document.getElementById('pc-field');
const pcSelect       = document.getElementById('pc-select');
const pcOptions      = document.querySelector('.pc-options');
const pcValue        = document.querySelector('.pc-select-value');
const pcNumberInput  = document.getElementById('pc-number-input'); 
const checkinSuccess = document.getElementById('checkin-success');
const checkinError   = document.getElementById('checkin-error');

let selectedPc = pcNumberInput.value || '';

function setError(field, hasError) {
    field.classList.toggle('incorrect', hasError);
    field.classList.toggle('is-invalid', hasError);
}

function closePcOptions() {
    pcOptions.hidden = true;
    pcSelect.setAttribute('aria-expanded', 'false');
}

pcSelect.addEventListener('click', () => {
    const willOpen = pcOptions.hidden;
    pcOptions.hidden = !willOpen;
    pcSelect.setAttribute('aria-expanded', String(willOpen));
});

pcOptions.addEventListener('click', (event) => {
    const option = event.target.closest('.pc-option');
    if (!option) return;

    selectedPc = option.dataset.value;

    pcValue.textContent = selectedPc;
    pcValue.classList.add('has-value');

    pcNumberInput.value = selectedPc;

    setError(pcField, false);
    closePcOptions();
    checkinError.classList.remove('is-visible');
});

document.addEventListener('click', (event) => {
    if (!event.target.closest('.pc-field')) closePcOptions();
});

idInput.addEventListener('input', () => {
    setError(idField, idInput.value.trim() === '');
    checkinSuccess.classList.remove('is-visible');
    checkinError.classList.remove('is-visible');
});

checkinForm.addEventListener('submit', (event) => {
    const idIsEmpty = idInput.value.trim() === '';
    const pcIsEmpty = selectedPc === '';

    setError(idField, idIsEmpty);
    setError(pcField, pcIsEmpty);

    if (idIsEmpty || pcIsEmpty) {

        event.preventDefault();
        checkinError.textContent = 'Please complete the required fields.';
        checkinError.classList.add('is-visible');
        checkinSuccess.classList.remove('is-visible');
        return;
    }
});
