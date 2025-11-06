<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_patient()) {
    header('Location: ../login.php');
    exit;
}

$user = get_current_user();

// Obtener estadísticas del paciente
$stmt = $pdo->prepare("SELECT COUNT(*) as total_appointments FROM appointments WHERE patient_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_appointments = $stmt->fetch()['total_appointments'];

$stmt = $pdo->prepare("SELECT COUNT(*) as upcoming_appointments FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled'");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_appointments = $stmt->fetch()['upcoming_appointments'];

$stmt = $pdo->prepare("SELECT COUNT(*) as medical_records FROM medical_records WHERE patient_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$medical_records = $stmt->fetch()['medical_records'];

// Próximas citas
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
           s.name as specialty_name
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    JOIN doctors d ON u.id = d.user_id
    JOIN specialties s ON d.specialty_id = s.id
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Paciente - MediConsulta</title>
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
        <h1><i class="fas fa-user"></i> Bienvenido/a, <?php echo htmlspecialchars($user); ?>!</h1
            <p>Gestiona tus citas médicas y consulta tu información de salud desde tu panel personal.</p>
        </div>
        
        <!-- Estadísticas -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Total de Citas</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">
                    <?php echo $total_appointments; ?>
                </div>
                <p>Citas médicas registradas</p>
            </div>

            <div class="dashboard-card">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Próximas Citas</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--warning-color);">
                    <?php echo $upcoming_appointments; ?>
                </div>
                <p>Citas programadas</p>
            </div>

            <div class="dashboard-card">
                <div class="icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <h3>Registros Médicos</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--success-color);">
                    <?php echo $medical_records; ?>
                </div>
                <p>Consultas en historial</p>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="card">
            <h2><i class="fas fa-bolt"></i> Acciones Rápidas</h2>
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <a href="request_appointment.php" class="dashboard-card" style="text-decoration: none; color: inherit;">
                    <div class="icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3>Pedir Turno</h3>
                    <p>Agenda una nueva cita médica</p>
                </a>

                <a href="my_appointments.php" class="dashboard-card" style="text-decoration: none; color: inherit;">
                    <div class="icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Mis Turnos</h3>
                    <p>Ver y gestionar citas</p>
                </a>

                <a href="medical_history.php" class="dashboard-card" style="text-decoration: none; color: inherit;">
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3>