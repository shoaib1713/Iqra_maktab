<!-- Signup Page -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Maktab-a-Ekra</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h2>Sign Up</h2>
        <form id="signupForm" action="modules/signup.php" method="POST">
            <label>Name</label>
            <input type="text" name="name" required>
            
            <label>Email</label>
            <input type="email" name="email" required>
            
            <label>Password</label>
            <input type="password" name="password" required>
            
            <button type="submit">Sign Up</button>
            <p id="signup-msg" class="error"></p>
        </form>
        <div class="links">
            <a href="index.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
<script>
    $(document).ready(function() {
        $('#signupForm').submit(function(event) {
            event.preventDefault();

            $.ajax({
                url: 'modules/signup.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    if (response === 'success') {
                        alert('Signup successful! Redirecting to login...');
                        window.location.href = 'index.php';
                    } else {
                        $('#signup-msg').text(response);
                    }
                }
            });
        });
    });
</script>

