<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_doctor()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// Manejar acciones de citas
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = clean_input($_POST['new_status']);
        
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$new_status, $appointment_id, $_SESSION['user_id']]);
            $message = 'Estado de la cita actualizado correctamente';
        } catch(PDOException $e) {
            $error = 'Error al actualizar la cita';
        }
    }
    
    if (isset($_POST['add_medical_record'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $patient_id = intval($_POST['patient_id']);
        $diagnosis = clean_input($_POST['diagnosis']);
        $treatment = clean_input($_POST['treatment']);
        $medications = clean_input($_POST['medications']);
        $notes = clean_input($_POST['notes']);
        $symptoms = clean_input($_POST['symptoms']);
        $visit_date = clean_input($_POST['visit_date']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, treatment, medications, notes, symptoms, visit_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$patient_id, $_SESSION['user_id'], $appointment_id, $diagnosis, $treatment, $medications, $notes, $symptoms, $visit_date]);
            
            // Actualizar estado de la cita a completada
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$appointment_id, $_SESSION['user_id']]);
            
            $message = 'Registro médico añadido y cita marcada como completada';
        } catch(PDOException $e) {
            $error = 'Error al guardar el registro médico';
        }
    }
}

// Obtener filtros
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Construir consulta según filtros
$where_conditions = ["a.doctor_id = ?"];
$params = [$_SESSION['user_id']];

if ($filter === 'today') {
    $where_conditions[] = "a.appointment_date = CURDATE()";
} elseif ($filter === 'upcoming') {
    $where_conditions[] = "a.appointment_date > CURDATE() AND a.status = 'scheduled'";
} elseif ($filter === 'completed') {
    $where_conditions[] = "a.status = 'completed'";
} elseif ($filter === 'cancelled') {
    $where_conditions[] = "a.status = 'cancelled'";
}

if ($search) {
    $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR a.notes LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($date_from) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener citas del doctor
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as patient_name,
           u.email as patient_email,
           u.phone as patient_phone,
           u.birth_date as patient_birth_date,
           (SELECT COUNT(*) FROM medical_records mr WHERE mr.appointment_id = a.id) as has_medical_record
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Obtener estadísticas rápidas
$stats_query = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'scheduled' AND appointment_date = CURDATE() THEN 1 END) as today,
        COUNT(CASE WHEN status = 'scheduled' AND appointment_date > CURDATE() THEN 1 END) as upcoming,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
    FROM appointments 
    WHERE doctor_id = ?
");
$stats_query->execute([$_SESSION['user_id']]);
$stats = $stats_query->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Citas - MediConsulta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 25px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-tab.active,
        .filter-tab:hover {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        .appointment-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }
        .appointment-card.completed {
            border-left-color: var(--success-color);
        }
        .appointment-card.cancelled {
            border-left-color: var(--error-color);
        }
        .appointment-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        .patient-info {
            flex: 1;
        }
        .appointment-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-item {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
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
        <div class="card">
            <h1><i class="fas fa-calendar-check"></i> Gestión de Citas Médicas</h1>
            <p>Administra todas tus citas y consultas de manera eficiente</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['today']; ?></div>
                <div>Citas Hoy</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['upcoming']; ?></div>
                <div>Próximas</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div>Completadas</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                <div>Canceladas</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div>Total</div>
            </div>
        </div>

        <!-- Filtros y búsqueda -->
        <div class="filters-section">
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Todas (<?php echo $stats['total']; ?>)
                </a>
                <a href="?filter=today" class="filter-tab <?php echo $filter === 'today' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> Hoy (<?php echo $stats['today']; ?>)
                </a>
                <a href="?filter=upcoming" class="filter-tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> Próximas (<?php echo $stats['upcoming']; ?>)
                </a>
                <a href="?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Completadas (<?php echo $stats['completed']; ?>)
                </a>
                <a href="?filter=cancelled" class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Canceladas (<?php echo $stats['cancelled']; ?>)
                </a>
            </div>

            <form method="GET" class="d-flex gap-3 align-items-center" style="flex-wrap: wrap;">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                
                <div class="form-group" style="margin: 0; min-width: 200px;">
                    <input type="text" name="search" placeholder="Buscar paciente..." class="form-control"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <input type="date" name="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>" placeholder="Desde">
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <input type="date" name="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>" placeholder="Hasta">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
                
                <a href="appointments.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </form>
        </div>

        <!-- Lista de citas -->
        <?php if (empty($appointments)): ?>
            <div class="card text-center">
                <i class="fas fa-calendar" style="font-size: 4rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                <h3>No se encontraron citas</h3>
                <p>No hay citas que coincidan con los filtros seleccionados.</p>
                <a href="appointments.php" class="btn btn-primary">Ver Todas las Citas</a>
            </div>
        <?php else: ?>
            <?php foreach ($appointments as $appointment): ?>
            <div class="appointment-card <?php echo $appointment['status']; ?>">
                <div class="appointment-header">
                    <div class="patient-info">
                        <h3>
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($appointment['patient_name']); ?>
                            <span class="status-<?php echo $appointment['status']; ?>" style="font-size: 0.8rem; margin-left: 1rem;">
                                <?php 
                                echo match($appointment['status']) {
                                    'scheduled' => 'Programada',
                                    'completed' => 'Completada',
                                    'cancelled' => 'Cancelada',
                                    'no_show' => 'No asistió',
                                    default => ucfirst($appointment['status'])
                                };
                                ?>
                            </span>
                        </h3>
                        
                        <div style="font-size: 1.1rem; font-weight: bold; color: var(--primary-color); margin: 0.5rem 0;">
                            <i class="fas fa-calendar"></i> 
                            <?php echo date('l, d/m/Y', strtotime($appointment['appointment_date'])); ?>
                            <i class="fas fa-clock ml-2"></i>
                            <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                        </div>
                    </div>
                    
                    <div class="appointment-actions">
                        <?php if ($appointment['status'] === 'scheduled'): ?>
                            <button onclick="openStatusModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>')" class="btn btn-warning" style="font-size: 0.8rem;">
                                <i class="fas fa-edit"></i> Estado
                            </button>
                            
                            <button onclick="openMedicalRecordModal(<?php echo $appointment['id']; ?>, <?php echo $appointment['patient_id']; ?>, '<?php echo htmlspecialchars($appointment['patient_name']); ?>')" class="btn btn-success" style="font-size: 0.8rem;">
                                <i class="fas fa-file-medical"></i> Completar
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($appointment['has_medical_record'] > 0): ?>
                            <a href="patient_history.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn btn-info" style="font-size: 0.8rem;">
                                <i class="fas fa-eye"></i> Ver Historial
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="patient-details">
                    <div class="detail-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($appointment['patient_email']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars($appointment['patient_phone']) ?: 'No disponible'; ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <i class="fas fa-birthday-cake"></i>
                        <span>
                            <?php 
                            if ($appointment['patient_birth_date']) {
                                $age = (new DateTime())->diff(new DateTime($appointment['patient_birth_date']))->y;
                                echo date('d/m/Y', strtotime($appointment['patient_birth_date'])) . " ($age años)";
                            } else {
                                echo 'No disponible';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <span>Creada: <?php echo date('d/m/Y H:i', strtotime($appointment['created_at'])); ?></span>
                    </div>
                </div>

                <?php if ($appointment['reason_for_visit'] || $appointment['notes']): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <?php if ($appointment['reason_for_visit']): ?>
                        <p><strong><i class="fas fa-stethoscope"></i> Motivo de consulta:</strong> <?php echo htmlspecialchars($appointment['reason_for_visit']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($appointment['notes']): ?>
                        <p><strong><i class="fas fa-sticky-note"></i> Notas del paciente:</strong> <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- Modal para cambiar estado -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('statusModal')">&times;</span>
            <h2><i class="fas fa-edit"></i> Cambiar Estado de Cita</h2>
            <form method="POST">
                <input type="hidden" name="appointment_id" id="status_appointment_id">
                
                <div class="form-group">
                    <label for="new_status">Nuevo Estado:</label>
                    <select name="new_status" id="new_status" class="form-control" required>
                        <option value="scheduled">Programada</option>
                        <option value="completed">Completada</option>
                        <option value="cancelled">Cancelada</option>
                        <option value="no_show">No asistió</option>
                    </select>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Estado
                    </button>
                    <button type="button" class="btn btn-outline ml-2" onclick="closeModal('statusModal')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para registro médico -->
    <div id="medicalRecordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('medicalRecordModal')">&times;</span>
            <h2><i class="fas fa-file-medical"></i> Completar Consulta</h2>
            <p>Paciente: <strong id="medical_patient_name"></strong></p>
            
            <form method="POST">
                <input type="hidden" name="appointment_id" id="medical_appointment_id">
                <input type="hidden" name="patient_id" id="medical_patient_id">
                
                <div class="form-group">
                    <label for="visit_date">Fecha de la Consulta:</label>
                    <input type="date" name="visit_date" id="visit_date" class="form-control" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="symptoms">Síntomas Presentados:</label>
                    <textarea name="symptoms" id="symptoms" class="form-control" rows="3"
                              placeholder="Describe los síntomas que presenta el paciente..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="diagnosis">Diagnóstico *:</label>
                    <textarea name="diagnosis" id="diagnosis" class="form-control" rows="3" required
                              placeholder="Diagnóstico médico detallado..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="treatment">Tratamiento:</label>
                    <textarea name="treatment" id="treatment" class="form-control" rows="3"
                              placeholder="Plan de tratamiento recomendado..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="medications">Medicamentos Recetados:</label>
                    <textarea name="medications" id="medications" class="form-control" rows="3"
                              placeholder="Lista de medicamentos, dosis y frecuencia..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notas Adicionales:</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"
                              placeholder="Observaciones adicionales..."></textarea>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="add_medical_record" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar y Completar Cita
                    </button>
                    <button type="button" class="btn btn-outline ml-2" onclick="closeModal('medicalRecordModal')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 MediConsulta. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        function openStatusModal(appointmentId, currentStatus) {
            document.getElementById('status_appointment_id').value = appointmentId;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function openMedicalRecordModal(appointmentId, patientId, patientName) {
            document.getElementById('medical_appointment_id').value = appointmentId;
            document.getElementById('medical_patient_id').value = patientId;
            document.getElementById('medical_patient_name').textContent = patientName;
            document.getElementById('medicalRecordModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Auto-actualizar página cada 5 minutos para citas del día
        if (window.location.search.includes('filter=today')) {
            setTimeout(() => {
                window.location.reload();
            }, 300000); // 5 minutos
        }
    </script>
</body>
</html>