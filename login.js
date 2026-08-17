// This file shows simple event listeners and handlers for the login activity.

var loginForm = document.getElementById('login-form');
var syncLoginButton = document.getElementById('sync-login-button');
var asyncLoginButton = document.getElementById('async-login-button');
var loginContainer = document.getElementById('login-container');
var usernameInput = document.getElementById('username-input');
var passwordInput = document.getElementById('password-input');
var formError = document.getElementById('form-error');

// A small Observer / Notification Center. Other code can subscribe to events.
var notificationCenter = {
    listeners: {},

    subscribe: function (eventName, callback) {
        if (!this.listeners[eventName]) {
            this.listeners[eventName] = [];
        }
        this.listeners[eventName].push(callback);
    },

    notify: function (eventName, message) {
        var eventListeners = this.listeners[eventName] || [];
        for (var i = 0; i < eventListeners.length; i++) {
            eventListeners[i](message);
        }
    }
};

function showNotification(message, alertIcon) {
    // The console keeps a record for the activity, while SweetAlert keeps the page clean.
    console.log('[Notification Center] ' + message);

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: alertIcon,
        title: message,
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true
    });
}

notificationCenter.subscribe('login:attempt', function (message) {
    showNotification(message, 'info');
});

notificationCenter.subscribe('login:success', function (message) {
    showNotification(message, 'success');
});

notificationCenter.subscribe('login:failed', function (message) {
    showNotification(message, 'error');
});

function showError(message) {
    formError.textContent = message;
    formError.classList.add('is-visible');
    notificationCenter.notify('login:failed', 'Login failed: ' + message);
}

function clearError() {
    formError.textContent = '';
    formError.classList.remove('is-visible');
}

function checkInputs() {
    var username = usernameInput.value.trim();
    var password = passwordInput.value;

    if (username === '' || password === '') {
        showError('Please enter both username and password.');
        return false;
    }
    return true;
}

function finishLogin() {
    // These are only demo credentials. PHP checks them again on the server.
    if (usernameInput.value.trim() !== 'admin01' || passwordInput.value !== 'password') {
        showError('Incorrect admin username or password.');
        return;
    }

    notificationCenter.notify('login:success', 'Login success! Sending the form now...');
    loginForm.submit();
}

function slowSynchronousWork() {
    // This loop is intentionally not good practice. It is only to show a frozen page.
    var startTime = new Date().getTime();
    while (new Date().getTime() - startTime < 3000) {
        // Wait here for 3 seconds.
    }
}

function handleLogin(event, validationMode) {
    if (event) {
        event.preventDefault();
    }
    clearError();

    notificationCenter.notify('login:attempt', 'Login attempted. Checking credentials...');

    if (!checkInputs()) {
        return;
    }

    if (validationMode === 'sync') {
        showNotification('Synchronous mode: page is busy for 3 seconds...');
        slowSynchronousWork();
        finishLogin();
    } else {
        showNotification('Asynchronous mode: checking for 3 seconds. You can still type.');
        syncLoginButton.disabled = true;
        asyncLoginButton.disabled = true;

        setTimeout(function () {
            syncLoginButton.disabled = false;
            asyncLoginButton.disabled = false;
            finishLogin();
        }, 3000);
    }
}

// Click listeners: the two Login buttons are event sources.
syncLoginButton.addEventListener('click', function () {
    showNotification('Synchronous Login button was clicked.');
    handleLogin(null, 'sync');
});

asyncLoginButton.addEventListener('click', function () {
    showNotification('Asynchronous Login button was clicked.');
});

// Form listener: pressing Enter uses asynchronous validation.
loginForm.addEventListener('submit', function (event) {
    handleLogin(event, 'async');
});

// Focus event: show the password hint.
passwordInput.addEventListener('focus', function () {
    showNotification('Password must be at least 6 characters.');
});

passwordInput.addEventListener('blur', function () {
    showNotification('Password field lost focus.');
});

// Capturing happens first, then the button's click handler, then bubbling.
loginContainer.addEventListener('click', function () {
    showNotification('Container capturing: the click is going to the button.');
}, true);

loginContainer.addEventListener('click', function () {
    showNotification('Container bubbling: the button click reached the container.');
});
