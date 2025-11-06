<?php
session_start();
require_once 'config/database.php';

// Si el usuario ya está logueado, redirigir
if (is_logged_in()) {
    redirect_by_user_type();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                
                redirect_by_user_type();
            } else {
                $error = 'Email o contraseña incorrectos';
            }
        } catch(PDOException $e) {
            $error = 'Error en el sistema. Inténtalo más tarde.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - MediConsulta</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <i class="fas fa-stethoscope"></i>
                <a href="index.php" style="color: white; text-decoration: none;">MediConsulta</a>
            </div>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="register.php">Registrarse</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="auth-container">
            <div class="auth-header">
                <i class="fas fa-sign-in-alt" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                <h1>Iniciar Sesión</h1>
                <p>Accede a tu cuenta de MediConsulta</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </button>
                </div>
            </form>

            <div class="auth-links">
                <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                <p><a href="index.php">← Volver al inicio</a></p>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 MediConsulta. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>