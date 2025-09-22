<?php
require_once __DIR__ . '/../lpm-core/init.inc.php';
new LightningEngine();
$pc = LightningEngine::getInstance()->getConstructor();

header("Content-type: application/x-javascript");
?>
window.lpmOptions = {
	url: '<?=SITE_URL;?>',
	themeUrl: '<?=$pc->getThemeUrl();?>',
	issueImgsCount: <?=Issue::MAX_IMAGES_COUNT;?>,
	issueFilesCount: <?=Issue::MAX_FILES_COUNT;?>,
	issueFileMaxSizeMb: <?=LPMFile::MAX_SIZE_MB;?>,
	gitlabUrl: '<?=defined('GITLAB_URL') ? GITLAB_URL : '';?>',
	videoUrlPatterns: <?=json_encode(AttachmentVideoHelper::URL_PATTERNS);?>,
	imageUrlPatterns: <?=json_encode(AttachmentImageHelper::URL_PATTERNS);?>,
	issueUrlPattern: <?=json_encode(OwnUrlHelper::getIssueUrlPattern());?>,
};
