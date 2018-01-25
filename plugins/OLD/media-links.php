<?php
/*
  PURPOSE: media link-listing functions for w3tpl
  HISTORY:
    2011-10-16 w3tpl code started to get too ugly, so pushing out some functionality into callable modules.
    2011-10-28 adapting filed-links.php to media-links.php
*/
require_once('filed-links.php');

class w3tpl_module_MediaLinks extends w3tpl_module_FiledLinks {

    // FUNCTIONS FOR THIS MODULE
      // inherits w3f_Links_forTopic() without modification

    // INTERNAL FUNCTIONS
    protected function RenderStart() {
	$out = '<table class=sortable>'
	  ."\n<tr><th>Title</th><th>Formats</th></tr>";
	return $out;
    }
    protected function RenderStop() {
	$out = "\n</table>";
	return $out;
    }
    protected function RenderLine($iTitle,array $arProps=NULL) {
	$objTitle = Title::newFromID($iTitle);

	$out = "\n<tr><td>";

	if (is_object($objTitle)) {
	    $strTitle = $objTitle->getText();
	    $urlMain = $objTitle->getLinkUrl();
	    //$objTalk = $objTitle->getTalkPage();
	    //$urlTalk = $objTalk->getLinkUrl();
	    $ok = FALSE;
	    if (is_array($arProps)) {
		if (array_key_exists('download-links',$arProps)) {
		    $ok = TRUE;
		    $htLine = '<a title="lyrics and other data" href="'.$urlMain.'">'.$strTitle.'</a>';
		    $htLine .= '</td><td>';
		    $strLinks = $this->Parse_WikiText($arProps['download-links']);
		    $htLine .= $strLinks;
		}
	    }
	    if (!$ok) {
		$htLine = '<a title="summary and index data (this needs to be updated)" href="'.$urlMain.'">'.$strTitle.'</a>';
	    }
	} else {
	    $htLine = 'No page for ID='.$idTitle;
	}
	$out .= $htLine;
	$out .= '</td></tr>';
	return $out;
    }
}

new w3tpl_module_MediaLinks();	// class will self-register

