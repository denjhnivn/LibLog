var checkinForm = document.getElementById('checkin-form');
var idInput = document.getElementById('id-number');
var idField = document.getElementById('id-field');
var pcField = document.getElementById('pc-field');
var pcSelect = document.getElementById('pc-select');
var pcOptions = document.querySelector('.pc-options');
var pcValue = document.querySelector('.pc-select-value');
var pcNumberInput = document.getElementById('pc-number-input');
var checkinSuccess = document.getElementById('checkin-success');
var checkinError = document.getElementById('checkin-error');
var checkinButton = document.getElementById('checkin-button');
var selectedPc = pcNumberInput.value || '';

var notificationCenter = {
    listeners: {},
    subscribe: function (eventName, handler) {
        if (!this.listeners[eventName]) {
            this.listeners[eventName] = [];
        }
        this.listeners[eventName].push(handler);
    },
    notify: function (eventName, detail) {
        var eventListeners = this.listeners[eventName] || [];
        var index;
        for (index = 0; index < eventListeners.length; index++) {
            eventListeners[index](detail);
        }
    }
};

function showToast(iconName, title) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: iconName,
        title: title,
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true
    });
}

notificationCenter.subscribe('checkin:error', function (message) {
    showToast('error', message);
});
notificationCenter.subscribe('checkin:pc-selected', function (pc) {
    showToast('success', pc + ' selected.');
});
notificationCenter.subscribe('checkin:menu-opened', function () {
    showToast('info', 'Choose an available PC.');
});

if (checkinSuccess.textContent.trim() !== '') {
    Swal.fire({ icon: 'success', title: 'Check-in complete', text: checkinSuccess.textContent.trim() });
}
if (checkinError.textContent.trim() !== '') {
    Swal.fire({ icon: 'error', title: 'Unable to check in', text: checkinError.textContent.trim() });
}

function setError(field, hasError) {
    field.classList.toggle('incorrect', hasError);
    field.classList.toggle('is-invalid', hasError);
}

function closePcOptions() {
    pcOptions.hidden = true;
    pcSelect.setAttribute('aria-expanded', 'false');
}

pcSelect.addEventListener('click', function () {
    var willOpen = pcOptions.hidden;
    pcOptions.hidden = !willOpen;
    pcSelect.setAttribute('aria-expanded', String(willOpen));
    if (willOpen) {
        notificationCenter.notify('checkin:menu-opened');
    }
});

function selectPc(option) {
    selectedPc = option.dataset.value;
    pcValue.textContent = selectedPc;
    pcValue.classList.add('has-value');
    pcNumberInput.value = selectedPc;
    setError(pcField, false);
    closePcOptions();
    checkinError.classList.remove('is-visible');
    notificationCenter.notify('checkin:pc-selected', selectedPc);
}

var pcOptionButtons = document.querySelectorAll('.pc-option');
var optionIndex;
for (optionIndex = 0; optionIndex < pcOptionButtons.length; optionIndex++) {
    pcOptionButtons[optionIndex].addEventListener('click', function () {
        selectPc(this);
    });
}

document.addEventListener('click', function (event) {
    if (!event.target.closest('.pc-field')) {
        closePcOptions();
    }
});

idInput.addEventListener('input', function () {
    setError(idField, idInput.value.trim() === '');
    checkinSuccess.classList.remove('is-visible');
    checkinError.classList.remove('is-visible');
});

checkinForm.addEventListener('click', function (event) {
    console.info('Check-in form capture:', event.target.id || event.target.className);
}, true);

checkinForm.addEventListener('click', function (event) {
    console.info('Check-in form bubble:', event.target.id || event.target.className);
});

function synchronousValidationDelay() {
    var startTime = Date.now();
    while (Date.now() - startTime < 3000) {
    
    }
}

checkinForm.addEventListener('submit', function (event) {
    var idIsEmpty = idInput.value.trim() === '';
    var pcIsEmpty = selectedPc === '';
    setError(idField, idIsEmpty);
    setError(pcField, pcIsEmpty);

    if (idIsEmpty || pcIsEmpty) {
        event.preventDefault();
        checkinError.textContent = 'Please complete the required fields.';
        checkinError.classList.add('is-visible');
        checkinSuccess.classList.remove('is-visible');
        notificationCenter.notify('checkin:error', 'Please complete the required fields.');
        return;
    }

    event.preventDefault();
    checkinButton.disabled = true;
    checkinButton.classList.add('is-loading');
    setTimeout(function () {
        synchronousValidationDelay();
        checkinForm.submit();
    }, 50);
});
