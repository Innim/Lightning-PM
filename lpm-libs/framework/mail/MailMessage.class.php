<?php
namespace GMFramework;

/**
 * Еще один велосипед - класс для отправки почты
 * @package ru.vbinc.gm.framework.mail
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1.0
 * // GMF2DO проверять и допиливать
 * <warning>не тестировались простые приложения (не embed)</warning>
 * <warning>не тестировались не html письма</warning>
 * <warning>не тестировалось вообще нихера толком</warning>
 */
class MailMessage 
{
	const TYPE_TEXT = 'text';
	const TYPE_HTML = 'html';
	const FILE_TYPE_PNG  = 'image/png';
	const FILE_TYPE_JPEG = 'image/jpeg';
	 
	private $_toEmail;
	private $_toName;
	private $_message;
    private $_subject;
	private $_type;
	private $_fromEmail = '';
    private $_fromName  = '';
    
    private $_contentType = 'mixed';
    
    private $_files = array();    
    private $_embed  = array();
	
	private $_delimiter;
	
	/*private $_headers = array();*/
	
	function __construct( $toEmail, $subject, $message, $type = '', $toName = '' ) {
		$this->_toEmail = $toEmail;
		$this->_subject = $subject;
		$this->_message = $message;
		$this->_toName  = $toName;
		$this->_type    = $type == '' ? self::TYPE_TEXT : $type;
		
		$this->_delimiter = '--gm--' . md5( BaseString::randomStr() );
	}
	
	function __toString() {
		return $this->getMessage();
	}
	
	public function setFrom( $fromEmail, $fromName = '' ) {
		$this->_fromEmail = $fromEmail;
		$this->_fromName  = $fromName;
	}
	
	public function addAttachment( $file, $type = '' ) {
		$this->addFile( $this->_files, $file, $type );
	}
    
	/**
	 * Добавляет изображение, которое затем будет встроена через cid
	 * Идентификатором будет выступать имя изображение, так что оно должно быть уникальным
	 * @param string $file путь к файлу изображения
	 * @param string $type mime тип изображения
	 * 
	 */
    public function addEmbed( $file, $type = '' ) {
        $this->addFile( $this->_embed, $file, $type );
    }
	
	private function addFile( &$toArr, $file, $type ) {
		$toArr[$file] = $type;
	}
	
	public function getMessage() {
		return $this->getHeaders() .
		       $this->getBody();
	}
	
	public function getTo() {
		return $this->_toEmail;
	}
    
    public function getSubject() {
        return $this->_subject;
    }
    
    public function setContentType( $value ) {
    	$this->_contentType = $value;
    }
	
	private function getHeaders() {
		$headers = array();
		
		// от кого
		if (!empty( $this->_fromEmail )) 
		  array_push( 
		      $headers, 
		      'From: ' . 
		      (!empty( $this->_fromName ) 
		       ? '"' . $this->getBase64Name( $this->_fromName ) . '" ' : '') .
		      '<' . $this->_fromEmail . '>'               
		  );
	   
	   // если будет нужен, можо еще добавить X-Mailer
	   // но пока бе него
	   
	   	  
	   array_push( 
           $headers,
           // кому  
           'To: ' . 
           (!empty( $this->_toName ) 
            ? '"' . $this->getBase64Name( $this->_toName ) . '" ' : '') .
            '<' . $this->_toEmail . '>',
            // тема (тут по идее надо проверять на "нехорошие символы",
            // поэтому пока отключим), если здесь будет влючено, тогда надо убрать из MailSender::send  
            //'Subject: ' . $this->_subject,
            // mime-версия
            'Mime-Version: 1.0',
            // content-type
	   		'Content-Type: ' .
	   		( $this->isSimpleText() 
	   		  ? 'text/plain; charset=utf-8'  
            // GMF2DO возможность отсылать две версии для html - html и текст  (alternative)
              : 'multipart/' . $this->_contentType . '; ' .
                'boundary="' . $this->_delimiter . '"' )
       );
	   
	   if ($this->isSimpleText()) {
	   	    array_push( $headers, 'Content-Transfer-Encoding: base64' );
	   }
	   array_push( $headers, '' );
	   	   
       return implode( "\r\n", $headers );
	}
	
	private function getBase64Name( $name ) {
		return '=?utf-8?B?' . base64_encode( $name ) . '?=';
		//0J/RgNGP0LTQuNC10LIg0JLQu9Cw0LTQuA==?= =?utf-8?B?0LzQuNGAINCb0LXQvtC90LjQtNC+0LLQuNGH?=
	}
	
	private function isSimpleText() {
		return $this->_type == MailMessage::TYPE_TEXT;
	}
	
	private function getBody() {
		$bodyParts = array();
				
		// GMF2DO переделать, чтобы текст сообщения отсылать в base64
		
		if ($this->isSimpleText()) {
			array_push(
				$bodyParts,
				chunk_split( base64_encode( $this->_message ) )
			);
		} else 
			array_push( 
	            $bodyParts, 
	            '--' . $this->_delimiter, // разделитель
	            // content-type
	            'Content-Type: text/' . $this->_type . '; ' . 
	                          'charset=utf-8',    
	            // Content-Transfer-Encoding 
	            'Content-Transfer-Encoding: 8bit',
	            '',
	            // текст сообщения        
	            $this->_message, 
	            '',
	            // добавляем файлы для встраивания и аттачменты, если они есть  
	            $this->getFiles( $this->_files, false ) .
	            $this->getFiles( $this->_embed, true  ),
	            // вот и сказочке конец
	            '--' . $this->_delimiter . '--'     
	        );
        
        return implode( "\r\n", $bodyParts ); 
	}
	
	private function getFiles( $arr, $embed = false ) {
		//$files = '';		
        $fileDesc = array();
		foreach ($arr as $file => $type){
			$filename = basename( $file );
            
            if (!file_exists( $file )) continue;
            //$f = fopen( $file, "rb" );            
            $fileBase64 = base64_encode(
                file_get_contents( $file )
                //fread( $f, filesize( $file ) )
            );
            //fclose( $f );
			
			array_push( 
			    $fileDesc,
			    '--' . $this->_delimiter,
                'Content-Type: ' . (empty( $type ) ? 'application/octet-stream' : $type ) . ';' .
                              'name="' . $filename . '"',
			    'Content-Transfer-Encoding: base64'
            ); 

            if ($embed) {
            	$disposition = 'inline';
            	// GMF2DO тут имя файла получается должно быть уникальным
                // надо бы переделать
                array_push( 
                    $fileDesc,
                    'Content-ID: <' . $filename . '>'
                );
            } else $disposition = 'attachment';  
            
            array_push( 
                $fileDesc,
                'Content-Disposition:' . $disposition . ';filename="' . $filename . '"',                
                '',
                chunk_split( $fileBase64 )
            );   
	    }
	    
	    return implode( "\r\n", $fileDesc );
	}
}
?>