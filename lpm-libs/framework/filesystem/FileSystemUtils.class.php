<?php
namespace GMFramework;

/**
 * Функции для работы с файлами и дректориями
 * @package ru.vbinc.gm.framework.filesystem
 * @author GreyMag
 * @version 0.1
 *
 */
class FileSystemUtils
{
	/**
	 * Удалить файл или директорию 
	 * @param string $file
	 * @param boolean $recursively удалять рекурсивно
	 * @return Boolean
	 */
	public static function remove( $file, $recursively = true )
	{
        // сначала проверим а есть ли такой файл вообще
        if (!file_exists( $file )) return true;
        
        // если параметром передан путь к файлу а не папка, удаляем файл и возвращаем результат удаления
        if (!is_dir( $file ) || is_link( $file )) return @unlink( $file );
        
        // если все таки передан не файл, а папка
        // и требуется удалять рекурсивно,
        // то обрабатываем ее содержимое
        if ($recursively) {
	        $files = scandir( $file );
        	foreach ($files as $item) { // проверяем каждый элемент (как файлы так и папки) папки
	           if ($item == '.' || $item == '..') continue; // пропускаем ненужные вещи :)
	           if (!self::remove( $file . "/" . $item )) { // вызываем рекурсивно deleteDirectory() передав теперь в качестве параметра путь к обрабатываемому элементу
	               @chmod( $file . "/" . $item, 0777 ); // если удаление не удалось, меняем права доступа к файлу/папке
	               if (!self::remove( $file . "/" . $item )) return false;// если и теперь удаелние не удалось, выходим из рекурсии	               
	           }
	        }
        }
        
        return @rmdir( $file ); // удаляем папку
	}

	/**
	 * Копировать файл или папку
     * @param string $srcFile
     * @param string $destFile
	 */
    public static function copy( $srcFile, $destFile )
    {
        // сначала проверим а есть ли такой файл вообще
        if (!file_exists( $srcFile )) return true;
        
        // если файл, то просто копируем
        if (is_file( $srcFile ) || is_link( $srcFile )) return copy( $srcFile, $destFile );
        
        if (!is_dir( $destFile ) && !mkdir( $destFile, 0755 )) return false;
        
        // если все таки передан не файл, а папка
        // и требуется удалять рекурсивно,
        // то обрабатываем ее содержимое
        $files = scandir( $srcFile );
        foreach ($files as $item) { // проверяем каждый элемент (как файлы так и папки) папки
            if ($item == '.' || $item == '..') continue; // пропускаем ненужные вещи :)
            // если не удалось скопировать - ошибка
            if (!self::copy( $srcFile . '/' . $item, $destFile . '/' . $item )) return false;
        }
        
        return true;
    }
    
    /**
     * Переместить файл или папку
     * @param string $srcFile
     * @param string $destFile
     */
    public static function move( $srcFile, $destFile ) 
    {
    	return rename( $srcFile, $destFile);
    }

    /**
     * Создает переданный путь (все промежуточные директории). <br>
     * Путь не должен включать имя файла!
     */ 
    public static function createPath($path, $chmod = 0755) 
    {
        if (is_dir($path)) return true;
        
        $path = str_replace('\\', '/', $path);
        $pathArr = explode('/', $path);
        $curPath = '';
        while (count($pathArr) > 0) {
            $dir = array_shift($pathArr);
            if ($dir != '') {
                $curPath .= $dir;
                if(!file_exists( $curPath ) || !is_dir( $curPath )) {
                    if (!@mkdir( $curPath, $chmod, true )) return false;
                }
                $curPath .= '/';
            } else if ($curPath == '') $curPath = '/'; 
        }

        return true;
    }
}
?>