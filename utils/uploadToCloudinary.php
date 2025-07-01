<?php
function subirACloudinary($archivoTmp, $nombreArchivo) // archivoTmp proviene de $_FILES y $nombreArchivo es el nombre original del archivo
{   
    // Variables de entorno.
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $uploadPreset = getenv('CLOUDINARY_UPLOAD_PRESET');

    $postData = [ // Cuerpo de la solicitud
        'file' => new CURLFile($archivoTmp, mime_content_type($archivoTmp), $nombreArchivo), // "file" : Archivo a subir, curlFile envia como archivo real.
        'api_key' => $apiKey,
        'upload_preset' => $uploadPreset,
        'folder' => 'hogar' //carpeta dnd se guardan en cloud
    ];

    $url = "https://api.cloudinary.com/v1_1/$cloudName/image/upload";   //donde se envia la img

    $ch = curl_init();   // Preparar la peticion cURL
    curl_setopt_array($ch, [         // Inicia sesion cURL, Dice que es peticion POST que devuelva string, y pase lso datos del formulario $postData.
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
    ]);

    $response = curl_exec($ch);   //Envia la img a cloud 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);   // Codigo de respuesta obtiene 
    curl_close($ch); // cierra la conexion.

    if ($httpCode === 200) {  // Procesa la respuesta segun el codigo obtenido.
        $data = json_decode($response, true);
        error_log("✅ Imagen subida a Cloudinary: " . $data['secure_url']);
        return $data['secure_url'];
    } else {
        error_log("❌ Falló la subida a Cloudinary. Código HTTP: $httpCode");
        error_log("⚠️ Resultado de la respuesta Cloudinary: " . $response);
        return false;
    }
}
