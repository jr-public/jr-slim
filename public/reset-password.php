<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; font-size: 14px; margin-top: 5px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <h2>Reset Your Password</h2>
    
    <form id="resetForm" action="http://localhost:80/guest/reset-password" method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
        
        <div class="form-group">
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" value="1234" required>
        </div>
        
        <div class="form-group">
            <label for="confirmPassword">Confirm Password:</label>
            <input type="password" id="confirmPassword" value="1234" required>
            <div id="passwordError" class="error hidden">Passwords do not match</div>
        </div>
        
        <button type="submit">Reset Password</button>
    </form>

    <script>
        const form = document.getElementById('resetForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const error = document.getElementById('passwordError');

        function validatePasswords() {
            if (password.value !== confirmPassword.value) {
                error.classList.remove('hidden');
                return false;
            } else {
                error.classList.add('hidden');
                return true;
            }
        }

        confirmPassword.addEventListener('input', validatePasswords);
        password.addEventListener('input', validatePasswords);

        form.addEventListener('submit', function(e) {
            if (!validatePasswords()) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>