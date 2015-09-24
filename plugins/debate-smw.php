<?php
/*
  PURPOSE: structured debate functions for w3tpl using Semantic MediaWiki
  HISTORY:
    2011-10-16 w3tpl code started to get too ugly, so pushing out some functionality into callable modules.
    2011-12-07 starting to adapt from filed-links.php
    2015-09-10 renaming from debate.php to debate-smw.php because it uses SMW
*/

new w3tpl_module_Debate();	// class will self-register

define('KWP_ICON_PRO_POINT','File:Arrow-button-up-20px.png');
define('KWP_ICON_CON_POINT','File:Arrow-button-dn-20px.png');

class w3tpl_module_Debate extends w3tpl_module_FiledLinks {
    protected $arPointTplt;
    protected $htPointTplt_end;
    protected $objTpltLine;

    // FUNCTIONS FOR THIS MODULE

    public function __construct() {
	parent::__construct();

	$this->objTpltLine = NULL;
	$this->arPointTplt['+'] =
	  '<span style="background: #eeffee;">'
	  .'[['.KWP_ICON_PRO_POINT.'|link=Issuepedia:Debaticons|alt=up-arrow debaticon|support point (argues in support of the main point)]] ';
	$this->arPointTplt['-'] =
	  '<span style="background: #ffeeee;">'
	  .'[['.KWP_ICON_CON_POINT.'|link=Issuepedia:Debaticons|alt=down-arrow debaticon|counterpoint (argues against the main point)]] ';
//	$this->arPointTplt['*'] =
//	  '<span style="background: #eeeeff;">[[File:Arrow-button-rt-25px.png|link=Issuepedia:Debaticons|alt=right-arrow debaticon|central claim of argument]] ';
	$this->htPointTplt_end = '</span>';
    }
    protected function GetLineTplt() {
	if (is_null($this->objTpltLine)) {
	    $obj = new clsStringTemplate_array('[$','$]',array());
	    $this->objTpltLine = $obj;
	}
	return $this->objTpltLine;
    }
    protected function GetLineText($iCode,$iText) {
	$objTp = $this->GetLineTplt();

	$htPfx = $this->arPointTplt[$iCode];
	$out = $this->Parse_WikiText($htPfx.$iText.$this->htPointTplt_end);
	return $out;
    }
    /*----
      INPUT:
	"title": name of SMW data page to start at
    */
    protected function w3f_ShowDebate(array $iArgs) {
	global $arPointTplt,$htPointTplt_end;

	if (array_key_exists('title',$iArgs)) {
	    $strTitleRaw = $iArgs['title'];
	    $objTitle = Title::newFromText($strTitleRaw);
	    if (is_object($objTitle)) {
		$strTitleSQL = $objTitle->getPrefixedDBkey();
	    } else {
		$out = '<b>Internal error</b>: could not load page [['.$strTitleRaw.']]';
		return $out;
	    }
	} else {
	    global $wgTitle;

	    $strTitleSQL = $wgTitle->getPrefixedDBkey();
	}
	$smwFilt = "[[Page type::Debate point]][[Response to::$strTitleSQL]]";
	$arCols = array('?summary','?response type');
	$arRes = $this->SMW_Query_asArray($smwFilt,$arCols,'');

	$out = '';
//	$out .= '<pre>'.print_r($arRes,TRUE).'</pre>';

	$out .= '<ul>';
	foreach ($arRes as $strTitle => $arData) {
	    $objTitle = Title::newFromText($strTitle);
	    $urlTitle = $objTitle->getLinkUrl();
	    $wtSummary = $arData['Summary'][0];
	    $txtType = strtolower($arData['Response type'][0]);
	    switch ($txtType) {
	      case 'support':
		$strTypeIdx = '+';
		break;
	      case 'counter':
		$strTypeIdx = '-';
		break;
	    }
	    $htPfx = $arPointTplt[$strTypeIdx];
	    //$out .= '<li> <a href="'.$urlTitle.'">#</a> '.$htPfx.$htSummary.$htPointTplt_end.' ['.$txtType.$strTypeIdx.']';
	    $out .= '<li> <a href="'.$urlTitle.'">#</a> '.$this->GetLineText($strTypeIdx,$wtSummary);
//	$out .= '<pre>'.print_r($arData,TRUE).'</pre>';
	}
	$out .= '</ul>';
/*
	$objRes = $this->SMW_Query($smwFilt,$arCols);	// SMWQueryResult
	$out = '';
	$out .= 'PAGE: ['.$strTitleSQL.']';
	$out .= 'TYPE: '.get_class($objRes).' RESULTS: '.$objRes->getCount();
//	$out .= '<pre>'.print_r($objRes,TRUE).'</pre>';
	while ( $row = $objRes->getNext() ) {	// returns array of SMWResultArray or false
//$out .= 'A';
	    foreach ( $row as $field ) {	// $field is SMWResultArray (see SMW_QueryResult.php)
//$out .= 'B';
//		$out .= '<pre>'.print_r($field,TRUE).'</pre>';
		$objCol = $field->getPrintRequest();
		$strKey = $objCol->getLabel();

		// a property can have multiple values for a given page -- usually just one, but there may be more
		$arVals = NULL;
		while ( ( $obj = $field->getNextObject() ) !== false ) {
		    $htVal = $obj->getLongText(SMW_OUTPUT_HTML);
		    $out .= '['.$htVal.']';
		}
	    }
	}
*/
	// list the SMW data we want from each matching page
/*	$out = '';
	foreach ($arData as $idx => $row) {
//	    $arName = $row['Summary'];
	    $out .= '<pre>'.print_r($row,TRUE).'</pre>';
	}
//	$out = 'TITLE=['.$strTitleSQL.']';
*/
	return $out;
    }
}

class DebatePoint /* extends clsTreeNode */ {
    public function __construct(w3tpl_module_Debate $iDebate, $iText, $iType, DebatePoint $iParent=NULL) {
    }
}