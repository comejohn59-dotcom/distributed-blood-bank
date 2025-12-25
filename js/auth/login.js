// Updated frontend/js/auth/login.js with real API integration
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const loginSpinner = document.getElementById('loginSpinner');

    // API Base URL - adjust this to your backend location
    // If your project is served at http://localhost/mySystem, include that path.
    const API_BASE_URL = 'http://localhost/mySystem/backend/api';

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const role = document.getElementById('role').value;

        // Clear previous errors
        clearErrors();

        // Basic validation
        if (!validateForm(email, password, role)) {
            return;
        }

        // Show loading state
        loginBtn.disabled = true;
        loginSpinner.style.display = 'inline-block';
        loginBtn.innerHTML = '<span>Signing In...</span>';

        try {
            const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    password: password,
                    role: role
                })
            });

            const data = await response.json();

            if (data.success) {
                // Store user data in localStorage
                localStorage.setItem('auth_token', data.data.token);
                localStorage.setItem('user_role', data.data.role);
                localStorage.setItem('user_name', data.data.name);
                localStorage.setItem('user_id', data.data.user_id);
                localStorage.setItem('user_email', data.data.email);

                // Show success message
                // showMessage('success', 'Login successful! Redirecting...');

                // Redirect based on role
                setTimeout(() => {
                    // redirectToDashboard(data.data.role);
                }, 1000);
            } else {
                showError('login', data.message || 'Invalid credentials. Please try again.');
            }
        } catch (error) {
            console.error('Login error:', error);
            showError('login', 'Network error. Please check your connection and try again.');
        } finally {
            // Reset loading state
            loginBtn.disabled = false;
            loginSpinner.style.display = 'none';
            loginBtn.innerHTML = '<span>Sign In</span>';
        }
    });

    function validateForm(email, password, role) {
        let isValid = true;

        if (!email || !email.includes('@')) {
            showError('email', 'Please enter a valid email address');
            isValid = false;
        }

        if (!password || password.length < 6) {
            showError('password', 'Password must be at least 6 characters');
            isValid = false;
        }

        if (!role) {
            showError('role', 'Please select a role');
            isValid = false;
        }

        return isValid;
    }

    function showError(field, message) {
        const errorElement = document.getElementById(`${field}Error`);
        if (errorElement) {
            errorElement.textContent = message;
            const inputElement = document.getElementById(field);
            if (inputElement) {
                inputElement.classList.add('error');
            }
        }
    }

    function showMessage(type, message) {
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background-color: ${type === 'success' ? '#d4edda' : '#f8d7da'};
            color: ${type === 'success' ? '#155724' : '#721c24'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
        `;

        // Insert before form
        loginForm.parentNode.insertBefore(messageDiv, loginForm);

        // Remove after 3 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }

    function clearErrors() {
        const errorElements = document.querySelectorAll('.form-error');
        errorElements.forEach(element => {
            element.textContent = '';
        });

        const inputElements = document.querySelectorAll('.form-control');
        inputElements.forEach(element => {
            element.classList.remove('error');
        });
    }

    function redirectToDashboard(role) {
        const dashboards = {
            'donor': '../donor/dashboard.html',
            'hospital': '../hospital/dashboard.html',
            'blood_bank': '../blood-bank/dashboard.html',
            'admin': '../admin/dashboard.html'
        };

        if (dashboards[role]) {
            window.location.href = dashboards[role];
        } else {
            showError('login', 'Invalid role configuration');
        }
    }

    // Auto-focus email field on page load
    document.getElementById('email').focus();

    // Check if user is already logged in
    checkExistingSession();
    
    function checkExistingSession() {
        const token = localStorage.getItem('auth_token');
        const role = localStorage.getItem('user_role');
        
        if (token && role) {
            // User is already logged in, redirect to dashboard
            showMessage('info', 'You are already logged in. Redirecting...');
            setTimeout(() => {
                redirectToDashboard(role);
            }, 1500);
        }
    }
});