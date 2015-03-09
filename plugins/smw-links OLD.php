<?php
/*
  PURPOSE: SMW listing management functions for w3tpl
  HISTORY:
    2011-10-16 w3tpl code started to get too ugly, so pushing out some functionality into callable modules.
    2012-01-24 split off SMW stuff from filed-links.php to smw-links.php
*/

new w3tpl_module_SMWLinks();	// class will self-register

class w3tpl_module_SMWLinks extends w3tpl_module {

    // FUNCTIONS FOR THIS MODULE


    /*----
      INPUT:
	site: name of site whose users should be listed
	filt: additional SMW terms to include in the filter
      NOTE: This uses SMW and probably belongs in a separate plugin.
    */
    public function w3f_SiteUserListing_with_xrefs(array $iArgs) {
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
/* works as far as it goes, but no Page info
	while ( $row = $smwRes->getNext() ) {	// returns array of SMWResultArray or false
	    foreach ( $row as $field ) {	// $field is SMWResultArray (see SMW_QueryResult.php)
		$objCol = $field->getPrintRequest();
		$strKey = $objCol->getLabel();
		$out .= '['.$strKey.']';
	    }
	}
*/
/* TAKE 2
	while ($smwRow = $smwRes->getNext()) {
	    // $smwRow is SMWResultArray
	    $out .= '*';
	    if (is_object($smwRow)) {
		$out .= '<pre>'.print_r($smwRow,TRUE).'</pre>';
		while ($smwItem = $smwRow->getNextDataItem()) {
		    // $smwItem is SMWDataItem
		    $objItem = new w3smwDataItem($smwItem);

		    $smwData = $objItem->getDataValue();
		    $htVal = $smwData-> getShortText(SMW_OUTPUT_HTML);
		    $out .= '/'.$htVal;

		}
	    }
	}
*/
	
/* TAKE 1
	$arData = $this->SMW_Query_asArray($smwFilt,$arCols,'userid');

	// set up for user xref queries
	$arRefCols = array('?name','?page','?URL');

	$out = '<table><tr><th></th><th>Name</th><th>About</th><th>More Information</th></tr>';
	foreach ($arData as $idx => $row) {
echo '<pre>'.print_r($arData,TRUE).'</pre>'; die();
	    //$arPage = $row[NULL];
	    $arUser = $row['Username'];
	    $arName = $row['Legal name'];
	    $arEmployer = $row['Employer'];
	    $arJobProj = $row['Job project'];
	    $arJobTitle = $row['Job title'];
	    $arNotes = $row['Note'];

	    $strUser = implode(',',array_values($arUser));
	    $strID = implode(',',$row['Userid']);

	    if (is_array($arName)) {
		$strName = implode(',',$arName);
		$htName = ' ('.$strName.')';
	    } else {
		$htName = NULL;
	    }

	    $htEmployer = ArrayToString($arEmployer);
	    $htJobProj = ArrayToString($arJobProj);
	    $htJobTitle = ArrayToString($arJobTitle);
	    $htJob = NULL;
	    if ($htEmployer) {
		$htJob = $htEmployer;
	    }
	    if ($htJobProj) {
		if ($htJob) {
		    $htJob .= ': ';
		}
		$htJob .= '<b>'.$htJobProj.'</b>';
	    }
	    if ($htJobTitle) {
		$htJob .= ' <b>'.$htJobTitle.'</b>';
	    }

	    $out .= "\n<tr>";
	    $htRefs = NULL;

	    if (is_array($arPage)) {
		$strPage = $arPage[0];	// we're going to blatantly assume there's only one title in the array
		// If I understand right, there's logically no way there *could* be multiple titles for this kind of query.


		$objTitle = Title::newFromText($strPage);
		$htLink = $objTitle->getFullURL();

		$out .= '<td valign=top><a href="'.$htLink.'" title="data for '.$strUser.'">#</a></td>';

		$out .= '<td valign=top><a href="https://plus.google.com/'.$strID.'" title="'.$strUser."'s $strSite user page".'">'
		  .$strUser.'</a>'.$htName;

	      // get additional info for this user
		
		$arData = $this->SMW_Query_asArray($smwFilt,$arCols,'userid');

		// check for any user xref links
		$smwRefFilt = "[[page type::link]][[about::$strPage]]";
		$arRefData = $this->SMW_Query_asArray($smwRefFilt,$arRefCols,'userid');
		//$out .= '<pre>'.print_r($arRefData,TRUE).'</pre>';
		if (is_array($arRefData)) {
		    foreach ($arRefData as $idx => $data) {
			$strLTitle = $data[NULL][0];
			$strLName = $data['Name'][0];
			$strLPage = $data['Page'][0];
			$strLURL = $data['URL'][0];
			$htLinkRef = Link_forURLorPage($strLURL,$strLPage);
			$htLTitle = Link_forPage($strLTitle);

			$htRefs .= '<a href="'.$htLinkRef.'" title="'.$strLName.' link for '.$strUser.'">'.$strLName.'</a>'
			  .'<sub><a href="'.$htLTitle.'" title="local data for the reference">#</a></sub>';
		    }
		}
		$out .= '</td>';
	    } else {
		$out .= '<td colspan=2>Data unexpectedly missing...</td>';
	    }

	    // "About" column
	    $out .= '<td>'.$htJob;
	    if (is_array($arNotes)) {
		$out .= '<small><ul>';
		foreach ($arNotes as $note) {
		    $out .= '<li> '.$note.'</li>';
		}
		$out .= '</ul></small>';
	    }
	    $out .= '</td>';

	    // "More info" column
	    if (!is_null($htRefs)) {
		$out .= '<td valign=top>'.$htRefs.'</td>';
	    }
	    $out .= '</tr>';
	    //$out .= $idx.'<pre>'.print_r($arName,TRUE).'</pre>';
	}
	$out .= "\n</table>";

	//$out = '<pre>'.print_r($arData,TRUE).'</pre>';
*/
	return $out;

    }

    // SUB-FUNCTIONS

    /*----
      INPUT:m
	filt: filter string formatted in {{#ask:}} syntax [[category:whatever]] [[property::value]], etc.
	cols: list of columns to retrieve in \prefix\demarcated\string format
      NOTE: This uses SMW and probably belongs in a separate plugin.
      RETURNS: SMWResultArray
    */
    protected function SMW_Query($iFilt,array $arCols) {
/*
	$strAsk = $iArgs['filt'];	// e.g. [[prop::value]]
	$strCols = $iArgs['cols'];	// \list\of\column\names
	$xtCols = new xtString($strCols);
	$arCols = $xtCols->Xplode();
*/
	$strAsk = $iFilt;

//	$out = NULL;

//	$out = '<pre>'.print_r($arCols,TRUE).'</pre>';

	$strQry = NULL;
	$arPrint = NULL;
	SMWQueryProcessor::processFunctionParams($arCols,$strQry,$arParams,$arPrint);
	  // each PrintRequest is approximately equivalent to a column without data
	  // OUTPUT:
	    // $arParams -- not sure; may be incomplete
	    // $arPrint contains an array of PrintRequest objects

//	$out .= 'params: <pre>'.print_r($arParams,TRUE).'</pre>';
//	$out .= 'print: <pre>'.print_r($arPrint,TRUE).'</pre>';
//	$out .= '<b>strQry</b>:['.$strQry.']';

/*
if (!array_key_exists('format',$arParams)) {
    echo '<b>cols</b>:<pre>'.print_r($arCols,TRUE).'</pre>';
    echo '<b>print</b>:<pre>'.print_r($arPrint,TRUE).'</pre>';
    echo '<b>params</b>:<pre>'.print_r($arParams,TRUE).'</pre>';
    throw new exception('Got to here');
}
*/
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

/* Leaving this here for now as sample code	
//	$out .= '<table>';
	while ( $row = $res->getNext() ) {	// returns array of SMWResultArray or false
//	    $out .= "\n<tr>";
	    foreach ( $row as $field ) {	// $field is SMWResultArray (see SMW_QueryResult.php)
//		$out .= "<td>";
		$objCol = $field->getPrintRequest();
		$out .= '<br>'.$objCol->getLabel().' =';
		//$out .= '<pre>'.print_r($objCol,TRUE).'</pre>';

		// a property can have multiple values for a given page -- usually just one, but there may be more
		while ( ( $obj = $field->getNextObject() ) !== false ) {
		    // $obj is SMWWikiPageValue
		    $htVal = $obj->getLongText(SMW_OUTPUT_HTML);
		    $out .= ' ['.$htVal.'] ';
//		    $out .= '<pre>'.print_r($obj,TRUE).'</pre>';
		}

//		$out .= "</td>";
	    }
//	    $out .= '</tr>';
	}
//	$out .= '</table>';
*/
    }
    /*----
      RETURNS: same data as SMW_Query(), but formatted in an array[key]=array{values...}
    */
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
    }

}

// HELPER CLASSES

/*
  PURPOSE: extends functionality of smwDataItem
*/
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
    */

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

    // TAG-CALLABLE FUNCTIONS

    /*----
      INPUT:
	site: name of site whose users should be listed
	filt: additional SMW terms to include in the filter
      NOTE: This uses SMW and probably belongs in a separate plugin.
    */
    public function w3f_SiteUserListing_with_xrefs(array $iArgs) {
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
/* works as far as it goes, but no Page info
	while ( $row = $smwRes->getNext() ) {	// returns array of SMWResultArray or false
	    foreach ( $row as $field ) {	// $field is SMWResultArray (see SMW_QueryResult.php)
		$objCol = $field->getPrintRequest();
		$strKey = $objCol->getLabel();
		$out .= '['.$strKey.']';
	    }
	}
*/
/* TAKE 2
	while ($smwRow = $smwRes->getNext()) {
	    // $smwRow is SMWResultArray
	    $out .= '*';
	    if (is_object($smwRow)) {
		$out .= '<pre>'.print_r($smwRow,TRUE).'</pre>';
		while ($smwItem = $smwRow->getNextDataItem()) {
		    // $smwItem is SMWDataItem
		    $objItem = new w3smwDataItem($smwItem);

		    $smwData = $objItem->getDataValue();
		    $htVal = $smwData-> getShortText(SMW_OUTPUT_HTML);
		    $out .= '/'.$htVal;

		}
	    }
	}
*/
	
/* TAKE 1
	$arData = $this->SMW_Query_asArray($smwFilt,$arCols,'userid');

	// set up for user xref queries
	$arRefCols = array('?name','?page','?URL');

	$out = '<table><tr><th></th><th>Name</th><th>About</th><th>More Information</th></tr>';
	foreach ($arData as $idx => $row) {
echo '<pre>'.print_r($arData,TRUE).'</pre>'; die();
	    //$arPage = $row[NULL];filed
	    $arUser = $row['Username'];
	    $arName = $row['Legal name'];
	    $arEmployer = $row['Employer'];
	    $arJobProj = $row['Job project'];
	    $arJobTitle = $row['Job title'];
	    $arNotes = $row['Note'];

	    $strUser = implode(',',array_values($arUser));
	    $strID = implode(',',$row['Userid']);

	    if (is_array($arName)) {
		$strName = implode(',',$arName);
		$htName = ' ('.$strName.')';
	    } else {
		$htName = NULL;
	    }

	    $htEmployer = ArrayToString($arEmployer);
	    $htJobProj = ArrayToString($arJobProj);
	    $htJobTitle = ArrayToString($arJobTitle);
	    $htJob = NULL;
	    if ($htEmployer) {
		$htJob = $htEmployer;
	    }
	    if ($htJobProj) {
		if ($htJob) {
		    $htJob .= ': ';
		}
		$htJob .= '<b>'.$htJobProj.'</b>';
	    }
	    if ($htJobTitle) {
		$htJob .= ' <b>'.$htJobTitle.'</b>';
	    }

	    $out .= "\n<tr>";
	    $htRefs = NULL;

	    if (is_array($arPage)) {
		$strPage = $arPage[0];	// we're going to blatantly assume there's only one title in the array
		// If I understand right, there's logically no way there *could* be multiple titles for this kind of query.


		$objTitle = Title::newFromText($strPage);
		$htLink = $objTitle->getFullURL();

		$out .= '<td valign=top><a href="'.$htLink.'" title="data for '.$strUser.'">#</a></td>';

		$out .= '<td valign=top><a href="https://plus.google.com/'.$strID.'" title="'.$strUser."'s $strSite user page".'">'
		  .$strUser.'</a>'.$htName;

	      // get additional info for this user
		
		$arData = $this->SMW_Query_asArray($smwFilt,$arCols,'userid');

		// check for any user xref links
		$smwRefFilt = "[[page type::link]][[about::$strPage]]";
		$arRefData = $this->SMW_Query_asArray($smwRefFilt,$arRefCols,'userid');
		//$out .= '<pre>'.print_r($arRefData,TRUE).'</pre>';
		if (is_array($arRefData)) {
		    foreach ($arRefData as $idx => $data) {
			$strLTitle = $data[NULL][0];
			$strLName = $data['Name'][0];
			$strLPage = $data['Page'][0];
			$strLURL = $data['URL'][0];
			$htLinkRef = Link_forURLorPage($strLURL,$strLPage);
			$htLTitle = Link_forPage($strLTitle);

			$htRefs .= '<a href="'.$htLinkRef.'" title="'.$strLName.' link for '.$strUser.'">'.$strLName.'</a>'
			  .'<sub><a href="'.$htLTitle.'" title="local data for the reference">#</a></sub>';
		    }
		}
		$out .= '</td>';
	    } else {
		$out .= '<td colspan=2>Data unexpectedly missing...</td>';
	    }

	    // "About" column
	    $out .= '<td>'.$htJob;
	    if (is_array($arNotes)) {
		$out .= '<small><ul>';
		foreach ($arNotes as $note) {
		    $out .= '<li> '.$note.'</li>';
		}
		$out .= '</ul></small>';
	    }
	    $out .= '</td>';

	    // "More info" column
	    if (!is_null($htRefs)) {
		$out .= '<td valign=top>'.$htRefs.'</td>';
	    }
	    $out .= '</tr>';
	    //$out .= $idx.'<pre>'.print_r($arName,TRUE).'</pre>';
	}
	$out .= "\n</table>";

	//$out = '<pre>'.print_r($arData,TRUE).'</pre>';
*/
	return $out;

    }

}
