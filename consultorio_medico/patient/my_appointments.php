<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_patient()) {
    header('Location: ../login.php');
    exit;
}

$message = '';

// Manejar cancelación de cita
if (isset($_POST['cancel_appointment']) && isset($_POST['appointment_id'])) {
    $appointment_id = intval($_POST['appointment_id']);
    
    try {
        // Verificar que la cita pertenece al paciente y se puede cancelar
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ? AND status = 'scheduled' AND appointment_date > CURDATE()");
        $stmt->execute([$appointment_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $message = 'Cita cancelada exitosamente.';
        } else {
            $message = 'No se puede cancelar esta cita.';
        }
    } catch(PDOException $e) {
        $message = 'Error al cancelar la cita.';
    }
}

// Obtener todas las citas del paciente
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
           s.name as specialty_name,
           d.consultation_fee
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    JOIN doctors d ON u.id = d.user_id
    JOIN specialties s ON d.specialty_id = s.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll();

// Separar citas por estado
$upcoming = [];
$past = [];
$cancelled = [];

foreach ($appointments as $appointment) {
    $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
    $current_time = time();
    
    if ($appointment['status'] === 'cancelled') {
        $cancelled[] = $appointment;
    } elseif ($appointment_datetime > $current_time && $appointment['status'] === 'scheduled') {
        $upcoming[] = $appointment;
    } else {
        $past[] = $appointment;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Turnos - MediConsulta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .tab-button {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
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
        .appointment-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }
        .appointment-card.past {
            border-left-color: var(--text-light);
            opacity: 0.8;
        }
        .appointment-card.cancelled {
            border-left-color: var(--error-color);
        }
        .appointment-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .appointment-info {
            flex: 1;
        }
        .appointment-actions {
            display: flex;
            gap: 0.5rem;
        }
        .date-time {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                    <li><a href="request_appointment.php"><i class="fas fa-calendar-plus"></i> Pedir Turno</a></li>
                    <li><a href="my_appointments.php"><i class="fas fa-calendar-check"></i> Mis Turnos</a></li>
                    <li><a href="medical_history.php"><i class="fas fa-clipboard-list"></i> Historial</a></li>
                    <li><a href="my_data.php"><i class="fas fa-user-cog"></i> Mis Datos</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-calendar-check"></i> Mis Turnos Médicos</h1>
                    <p>Gestiona todas tus citas médicas</p>
                </div>
                <a href="request_appointment.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Nuevo Turno
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Pestañas -->
        <div class="tabs">
            <button class="tab-button active" onclick="showTab('upcoming')">
                <i class="fas fa-clock"></i> Próximas (<?php echo count($upcoming); ?>)
            </button>
            <button class="tab-button" onclick="showTab('past')">
                <i class="fas fa-history"></i> Pasadas (<?php echo count($past); ?>)
            </button>
            <button class="tab-button" onclick="showTab('cancelled')">
                <i class="fas fa-times-circle"></i> Canceladas (<?php echo count($cancelled); ?>)
            </button>
        </div>

        <!-- Citas Próximas -->
        <div id="upcoming" class="tab-content active">
            <?php if (empty($upcoming)): ?>
                <div class="card empty-state">
                    <i class="fas fa-calendar-plus"></i>
                    <h3>No tienes citas próximas</h3>
                    <p>¡Agenda tu próxima cita médica!</p>
                    <a href="request_appointment.php" class="btn btn-primary">Pedir Turno</a>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming as $appointment): ?>
                <div class="appointment-card">
                    <div class="appointment-header">
                        <div class="appointment-info">
                            <div class="date-time">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                <i class="fas fa-clock ml-2"></i>
                                <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h3>
                            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($appointment['specialty_name']); ?></p>
                            <p><i class="fas fa-dollar-sign"></i> $<?php echo number_format($appointment['consultation_fee'], 0, ',', '.'); ?></p>
                            <?php if ($appointment['notes']): ?>
                                <p><i class="fas fa-sticky-note"></i> <strong>Notas:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="appointment-actions">
                            <span class="status-<?php echo $appointment['status']; ?>">
                                <?php echo $appointment['status'] === 'scheduled' ? 'Programada' : ucfirst($appointment['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div style="font-size: 0.9rem; color: var(--text-light);">
                            <i class="fas fa-info-circle"></i> 
                            Puedes cancelar hasta <?php echo date('d/m/Y H:i', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'] . ' -24 hours')); ?>
                        </div>
                        <div>
                            <?php if (strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) > time() + 86400): // 24 horas ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas cancelar esta cita?');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                <button type="submit" name="cancel_appointment" class="btn btn-danger" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                    <i class="fas fa-times"></i> Cancelar
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="font-size: 0.9rem; color: var(--text-light);">No se puede cancelar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Citas Pasadas -->
        <div id="past" class="tab-content">
            <?php if (empty($past)): ?>
                <div class="card empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No tienes citas pasadas</h3>
                    <p>Aquí aparecerán tus consultas anteriores</p>
                </div>
            <?php else: ?>
                <?php foreach ($past as $appointment): ?>
                <div class="appointment-card past">
                    <div class="appointment-header">
                        <div class="appointment-info">
                            <div class="date-time">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                <i class="fas fa-clock ml-2"></i>
                                <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h3>
                            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($appointment['specialty_name']); ?></p>
                            <p><i class="fas fa-dollar-sign"></i> $<?php echo number_format($appointment['consultation_fee'], 0, ',', '.'); ?></p>
                            <?php if ($appointment['notes']): ?>
                                <p><i class="fas fa-sticky-note"></i> <strong>Notas:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="appointment-actions">
                            <span class="status-completed">
                                Completada
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <a href="medical_history.php" class="btn btn-outline" style="font-size: 0.9rem;">
                            <i class="fas fa-file-medical"></i> Ver Historial Médico
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Citas Canceladas -->
        <div id="cancelled" class="tab-content">
            <?php if (empty($cancelled)): ?>
                <div class="card empty-state">
                    <i class="fas fa-times-circle"></i>
                    <h3>No tienes citas canceladas</h3>
                    <p>Aquí aparecerán las citas que hayas cancelado</p>
                </div>
            <?php else: ?>
                <?php foreach ($cancelled as $appointment): ?>
                <div class="appointment-card cancelled">
                    <div class="appointment-header">
                        <div class="appointment-info">
                            <div class="date-time">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                <i class="fas fa-clock ml-2"></i>
                                <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h3>
                            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($appointment['specialty_name']); ?></p>
                            <?php if ($appointment['notes']): ?>
                                <p><i class="fas fa-sticky-note"></i> <strong>Notas:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="appointment-actions">
                            <span class="status-cancelled">
                                Cancelada
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <small style="color: var(--text-light);">
                            <i class="fas fa-info-circle"></i> 
                            Cita cancelada el <?php echo date('d/m/Y', strtotime($appointment['created_at'])); ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Información adicional -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Información Importante</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                <div style="padding: 1rem; background: #f0f9ff; border-radius: 8px;">
                    <h4><i class="fas fa-clock"></i> Política de Cancelación</h4>
                    <p>Las citas pueden cancelarse hasta 24 horas antes de la fecha programada.</p>
                </div>
                <div style="padding: 1rem; background: #ecfdf5; border-radius: 8px;">
                    <h4><i class="fas fa-bell"></i> Recordatorios</h4>
                    <p>Te enviaremos recordatorios por email 24 horas antes de tu cita.</p>
                </div>
                <div style="padding: 1rem; background: #fef3c7; border-radius: 8px;">
                    <h4><i class="fas fa-exclamation-triangle"></i> Llegadas Tarde</h4>
                    <p>Por favor llega 15 minutos antes de tu cita para completar el registro.</p>
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
        function showTab(tabName) {
            // Ocultar todos los contenidos de las pestañas
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar el contenido seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar el botón correspondiente
            event.target.classList.add('active');
        }

        // Actualizar estados de citas cada minuto
        setInterval(function() {
            // Aquí podrías hacer una petición AJAX para actualizar el estado de las citas
            // Por ahora, simplemente recargamos la página si han pasado citas programadas
            const now = new Date();
            const appointments = document.querySelectorAll('.appointment-card');
            
            appointments.forEach(card => {
                // Verificar si alguna cita ha pasado y debería moverse a "pasadas"
                const dateTimeText = card.querySelector('.date-time').textContent;
                // Implementar lógica de actualización si es necesario
            });
        }, 60000); // Cada minuto
    </script>
</body>
</html>