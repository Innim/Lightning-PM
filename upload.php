<?php
require_once dirname(__FILE__) . '/lpm-config.inc.php';
$uploader = new ASyncUploader();
$uploader->init($_REQUEST);
