<?php
session_start();
require_once '../config/database.php';

if (!is_logged_in() || !is_patient()) {
    header('Location: ../login.php');
    exit;
}

// Obtener historial médico del paciente
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
           s.name as specialty_name,
           a.appointment_date,
           a.appointment_time
    FROM medical_records mr
    JOIN users u ON mr.doctor_id = u.id
    JOIN doctors d ON u.id = d.user_id
    JOIN specialties s ON d.specialty_id = s.id
    LEFT JOIN appointments a ON mr.appointment_id = a.id
    WHERE mr.patient_id = ?
    ORDER BY mr.visit_date DESC, mr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$medical_records = $stmt->fetchAll();

// Obtener estadísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_visits,
        COUNT(DISTINCT doctor_id) as different_doctors,
        MIN(visit_date) as first_visit,
        MAX(visit_date) as last_visit
    FROM medical_records 
    WHERE patient_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Obtener especialidades consultadas
$stmt = $pdo->prepare("
    SELECT s.name as specialty, COUNT(*) as visits
    FROM medical_records mr
    JOIN users u ON mr.doctor_id = u.id
    JOIN doctors d ON u.id = d.user_id
    JOIN specialties s ON d.specialty_id = s.id
    WHERE mr.patient_id = ?
    GROUP BY s.id, s.name
    ORDER BY visits DESC
");
$stmt->execute([$_SESSION['user_id']]);
$specialties_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Médico - MediConsulta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .medical-record {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }
        .record-info {
            flex: 1;
        }
        .record-date {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .record-section {
            margin-bottom: 1.5rem;
        }
        .record-section h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
        }
        .record-content {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid var(--secondary-color);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, white, #f8fafc);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            border-top: 3px solid var(--primary-color);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        .specialty-chart {
            display: grid;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .specialty-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .specialty-name {
            min-width: 120px;
            font-size: 0.9rem;
        }
        .bar-container {
            flex: 1;
            height: 20px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .visit-count {
            font-size: 0.9rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .no-records {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }
        .no-records i {
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
            <h1><i class="fas fa-clipboard-list"></i> Mi Historial Médico</h1>
            <p>Registro completo de tus consultas y tratamientos médicos</p>
        </div>

        <?php if (!empty($medical_records)): ?>
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['total_visits']; ?></span>
                    <div><i class="fas fa-hospital"></i> Total de Visitas</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['different_doctors']; ?></span>
                    <div><i class="fas fa-user-md"></i> Doctores Consultados</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['first_visit'] ? date('Y', strtotime($stats['first_visit'])) : '-'; ?></span>
                    <div><i class="fas fa-calendar"></i> Año Primera Visita</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number">
                        <?php echo $stats['last_visit'] ? (new DateTime($stats['last_visit']))->diff(new DateTime())->days . 'd' : '-'; ?>
                    </span>
                    <div><i class="fas fa-clock"></i> Última Visita</div>
                </div>
            </div>

            <!-- Especialidades más consultadas -->
            <?php if (!empty($specialties_stats)): ?>
            <div class="card">
                <h2><i class="fas fa-chart-bar"></i> Especialidades Consultadas</h2>
                <div class="specialty-chart">
                    <?php 
                    $max_visits = max(array_column($specialties_stats, 'visits'));
                    foreach ($specialties_stats as $specialty): 
                        $percentage = ($specialty['visits'] / $max_visits) * 100;
                    ?>
                    <div class="specialty-bar">
                        <div class="specialty-name"><?php echo htmlspecialchars($specialty['specialty']); ?></div>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <div class="visit-count"><?php echo $specialty['visits']; ?> visitas</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Registros médicos -->
            <div class="card">
                <h2><i class="fas fa-file-medical"></i> Registros de Consultas</h2>
            </div>

            <?php foreach ($medical_records as $record): ?>
            <div class="medical-record">
                <div class="record-header">
                    <div class="record-info">
                        <h3>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></h3>
                        <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($record['specialty_name']); ?></p>
                        <?php if ($record['appointment_date']): ?>
                        <p><i class="fas fa-calendar-alt"></i> Cita: <?php echo date('d/m/Y H:i', strtotime($record['appointment_date'] . ' ' . $record['appointment_time'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="record-date">
                        <?php echo date('d/m/Y', strtotime($record['visit_date'])); ?>
                    </div>
                </div>

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
                    <i class="fas fa-clock"></i> Registro creado el <?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <!-- Sin registros -->
            <div class="card no-records">
                <i class="fas fa-clipboard-list"></i>
                <h3>No tienes registros médicos aún</h3>
                <p>Tu historial médico aparecerá aquí después de tus consultas con los doctores.</p>
                <a href="request_appointment.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Agendar Primera Consulta
                </a>
            </div>
        <?php endif; ?>

        <!-- Información sobre el historial -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Información sobre tu Historial Médico</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <div style="padding: 1rem; background: #f0f9ff; border-radius: 8px;">
                    <h4><i class="fas fa-shield-alt"></i> Privacidad y Seguridad</h4>
                    <p>Tu historial médico está protegido y solo es accesible para ti y los doctores que te atienden.</p>
                </div>
                
                <div style="padding: 1rem; background: #ecfdf5; border-radius: 8px;">
                    <h4><i class="fas fa-download"></i> Exportar Historial</h4>
                    <p>Puedes solicitar una copia de tu historial médico completo contactando con nuestro soporte.</p>
                </div>
                
                <div style="padding: 1rem; background: #fef3c7; border-radius: 8px;">
                    <h4><i class="fas fa-question-circle"></i> Dudas sobre Diagnósticos</h4>
                    <p>Si tienes preguntas sobre algún diagnóstico o tratamiento, consulta directamente con tu doctor.</p>
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
        // Función para buscar en el historial
        function searchHistory() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const records = document.querySelectorAll('.medical-record');
            
            records.forEach(record => {
                const text = record.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    record.style.display = 'block';
                } else {
                    record.style.display = 'none';
                }
            });
        }

        // Animar las barras de especialidades al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const bars = document.querySelectorAll('.bar-fill');
            bars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });

        // Función para imprimir historial
        function printHistory() {
            window.print();
        }

        // Añadir estilos para impresión
        const printStyles = `
            @media print {
                .header, .footer, .btn { display: none !important; }
                .medical-record { break-inside: avoid; page-break-inside: avoid; }
                body { font-size: 12pt; }
                .card { box-shadow: none; border: 1px solid #ccc; }
            }
        `;
        
        const styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>