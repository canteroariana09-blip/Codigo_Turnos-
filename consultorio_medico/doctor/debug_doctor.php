<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_doctor()) {
    header('Location: ../login.php');
    exit;
}

$user = get_current_user($pdo);

// Obtener información del doctor
$stmt = $pdo->prepare("
    SELECT d.*, s.name as specialty_name 
    FROM doctors d 
    JOIN specialties s ON d.specialty_id = s.id 
    WHERE d.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor_info = $stmt->fetch();

// Estadísticas del doctor
$stmt = $pdo->prepare("SELECT COUNT(*) as total_appointments FROM appointments WHERE doctor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_appointments = $stmt->fetch()['total_appointments'];

$stmt = $pdo->prepare("SELECT COUNT(*) as today_appointments FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status = 'scheduled'");
$stmt->execute([$_SESSION['user_id']]);
$today_appointments = $stmt->fetch()['today_appointments'];

$stmt = $pdo->prepare("SELECT COUNT(*) as upcoming_appointments FROM appointments WHERE doctor_id = ? AND appointment_date > CURDATE() AND status = 'scheduled'");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_appointments = $stmt->fetch()['upcoming_appointments'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total_patients FROM appointments WHERE doctor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_patients = $stmt->fetch()['total_patients'];

// Citas de hoy
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as patient_name,
           u.phone as patient_phone
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status = 'scheduled'
    ORDER BY a.appointment_time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$today_appointments_list = $stmt->fetchAll();

// Próximas citas (siguientes 5 días)
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as patient_name,
           u.phone as patient_phone
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 5 DAY 
    AND a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_appointments_list = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Doctor - MediConsulta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        <div class="card">
            <h1><i class="fas fa-user-md"></i> Bienvenido/a, Dr. <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
            <p>Especialidad: <strong><?php echo htmlspecialchars($doctor_info['specialty_name']); ?></strong></p>
            <p>Gestiona tus citas y pacientes desde tu panel profesional.</p>
        </div>

        <!-- Estadísticas -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <h3>Próximas Citas</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">
                    <?php echo $upcoming_appointments; ?>
                </div>
                <p>Citas programadas</p>
            </div>

            <div class="dashboard-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Total Pacientes</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--secondary-color);">
                    <?php echo $total_patients; ?>
                </div>
                <p>Pacientes atendidos</p>
            </div>

            <div class="dashboard-card">
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Total Consultas</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--success-color);">
                    <?php echo $total_appointments; ?>
                </div>
                <p>Citas realizadas</p>
            </div>
        </div>

        <!-- Mensaje si no tienes la carpeta doctor completa -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Panel de Doctor Funcionando</h2>
            <p>✅ ¡Excelente! Ya estás en el panel del doctor.</p>
            <p>🩺 Especialidad: <strong><?php echo htmlspecialchars($doctor_info['specialty_name'] ?? 'No definida'); ?></strong></p>
            <p>📋 Licencia: <strong><?php echo htmlspecialchars($doctor_info['license_number'] ?? 'No definida'); ?></strong></p>
            
            <div style="margin-top: 2rem;">
                <h3>🚀 Funcionalidades Disponibles:</h3>
                <ul style="list-style: none; padding: 0;">
                    <li>📅 <a href="appointments.php" style="color: var(--primary-color);">Gestión de Citas</a> - Ver y administrar todas tus citas</li>
                    <li>👥 <a href="patient_history.php" style="color: var(--primary-color);">Historiales de Pacientes</a> - Consultar expedientes médicos</li>
                    <li>⚙️ <a href="my_data.php" style="color: var(--primary-color);">Mi Perfil</a> - Actualizar información profesional</li>
                </ul>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="card">
            <h2><i class="fas fa-bolt"></i> Acciones Rápidas</h2>
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <a href="appointments.php" class="dashboard-card" style="text-decoration: none; color: inherit;">
                    <div class="icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Ver Citas</h3>
                    <p>Gestionar agenda médica</p>
                </a>

                <a href="patient_history.php" class="dashboard-card" style="text-decoration: none; color: inherit;">
                    <div class="icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h3>Historiales</h3>
                    <p>Consultar pacientes</p>
                </a>

                <a href="appointments.php?filter=today" class="dashboard-card" style="text-decoration: none; color: inherit;">
                    <div class="icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <h3>Agenda Hoy</h3>
                    <p>Citas de hoy</p>
                </a>

                <a href="my_data.php" class="dashboard-card" style="text-decoration: none; color: inherit;">
                    <div class="icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h3>Mi Perfil</h3>
                    <p>Configurar perfil</p>
                </a>
            </div>
        </div>

        <!-- Información básica si no hay citas -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Información del Sistema</h2>
            <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 8px; margin: 1rem 0;">
                <h4>📋 Para empezar a usar el sistema:</h4>
                <ol>
                    <li>Los pacientes deben <strong>registrarse</strong> y <strong>pedir turnos</strong></li>
                    <li>Tú puedes ver las citas en <a href="appointments.php">Gestión de Citas</a></li>
                    <li>Cuando atiendas un paciente, podrás <strong>completar la consulta</strong> y crear el historial médico</li>
                    <li>Los historiales quedan guardados en <a href="patient_history.php">Historiales de Pacientes</a></li>
                </ol>
            </div>
        </div>

        <!-- Consejos profesionales -->
        <div class="card">
            <h2><i class="fas fa-lightbulb"></i> Consejos Profesionales</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <div style="padding: 1rem; background: #f0f9ff; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                    <h4><i class="fas fa-user-friends"></i> Comunicación con Pacientes</h4>
                    <p>Mantén una comunicación clara y empática. Explica los diagnósticos en términos comprensibles.</p>
                </div>
                
                <div style="padding: 1rem; background: #ecfdf5; border-radius: 8px; border-left: 4px solid var(--success-color);">
                    <h4><i class="fas fa-clipboard-list"></i> Documentación</h4>
                    <p>Registra detalladamente cada consulta en el historial médico del paciente.</p>
                </div>
                
                <div style="padding: 1rem; background: #fef3c7; border-radius: 8px; border-left: 4px solid var(--warning-color);">
                    <h4><i class="fas fa-clock"></i> Puntualidad</h4>
                    <p>Mantén los horarios de consulta. Los pacientes valoran la puntualidad profesional.</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 MediConsulta. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>d-card">
                <div class="icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <h3>Citas Hoy</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--warning-color);">
                    <?php echo $today_appointments; ?>
                </div>
                <p>Citas programadas para hoy</p>
            </div>

            <div class="dashboar