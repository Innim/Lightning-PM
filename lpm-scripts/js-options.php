<?php
require_once __DIR__ . '/../lpm-core/init.inc.php';
new LightningEngine();
$pc = LightningEngine::getInstance()->getCostructor();

header("Content-type: application/x-javascript");
?>
window.lpmOptions = {
	url : '<?=SITE_URL;?>',
	themeUrl : '<?=$pc->getThemeUrl();?>',
	issueImgsCount : <?=Issue::MAX_IMAGES_COUNT;?>
};