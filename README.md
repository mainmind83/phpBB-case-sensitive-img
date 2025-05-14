# phpBB-case-sensitive-img

Después de una [migración de alojamiento de IIS a Apache/Linux](https://www.mainmind.com/blog/case-sensitive-phpbb-iis-linux/) las URL de imágenes no funcionaban correctamente al ser sensibles a mayúsculas/minúsculas en el nuevo sistema operativo, aunque existe la posibilidad de establecer reglas a nivel de servidor. Se crea un script para buscar imágenes en los mensajes de la base de datos de phpBB y buscar imágenes en el mismo directorio con el mismo nombre pero con posibles variantes de mayúsculas/minúsculas.

Se tienen en cuenta:

- Posibles alias de dominio
- Urls relativas o absolutas
- Se limita a directorio /galeria e /imagenes

En el proceso:

1. Se obtiene el contenido de los posts de la base de datos
2. Se extraen las URLs de las imágenes
3. Para cada URL de imagen, si es relativa se intentan buscar varias rutas absolutas basadas en los alias del dominio y se comprueba si existe en el servidor
4. Los resultados se muestran en una sencilla tabla para revisar y poder aplicar la corrección selectivamente
5. Se genera un log de las modificaciones efectuadas

El archivo se debe copiar en el directorio raiz de la instalación de phpBB junto al archivo config.php del cual obtendrá los datos de acceso a la conexión de MySQL.
