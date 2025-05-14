<?php
// Incluir configuración phpBB
include('config.php');

// Conectar con la base de datos
$conn = new mysqli($dbhost, $dbuser, $dbpasswd, $dbname);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
$table_prefix = isset($table_prefix) ? $table_prefix : 'phpbb_';

// Dominios válidos para comprobar URLs absolutas
$dominios_validos = [
    'https://www.midominio.es',
    'http://www.midominio.es',
    'https://miotrodominio.es',
    'http://miotrodominio.es',
    'https://m.otromas.com',
    'http://m.otromas.com'
];

// Ruta raíz del sitio web
$raiz = realpath(dirname(__FILE__));

// Recoger posibles correcciones aplicadas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['corregir'])) {
    foreach ($_POST['corregir'] as $post_id => $data) {
        foreach ($data as $original => $corregida) {
            $original = $conn->real_escape_string($original);
            $corregida = $conn->real_escape_string($corregida);
            $conn->query("UPDATE {$table_prefix}posts SET post_text = REPLACE(post_text, '$original', '$corregida') WHERE post_id = " . (int)$post_id);

            // Registrar el cambio en un archivo log
            $log_line = "[" . date('Y-m-d H:i:s') . "] Post ID: $post_id | Original: $original | Corregida: $corregida\n";
            file_put_contents('log_cambios.txt', $log_line, FILE_APPEND);
        }
    }
    echo "<p style='color:green;font-weight:bold'>Correcciones aplicadas correctamente.</p>";
	echo "<p><a href='log_cambios.txt' download>📄 Descargar log de cambios aplicados</a></p>";

}


// Consulta de los posts
$sql = "SELECT post_id, post_text FROM {$table_prefix}posts";
$result = $conn->query($sql);

$correcciones = [];

echo "<form method='post'>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr>";
echo "<th>Post ID</th>";
echo "<th>Imagen rota</th>";
echo "<th>Imagen sugerida</th>";
echo "<th>Todas<br><input type='checkbox' id='checkAll' onclick='toggleCheckboxes(this)'></th>";
echo "</tr>";


if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $total_posts++;
        
        $post_id = (int)$row['post_id'];
        $post_text = $row['post_text'];

        // Buscar posibles URLs con extensiones de imagen (incluyendo etiquetas [img])
        preg_match_all('/\[img\]([^\[]+?)\[\/img\]|((?:https?:\/\/|\/\/)?[^\s\'"]+\.(?:jpg|jpeg|png|gif|bmp|webp))/i', $post_text, $matches);

        $urls_extraidas = [];

        foreach ($matches[1] as $url1) {
            if (!empty($url1)) $urls_extraidas[] = $url1;
        }
        foreach ($matches[2] as $url2) {
            if (!empty($url2)) $urls_extraidas[] = $url2;
        }

        // Eliminar posibles duplicadas en el mismo post
        $urls_unicas = array_unique($urls_extraidas);
        
        foreach ($urls_unicas as $image_url) {
            $comprobar = false;
            $ruta_relativa = '';

            foreach ($dominios_validos as $dominio) {
                if (stripos($image_url, $dominio . '/eventos/') === 0 || stripos($image_url, $dominio . '/proyectos/') === 0) {
                    $comprobar = true;
                    $ruta_relativa = str_ireplace($dominio, '', $image_url);
                    break;
                }
            }

            if (!$comprobar && (stripos($image_url, '/eventos/') === 0 || stripos($image_url, '/proyectos/') === 0)) {
                $comprobar = true;
                $ruta_relativa = $image_url;
            }

            if ($comprobar) {
                $ruta_fisica = $raiz . $ruta_relativa;
                
                if (!file_exists($ruta_fisica)) {
                    // Obtener información sobre la ruta del archivo
                    $pathinfo = pathinfo($ruta_fisica);
                    $dirname = $pathinfo['dirname'];
                    $basename = $pathinfo['basename']; // Nombre original
                    $ext = $pathinfo['extension']; // Extensión original

                    // Realizar búsqueda de archivo ignorando mayúsculas/minúsculas en nombre y extensión
                    $corregida_relativa = buscar_imagen_case_insensitive($dirname, $basename, $ext);

                    if ($corregida_relativa) {
                        // Ajustar la URL para que sea relativa
                        $url_corregida = str_ireplace($raiz, '', $corregida_relativa);

                        $enlace_post = "/viewtopic.php?p={$post_id}#p{$post_id}";
                        echo "<tr>";
                        echo "<td><a href=\"$enlace_post\" target=\"_blank\">$post_id</a></td>";
                        echo "<td><img src=\"$ruta_relativa\" width=\"100\"><br><small>$ruta_relativa</small></td>";
                        echo "<td><img src=\"$url_corregida\" width=\"100\"><br><small>$url_corregida</small></td>";
                        echo "<td><input type='checkbox' name='corregir[$post_id][" . htmlspecialchars($image_url) . "]' value='" . htmlspecialchars($url_corregida) . "'></td>";
                        echo "</tr>";
                    }
                }
            }
        }
        $total_matches += count($urls_unicas);
    }
}
echo "</table>";
echo "<br><input type='submit' value='Aplicar correcciones seleccionadas'>";
echo <<<HTML
<script>
function toggleCheckboxes(source) {
    let checkboxes = document.querySelectorAll("input[type='checkbox'][name^='corregir']");
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>
HTML;

echo "</form>";

echo "<p>$total_posts posts analizados.</p>";
echo "<p>$total_matches imágenes encontradas.</p>";

$conn->close();

// Función para buscar la imagen ignorando mayúsculas/minúsculas
function buscar_imagen_case_insensitive($dir, $basename, $ext) {
    // Obtener una lista de todos los archivos en el directorio
    $archivos = scandir($dir);

    // Buscar el archivo que coincida sin importar mayúsculas/minúsculas
    foreach ($archivos as $archivo) {
        if (strcasecmp($archivo, $basename) === 0) {
            return $dir . '/' . $archivo;
        }
    }

    return false;
}
?>
