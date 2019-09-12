<?php
// инициализация
require_once( dirname( __FILE__ ) . '/lpm-core/init.inc.php' );

//echo "<pre/>";
//var_dump($_GET);
//var_dump($this->getParam(0));
//exit;

$lightning = new LightningEngine();
$lightning->createPage();
?>