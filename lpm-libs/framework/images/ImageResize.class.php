<?php
namespace GMFramework;

/**
 * Изменение размеров изображений
 * Реализация на базе готовой функции неизвестно кого
 * @package ru.vbinc.gm.framework.images 
 * @author GreyMag <greymag@gmail.com>
 * @version 0.2
 */
class ImageResize
{
	/**
	 * Mime-тип, соответствующий изображению png
	 * @var string 
	 */
	const MIME_PNG     = 'png';
	/**
     * Тип преобразования: вписать в заданную область
     * @var int
     */
    const FIT_IN_AREA   = 0;
	/**
     * Тип преобразования: заполнить заданную область,
     * с сохранением пропорций изображения
     * @var int
     */
    const FILL_AREA     = 1;
	/**
     * Тип преобразования: растянуть изображение по заданной области
     * @var int
     */
    const SCALE_TO_AREA = 2;

	/**
	 * Изменяет размер изображения
	 * @param string $srcImage путь до исходного изображения
	 * @param string $destImage путь для сохранения обработанного изображения
	 * @param int $width
	 * @param int $height
	 * @param int $scaleType тип преобразования
	 * @param int $bgColor цвет фона
	 * @param int $quality качество
	 * @param boolean $useBg если флаг выставлен - под изображением рисуется подложка,
	 * и размеры в точности 
	 * @return boolean
	 */
	public static function resize( $srcImage, $destImage, $width, $height, $scaleType = 0, $bgColor = 0xFFFFFF, $quality = 100, $transparentBG = false )
	{
		$width = (int)$width;
		$height = (int)$height;

		if( !file_exists( $srcImage ) ) return false;
		//{throw new Exception( 'нет файла ' . $srcImage ); return false;}

		if( !$size = getimagesize( $srcImage ) ) return false;
		//{throw new Exception( 'не возможно получить размеры ' );return false;}

		//if( $width == 0 ) $width = $size[0];
		//if( $height == 0 ) $height = $size[1];

		// Определяем исходный формат по MIME-информации, предоставленной
		// функцией getimagesize, и выбираем соответствующую формату
		// imagecreatefrom-функцию.
		$format = strtolower( substr( $size['mime'], strpos( $size['mime'], '/' ) + 1 ) );
		$icFunc = "imagecreatefrom" . $format;
		if( !function_exists( $icFunc ) ) return false;
		//{throw new Exception( 'нет функции' );return false;}

		// получаем новые размеры
		$newSize = ImageResize::getSize( $scaleType, $size[0], $size[1], $width, $height );

		// создаем изображение на основе переданного
		$image = $icFunc( $srcImage );

		// делаем новое изображение с заданными размерами
		$newImage = imagecreatetruecolor( $width, $height );


		//if( $size['mime'] != ImageResize::MIME_PNG )
		imagefill( $newImage, 0, 0, $bgColor );
		if( $transparentBG ) {
			ImageColorTransparent( $newImage, $bgColor );
			ImageColorTransparent( $newImage );
		}

		imagecopyresampled( $newImage, $image, $newSize->x, $newSize->y, 0, 0, $newSize->width, $newSize->height, $size[0], $size[1] );

		if( $transparentBG || $size['mime'] == ImageResize::MIME_PNG ){
			if( !imagepng( $newImage, $destImage ) ) return false;
			//{throw new Exception( 'не могу создать пнг файл' );return false;}
		} else {
			if( !imagejpeg( $newImage, $destImage, $quality ) ) return false;
			//{throw new Exception( 'не могу создать жпг файл' );return false;}
		}

		imagedestroy( $image    );
		imagedestroy( $newImage );

		return true;
	}
    
	
	/**
	 * Рассчитать новые размеры и координаты прямоугольника заданных размеров,
	 * чтобы он занял прямоугольник новых размеров в соответствии с типом преобразования
	 * @param int $scaleType тип преобразования
	 * @param float $width ширина исходного прямоугольника
	 * @param float $height высота исходного прямоугольника
	 * @param float $newWidth ширина нового прямоугольника
	 * @param float $newHeight высота нового прямоугольника
	 * @return Rectangle 
	 */
	public static function getSize( $scaleType, $width, $height, $newWidth, $newHeight )
	{
		$width     = (int)$width;
		$height    = (int)$height;
		$newWidth  = (int)$newWidth;
		$newHeight = (int)$newHeight;

		$result = new Rectangle();

		switch( $scaleType )
		{
			//case ImageResize::FILL_AREA :
			//case ImageResize::SCALE_TO_AREA :
			case ImageResize::FIT_IN_AREA :
			default : {
				$ratio       = min( $newWidth / $width, $newHeight / $height );

				$result->width  = $ratio * $width;
				$result->height = $ratio * $height;
				$result->x = round( ( $newWidth  - $result->width  ) / 2 );
				$result->y = round( ( $newHeight - $result->height ) / 2 );

				/*$use_x_ratio = ($x_ratio == $ratio);

				$new_width   = $use_x_ratio  ? $width  : floor($size[0] * $ratio);
				$new_height  = !$use_x_ratio ? $height : floor($size[1] * $ratio);
				$new_left    = $use_x_ratio  ? 0 : floor(($width - $new_width) / 2);
				$new_top     = !$use_x_ratio ? 0 : floor(($height - $new_height) / 2);*/
			}
		}

		return $result;
	}
}
?>