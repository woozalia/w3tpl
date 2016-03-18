<?php
/*
  PURPOSE: media link-listing functions for w3tpl
  HISTORY:
    2011-10-16 w3tpl code started to get too ugly, so pushing out some functionality into callable modules.
    2011-10-28 adapting filed-links.php to media-links.php
    2012-08-07 started adapting for PsyCrit
    2016-03-17 changed "=& wfGetDB()" to "= wfGetDB()" to remove strict-mode errors
*/
require_once('filed-links.php');
require_once('smw-links.php');

class w3tpl_module_PsyCrit extends w3tpl_module_FiledLinks {
    /*----
      RETURNS: Wzl data object for the MW/SMW database
      TODO: this should eventually be an override
    */
    public function Engine() {
	$dbr = wfGetDB( DB_SLAVE );
	$db = new PsyCrit_Data($dbr);
	return $db;
    }
    // FUNCTIONS FOR THIS MODULE
      // inherits w3f_Links_forTopic() without modification
    protected function GetSQL_for_Targets() {
	$sql = 'SELECT * FROM'
	  .' categorylinks AS cl'
	  .' LEFT JOIN page AS p'
	  .' ON cl_from=page_id'
	  .' WHERE (cl_to="Specs/target");';
	return $sql;
    }
    /*----
      TODO: Move most of this code into the PsyCrit_Page class
    */
    public function w3f_Show_Target_Page() {
	$wtOut =
	  '[[page type::specs]]'
	  .'[[specs type::target]]'
	  .'[[format version::3]]'
	  .'[[category:specs/target]]';
	$this->Parse_WikiText($wtOut);	// parse but don't show

	$smoTitle = new PsyCrit_Page($this);
	$smoTitle->Use_GlobalTitle();

	$out = $smoTitle->RenderBit_Keyname();

	$txtDiTitle = DBkeyToDisplay($smoTitle->GetPropVal('title'));
	$htAuthor = $smoTitle->GetPropLinks('Author');
	$htCiteSrc = clsArray::RenderList($smoTitle->GetPropVal('cite/source'));
	$htYear = $smoTitle->GetPropLinks('year');
	$wtAbstract = $smoTitle->GetPropVal('abstract');

	$strKey = $smoTitle->Keyname();
	$htResps = $this->Engine()->RenderResponsesFor($strKey);


	$out .= "'''$txtDiTitle''': "
	  ."$htAuthor, "
	  .$htCiteSrc
	  ." ($htYear)."
	  ."<h2>Abstract</h2>"
	  .$wtAbstract;

	if (!is_null($htResps)) {
	    $out .= "\n* '''Responses''': $htResps";
	}

	return $this->Parse_WikiText($out);
    }
    /*----
      ACTION: Format the header (what goes above the main text) for the current Response article
      ASSUMES: current page is a Response article
    */
    public function w3f_Show_Response_Header() {
	$smoATitle = new PsyCrit_Page($this->Engine());
	$smoATitle->Use_GlobalTitle();

	$ar = $smoATitle->Render_ResponseHeader();

	$this->Parse_WikiText($ar['hide']);		// render for tags, but don't show
	return $this->Parse_WikiText($ar['show']);	// stuff to actually show
    }
    /*----
      ACTION: list all responses to the current page
      ASSUMES: current page is a Target page
    */
    public function w3f_List_Responses_toThis_summary() {
    }
    /*----
      ACTION: list all articles Targeted by this page
    */
    public function w3f_List_Targets_forThis_ref() {

	// 1. Get targets list
	$smoCTitle = new PsyCrit_Page($this->Engine());
	$smoCTitle->Use_GlobalTitle();

	return $smoCTitle->Render_Targets_forThis_ref();
    }
    /*----
      ACTION: list ALL Target articles, with a formatted description of each.
    */
    public function w3f_List_Targets_summary() {
	$sql = $this->GetSQL_for_Targets();

	// get a database connection object
	$dbr =& wfGetDB( DB_SLAVE );
	// execute SQL and get data
	$res = $dbr->query($sql);

	// process the data
	if ($dbr->numRows($res)) {
	    $idLast = 0;
	    $out = $this->RenderStart();
	    while ( $row = $dbr->fetchObject($res) ) {
		$sPgTitle = $row->page_title;

		// get the display title (not the wiki-page title) for each Target article

		$arArgs = array($sPgTitle,'?title','link=none');
		list( $oQuery, $oParams ) = SMWQueryProcessor::getQueryAndParamsFromFunctionParams(
		  $arArgs,
		  SMW_OUTPUT_FILE,
		  SMWQueryProcessor::INLINE_QUERY,
		  TRUE);	// treat as if #show (rather than #ask)
		$sDiTitle = SMWQueryProcessor::getResultFromQuery(
		  $oQuery,
		  $oParams,
		  SMW_OUTPUT_WIKI,
		  SMWQueryProcessor::INLINE_QUERY
		  );

		if (empty($sDiTitle)) {
		    $wtDiTitle = "[[$sPgTitle]]";
		} else {
		    $wtDiTitle = "[[$sPgTitle|$sDiTitle]]";
		}
		$out .= '<li>'
		  .$this->Parse_WikiText("<b>{{#show:$sPgTitle|?author}}</b>").' '
		  .$this->Parse_WikiText("({{#show:$sPgTitle|?year}})").' '
		  .$this->Parse_WikiText($wtDiTitle);
	    }
	    $out .= $this->RenderStop();
	} else {
	    $out = 'No targets found!';
	}
	return $out;
    }
    // DEPRECATED
    protected function GetAllResponses() {
	$sql = 'SELECT * FROM'
	  .' categorylinks AS cl'
	  .' LEFT JOIN page AS p'
	  .' ON cl_from=page_id'
	  .' WHERE (cl_to="Specs/response");';

	// get a database connection object
	$dbr = wfGetDB( DB_SLAVE );
	// execute SQL and get data
	$res = $dbr->query($sql);

	return $res;
    }
    /*----
      ACTION: Lists all responses in summary form (multiline)
    */
    public function w3f_List_Responses_summary(array $iArgs) {
	$res = $this->GetAllResponses();

	// process the data
	$dbr = wfGetDB( DB_SLAVE );
	if ($dbr->numRows($res)) {
	    $idLast = 0;
	    while ( $row = $dbr->fetchObject($res) ) {
		$arOut[] = $this->RenderSummary_forResponse($row);
	    }

	    $cntRows = count($arOut);
	    $intCols = clsArray::Nz($iArgs,'cols');
	    if ($intCols) {
		$cntSplit = (int)$cntRows/$intCols;
		$didSplit = FALSE;
	    } else {
		$cntSplit = 0;
		$didSplit = TRUE;
	    }

	    //$out = '__NOEDITSECTION__';	// don't show edit links - is this actually needed now?
	    $txtHdrOld = NULL;
	    $idxRow = 0;
	    $out = '';
	    foreach ($arOut as $arRow) {
		$idxRow++;
		if (($idxRow > $cntSplit) && !$didSplit) {
		    $out .= "\n| valign=top width=50% |";
		    $didSplit = TRUE;
		}

		$txtHdr = $arRow['hdr'];
		if ($txtHdr != $txtHdrOld) {
		    $out .= "\n===$txtHdr===\n";
		    $txtHdrOld = $txtHdr;
		}

		$out .= '<p>'.$arRow['wt'].'</p>';
		//$out .= $arRow['debug'];
	    }
	} else {
	    $out = 'No responses found!';
	}
	$wtPfx = clsArray::Nz($iArgs,'pfx');
	$wtSfx = clsArray::Nz($iArgs,'sfx');
	$wtOut = $this->Parse_WikiText($wtPfx.$out.$wtSfx);	// parse it all together

	return $wtOut;
	//return $out;
    }
    /*----
      INPUT: iRow = object whose fields are taken from the response page data
      TODO: make this static or something
    */
    private function RenderSummary_forResponse($iRow) {
	$strPTitle = $iRow->page_title;

	$arOut['debug'] = '<pre>'.print_r($iRow,TRUE).'</pre>';

	$smoTitle = new PsyCrit_Page($this);
	$smoTitle->Use_Title_Named($strPTitle);

	$txtDate = $smoTitle->GetPropVal('_dat');
	$dtDate = strtotime($txtDate);
	$wtDate = date('Y/m/d',$dtDate);

	$strDTitle = DBkeyToDisplay($smoTitle->GetPropVal('Title'));
	$wtLead =  $smoTitle->GetPropVal('Lead-in');

	$wtCred =  $smoTitle->GetPropVal('Author/ref');

	$wtOut = "{{faint|$wtDate}} '''$strDTitle''' ''$wtLead'' [[$strPTitle|$wtCred]]";

	// TODO: media (downloads)

	$arOut['wt'] = $wtOut;
	$arOut['hdr'] = $smoTitle->GetPropVal('Listing section');
	return $arOut;
    }
    // development version for trying to figure out SMW data schema
    public function w3f_List_Responses_dev() {
/*
	global $smwgQFeatures, $smwgQDefaultNamespaces;

	$qp = new SMWQueryParser( $smwgQFeatures );
	$qp->setDefaultNamespaces( $smwgQDefaultNamespaces );
*/

/*
	$soQuery = SMWQueryProcessor::createQuery('[[specs type::target]]'
		array $  	params,
		$  	context = self::INLINE_QUERY,
		$  	format = '',
		array $  	extraprintouts = array()
	)
*/
//	$dbr =& wfGetDB( DB_SLAVE );
//	$db = new csSMWData($dbr);
	$db = $this->Engine();

	$rs = $db->GetPages_forPropVal('Specs_type','Target');

	if ($rs->HasRows()) {
	    $out = 'Rows found...';
	    while ($rs->NextRow()) {
		$out .= '<pre>'.print_r($rs->Values(),TRUE).'</pre>';
	    }
	} else {
	    $out = 'No data';
	}

	return $out;
    }

    // INTERNAL FUNCTIONS
/*
    protected function RenderStart() {
	return '';
    }
    protected function RenderStop() {
	return '';
    }
*/
    protected function RenderLine($iTitle,array $arProps=NULL) {
	throw new exception('What calls this?');

	$objTitle = Title::newFromID($iTitle);

	$out = '{{faint|'.respDate.'}} <b>'.respTitle.'</b> <i>'.respSnip.'</i> [['.resp_pg_title.'|'.respRef.']]';

/*
  <let name=respKey copy=target />
  <let name=respKey append>/</let>
  <let name=respKey append copy=respDate />
  <let name=respKey append>_</let>
  <let name=respKey append copy=respRef />

  <call listMedia iRespKey=$respKey />

  <let name=arrKey copy=respDate />
  <let name=arrKey append copy=idx /> <!-- so we don't get two identical keys -->
  <let name=hdrArr index=$arrKey copy=respHdln />
  <let name=outArr index=$arrKey copy=out />
  <let name=outArr index=$arrKey append> </let>
  <let name=outArr index=$arrKey append copy=respMedia />

	if (is_object($objTitle)) {
	    $strTitle = $objTitle->getText();
	    $urlMain = $objTitle->getLinkUrl();
	    //$objTalk = $objTitle->getTalkPage();
	    //$urlTalk = $objTalk->getLinkUrl();
	    if (array_key_exists('download-links',$arProps)) {
		$htLine = '<a title="lyrics and other data" href="'.$urlMain.'">'.$strTitle.'</a>';
		$htLine .= '</td><td>';
		$strLinks = $this->Parse_WikiText($arProps['download-links']);
		$htLine .= $strLinks;
	    } else {
		$htLine = '<a title="summary and index data (this needs to be updated)" href="'.$urlMain.'">'.$strTitle.'</a>';
	    }
	} else {
	    $htLine = 'No page for ID='.$idTitle;
	}
	$out .= $htLine;
	$out .= '</td></tr>';
	return $out;
*/
    }
}

class PsyCrit_Data extends fcDataConn_SMW {
    /*----
      RETURNS: short list of responses to the given keyname
	or NULL if none found.
    */
    public function RenderResponsesFor($iKeyname) {
	$arResps = $this->GetPages_forPropVal('Responds_to',$iKeyname);
	$htResps = NULL;
	if (is_array($arResps)) {
	    foreach ($arResps AS $id_smw => $objMTitle) {
		$txtMTitle = $objMTitle->TitleShown();
		$txtMFTitle = $objMTitle->TitleFull();

		$strCite = $objMTitle->GetPropVal('Cite/author');
		if ($strCite == '') {
		    $strCoOf = $objMTitle->GetPropVal('Content_of');
		    if ($strCoOf != '') {
			$strFmt = $objMTitle->GetPropVal('Format');
			$htLink =
			  '[[media:'.$txtMTitle.'|'.$strFmt.']]'
			  .'<sup>[[:'.$txtMFTitle.'|i]]</sup>';
		    } else {
			// can't find any tidy way to cite this page, so just use MW title for now:
			$htLink = '[[:'.$txtMFTitle.']]';
		    }
		} else {
		    $htLink = '[['.$txtMFTitle.'|'.$strCite.']]';
		}

		$htResps .= ' '.$htLink;
	    }
	}
	return $htResps;
    }
}

class PsyCrit_Page extends w3smwPage {
    protected $strKey;

    public function Keyname() {
	if (empty($this->strKey)) {
	    $this->strKey = $this->GetPropVal('Keyname');
	}
	return $this->strKey;
    }
    public function RenderBit_Keyname() {
	$strKey = $this->Keyname();
	$out = '__NOEDITSECTION__<p style="float:right;"><small>{{faint|<b>key</b>: '.$strKey.'}}</small></p>';
	return $out;
    }
    public function Render_ResponseHeader() {
	$txtPTFull = $this->GetPropVal('title');
	$txtPTShort = $this->GetPropVal('Title/short');
	if ($txtPTShort == '') {
	    $txtPTHdr = $txtPTFull;
	} else {
	    $txtPTHdr = $txtPTShort;
	}
	$htTarget = $this->GetPropVal('target');	// TODO: handle multiple targets, link to target

	$wtHide =
	  '[[page type::specs]]'
	  .'[[specs type::response]]'
	  .'[[format version::3]]'
	  .'[[category:specs/response]]';

	$htAuthors = $this->GetPropLinks('author');
	$htAuthAffil = $this->GetPropLinks('Author/affil');
	if ($htAuthAffil != '') {
	    $htAuthors .= ', '.$htAuthAffil;
	}

	$htDate = $this->GetPropLinks('Date');
	$txtLead = $this->GetPropVal('lead-in');
	$htRespTo = $this->Render_Targets_forThis_ref();
	$htMedia = '';	// to be written

	if (stristr($txtPTHdr,$this->TitleShown()) === FALSE) {
	    $wtShow = "<h1>$txtPTHdr</h1>";
	} else {
	    $wtShow = NULL;
	}

	$htTopix = $this->GetPropLinks('Topic');

/*
	$strKey = $smoATitle->GetPropVal('Keyname');
	$out .= '__NOEDITSECTION__<p style="float:right;"><small>{{faint|<b>key</b>: '.$strKey.'}}</small></p>';
*/
	$wtShow .= $this->RenderBit_Keyname();
	$strKey = $this->Keyname();

	$arMedia = $this->Engine()->GetPages_forPropVal('Content_of',$strKey);
	$htMedia = NULL;
	if (is_array($arMedia)) {
	    foreach ($arMedia AS $objMTitle) {
		if ($objMTitle->MW_Object()->getNamespace() == NS_FILE) {
		    $txtMTitle = $objMTitle->TitleShown();
		    $txtMFTitle = $objMTitle->TitleFull();

		    $strFmt = $objMTitle->GetPropVal('Format');
		    $htLink =
		      '[[media:'.$txtMTitle.'|'.$strFmt.']]'
		      .'<sup>[[:'.$txtMFTitle.'|i]]</sup>';

		    $htMedia .= ' '.$htLink;
		} else {
		    // LATER: handle non-FILE pages, if needed
		}
	    }
	}
	$hasMedia = !is_null($htMedia);

	$htResps = $this->Engine()->RenderResponsesFor($strKey);
	$hasResps = !is_null($htResps);

	$hasTarg = !is_null($htRespTo);

	$hasTopix = ($htTopix != '');

	$wtShow .= "\n==Specs==\n"
	  ."\n* '''Title''': ''$txtPTFull''"
	  ."\n* '''Author''': $htAuthors"
	  ."\n* '''Date''': $htDate";
	if ($hasTopix) {
	  $wtShow .=
	  "\n* '''Topics''': $htTopix";
	}
	if ($hasTarg) {
	  $wtShow .=
	  "\n* '''Response to''': $htRespTo";
	}
	if ($hasResps) {
	  $wtShow .=
	  "\n* '''Further responses''': $htResps";
	}
	if ($hasMedia) {
	    $wtShow .=
	  "\n* '''Download''': [$htMedia ]";
	}
	if ($hasMedia) {
	    // if there's no media, then presumably we have the full text -- so no need for preview
	    if ($txtLead != '') {
		$wtShow .= "\n==Preview==\n$txtLead";
		$wtShow .= " <span style='white-space: nowrap;'>'''more''': [$htMedia ]</span>";
	    }
	}

	$out = array(
	  'show'	=> $wtShow,
	  'hide'	=> $wtHide);
	return $out;
    }
    /*----
      ACTION: list all articles Targeted by this page
    */
    public function Render_Targets_forThis_ref() {

	// 1. Get targets list
	$arTarg = $this->GetPropVals('Responds_to');

	// 2. For each target (keyname), generate link to the corresponding page
	$objTTitle = new PsyCrit_Page($this->Engine());
	if (count($arTarg) > 0) {
	    $out = '';
	    foreach ($arTarg AS $id => $keyname) {
		$sqlKeyname = $this->Engine()->SafeParam($keyname);
		$ar = $this->Engine()->GetPages_forPropVal('Keyname',$sqlKeyname);
		if (count($ar) > 0) {
		    if (count($ar) == 1) {
			// get object for Target page
			$objTPage = array_shift($ar);	// load first-and-only title
			$strTitle = $objTPage->TitleKey();
			$strRef = $objTPage->GetPropVal('Cite/author');
			if ($strRef == '') {
			    $wtTLink = '[['.$strTitle.'|needs cite/author]]';
			} else {
			    $wtTLink = '[['.$strTitle.'|'.$strRef.']]';
			}
			$outRow = $wtTLink;
		    } else {
			$outRow = $keyname.' is used <font color=red>'.count($ar).'</font> times!';
		    }
		} else {
		    $outRow = '<font color=red>?</font>'.$keyname;
		}
		$out .= ' '.$outRow;
	    }
	} else {
	    $out = NULL;
	}
	return $out;
    }
}

new w3tpl_module_PsyCrit();	// class will self-register
