<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_doctor()) {
    header('Location: ../login.php');
    exit;
}

// Obtiene el nombre de usuario directamente desde la sesión
$user = $_SESSION['username'];

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
            <?php
        // $user ya es la cadena de texto con el nombre de usuario
        echo "Bienvenido/a, Dr. " . $user; 
        ?>
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

        <!-- Citas de hoy -->
        <?php if (!empty($today_appointments_list)): ?>
        <div class="card">
            <h2><i class="fas fa-calendar-day"></i> Citas de Hoy - <?php echo date('d/m/Y'); ?></h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th>Teléfono</th>
                            <th>Notas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_appointments_list as $appointment): ?>
                        <tr>
                            <td><strong><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['patient_phone']) ?: 'No disponible'; ?></td>
                            <td><?php echo $appointment['notes'] ? htmlspecialchars(substr($appointment['notes'], 0, 50)) . '...' : '-'; ?></td>
                            <td>
                                <a href="appointments.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center">
                <a href="appointments.php?filter=today" class="btn btn-primary">Ver Todas las Citas de Hoy</a>
            </div>
        </div>
        <?php else: ?>
        <div class="card text-center">
            <h2><i class="fas fa-calendar-day"></i> No tienes citas hoy</h2>
            <p>¡Disfruta tu día libre!</p>
            <a href="appointments.php" class="btn btn-primary">Ver Próximas Citas</a>
        </div>
        <?php endif; ?>

        <!-- Próximas citas -->
        <?php if (!empty($upcoming_appointments_list)): ?>
        <div class="card">
            <h2><i class="fas fa-calendar-week"></i> Próximas Citas</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_appointments_list as $appointment): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                            <td><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['patient_phone']) ?: 'No disponible'; ?></td>
                            <td><span class="status-<?php echo $appointment['status']; ?>">Programada</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center">
                <a href="appointments.php" class="btn btn-primary">Ver Todas las Citas</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Información del doctor -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Mi Información Profesional</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div style="padding: 1rem; background: #f0f9ff; border-radius: 8px;">
                    <h4><i class="fas fa-stethoscope"></i> Especialidad</h4>
                    <p><strong><?php echo htmlspecialchars($doctor_info['specialty_name']); ?></strong></p>
                </div>
                
                <div style="padding: 1rem; background: #ecfdf5; border-radius: 8px;">
                    <h4><i class="fas fa-id-card"></i> Licencia</h4>
                    <p><strong><?php echo htmlspecialchars($doctor_info['license_number']); ?></strong></p>
                </div>
                
                <div style="padding: 1rem; background: #fef3c7; border-radius: 8px;">
                    <h4><i class="fas fa-dollar-sign"></i> Tarifa Consulta</h4>
                    <p><strong>$<?php echo number_format($doctor_info['consultation_fee'], 0, ',', '.'); ?></strong></p>
                </div>
                
                <div style="padding: 1rem; background: #f3e8ff; border-radius: 8px;">
                    <h4><i class="fas fa-clock"></i> Horario</h4>
                    <p><strong>Lun-Vie 9:00-17:00</strong></p>
                </div>
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

    <script>
        // Actualizar estadísticas en tiempo real (opcional)
        function updateStats() {
            // Aquí podrías hacer una petición AJAX para actualizar las estadísticas
            // Por ahora, solo mostraremos la hora actual
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES');
            
            // Si existe un elemento para mostrar la hora
            const timeElements = document.querySelectorAll('.current-time');
            timeElements.forEach(el => {
                el.textContent = timeString;
            });
        }

        // Actualizar cada minuto
        setInterval(updateStats, 60000);
        updateStats();

        // Resaltar citas próximas (dentro de 1 hora)
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const timeCell = row.querySelector('td:first-child strong');
                if (timeCell) {
                    const appointmentTime = timeCell.textContent;
                    const [hours, minutes] = appointmentTime.split(':').map(Number);
                    
                    const appointmentDate = new Date();
                    appointmentDate.setHours(hours, minutes, 0, 0);
                    
                    const timeDiff = appointmentDate.getTime() - now.getTime();
                    const hoursDiff = timeDiff / (1000 * 60 * 60);
                    
                    // Si la cita es dentro de 1 hora, resaltarla
                    if (hoursDiff > 0 && hoursDiff <= 1) {
                        row.style.backgroundColor = '#fef3c7';
                        row.style.borderLeft = '4px solid var(--warning-color)';
                    }
                }
            });
        });
    </script>
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