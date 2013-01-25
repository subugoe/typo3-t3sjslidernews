<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_t3sjslidernews_pi1.php', '_pi1', 'list_type', 1);

$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3s_jslidernews']);

if ( $extConf['overlaySelected'] ) {
  t3lib_extMgm::addPageTSConfig('
      TCEFORM.tt_content.section_frame.addItems.84 = Content overlay (t3s_jslidernews)
  ');
}

?>