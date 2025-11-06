<?php

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'consultorio_medico';
$username = 'root'; // Cambiar por tu usuario de MySQL
$password = '';     // Cambiar por tu contraseña de MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para limpiar datos de entrada
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para verificar si el usuario está logueado
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Función para obtener información del usuario actual
if (!function_exists('get_current_user')) {
    function get_current_user($pdo) {
        if (!is_logged_in()) {
            return null;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
}


// Función para verificar el tipo de usuario
function is_patient() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'patient';
}

function is_doctor() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'doctor';
}

// Función para redirigir según el tipo de usuario
function redirect_by_user_type() {
    if (is_patient()) {
        header('Location: patient/dashboard.php');
    } elseif (is_doctor()) {
        header('Location: doctor/dashboard.php');
    }
    exit;
}
?>