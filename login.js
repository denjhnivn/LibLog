var loginForm = document.getElementById('login-form');
var loginButton = document.getElementById('login-button');
var loginContainer = document.getElementById('login-container');
var usernameInput = document.getElementById('username-input');
var passwordInput = document.getElementById('password-input');
var formError = document.getElementById('form-error');

var notificationCenter = {
    listeners: {},

    subscribe: function (eventName, handler) {
        if (!this.listeners[eventName]) {
            this.listeners[eventName] = [];
        }
        this.listeners[eventName].push(handler);
    },

    notify: function (eventName, message) {
        var eventListeners = this.listeners[eventName] || [];
        var index;

        for (index = 0; index < eventListeners.length; index++) {
            eventListeners[index](message);
        }
    }
};

function showToast(iconName, message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: iconName,
        title: message,
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true
    });
}

notificationCenter.subscribe('login:attempt', function (message) {
    showToast('info', message);
});

notificationCenter.subscribe('login:error', function (message) {
    showToast('error', message);
});

if (formError.textContent.trim() !== '') {
    Swal.fire({
        icon: 'error',
        title: 'Login failed',
        text: formError.textContent.trim()
    });
}

function showError(message) {
    formError.textContent = message;
    formError.classList.add('is-visible');
    notificationCenter.notify('login:error', message);
}

function setFieldError(input, hasError) {
    input.parentElement.classList.toggle('error', hasError);
    input.parentElement.classList.toggle('is-invalid', hasError);
}

function clearError() {
    formError.textContent = '';
    formError.classList.remove('is-visible');
}

function inputsAreValid() {
    var usernameIsEmpty = usernameInput.value.trim() === '';
    var passwordIsEmpty = passwordInput.value === '';

    setFieldError(usernameInput, usernameIsEmpty);
    setFieldError(passwordInput, passwordIsEmpty);

    if (usernameIsEmpty || passwordIsEmpty) {
        showError('Please enter both username and password.');
        return false;
    }

    return true;
}

function submitLogin(event) {
    event.preventDefault();
    clearError();

    if (!inputsAreValid()) {
        return;
    }

    notificationCenter.notify('login:attempt', 'Checking your credentials...');
    loginButton.disabled = true;
    loginButton.classList.add('is-loading');

    setTimeout(function () {
        loginForm.submit();
    }, 1000);
}

loginForm.addEventListener('submit', submitLogin);

usernameInput.addEventListener('input', function () {
    setFieldError(usernameInput, usernameInput.value.trim() === '');
    clearError();
});

passwordInput.addEventListener('input', function () {
    setFieldError(passwordInput, passwordInput.value === '');
    clearError();
});

loginContainer.addEventListener('click', function (event) {
    console.info('Login container capture:', event.target.id || event.target.tagName);
}, true);

loginButton.addEventListener('click', function () {
    console.info('Login button clicked.');
});

loginContainer.addEventListener('click', function (event) {
    console.info('Login container bubble:', event.target.id || event.target.tagName);
});
