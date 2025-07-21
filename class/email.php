<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';


class Email
{
    public function enviarCorreoVerificacion($correo, $token)
    {
        $mail = new PHPMailer(true);
        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'alejogbs8@gmail.com'; // Cambia esto por tu email
            $mail->Password = 'tamfrxyfahzlbwng'; // Usa una App Password en Gmail
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Configuración del correo
            $mail->setFrom('alejogbs8@gmail.com', "Reliable");
            $mail->addAddress($correo);

            $mail->isHTML(true);
            $mail->Subject = 'Verifica tu cuenta';
            $enlace = "https://backend-reliable.onrender.com/auth/verificar.php?token=" . $token;
            $mail->Body = "
                <h2>Bienvenido a Reliable</h2>
                <p>Por favor, verifica tu cuenta haciendo clic en el siguiente enlace:</p>
                <a href='$enlace'>$enlace</a>";

            // Enviar correo
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $mail->ErrorInfo);
            return false;
        }
    }
}
