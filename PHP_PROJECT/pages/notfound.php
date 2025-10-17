<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="../assets/css/notfound.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <main>
        <div class="error-page">
            <div class="error-container">
                <div class="error-content">
                    <h1 class="error-code">404</h1>
                    <p class="error-title">
                        Oops! Page not found.
                    </p>
                    <p class="error-description">
                        The page you're looking for doesn't exist.
                    </p>
                    <a href="/PHP_PROJECT/" class="back-button">
                        Eyeglasses Page
                    </a>
                </div>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>

</html>