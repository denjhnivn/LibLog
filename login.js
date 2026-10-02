function LoginEventPublisher() {
    this.handlers = [];
}

LoginEventPublisher.prototype.subscribe = function (handler) {
    this.handlers.push(handler);
};

LoginEventPublisher.prototype.publish = function (eventData) {
    this.handlers.forEach(function (handler) {
        handler(eventData);
    });
};

var loginForm = document.getElementById('login-form');
var loginButton = document.getElementById('login-button');
var usernameInput = document.getElementById('username-input');
var passwordInput = document.getElementById('password-input');
var formError = document.getElementById('form-error');
var formSuccess = document.getElementById('form-success');
var loginAttemptEvent = new LoginEventPublisher();

function setFieldError(input, hasError) {
    input.parentElement.classList.toggle('is-invalid', hasError);
    input.parentElement.classList.toggle('incorrect', hasError);
}

function clearMessages() {
    formError.textContent = '';
    formError.classList.remove('is-visible');
    formSuccess.textContent = '';
    formSuccess.classList.remove('not-visible');
}

function validateLogin(eventData) {
    var usernameIsEmpty = eventData.username === '';
    var passwordIsEmpty = passwordInput.value === '';

    setFieldError(usernameInput, usernameIsEmpty);
    setFieldError(passwordInput, passwordIsEmpty);

    eventData.isValid = !usernameIsEmpty && !passwordIsEmpty;
    eventData.message = eventData.isValid
        ? ''
        : 'Please enter a valid username and password.';

    console.log('Validation:', eventData.message);
}

function recordLoginAudit(eventData) {
    console.log(
        'Audit: login attempt by "' + (eventData.username || 'unknown user') +
        '" at ' + eventData.timestamp.toLocaleString()
    );
}

function displayLoginResult(eventData) {
    if (eventData.isValid) {

        console.log("Welcome, admin!");
        return;
    }

    formError.textContent = eventData.message;
    formError.classList.add('is-visible');
    console.error('Failure message to user: ' + eventData.message);
}

loginAttemptEvent.subscribe(validateLogin);
loginAttemptEvent.subscribe(recordLoginAudit);
loginAttemptEvent.subscribe(displayLoginResult);
loginAttemptEvent.subscribe(() => {
    console.log('Lambda: Login attempt detected!');
});

loginForm.addEventListener('submit', function (browserEvent) {
    if (loginButton.disabled) {
        browserEvent.preventDefault();
        return;
    }
    clearMessages();

    var eventData = {
        username: usernameInput.value.trim(),
        timestamp: new Date(),
        isValid: false,
        message: ''
    };

    loginAttemptEvent.publish(eventData);

    if (!eventData.isValid) {
        browserEvent.preventDefault();
        return;
    }

    loginButton.disabled = true;
    loginButton.classList.add('is-loading');
    loginButton.setAttribute('aria-busy', 'true');
    loginButton.querySelector('.login-spinner').hidden = false;
});

usernameInput.addEventListener('input', function () {
    setFieldError(usernameInput, false);
    clearMessages();
});

usernameInput.addEventListener('click', function () {
    console.log('Event: Username field clicked.');
});

usernameInput.addEventListener('focus', function () {
    console.log('Event: Username field focused.');
});

passwordInput.addEventListener('input', function () {
    setFieldError(passwordInput, false);
    clearMessages();
});

passwordInput.addEventListener('click', function () {
    console.log('Event: Password field clicked.');
});

passwordInput.addEventListener('focus', function () {
    console.log('Event: Password field focused.');
});

loginButton.addEventListener('click', function () {
    console.log('Event: Login button clicked.');
});

window.addEventListener('pageshow', function () {
    loginButton.disabled = false;
    loginButton.classList.remove('is-loading');
    loginButton.removeAttribute('aria-busy');
    loginButton.querySelector('.login-spinner').hidden = true;
});
