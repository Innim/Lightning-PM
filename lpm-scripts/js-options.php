<?php
require_once __DIR__ . '/../lpm-core/init.inc.php';
new LightningEngine();
$pc = LightningEngine::getInstance()->getCostructor();
?>
window.lpmOptions = {
	url : '<?=SITE_URL;?>',
	themeUrl : '<?=$pc->getThemeUrl();?>',
	issueImgsCount : <?=Issue::MAX_IMAGES_COUNT;?>
};
<?php
header("Content-type: application/x-javascript");
?>