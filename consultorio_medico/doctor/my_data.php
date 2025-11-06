<?php
session_start();

// Verificar que sea doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Conexión a base de datos
try {
    $pdo = new PDO("mysql:host=localhost;dbname=consultorio_medico;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener información del usuario
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("Usuario no encontrado");
    }
} catch(PDOException $e) {
    die("Error al obtener usuario: " . $e->getMessage());
}

// Obtener información específica del doctor
$doctor_info = null;
try {
    $stmt = $pdo->prepare("
        SELECT d.*, s.name as specialty_name 
        FROM doctors d 
        LEFT JOIN specialties s ON d.specialty_id = s.id 
        WHERE d.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Si no existe en la tabla doctors, creamos un registro básico
    $doctor_info = [
        'specialty_name' => 'No definida',
        'license_number' => 'No definida',
        'consultation_fee' => 0,
        'years_experience' => 0
    ];
}

// Obtener todas las especialidades para el formulario
try {
    $stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
    $specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $specialties = [];
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $birth_date = trim($_POST['birth_date']);
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'El nombre y apellido son obligatorios';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, birth_date = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $address, $birth_date, $_SESSION['user_id']]);
                
                $success = 'Perfil actualizado correctamente';
                // Recargar datos
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                $error = 'Error al actualizar el perfil';
            }
        }
    }
    
    if (isset($_POST['update_professional'])) {
        $specialty_id = intval($_POST['specialty_id']);
        $license_number = trim($_POST['license_number']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        $years_experience = intval($_POST['years_experience']);
        
        try {
            // Verificar si ya existe registro en doctors
            $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE doctors SET specialty_id = ?, license_number = ?, consultation_fee = ?, years_experience = ? WHERE user_id = ?");
                $stmt->execute([$specialty_id, $license_number, $consultation_fee, $years_experience, $_SESSION['user_id']]);
            } else {
                // Insertar nuevo
                $stmt = $pdo->prepare("INSERT INTO doctors (user_id, specialty_id, license_number, consultation_fee, years_experience) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $specialty_id, $license_number, $consultation_fee, $years_experience]);
            }
            
            $success = 'Información profesional actualizada';
            
            // Recargar información del doctor
            $stmt = $pdo->prepare("
                SELECT d.*, s.name as specialty_name 
                FROM doctors d 
                LEFT JOIN specialties s ON d.specialty_id = s.id 
                WHERE d.user_id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $error = 'Error al actualizar información profesional: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Todos los campos de contraseña son obligatorios';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Las nuevas contraseñas no coinciden';
        } elseif (strlen($new_password) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres';
        } else {
            if (password_verify($current_password, $user['password'])) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    $success = 'Contraseña cambiada correctamente';
                } catch(PDOException $e) {
                    $error = 'Error al cambiar la contraseña';
                }
            } else {
                $error = 'La contraseña actual es incorrecta';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - MediConsulta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .tab-button {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-dark);
        }
        .tab-button.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-item {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }
        .info-label {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-weight: 500;
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <i class="fas fa-stethoscope"></i>
                <span>MediConsulta</span>
            </div>
            <nav>
                <ul class="nav-links">
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
                    <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Citas</a></li>
                    <li><a href="patient_history.php"><i class="fas fa-users"></i> Pacientes</a></li>
                    <li><a href="my_data.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <!-- Encabezado del perfil -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user-md"></i>
            </div>
            <h1>Dr. <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor_info['specialty_name'] ?? 'Especialidad no definida'); ?></p>
            <small>Miembro desde <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></small>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Pestañas -->
        <div class="tabs">
            <button class="tab-button active" onclick="showTab('personal')">
                <i class="fas fa-user"></i> Información Personal
            </button>
            <button class="tab-button" onclick="showTab('professional')">
                <i class="fas fa-user-md"></i> Información Profesional
            </button>
            <button class="tab-button" onclick="showTab('security')">
                <i class="fas fa-shield-alt"></i> Seguridad
            </button>
        </div>

        <!-- Información Personal -->
        <div id="personal" class="tab-content active">
            <div class="card">
                <h2><i class="fas fa-user"></i> Información Personal</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nombre Completo</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Correo Electrónico</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value"><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : 'No especificado'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Fecha de Nacimiento</div>
                        <div class="info-value">
                            <?php 
                            if ($user['birth_date']) {
                                $age = (new DateTime())->diff(new DateTime($user['birth_date']))->y;
                                echo date('d/m/Y', strtotime($user['birth_date'])) . " ($age años)";
                            } else {
                                echo 'No especificada';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <?php if ($user['address']): ?>
                <div class="info-item" style="margin-top: 1rem;">
                    <div class="info-label">Dirección</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['address']); ?></div>
                </div>
                <?php endif; ?>

                <h3>Actualizar Información Personal</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Nombre *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Apellido *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Teléfono</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_date">Fecha de Nacimiento</label>
                            <input type="date" id="birth_date" name="birth_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['birth_date']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Dirección</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Actualizar Información Personal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Información Profesional -->
        <div id="professional" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-user-md"></i> Información Profesional</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Especialidad</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor_info['specialty_name'] ?? 'No definida'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Número de Licencia</div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor_info['license_number'] ?? 'No definida'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Tarifa de Consulta</div>
                        <div class="info-value">$<?php echo number_format($doctor_info['consultation_fee'] ?? 0, 0, ',', '.'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Años de Experiencia</div>
                        <div class="info-value"><?php echo ($doctor_info['years_experience'] ?? 0) . ' años'; ?></div>
                    </div>
                </div>

                <h3>Actualizar Información Profesional</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialty_id">Especialidad *</label>
                            <select id="specialty_id" name="specialty_id" class="form-control" required>
                                <option value="">Seleccionar especialidad</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?php echo $specialty['id']; ?>" 
                                            <?php echo (isset($doctor_info['specialty_id']) && $doctor_info['specialty_id'] == $specialty['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($specialty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number">Número de Licencia *</label>
                            <input type="text" id="license_number" name="license_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($doctor_info['license_number'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="consultation_fee">Tarifa de Consulta ($)</label>
                            <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $doctor_info['consultation_fee'] ?? 0; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="years_experience">Años de Experiencia</label>
                            <input type="number" id="years_experience" name="years_experience" class="form-control" 
                                   min="0" value="<?php echo $doctor_info['years_experience'] ?? 0; ?>">
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="update_professional" class="btn btn-success">
                            <i class="fas fa-save"></i> Actualizar Información Profesional
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Seguridad -->
        <div id="security" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-shield-alt"></i> Seguridad de la Cuenta</h2>
                
                <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <h4><i class="fas fa-info-circle"></i> Información de Seguridad</h4>
                    <p>Cuenta creada: <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                    <p>Email: <strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
                    <p>Usuario: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
                </div>

                <h3>Cambiar Contraseña</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual *</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Nueva Contraseña *</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" 
                                   minlength="6" required>
                            <small style="color: var(--text-light);">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Nueva Contraseña *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   minlength="6" required>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 MediConsulta. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        function showTab(tabName) {
            // Ocultar todos los contenidos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Desactivar todos los botones
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar contenido seleccionado
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Validación de contraseñas
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (newPassword && confirmPassword) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Las contraseñas no coinciden');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            }
            
            if (newPassword) newPassword.addEventListener('input', validatePasswords);
            if (confirmPassword) confirmPassword.addEventListener('input', validatePasswords);
        });
    </script>
</body>
</html>