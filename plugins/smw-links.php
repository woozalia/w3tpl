<?php namespace w3tpl;
/*
  PURPOSE: SMW listing management functions for w3tpl
    (2018-01-28) Yeah, but why Google+ Communities?
  REQUIRES: filed-links.php
  TODO: SiteGroupListing() is currently hard-wired for Google+
  HISTORY:
    2011-10-16 w3tpl code started to get too ugly, so pushing out some functionality into callable modules.
    2012-01-24 split off SMW stuff from filed-links.php to smw-links.php
    2013-02-14 w3f_SiteGroupListing() now works for G+
    2015-03-14 removed explicit check for needed library
    2018-01-22 update to work with latest w3tpl
*/

$csModuleClass = 'xcModule_SMWLinks';

class xcModule_SMWLinks extends xcModule {

    // ++ TAG API ++ //

    public function TagAPI_SiteGroupListing(array $iArgs) {
	$db = \fcApp::Me()->GetDatabase();
	
	/*
	  GetPages_forPropVal() now replaced by GetTitleObjects_forPropertyValue(),
	    but return format is different.
	*/
	throw new \exception('2018-02-02 This will need some rewriting.');
	$ar = $db->GetPages_forPropVal('thing type','site-group');
/*
	$out = '<pre>';
	foreach ($ar as $key => $arRow) {
	    $out .= print_r($arRow,TRUE);
	}
	$out .= '</pre>';
*/

	$out = '<ul>';
	$objPage = new \fcPageData_SMW($this);
	foreach ($ar as $key => $arRow) {
	    $idSMW = $arRow['s_id'];	// for future coding reference; not currently used
	    $idNSpace = $arRow['s_namespace'];
	    $sTitle = $arRow['s_title'];
	    $objPage->Use_Title_Keyed($sTitle,$idNSpace);

	    $sName = $objPage->GetPropData('name');
	    $sSub = $objPage->GetPropData('subtitle');
	    $ftSub = is_null($sSub)?'':" - <i>$sSub</i>";
	    $sSumm = $objPage->GetPropData('summary');
	    $idSG = $objPage->GetPropData('ID');
	    $htIdx = $objPage->PageLink('idx');
	    $htLink = '<a href="https://plus.google.com/communities/'.$idSG.'">'.$sName.'</a>';
	    $out .= "<li>[$htIdx] <b>$htLink</b>$ftSub: $sSumm";
	}
	$out .= '</ul>';

	return $out;
    }
    /*----
      INPUT:
	site: name of site whose users should be listed
	filt: additional SMW terms to include in the filter
      USAGE:
	example: <exec module=smw-links func=SiteUserListing_with_xrefs site="Google+" />
    */
    public function w3f_SiteUserListing_with_xrefs(array $iArgs) {
	$strSite = $iArgs['site'];
	$strFilt = NzArray($iArgs,'filt');
	$smwFilt = "[[thing type::site-account]][[site::$strSite]]$strFilt";

	$dbr =& wfGetDB( DB_SLAVE );
	$db = new clsSMWData($dbr);

	$arFilt = array(
	  'PropName'	=> '="Thing type"',
	  'Value'	=> '="site-account"',
	  );
	$rs = $db->GetPages_forProps($arFilt);

	$out = 'blah';
	while ($rs->NextRow()) {
	    $out .= '<pre>'.print_r($rs->Values(),TRUE).'</pre>';
	}
echo 'got to here';
	return $out;
    }
    /*----
      INPUT:
	site: name of site whose users should be listed
	filt: additional SMW terms to include in the filter
      THIS IS THE OLD VERSION. Delete it when the new one is working.
    */
    /* 2018-01-26 This seems to be obsolete.
    public function w3f_SiteUserListing_with_xrefs_OLD(array $iArgs) {
	$strSite = $iArgs['site'];
	$strFilt = NzArray($iArgs,'filt');
	$smwFilt = "[[thing type::site-account]][[site::$strSite]]$strFilt";

	// there's probably a more direct way to pass the column names so they don't need "?"s... working on that.
	$arCols = array('?userid','?username','?employer','?job project','?job title','?legal name', '?note');
	$smwRes = $this->SMW_Query($smwFilt,$arCols);	// returns SMWQueryResult

	$out = '';

	$arPR = $smwRes->getPrintRequests();

	foreach ($arPR as $smwReq) {
	    $out .= '['.$smwReq->getLabel().']='.$smwReq->getHTMLText().' / ';
	    //$out .= '<pre>'.print_r($smwReq,TRUE).'</pre>';
	}
	return $out;

    } */

    // -- TAG API -- //
    // ++ DATA READ ++ //

    /*----
      INPUT:
	filt: filter string formatted in {{#ask:}} syntax [[category:whatever]] [[property::value]], etc.
	cols: list of columns to retrieve in \prefix\demarcated\string format
      RETURNS: SMWResultArray
    */
    /* 2018-01-26 This seems to be obsolete. If not, it should probably go in Ferreteria's SMW library. Does not reference $this.
    protected function SMW_Query($iFilt,array $arCols) {
	$strAsk = $iFilt;

	$strQry = NULL;
	$arPrint = NULL;
	SMWQueryProcessor::processFunctionParams($arCols,$strQry,$arParams,$arPrint);
	  // each PrintRequest is approximately equivalent to a column without data
	  // OUTPUT:
	    // $arParams -- not sure; may be incomplete
	    // $arPrint contains an array of PrintRequest objects


	$arParams['order'] = array();
	$arParams['sort'] = array();
	$arParams['mainlabel'] = 'The Main Label';
	$arParams['format'] = 'template';

	$smwQry = SMWQueryProcessor::createQuery($strAsk,$arParams);
	  // createQuery( $querystring, array $params, $context = SMWQueryProcessor::INLINE_QUERY, $format = '', $extraprintouts = array() )
	$smwQry->setExtraPrintouts( $arPrint );
//	$objQry = SMWQueryProcessor::createQuery($strAsk,$arCols);
	$smwStore = smwfGetStore(); // default store (what are the options?)
	$res = $smwStore->getQueryResult($smwQry);	// returns SMWQueryResult
//echo '<pre>'.print_r($res,TRUE).'</pre>';
	return $res;
    } */
    /*----
      RETURNS: same data as SMW_Query(), but formatted in an array[key]=array{values...}
    */
    /* 2018-01-26 This seems to be obsolete.
    protected function SMW_Query_asArray($iFilt,array $arCols,$iKeyCol) {
	$objRes = $this->SMW_Query($iFilt,$arCols);

	$arOut = NULL;
	while ( $row = $objRes->getNext() ) {	// returns array of SMWResultArray or false
	    $arRow = array();
	    $strIdx = NULL;
	    foreach ( $row as $field ) {	// $field is SMWResultArray (see SMW_QueryResult.php)
		$objCol = $field->getPrintRequest();
		$strKey = $objCol->getLabel();

		// a property can have multiple values for a given page -- usually just one, but there may be more
		$arVals = NULL;
		while ( ( $obj = $field->getNextObject() ) !== false ) {
		    // $obj is SMWWikiPageValue
		    $htVal = $obj->getLongText(SMW_OUTPUT_HTML);
		    $arVals[] = $htVal;
		}
		$arRow[$strKey] = $arVals;
		if ($strKey == $iKeyCol) {
		    $strIdx = $htVal;
		}
	    }
	    if (is_null($strIdx)) {
		$arOut[] = $arRow;
	    } else {
		$arOut[$strIdx] = $arRow;
	    }
	}
	return $arOut;
    } */

}

// HELPER CLASSES

/*
  PURPOSE: extends functionality of smwDataItem
    ...which currently (no longer?) exists
*/
/* 2018-01-26 This appears to be obsolete.
class w3smwDataItem {
    protected $smwObj;

    public function __construct(smwDataItem $iItem) {
	$this->smwObj = $iItem;
    }
    public function Object() {
	return $this->smwObj;
    }
    
    /*----
      BASED ON: smwDataItem::getNextDataValue()
    * /

    public function getDataValue() {
	$obj = $this->Object();
	if ( $obj === false ) {
	    return false;
	}
	if ( $obj->mPrintRequest->getMode() == SMWPrintRequest::PRINT_PROP ) {
	    $diProperty = $obj->mPrintRequest->getData()->getDataItem();
	} else {
	    $diProperty = null;
	}
	$dv = SMWDataValueFactory::newDataItemValue( $obj, $diProperty );
	if ( $obj->mPrintRequest->getOutputFormat() ) {
	    $dv->setOutputFormat( $obj->mPrintRequest->getOutputFormat() );
	}
	return $dv;
    }
} */