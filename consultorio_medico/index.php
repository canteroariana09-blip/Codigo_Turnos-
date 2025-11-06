<?php
session_start();
require_once 'config/database.php';

// Si el usuario ya está logueado, redirigir al dashboard correspondiente
if (is_logged_in()) {
    redirect_by_user_type();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConsulta - Sistema de Gestión Médica</title>
    <link rel="stylesheet" href="css/style.css">
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
                    <li><a href="login.php">Iniciar Sesión</a></li>
                    <li><a href="register.php">Registrarse</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <h1><i class="fas fa-heartbeat"></i> Bienvenido a MediConsulta</h1>
                <p>La plataforma integral para la gestión de consultorios médicos. Agenda tus citas, consulta tu historial médico y mantén un control completo de tu salud.</p>
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </a>
                    <a href="register.php" class="btn btn-outline">
                        <i class="fas fa-user-plus"></i> Registrarse
                    </a>
                </div>
            </div>
        </section>

        <section class="main-content">
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Agenda de Citas</h3>
                    <p>Programa tus consultas médicas de manera fácil y rápida. Selecciona tu especialista, fecha y horario preferido.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3>Especialistas Calificados</h3>
                    <p>Accede a una amplia red de médicos especialistas en diferentes áreas de la salud, todos certificados y experimentados.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3>Historial Médico</h3>
                    <p>Mantén un registro completo de tus consultas, diagnósticos y tratamientos en un solo lugar seguro.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Acceso 24/7</h3>
                    <p>Consulta tu información médica y gestiona tus citas desde cualquier dispositivo, en cualquier momento.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Seguridad Garantizada</h3>
                    <p>Tu información médica está protegida con los más altos estándares de seguridad y privacidad.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Recordatorios</h3>
                    <p>Recibe notificaciones de tus próximas citas y nunca olvides una consulta importante.</p>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-info-circle"></i> ¿Cómo funciona?</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem;">
                    <div style="text-align: center;">
                        <div style="background: var(--primary-color); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem; font-weight: bold;">1</div>
                        <h4>Regístrate</h4>
                        <p>Crea tu cuenta como paciente o doctor en pocos minutos</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="background: var(--secondary-color); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem; font-weight: bold;">2</div>
                        <h4>Agenda tu Cita</h4>
                        <p>Selecciona especialidad, doctor y horario disponible</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="background: var(--success-color); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem; font-weight: bold;">3</div>
                        <h4>Consulta Médica</h4>
                        <p>Asiste a tu cita y recibe atención médica personalizada</p>
                    </div>
                </div>
            </div>

            <div class="card text-center">
                <h2><i class="fas fa-users"></i> Únete a MediConsulta Hoy</h2>
                <p style="font-size: 1.1rem; margin: 1rem 0 2rem;">Miles de pacientes y doctores ya confían en nuestra plataforma para gestionar su atención médica.</p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="register.php?type=patient" class="btn btn-primary">
                        <i class="fas fa-user"></i> Soy Paciente
                    </a>
                    <a href="register.php?type=doctor" class="btn btn-secondary">
                        <i class="fas fa-user-md"></i> Soy Doctor
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 MediConsulta. Todos los derechos reservados.</p>
            <p>Sistema de gestión médica seguro y confiable.</p>
        </div>
    </footer>
</body>
</html>