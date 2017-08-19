<?php
namespace GMFramework;

/**
 * Еще один велосипед - класс для отправки почты
 * @package ru.vbinc.gm.framework.mail
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1.0
 *
 */
class MailSender
{
	private $_fromEmail;
	private $_fromName;
	
	function __construct( $fromEmail = '', $fromName = '' ) 
	{
		$this->setFrom( $fromEmail, $fromName );
				
	}
	
	public function setFrom( $email, $name = '') {
		$this->_fromEmail = $email;
        $this->_fromName  = $name;
	}
	
	public function sendMail( $toEmail, $message, $subject = '', $toName = '', $type = '' ) {
		return $this->send( new MailMessage( $toEmail, $subject, $message, $type, $toName ) );
	}
	
	public function send( MailMessage $mess ) {
		$mess->setFrom( $this->_fromEmail, $this->_fromName );
		$mess->setContentType( 'related' );
		
		return @mail( 
		          '',//$mess->getTo(), 
		          $mess->getSubject(),
		          '',//$mess->getBody(),
		          $mess->getMessage()
		);
	}
/*function KMail($to, $from, $subj, $text, $files = null, $isHTML = false){
 $boundary = "------------".strtoupper(md5(uniqid(rand())));
 $headers  = "From: ".$from."\r\n
              X-Mailer: koz1024.net\r\n
              MIME-Version: 1.0\r\n
              Content-Type: multipart/alternative;boundary=\"$boundary\"\r\n\r\n
             ";
 if (!$isHTML){
  $type = 'text/plain';
 }else{
  $type = 'text/html';
 }
   $body =  $boundary."\r\n\r\n
            Content-Type:".$type."; charset=utf-8\r\n
            Content-Transfer-Encoding: 8bit\r\n\r\n
            ".$text."\r\n\r\n";
 if ((is_array($files))&&(!empty($files))){
    foreach($files as $filename => $filecontent){
       $body .= $boundary."\r\n
                Content-Type: application/octet-stream;name=\"".$filename."\"\r\n
                Content-Transfer-Encoding:base64\r\n
                Content-Disposition:attachment;filename=\"".$filename."\"\r\n\r\n
                ".chunk_split(base64_encode($filecontent));
    }
 }
 return mail($to, $subj, $body, $headers);
}*/
/*
function mail_attachment($filename, $path, $mailto, $from_mail, $from_name, $replyto, $subject, $message) {
    $file = $path.$filename;
    $file_size = filesize($file);
    $handle = fopen($file, "r");
    $content = fread($handle, $file_size);
    fclose($handle);
    $content = chunk_split(base64_encode($content));
    $uid = md5(uniqid(time()));
    $name = basename($file);
    $header = "From: ".$from_name." <".$from_mail.">\r\n";
    $header .= "Reply-To: ".$replyto."\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";
    $header .= "This is a multi-part message in MIME format.\r\n";
    $header .= "--".$uid."\r\n";
    $header .= "Content-type:text/plain; charset=iso-8859-1\r\n";
    $header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $header .= $message."\r\n\r\n";
    $header .= "--".$uid."\r\n";
    $header .= "Content-Type: application/octet-stream; name=\"".$filename."\"\r\n"; // use different content types here
    $header .= "Content-Transfer-Encoding: base64\r\n";
    $header .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
    $header .= $content."\r\n\r\n";
    $header .= "--".$uid."--";
    if (mail($mailto, $subject, "", $header)) {
        echo "mail send ... OK"; // or use booleans here
    } else {
        echo "mail send ... ERROR!";
    }
}
*/
/*
function sweb () { 
$file_name="5.jpg";
$subj="Отправка изображения";
$bound="spravkaweb-1234";
$headers="From: \"Evgen\" <admin@spravkaweb.ru>n";
$headers.="To: admin@localhost.run";
$headers.="Subject: $subjn";
$headers.="Mime-Version: 1.0n";
$headers.="Content-Type: multipart/alternative; boundary=\"$bound\"n";
$body="--$boundn";
$body.="Content-type: text/html; charset=\"windows-1251\"n";
$body.="Content-Transfer-Encoding: 8bitnn";
$body.="<h3>Привет</h3>
<p>Это проба отправки письма с прикрепленной картинкой.<br>
А вот и сама картинка:<br>
<img src=\"cid:spravkaweb_img_1\">";
$body.="nn--$boundn";
$body.="Content-Type: image/jpeg; name=\"".basename($file_name)."\"n";
$body.="Content-Transfer-Encoding:base64n";
$body.="Content-ID: <spravkaweb_img_1>nn";
$f=fopen($file_name,"rb");
$body.=base64_encode(fread($f,filesize($file_name)))."n";
$body.="--$bound--nn";
mail("admin@localhost.ru", $subj, $body, $headers);
}*/
}
?>