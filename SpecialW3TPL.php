<?php
/* ****

REQUIRES: w3tpl.php
HISTORY:
  2013-01-22 creating for the purpose of figuring out where functions are (or are not) being defined

*/

$wgSpecialPages[ 'w3tpl' ] = 'SpecialW3TPL'; # Tell MediaWiki about the new special page and its class name
$wgAutoloadClasses['SpecialW3TPL'] = $dir . 'SpecialW3TPL.class.php'; # Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['w3tpl'] = $dir . 'w3tpl.i18n.php';
$wgSpecialPageGroups[ 'w3tpl' ] = 'other';

