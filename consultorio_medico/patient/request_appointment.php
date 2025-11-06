<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_patient()) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Obtener especialidades
$stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
$specialties = $stmt->fetchAll();

// Si se seleccionó especialidad, obtener doctores
$doctors = [];
$selected_specialty = '';
if (isset($_GET['specialty']) && !empty($_GET['specialty'])) {
    $selected_specialty = intval($_GET['specialty']);
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, d.consultation_fee, d.license_number 
        FROM users u 
        JOIN doctors d ON u.id = d.user_id 
        WHERE d.specialty_id = ? AND u.user_type = 'doctor'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$selected_specialty]);
    $doctors = $stmt->fetchAll();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $specialty_id = intval($_POST['specialty_id']);
    $doctor_id = intval($_POST['doctor_id']);
    $appointment_date = clean_input($_POST['appointment_date']);
    $appointment_time = clean_input($_POST['appointment_time']);
    $notes = clean_input($_POST['notes']);
    
    if (empty($specialty_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = 'Por favor completa todos los campos obligatorios';
    } elseif (strtotime($appointment_date) <= time()) {
        $error = 'La fecha debe ser posterior a hoy';
    } else {
        try {
            // Verificar que el doctor pertenece a la especialidad
            $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ? AND specialty_id = ?");
            $stmt->execute([$doctor_id, $specialty_id]);
            if (!$stmt->fetch()) {
                $error = 'Doctor no válido para la especialidad seleccionada';
            } else {
                // Verificar disponibilidad
                $stmt = $pdo->prepare("
                    SELECT id FROM appointments 
                    WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status = 'scheduled'
                ");
                $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
                if ($stmt->fetch()) {
                    $error = 'El horario seleccionado no está disponible';
                } else {
                    // Crear la cita
                    $stmt = $pdo->prepare("
                        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, notes) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $doctor_id, $appointment_date, $appointment_time, $notes]);
                    
                    $success = 'Cita agendada exitosamente. Te confirmaremos la cita pronto.';
                    
                    // Limpiar formulario
                    $_POST = [];
                    $selected_specialty = '';
                    $doctors = [];
                }
            }
        } catch(PDOException $e) {
            $error = 'Error al agendar la cita. Inténtalo más tarde.';
        }
    }
}

// Generar horarios disponibles (ejemplo: cada 30 minutos de 9:00 a 17:00)
$time_slots = [];
for ($hour = 9; $hour <= 16; $hour++) {
    for ($minute = 0; $minute < 60; $minute += 30) {
        if ($hour == 16 && $minute > 0) break; // Último horario a las 16:30
        $time_slots[] = sprintf('%02d:%02d', $hour, $minute);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedir Turno - MediConsulta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #e2e8f0;
            border-radius: 20px;
            margin: 0 0.5rem;
            font-size: 0.9rem;
        }
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        .step.completed {
            background: var(--success-color);
            color: white;
        }
        .doctor-card {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .doctor-card:hover {
            border-color: var(--primary-color);
            background: #f8fafc;
        }
        .doctor-card.selected {
            border-color: var(--primary-color);
            background: #f0f9ff;
        }
        .doctor-card input[type="radio"] {
            display: none;
        }
        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .time-slot {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .time-slot:hover {
            border-color: var(--primary-color);
            background: #f0f9ff;
        }
        .time-slot.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        .time-slot input[type="radio"] {
            display: none;
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
            <h1><i class="fas fa-calendar-plus"></i> Pedir Turno Médico</h1>
            <p>Agenda tu cita médica en 4 sencillos pasos</p>
        </div>

        <!-- Indicador de pasos -->
        <div class="step-indicator">
            <div class="step completed">
                <i class="fas fa-check"></i>
                <span>Especialidad</span>
            </div>
            <div class="step <?php echo !empty($doctors) ? 'active' : ''; ?>">
                <i class="fas fa-user-md"></i>
                <span>Doctor</span>
            </div>
            <div class="step">
                <i class="fas fa-calendar"></i>
                <span>Fecha</span>
            </div>
            <div class="step">
                <i class="fas fa-check-circle"></i>
                <span>Confirmar</span>
            </div>
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
                    <a href="my_appointments.php" class="btn btn-primary">Ver Mis Turnos</a>
                    <a href="request_appointment.php" class="btn btn-outline ml-2">Pedir Otro Turno</a>
                </div>
            </div>
        <?php else: ?>

        <form method="POST" action="" id="appointmentForm">
            <!-- Paso 1: Seleccionar Especialidad -->
            <div class="card">
                <h2><i class="fas fa-stethoscope"></i> Paso 1: Seleccionar Especialidad</h2>
                <div class="form-group">
                    <label for="specialty_id">Especialidad Médica *</label>
                    <select id="specialty_id" name="specialty_id" class="form-control" required onchange="loadDoctors(this.value)">
                        <option value="">Seleccionar especialidad</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo $specialty['id']; ?>" 
                                    <?php echo ($selected_specialty == $specialty['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($specialty['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Paso 2: Seleccionar Doctor -->
            <?php if (!empty($doctors)): ?>
            <div class="card">
                <h2><i class="fas fa-user-md"></i> Paso 2: Seleccionar Doctor</h2>
                <div id="doctorsContainer">
                    <?php foreach ($doctors as $doctor): ?>
                    <label class="doctor-card" for="doctor_<?php echo $doctor['id']; ?>">
                        <input type="radio" name="doctor_id" value="<?php echo $doctor['id']; ?>" 
                               id="doctor_<?php echo $doctor['id']; ?>" required>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h4>
                                <p><i class="fas fa-id-card"></i> Licencia: <?php echo htmlspecialchars($doctor['license_number']); ?></p>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.2rem; font-weight: bold; color: var(--primary-color);">
                                    $<?php echo number_format($doctor['consultation_fee'], 0, ',', '.'); ?>
                                </div>
                                <small>Consulta</small>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Paso 3: Seleccionar Fecha y Hora -->
            <div class="card" id="dateTimeSection" style="<?php echo empty($doctors) ? 'display: none;' : ''; ?>">
                <h2><i class="fas fa-calendar"></i> Paso 3: Seleccionar Fecha y Hora</h2>
                
                <div class="form-group">
                    <label for="appointment_date">Fecha de la Cita *</label>
                    <input type="date" id="appointment_date" name="appointment_date" class="form-control" 
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>

                <div class="form-group">
                    <label>Horario Disponible *</label>
                    <div class="time-grid">
                        <?php foreach ($time_slots as $time): ?>
                        <label class="time-slot" for="time_<?php echo str_replace(':', '', $time); ?>">
                            <input type="radio" name="appointment_time" value="<?php echo $time; ?>" 
                                   id="time_<?php echo str_replace(':', '', $time); ?>" required>
                            <?php echo $time; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notas adicionales (opcional)</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                              placeholder="Describe brevemente el motivo de la consulta o cualquier información relevante"></textarea>
                </div>
            </div>

            <!-- Paso 4: Confirmar -->
            <div class="card" id="confirmSection" style="<?php echo empty($doctors) ? 'display: none;' : ''; ?>">
                <h2><i class="fas fa-check-circle"></i> Paso 4: Confirmar Cita</h2>
                <div id="appointmentSummary">
                    <!-- Se llenará con JavaScript -->
                </div>
                
                <div class="text-center" style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        <i class="fas fa-calendar-check"></i> Confirmar Cita
                    </button>
                    <button type="reset" class="btn btn-outline ml-2" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Reiniciar
                    </button>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 MediConsulta. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        function loadDoctors(specialtyId) {
            if (specialtyId) {
                window.location.href = `request_appointment.php?specialty=${specialtyId}`;
            } else {
                // Ocultar secciones siguientes
                document.getElementById('dateTimeSection').style.display = 'none';
                document.getElementById('confirmSection').style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const doctorRadios = document.querySelectorAll('input[name="doctor_id"]');
            const timeRadios = document.querySelectorAll('input[name="appointment_time"]');
            const dateInput = document.getElementById('appointment_date');
            
            // Manejar selección de doctor
            doctorRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Remover clase selected de todas las cards
                    document.querySelectorAll('.doctor-card').forEach(card => {
                        card.classList.remove('selected');
                    });
                    
                    // Añadir clase selected a la card seleccionada
                    if (this.checked) {
                        this.closest('.doctor-card').classList.add('selected');
                        document.getElementById('dateTimeSection').style.display = 'block';
                        document.getElementById('confirmSection').style.display = 'block';
                    }
                    
                    updateSummary();
                });
            });

            // Manejar selección de horario
            timeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Remover clase selected de todos los horarios
                    document.querySelectorAll('.time-slot').forEach(slot => {
                        slot.classList.remove('selected');
                    });
                    
                    // Añadir clase selected al horario seleccionado
                    if (this.checked) {
                        this.closest('.time-slot').classList.add('selected');
                    }
                    
                    updateSummary();
                });
            });

            // Actualizar resumen cuando cambie la fecha
            if (dateInput) {
                dateInput.addEventListener('change', updateSummary);
            }

            function updateSummary() {
                const specialty = document.getElementById('specialty_id');
                const selectedDoctor = document.querySelector('input[name="doctor_id"]:checked');
                const selectedTime = document.querySelector('input[name="appointment_time"]:checked');
                const selectedDate = dateInput ? dateInput.value : '';

                let summaryHTML = '<h3>Resumen de la Cita</h3>';
                
                if (specialty.value) {
                    summaryHTML += `<p><strong>Especialidad:</strong> ${specialty.options[specialty.selectedIndex].text}</p>`;
                }
                
                if (selectedDoctor) {
                    const doctorCard = selectedDoctor.closest('.doctor-card');
                    const doctorName = doctorCard.querySelector('h4').textContent;
                    summaryHTML += `<p><strong>Doctor:</strong> ${doctorName}</p>`;
                }
                
                if (selectedDate) {
                    const date = new Date(selectedDate);
                    summaryHTML += `<p><strong>Fecha:</strong> ${date.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>`;
                }
                
                if (selectedTime) {
                    summaryHTML += `<p><strong>Hora:</strong> ${selectedTime.value}</p>`;
                }

                document.getElementById('appointmentSummary').innerHTML = summaryHTML;
            }
        });

        function resetForm() {
            document.getElementById('appointmentForm').reset();
            document.querySelectorAll('.doctor-card').forEach(card => card.classList.remove('selected'));
            document.querySelectorAll('.time-slot').forEach(slot => slot.classList.remove('selected'));
            document.getElementById('dateTimeSection').style.display = 'none';
            document.getElementById('confirmSection').style.display = 'none';
        }
    </script>
</body>
</html>