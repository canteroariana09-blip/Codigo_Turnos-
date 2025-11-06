<?php
session_start();

// Verificar que sea paciente
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
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

// Obtener información específica del paciente
$patient_info = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Si no existe en la tabla patients, creamos un registro básico
    $patient_info = [
        'dni' => '',
        'gender' => '',
        'marital_status' => '',
        'social_security_name' => '',
        'social_security_number' => '',
        'social_security_plan' => '',
        'social_security_expiration' => '',
        'allergies' => '',
        'pre_existing_conditions' => '',
        'medication' => '',
        'surgical_history' => '',
        'family_history' => ''
    ];
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Actualizar perfil
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $birth_date = trim($_POST['birth_date']);
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'El nombre y apellido son obligatorios.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, birth_date = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $address, $birth_date, $_SESSION['user_id']]);
                
                $success = 'Perfil actualizado correctamente.';
                $pdo->commit();
            } catch(PDOException $e) {
                $pdo->rollBack();
                $error = 'Error al actualizar el perfil: ' . $e->getMessage();
            }
        }
    }
    
    // Actualizar información médica
    if (isset($_POST['update_medical_info'])) {
        $dni = trim($_POST['dni']);
        $gender = trim($_POST['gender']);
        $marital_status = trim($_POST['marital_status']);
        $social_security_name = trim($_POST['social_security_name']);
        $social_security_number = trim($_POST['social_security_number']);
        $social_security_plan = trim($_POST['social_security_plan']);
        $social_security_expiration = trim($_POST['social_security_expiration']);
        $allergies = trim($_POST['allergies']);
        $pre_existing_conditions = trim($_POST['pre_existing_conditions']);
        $medication = trim($_POST['medication']);
        $surgical_history = trim($_POST['surgical_history']);
        $family_history = trim($_POST['family_history']);

        try {
            $pdo->beginTransaction();
            // Verificar si ya existe un registro en la tabla patients
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                // Actualizar
                $stmt = $pdo->prepare("
                    UPDATE patients 
                    SET dni = ?, gender = ?, marital_status = ?, social_security_name = ?, social_security_number = ?, social_security_plan = ?, social_security_expiration = ?, allergies = ?, pre_existing_conditions = ?, medication = ?, surgical_history = ?, family_history = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$dni, $gender, $marital_status, $social_security_name, $social_security_number, $social_security_plan, $social_security_expiration, $allergies, $pre_existing_conditions, $medication, $surgical_history, $family_history, $_SESSION['user_id']]);
            } else {
                // Insertar nuevo
                $stmt = $pdo->prepare("
                    INSERT INTO patients (user_id, dni, gender, marital_status, social_security_name, social_security_number, social_security_plan, social_security_expiration, allergies, pre_existing_conditions, medication, surgical_history, family_history) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $dni, $gender, $marital_status, $social_security_name, $social_security_number, $social_security_plan, $social_security_expiration, $allergies, $pre_existing_conditions, $medication, $surgical_history, $family_history]);
            }
            
            $success = 'Información médica actualizada correctamente.';
            $pdo->commit();
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Error al actualizar la información médica: ' . $e->getMessage();
        }
    }
    
    // Cambiar contraseña
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Todos los campos de contraseña son obligatorios.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Las nuevas contraseñas no coinciden.';
        } elseif (strlen($new_password) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } else {
            if (password_verify($current_password, $user['password'])) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    $success = 'Contraseña cambiada correctamente.';
                } catch(PDOException $e) {
                    $error = 'Error al cambiar la contraseña: ' . $e->getMessage();
                }
            } else {
                $error = 'La contraseña actual es incorrecta.';
            }
        }
    }
    
    // Recargar datos para mostrar los cambios
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
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
                    <li><a href="find_doctor.php"><i class="fas fa-user-md"></i> Buscar Doctor</a></li>
                    <li><a href="my_data.php"><i class="fas fa-user-cog"></i> Mi Perfil</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
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

        <div class="tabs">
            <button class="tab-button active" onclick="showTab('personal')">
                <i class="fas fa-user"></i> Información Personal
            </button>
            <button class="tab-button" onclick="showTab('medical')">
                <i class="fas fa-notes-medical"></i> Historia Clínica
            </button>
            <button class="tab-button" onclick="showTab('security')">
                <i class="fas fa-shield-alt"></i> Seguridad
            </button>
        </div>

        <div id="personal" class="tab-content active">
            <div class="card">
                <h2><i class="fas fa-user"></i> Información Personal</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nombre Completo</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">DNI</div>
                        <div class="info-value"><?php echo $patient_info['dni'] ? htmlspecialchars($patient_info['dni']) : 'No especificado'; ?></div>
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
                    
                    <div class="info-item">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value"><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : 'No especificado'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Correo Electrónico</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Domicilio</div>
                        <div class="info-value"><?php echo $user['address'] ? htmlspecialchars($user['address']) : 'No especificado'; ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Sexo</div>
                        <div class="info-value"><?php echo $patient_info['gender'] ? htmlspecialchars($patient_info['gender']) : 'No especificado'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Estado Civil</div>
                        <div class="info-value"><?php echo $patient_info['marital_status'] ? htmlspecialchars($patient_info['marital_status']) : 'No especificado'; ?></div>
                    </div>
                </div>

                <h3>Actualizar Información Personal</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Nombre *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Apellido *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Teléfono</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_date">Fecha de Nacimiento</label>
                            <input type="date" id="birth_date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($user['birth_date']); ?>">
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

        <div id="medical" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-notes-medical"></i> Historia Clínica y Datos Médicos</h2>
                
                <h3>Datos de Obra Social / Cobertura Médica</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Obra Social / Prepaga</div>
                        <div class="info-value"><?php echo $patient_info['social_security_name'] ? htmlspecialchars($patient_info['social_security_name']) : 'No especificado'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">N° de Afiliado</div>
                        <div class="info-value"><?php echo $patient_info['social_security_number'] ? htmlspecialchars($patient_info['social_security_number']) : 'No especificado'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Plan / Categoría</div>
                        <div class="info-value"><?php echo $patient_info['social_security_plan'] ? htmlspecialchars($patient_info['social_security_plan']) : 'No especificado'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Vencimiento Credencial</div>
                        <div class="info-value"><?php echo $patient_info['social_security_expiration'] ? date('d/m/Y', strtotime($patient_info['social_security_expiration'])) : 'No especificado'; ?></div>
                    </div>
                </div>

                <h3>Antecedentes Médicos Relevantes</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Alergias</div>
                        <div class="info-value"><?php echo $patient_info['allergies'] ? htmlspecialchars($patient_info['allergies']) : 'Ninguna conocida'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Enfermedades Preexistentes</div>
                        <div class="info-value"><?php echo $patient_info['pre_existing_conditions'] ? htmlspecialchars($patient_info['pre_existing_conditions']) : 'Ninguna conocida'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Medicación Habitual</div>
                        <div class="info-value"><?php echo $patient_info['medication'] ? htmlspecialchars($patient_info['medication']) : 'Ninguna'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Antecedentes Quirúrgicos</div>
                        <div class="info-value"><?php echo $patient_info['surgical_history'] ? htmlspecialchars($patient_info['surgical_history']) : 'Ninguno'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Antecedentes Familiares</div>
                        <div class="info-value"><?php echo $patient_info['family_history'] ? htmlspecialchars($patient_info['family_history']) : 'Ninguno'; ?></div>
                    </div>
                </div>
                
                <h3>Actualizar Información Médica</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dni">DNI / Documento de Identidad</label>
                            <input type="text" id="dni" name="dni" class="form-control" value="<?php echo htmlspecialchars($patient_info['dni'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="gender">Sexo / Género</label>
                            <select id="gender" name="gender" class="form-control">
                                <option value="">Seleccionar</option>
                                <option value="Masculino" <?php echo ($patient_info['gender'] == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="Femenino" <?php echo ($patient_info['gender'] == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                                <option value="Otro" <?php echo ($patient_info['gender'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="marital_status">Estado Civil</label>
                            <select id="marital_status" name="marital_status" class="form-control">
                                <option value="">Seleccionar</option>
                                <option value="Soltero" <?php echo ($patient_info['marital_status'] == 'Soltero') ? 'selected' : ''; ?>>Soltero/a</option>
                                <option value="Casado" <?php echo ($patient_info['marital_status'] == 'Casado') ? 'selected' : ''; ?>>Casado/a</option>
                                <option value="Divorciado" <?php echo ($patient_info['marital_status'] == 'Divorciado') ? 'selected' : ''; ?>>Divorciado/a</option>
                                <option value="Viudo" <?php echo ($patient_info['marital_status'] == 'Viudo') ? 'selected' : ''; ?>>Viudo/a</option>
                                <option value="Union libre" <?php echo ($patient_info['marital_status'] == 'Union libre') ? 'selected' : ''; ?>>Unión Libre</option>
                            </select>
                        </div>
                    </div>

                    <h3>Datos de Cobertura Médica</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="social_security_name">Nombre de la Obra Social / Prepaga</label>
                            <input type="text" id="social_security_name" name="social_security_name" class="form-control" value="<?php echo htmlspecialchars($patient_info['social_security_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="social_security_number">N° de Afiliado</label>
                            <input type="text" id="social_security_number" name="social_security_number" class="form-control" value="<?php echo htmlspecialchars($patient_info['social_security_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="social_security_plan">Plan o Categoría</label>
                            <input type="text" id="social_security_plan" name="social_security_plan" class="form-control" value="<?php echo htmlspecialchars($patient_info['social_security_plan'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="social_security_expiration">Vencimiento de Credencial</label>
                            <input type="date" id="social_security_expiration" name="social_security_expiration" class="form-control" value="<?php echo htmlspecialchars($patient_info['social_security_expiration'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <h3>Antecedentes Médicos</h3>
                    <div class="form-group">
                        <label for="allergies">Alergias</label>
                        <textarea id="allergies" name="allergies" class="form-control" rows="3"><?php echo htmlspecialchars($patient_info['allergies'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="pre_existing_conditions">Enfermedades Preexistentes</label>
                        <textarea id="pre_existing_conditions" name="pre_existing_conditions" class="form-control" rows="3"><?php echo htmlspecialchars($patient_info['pre_existing_conditions'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="medication">Medicación Habitual</label>
                        <textarea id="medication" name="medication" class="form-control" rows="3"><?php echo htmlspecialchars($patient_info['medication'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="surgical_history">Antecedentes Quirúrgicos</label>
                        <textarea id="surgical_history" name="surgical_history" class="form-control" rows="3"><?php echo htmlspecialchars($patient_info['surgical_history'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="family_history">Antecedentes Familiares Relevantes</label>
                        <textarea id="family_history" name="family_history" class="form-control" rows="3"><?php echo htmlspecialchars($patient_info['family_history'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="update_medical_info" class="btn btn-success">
                            <i class="fas fa-save"></i> Actualizar Historia Clínica
                        </button>
                    </div>
                </form>
            </div>
        </div>

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
                            <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required>
                            <small style="color: var(--text-light);">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Nueva Contraseña *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
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
            
            // Mostrar contenido seleccionado y activar el botón
            document.getElementById(tabName).classList.add('active');
            const clickedButton = document.querySelector(`.tab-button[onclick="showTab('${tabName}')"]`);
            if (clickedButton) {
                clickedButton.classList.add('active');
            }
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

        // Asegurar que la primera pestaña esté activa al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            showTab('personal');
        });
    </script>
</body>
</html>