<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_doctor()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// Obtener paciente específico si se solicita
$selected_patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Manejar edición de registro médico
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_medical_record'])) {
    $record_id = intval($_POST['record_id']);
    $diagnosis = clean_input($_POST['diagnosis']);
    $treatment = clean_input($_POST['treatment']);
    $medications = clean_input($_POST['medications']);
    $notes = clean_input($_POST['notes']);
    
    try {
        // Verificar que el registro pertenece al doctor
        $stmt = $pdo->prepare("SELECT id FROM medical_records WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$record_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE medical_records 
                SET diagnosis = ?, treatment = ?, medications = ?, notes = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$diagnosis, $treatment, $medications, $notes, $record_id, $_SESSION['user_id']]);
            $message = 'Registro médico actualizado correctamente';
        } else {
            $error = 'No tienes permisos para editar este registro';
        }
    } catch(PDOException $e) {
        $error = 'Error al actualizar el registro médico';
    }
}

// Obtener lista de pacientes del doctor
$patients_query = "
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone, u.birth_date,
           COUNT(DISTINCT a.id) as total_appointments,
           COUNT(DISTINCT mr.id) as total_records,
           MAX(a.appointment_date) as last_appointment,
           MIN(a.appointment_date) as first_appointment
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    LEFT JOIN medical_records mr ON u.id = mr.patient_id AND mr.doctor_id = ?
    WHERE a.doctor_id = ?
";

$params = [$_SESSION['user_id'], $_SESSION['user_id']];

if ($search) {
    $patients_query .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$patients_query .= " GROUP BY u.id ORDER BY MAX(a.appointment_date) DESC";

$stmt = $pdo->prepare($patients_query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Si hay un paciente seleccionado, obtener su historial completo
$patient_details = null;
$medical_records = [];
$appointments_history = [];

if ($selected_patient_id) {
    // Verificar que el paciente ha sido atendido por este doctor
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.*, COUNT(a.id) as total_appointments
        FROM users u 
        JOIN appointments a ON u.id = a.patient_id 
        WHERE u.id = ? AND a.doctor_id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$selected_patient_id, $_SESSION['user_id']]);
    $patient_details = $stmt->fetch();
    
    if ($patient_details) {
        // Obtener registros médicos del paciente con este doctor
        $stmt = $pdo->prepare("
            SELECT mr.*, a.appointment_date, a.appointment_time
            FROM medical_records mr
            LEFT JOIN appointments a ON mr.appointment_id = a.id
            WHERE mr.patient_id = ? AND mr.doctor_id = ?
            ORDER BY mr.visit_date DESC, mr.created_at DESC
        ");
        $stmt->execute([$selected_patient_id, $_SESSION['user_id']]);
        $medical_records = $stmt->fetchAll();
        
        // Obtener historial de citas con este doctor
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   (SELECT COUNT(*) FROM medical_records mr WHERE mr.appointment_id = a.id) as has_record
            FROM appointments a
            WHERE a.patient_id = ? AND a.doctor_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->execute([$selected_patient_id, $_SESSION['user_id']]);
        $appointments_history = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historiales de Pacientes - MediConsulta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .patients-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            align-items: start;
        }
        .patient-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid var(--primary-color);
        }
        .patient-card:hover,
        .patient-card.active {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-left-color: var(--secondary-color);
        }
        .patient-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            font-size: 0.8rem;
        }
        .stat {
            text-align: center;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 6px;
        }
        .stat-number {
            display: block;
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        .medical-record {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--success-color);
        }
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }
        .record-section {
            margin-bottom: 1.5rem;
        }
        .record-section h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .record-content {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid var(--secondary-color);
        }
        .patient-profile {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .patient-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        .appointment-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .appointment-card.completed {
            border-left-color: var(--success-color);
        }
        .appointment-card.cancelled {
            border-left-color: var(--error-color);
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
        @media (max-width: 768px) {
            .patients-grid {
                grid-template-columns: 1fr;
            }
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-users"></i> Historiales de Pacientes</h1>
                    <p>Consulta el historial médico completo de tus pacientes</p>
                </div>
                <?php if ($selected_patient_id): ?>
                <a href="patient_history.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Volver a Lista
                </a>
                <?php endif; ?>
            </div>
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

        <?php if (!$selected_patient_id): ?>
            <!-- Lista de pacientes -->
            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2><i class="fas fa-list"></i> Mis Pacientes (<?php echo count($patients); ?>)</h2>
                    
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" placeholder="Buscar paciente..." 
                               class="form-control" value="<?php echo htmlspecialchars($search); ?>"
                               style="min-width: 200px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($search): ?>
                        <a href="patient_history.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (empty($patients)): ?>
                    <div class="text-center" style="padding: 3rem;">
                        <i class="fas fa-users" style="font-size: 4rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                        <h3>No se encontraron pacientes</h3>
                        <p>No hay pacientes que coincidan con la búsqueda o aún no has atendido pacientes.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 1rem;">
                        <?php foreach ($patients as $patient): ?>
                        <div class="patient-card" onclick="window.location.href='?patient_id=<?php echo $patient['id']; ?>'">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></p>
                                    <?php if ($patient['phone']): ?>
                                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($patient['birth_date']): ?>
                                        <p><i class="fas fa-birthday-cake"></i> 
                                        <?php 
                                        $age = (new DateTime())->diff(new DateTime($patient['birth_date']))->y;
                                        echo date('d/m/Y', strtotime($patient['birth_date'])) . " ($age años)";
                                        ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="patient-stats">
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $patient['total_appointments']; ?></span>
                                        <span>Citas</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $patient['total_records']; ?></span>
                                        <span>Registros</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-light);">
                                <i class="fas fa-calendar"></i>
                                Primera cita: <?php echo $patient['first_appointment'] ? date('d/m/Y', strtotime($patient['first_appointment'])) : 'N/A'; ?>
                                <span style="margin-left: 1rem;">
                                    <i class="fas fa-clock"></i>
                                    Última cita: <?php echo $patient['last_appointment'] ? date('d/m/Y', strtotime($patient['last_appointment'])) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Detalles del paciente seleccionado -->
            <?php if (!$patient_details): ?>
                <div class="card text-center">
                    <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--error-color); margin-bottom: 1rem;"></i>
                    <h3>Paciente no encontrado</h3>
                    <p>No tienes acceso a este paciente o no existe.</p>
                    <a href="patient_history.php" class="btn btn-primary">Volver a la Lista</a>
                </div>
            <?php else: ?>
                <!-- Perfil del paciente -->
                <div class="patient-profile">
                    <div class="d-flex align-items-center gap-3">
                        <div class="patient-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h1><?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?></h1>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient_details['email']); ?></p>
                            <?php if ($patient_details['phone']): ?>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient_details['phone']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="patient-info-grid">
                        <?php if ($patient_details['birth_date']): ?>
                        <div class="info-card">
                            <i class="fas fa-birthday-cake"></i>
                            <strong>Edad</strong><br>
                            <?php 
                            $age = (new DateTime())->diff(new DateTime($patient_details['birth_date']))->y;
                            echo "$age años (" . date('d/m/Y', strtotime($patient_details['birth_date'])) . ")";
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-card">
                            <i class="fas fa-calendar-check"></i>
                            <strong>Total Citas</strong><br>
                            <?php echo $patient_details['total_appointments']; ?> consultas
                        </div>
                        
                        <div class="info-card">
                            <i class="fas fa-file-medical"></i>
                            <strong>Registros Médicos</strong><br>
                            <?php echo count($medical_records); ?> registros
                        </div>
                        
                        <div class="info-card">
                            <i class="fas fa-user-md"></i>
                            <strong>Paciente desde</strong><br>
                            <?php echo date('d/m/Y', strtotime($patient_details['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Pestañas de información -->
                <div class="card">
                    <div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--border-color);">
                        <button class="btn btn-outline tab-button active" onclick="showTab('medical-records')">
                            <i class="fas fa-file-medical"></i> Registros Médicos (<?php echo count($medical_records); ?>)
                        </button>
                        <button class="btn btn-outline tab-button" onclick="showTab('appointments-history')">
                            <i class="fas fa-calendar-alt"></i> Historial de Citas (<?php echo count($appointments_history); ?>)
                        </button>
                    </div>

                    <!-- Registros médicos -->
                    <div id="medical-records" class="tab-content active">
                        <?php if (empty($medical_records)): ?>
                            <div class="text-center" style="padding: 3rem;">
                                <i class="fas fa-file-medical" style="font-size: 4rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                                <h3>Sin registros médicos</h3>
                                <p>Este paciente aún no tiene registros médicos registrados en el sistema.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($medical_records as $record): ?>
                            <div class="medical-record">
                                <div class="record-header">
                                    <div>
                                        <h3>Consulta del <?php echo date('d/m/Y', strtotime($record['visit_date'])); ?></h3>
                                        <?php if ($record['appointment_date']): ?>
                                            <p><i class="fas fa-calendar"></i> Cita: <?php echo date('d/m/Y H:i', strtotime($record['appointment_date'] . ' ' . $record['appointment_time'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <button onclick="openEditRecordModal(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['diagnosis']); ?>', '<?php echo htmlspecialchars($record['treatment']); ?>', '<?php echo htmlspecialchars($record['medications']); ?>', '<?php echo htmlspecialchars($record['notes']); ?>')" 
                                            class="btn btn-warning" style="font-size: 0.8rem;">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                </div>

                                <?php if ($record['symptoms']): ?>
                                <div class="record-section">
                                    <h4><i class="fas fa-thermometer-half"></i> Síntomas</h4>
                                    <div class="record-content">
                                        <?php echo nl2br(htmlspecialchars($record['symptoms'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($record['diagnosis']): ?>
                                <div class="record-section">
                                    <h4><i class="fas fa-diagnoses"></i> Diagnóstico</h4>
                                    <div class="record-content">
                                        <?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($record['treatment']): ?>
                                <div class="record-section">
                                    <h4><i class="fas fa-procedures"></i> Tratamiento</h4>
                                    <div class="record-content">
                                        <?php echo nl2br(htmlspecialchars($record['treatment'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($record['medications']): ?>
                                <div class="record-section">
                                    <h4><i class="fas fa-pills"></i> Medicamentos</h4>
                                    <div class="record-content">
                                        <?php echo nl2br(htmlspecialchars($record['medications'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($record['notes']): ?>
                                <div class="record-section">
                                    <h4><i class="fas fa-sticky-note"></i> Notas Adicionales</h4>
                                    <div class="record-content">
                                        <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-light);">
                                    <i class="fas fa-clock"></i> 
                                    Creado: <?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?>
                                    <?php if ($record['updated_at'] !== $record['created_at']): ?>
                                        | Actualizado: <?php echo date('d/m/Y H:i', strtotime($record['updated_at'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Historial de citas -->
                    <div id="appointments-history" class="tab-content">
                        <?php if (empty($appointments_history)): ?>
                            <div class="text-center" style="padding: 3rem;">
                                <i class="fas fa-calendar-alt" style="font-size: 4rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                                <h3>Sin historial de citas</h3>
                                <p>Este paciente no tiene citas registradas contigo.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($appointments_history as $appointment): ?>
                            <div class="appointment-card <?php echo $appointment['status']; ?>">
                                <div>
                                    <strong><?php echo date('d/m/Y H:i', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?></strong>
                                    <br>
                                    <span class="status-<?php echo $appointment['status']; ?>">
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
                                    <?php if ($appointment['reason_for_visit']): ?>
                                        <br><small><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($appointment['reason_for_visit']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($appointment['has_record'] > 0): ?>
                                        <span style="color: var(--success-color);">
                                            <i class="fas fa-file-medical"></i> Con registro
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">
                                            <i class="fas fa-file"></i> Sin registro
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Modal para editar registro médico -->
    <div id="editRecordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editRecordModal')">&times;</span>
            <h2><i class="fas fa-edit"></i> Editar Registro Médico</h2>
            
            <form method="POST">
                <input type="hidden" name="record_id" id="edit_record_id">
                
                <div class="form-group">
                    <label for="edit_diagnosis">Diagnóstico *:</label>
                    <textarea name="diagnosis" id="edit_diagnosis" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_treatment">Tratamiento:</label>
                    <textarea name="treatment" id="edit_treatment" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_medications">Medicamentos:</label>
                    <textarea name="medications" id="edit_medications" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">Notas Adicionales:</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="edit_medical_record" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn btn-outline ml-2" onclick="closeModal('editRecordModal')">
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
        function showTab(tabId) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remover active de todos los botones
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar pestaña seleccionada
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }

        function openEditRecordModal(recordId, diagnosis, treatment, medications, notes) {
            document.getElementById('edit_record_id').value = recordId;
            document.getElementById('edit_diagnosis').value = diagnosis;
            document.getElementById('edit_treatment').value = treatment;
            document.getElementById('edit_medications').value = medications;
            document.getElementById('edit_notes').value = notes;
            document.getElementById('editRecordModal').style.display = 'block';
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
    </script>

    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button {
            border: none !important;
            background: transparent !important;
            color: var(--text-dark) !important;
            padding: 1rem 1.5rem !important;
            border-bottom: 3px solid transparent !important;
        }
        .tab-button.active {
            color: var(--primary-color) !important;
            border-bottom-color: var(--primary-color) !important;
            background: transparent !important;
        }
    </style>
</body>
</html>