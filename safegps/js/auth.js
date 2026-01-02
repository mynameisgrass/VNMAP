document.addEventListener('DOMContentLoaded', () => {
    const loginFormEl = document.getElementById('login-form');
    const registerFormEl = document.getElementById('register-form');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const loginError = document.getElementById('login-error');
    const registerError = document.getElementById('register-error');

    document.getElementById('showRegister').addEventListener('click', (e) => {
        e.preventDefault();
        loginFormEl.style.display = 'none';
        registerFormEl.style.display = 'block';
    });
    
    document.getElementById('showLogin').addEventListener('click', (e) => {
        e.preventDefault();
        loginFormEl.style.display = 'block';
        registerFormEl.style.display = 'none';
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginError.textContent = '';
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;

        try {
            const response = await fetch('api/auth_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            const result = await response.json();
            if (response.ok) {
                window.location.href = 'index.html';
            } else {
                loginError.textContent = result.message;
            }
        } catch (error) {
            loginError.textContent = 'Lỗi kết nối đến máy chủ.';
        }
    });
    
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        registerError.textContent = '';
        const email = document.getElementById('registerEmail').value;
        const password = document.getElementById('registerPassword').value;

        try {
            const response = await fetch('api/auth_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            const result = await response.json();
            if (response.ok) {
                alert(result.message);
                // Switch to login form after successful registration
                document.getElementById('showLogin').click();
                document.getElementById('loginEmail').value = email;
                document.getElementById('loginPassword').focus();
            } else {
                registerError.textContent = result.message;
            }
        } catch (error) {
            registerError.textContent = 'Lỗi kết nối đến máy chủ.';
        }
    });
});
