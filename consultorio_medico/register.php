<?php
session_start();
require_once 'config/database.php';

// Si el usuario ya está logueado, redirigir
if (is_logged_in()) {
    redirect_by_user_type();
}

$error = '';
$success = '';
$user_type = isset($_GET['type']) ? $_GET['type'] : (isset($_POST['user_type']) ? $_POST['user_type'] : '');

// Obtener especialidades para doctores
$specialties = [];
if ($user_type === 'doctor' || empty($user_type)) {
    $stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
    $specialties = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = clean_input($_POST['user_type']);
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $birth_date = clean_input($_POST['birth_date']);
    
    // Campos específicos para doctores
    $specialty_id = isset($_POST['specialty_id']) ? intval($_POST['specialty_id']) : null;
    $license_number = isset($_POST['license_number']) ? clean_input($_POST['license_number']) : '';
    $consultation_fee = isset($_POST['consultation_fee']) ? floatval($_POST['consultation_fee']) : 0;
    
    // Validaciones
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($user_type)) {
        $error = 'Por favor completa todos los campos obligatorios';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($user_type === 'doctor' && (empty($specialty_id) || empty($license_number))) {
        $error = 'Los doctores deben completar especialidad y número de licencia';
    } else {
        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'El nombre de usuario o email ya están registrados';
            } else {
                // Crear usuario
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, user_type, first_name, last_name, phone, address, birth_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $user_type, $first_name, $last_name, $phone, $address, $birth_date]);
                
                $user_id = $pdo->lastInsertId();
                
                // Si es doctor, crear registro en tabla doctors
                if ($user_type === 'doctor') {
                    $availability = json_encode([
                        'monday' => ['09:00-17:00'],
                        'tuesday' => ['09:00-17:00'],
                        'wednesday' => ['09:00-17:00'],
                        'thursday' => ['09:00-17:00'],
                        'friday' => ['09:00-17:00']
                    ]);
                    
                    $stmt = $pdo->prepare("INSERT INTO doctors (user_id, specialty_id, license_number, consultation_fee, availability) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $specialty_id, $license_number, $consultation_fee, $availability]);
                }
                
                $pdo->commit();
                $success = 'Registro exitoso. Ya puedes iniciar sesión.';
                
                // Limpiar variables POST para evitar reenvío
                $_POST = [];
                
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
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
    <title>Registro - MediConsulta</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-container { max-width: 600px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .type-selector { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .type-option { flex: 1; padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .type-option.active { border-color: var(--primary-color); background-color: #f0f9ff; }
        .type-option input[type="radio"] { display: none; }
        .doctor-fields { display: none; }
        .doctor-fields.show { display: block; }
    </style>
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
                    <li><a href="login.php">Iniciar Sesión</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="auth-container">
            <div class="auth-header">
                <i class="fas fa-user-plus" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                <h1>Crear Cuenta</h1>
                <p>Únete a la comunidad de MediConsulta</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <div style="margin-top: 1rem;">
                        <a href="login.php" class="btn btn-primary">Iniciar Sesión</a>
                    </div>
                </div>
            <?php else: ?>

            <form method="POST" action="" id="registerForm">
                <!-- Selector de tipo de usuario -->
                <div class="form-group">
                    <label>Tipo de Usuario</label>
                    <div class="type-selector">
                        <label class="type-option <?php echo ($user_type === 'patient' || empty($user_type)) ? 'active' : ''; ?>" for="patient">
                            <input type="radio" name="user_type" value="patient" id="patient" 
                                   <?php echo ($user_type === 'patient' || empty($user_type)) ? 'checked' : ''; ?> required>
                            <i class="fas fa-user" style="font-size: 2rem; color: var(--primary-color); display: block; margin-bottom: 0.5rem;"></i>
                            <strong>Paciente</strong>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Agenda citas y consulta tu historial</p>
                        </label>
                        <label class="type-option <?php echo $user_type === 'doctor' ? 'active' : ''; ?>" for="doctor">
                            <input type="radio" name="user_type" value="doctor" id="doctor" 
                                   <?php echo $user_type === 'doctor' ? 'checked' : ''; ?> required>
                            <i class="fas fa-user-md" style="font-size: 2rem; color: var(--secondary-color); display: block; margin-bottom: 0.5rem;"></i>
                            <strong>Doctor</strong>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Gestiona pacientes y citas</p>
                        </label>
                    </div>
                </div>

                <!-- Información básica -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-at"></i> Nombre de Usuario *</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Correo Electrónico *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Contraseña *</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirmar Contraseña *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name"><i class="fas fa-user"></i> Nombre *</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name"><i class="fas fa-user"></i> Apellido *</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Teléfono</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="birth_date"><i class="fas fa-calendar"></i> Fecha de Nacimiento</label>
                        <input type="date" id="birth_date" name="birth_date" class="form-control" 
                               value="<?php echo isset($_POST['birth_date']) ? htmlspecialchars($_POST['birth_date']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address"><i class="fas fa-map-marker-alt"></i> Dirección</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>

                <!-- Campos específicos para doctores -->
                <div class="doctor-fields <?php echo $user_type === 'doctor' ? 'show' : ''; ?>">
                    <h3 style="color: var(--primary-color); margin: 2rem 0 1rem;">Información Profesional</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialty_id"><i class="fas fa-stethoscope"></i> Especialidad *</label>
                            <select id="specialty_id" name="specialty_id" class="form-control">
                                <option value="">Seleccionar especialidad</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?php echo $specialty['id']; ?>" 
                                            <?php echo (isset($_POST['specialty_id']) && $_POST['specialty_id'] == $specialty['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($specialty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="license_number"><i class="fas fa-id-card"></i> Número de Licencia *</label>
                            <input type="text" id="license_number" name="license_number" class="form-control" 
                                   value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="consultation_fee"><i class="fas fa-dollar-sign"></i> Tarifa de Consulta</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo isset($_POST['consultation_fee']) ? htmlspecialchars($_POST['consultation_fee']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-user-plus"></i> Crear Cuenta
                    </button>
                </div>
            </form>

            <?php endif; ?>

            <div class="auth-links">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                <p><a href="index.php">← Volver al inicio</a></p>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 MediConsulta. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const patientRadio = document.getElementById('patient');
            const doctorRadio = document.getElementById('doctor');
            const doctorFields = document.querySelector('.doctor-fields');
            const typeOptions = document.querySelectorAll('.type-option');
            
            function toggleUserType() {
                // Actualizar estilos de selección
                typeOptions.forEach(option => {
                    option.classList.remove('active');
                });
                
                const selectedType = document.querySelector('input[name="user_type"]:checked');
                if (selectedType) {
                    selectedType.closest('.type-option').classList.add('active');
                    
                    // Mostrar/ocultar campos de doctor
                    if (selectedType.value === 'doctor') {
                        doctorFields.classList.add('show');
                        // Hacer campos obligatorios
                        document.getElementById('specialty_id').required = true;
                        document.getElementById('license_number').required = true;
                    } else {
                        doctorFields.classList.remove('show');
                        // Quitar obligatoriedad
                        document.getElementById('specialty_id').required = false;
                        document.getElementById('license_number').required = false;
                    }
                }
            }
            
            patientRadio.addEventListener('change', toggleUserType);
            doctorRadio.addEventListener('change', toggleUserType);
            
            // Ejecutar al cargar la página
            toggleUserType();
            
            // Validación de contraseñas
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Las contraseñas no coinciden');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });
    </script>
</body>
</html>