<?php namespace w3tpl;
/*
  PURPOSE: media link-listing functions for w3tpl
  HISTORY:
    2011-10-16 w3tpl code started to get too ugly, so pushing out some functionality into callable modules.
    2011-10-28 adapting filed-links.php to media-links.php
    2012-08-07 started adapting for PsyCrit
    2016-03-17 changed "=& wfGetDB()" to "= wfGetDB()" to remove strict-mode errors
    2018-01-28
      fixed lots of stuff to work with current Ferreteria
      using database wrapper instead of trying to inherit Connection class
*/
xcModule::LoadModule('filed-links');	// defines xcModule_FiledLinks

$csModuleClass = 'xcModule_PsyCrit';
class xcModule_PsyCrit extends xcModule_FiledLinks {

    // ++ CLASSES ++ //
      
    // OVERRIDE
    protected function DataHelperClass() {
	return __NAMESPACE__.'\\xcPsyCrit_DataHelper';
    }

    // -- CLASSES -- //
    // ++ FRAMEWORK ++ //
    
    // OVERRIDE
    private $oDBHelp = NULL;
    // PUBLIC because this is a service provided to the Page class
    public function GetDataHelper() {
	if (is_null($this->oDBHelp)) {
	    $sClass = $this->DataHelperClass();
	    $this->oDBHelp = new $sClass(\fcApp::Me()->GetDatabase());
	}
	return $this->oDBHelp;
    }
    
    // -- FRAMEWORK -- //
    // ++ TABLES ++ //
    
    protected function ResponseQuery() {
	return $this->GetDatabase()->MakeTableWrapper(__NAMESPACE__.'\\fctqPsyCritResponses');
    }

    // -- TABLES -- //
    // ++ SQL: READ ++ //

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

    // -- SQL: READ -- //
    // ++ OUTPUT ++ //

    /*----
      INPUT: iRow = object whose fields are taken from the response page data
      TODO: make this static or something
    */
    private function RenderSummary_forResponse($iRow) {
	$strPTitle = $iRow->page_title;

	$arOut['debug'] = '<pre>'.print_r($iRow,TRUE).'</pre>';

	$smoTitle = new xcPsyCrit_Page($this);
	$smoTitle->Use_Title_Named($strPTitle);

	$txtDate = $smoTitle->GetPropertyValue('_dat');
	$dtDate = strtotime($txtDate);
	$wtDate = date('Y/m/d',$dtDate);

	$strDTitle = DBkeyToDisplay($smoTitle->GetPropertyValue('Title'));
	$wtLead =  $smoTitle->GetPropertyValue('Lead-in');

	$wtCred =  $smoTitle->GetPropertyValue('Author/ref');

	$wtOut = "{{faint|$wtDate}} '''$strDTitle''' ''$wtLead'' [[$strPTitle|$wtCred]]";

	// TODO: media (downloads)

	$arOut['wt'] = $wtOut;
	$arOut['hdr'] = $smoTitle->GetPropertyValue('Listing section');
	return $arOut;
    }
    
    // -- OUTPUT -- //
    // ++ TAG API ++ //
    
    /*----
      TODO: Move most of this code into the PsyCrit_Page class
    */
    public function TagAPI_Show_Target_Page() {
	$wtOut =
	  '[[page type::specs]]'
	  .'[[specs type::target]]'
	  .'[[format version::3]]'
	  .'[[category:specs/target]]';
	$this->Parse_WikiText($wtOut);	// parse but don't show

	$smoTitle = new xcPsyCrit_Page($this);
	$smoTitle->Use_GlobalTitle();

	$out = $smoTitle->RenderBit_Keyname();

	$txtDiTitle = DBkeyToDisplay($smoTitle->GetPropertyValue('title'));
	$htAuthor = $smoTitle->GetPropLinks('Author');
	$htCiteSrc = $smoTitle->RenderPropertyValues('cite/source');
	$htYear = $smoTitle->GetPropLinks('year');
	$wtAbstract = $smoTitle->GetPropertyValue('abstract');

	$strKey = $smoTitle->Keyname();
	$htResps = $this->GetDataHelper()->RenderResponsesFor($strKey);


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
    public function TagAPI_Show_Response_Header() {
	$smoATitle = new xcPsyCrit_Page($this);
	$smoATitle->Use_GlobalTitle();

	$ar = $smoATitle->Render_ResponseHeader();

	$this->Parse_WikiText($ar['hide']);		// render for tags, but don't show
	return $this->Parse_WikiText($ar['show']);	// stuff to actually show
    }
    /*----
      ACTION: list all responses to the current page
      ASSUMES: current page is a Target page
    */
    public function TagAPI_List_Responses_toThis_summary() {
	throw new exception('Function not defined yet');
    }
    /*----
      ACTION: list all articles Targeted by this page
    */
    public function TagAPI_List_Targets_forThis_ref() {

	// 1. Get targets list
	$smoCTitle = new PsyCrit_Page($this->Engine());
	$smoCTitle->Use_GlobalTitle();

	return $smoCTitle->Render_Targets_forThis_ref();
    }
    /*----
      ACTION: list ALL Target articles, with a formatted description of each.
    */
    public function TagAPI_List_Targets_summary() {
	$sql = $this->GetSQL_for_Targets();

	// get a database connection object
	//$dbr =& wfGetDB( DB_SLAVE );
	$dbr = static::GetDatabase_MW();
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
    /*----
      ACTION: Lists all responses in summary form (multiline)
    */
    public function TagAPI_List_Responses_summary(array $arArgs) {
	$rs = $this->GetAllResponses();

	// process the data

	if ($rs->HasRows()) {
	    $idLast = 0;
	    while ($rs->NextRow()) {
		$arOut[] = $rs->RenderSummary();
	    }
	    $nRows = count($arOut);
	    $nCols = \fcArray::Nz($arArgs,'cols');
	    if ($nCols) {
		$nSplit = (int)$nRows/$nCols;
		$didSplit = FALSE;
	    } else {
		$nSplit = 0;
		$didSplit = TRUE;
	    }

	    $txtHdrOld = NULL;
	    $idxRow = 0;
	    $out = '';
	    foreach ($arOut as $arRow) {
		$idxRow++;
		if (($idxRow > $nSplit) && !$didSplit) {
		    $out .= "\n| valign=top width=50% |";
		    $didSplit = TRUE;
		}

		$txtHdr = $arRow['hdr'];
		if ($txtHdr != $txtHdrOld) {
		    $out .= "\n===$txtHdr===\n";
		    $txtHdrOld = $txtHdr;
		}

		$out .= '<p>'.$arRow['wt'].'</p>';
	    }
	} else {
	    $out = 'No responses found!';
	}
	    
	$wtPfx = \fcArray::Nz($arArgs,'pfx');
	$wtSfx = \fcArray::Nz($arArgs,'sfx');
	$wtOut = $this->Parse_WikiText($wtPfx.$out.$wtSfx);	// parse it all together

	return $wtOut;
    }

    // -- TAG API -- //

    // DEPRECATED
    protected function GetAllResponses() {
	$t = $this->ResponseQuery();
	$rs = $t->SelectRecords('cl_to="Specs/response"');
	/*
	$sql = 'SELECT * FROM'
	  .' categorylinks AS cl'
	  .' LEFT JOIN page AS p'
	  .' ON cl_from=page_id'
	  .' WHERE (cl_to="Specs/response");';
	// get a database connection object
	$dbr = static::GetDatabase_MW();
	// execute SQL and get data
	$res = $dbr->query($sql);

	return $res;
	*/
	return $rs;
    }

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

class xcPsyCrit_DataHelper extends \fcDataConn_SMW {

    // ++ SETUP ++ //

    public function __construct(\fcDataConn_SMW $oConn) {
	$this->SetDatabase($oConn);
    }
    private $oConn;
    protected function SetDatabase(\fcDataConn_SMW $oConn) {
	$this->oConn = $oConn;
    }
    protected function GetDatabase() {
	return $this->oConn;
    }

    // -- SETUP -- //
    // ++ DATA READ ++ //

    /*----
      RETURNS: short list of responses to the given keyname
	or NULL if none found.
    */
    public function RenderResponsesFor($iKeyname) {
	$arResps = $this->GetDatabase()->GetTitleObjects_forPropertyValue('Responds_to',$iKeyname);
	$htResps = NULL;
	if (is_array($arResps)) {
	    $ofTitle = new \fcPageData_SMW();
	    foreach ($arResps AS $id_smw => $ofTitle) {
		//$omwTitle = \Title::makeTitle($arTitle['s_namespace'],$arTitle['s_title']);
		//$ofTitle->SetTitleObject($omwTitle);
		$txtMTitle = $ofTitle->TitleShown();
		$txtMFTitle = $ofTitle->TitleFull();

		$strCite = $ofTitle->GetPropertyValue('Cite/author');
		if ($strCite == '') {
		    $strCoOf = $ofTitle->GetPropertyValue('Content_of');
		    if ($strCoOf != '') {
			$strFmt = $ofTitle->GetPropertyValue('Format');
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

    // ++ DATA READ ++ //
}

class xcPsyCrit_Page extends \fcPageData_SMW {

    private $strKey = NULL;
    public function Keyname() {	// TODO: split into Set/Get
	if (is_null($this->strKey)) {
	    $this->strKey = $this->GetPropertyValue('Keyname');
	}
	return $this->strKey;
    }
    public function RenderBit_Keyname() {
	$strKey = $this->Keyname();
	$out = '__NOEDITSECTION__<p style="float:right;"><small>{{faint|<b>key</b>: '.$strKey.'}}</small></p>';
	return $out;
    }
    public function Render_ResponseHeader() {
	$txtPTFull = $this->GetPropertyValue('title');
	$txtPTShort = $this->GetPropertyValue('Title/short');
	if ($txtPTShort == '') {
	    $txtPTHdr = $txtPTFull;
	} else {
	    $txtPTHdr = $txtPTShort;
	}
	$htTarget = $this->GetPropertyValue('target');	// TODO: handle multiple targets, link to target

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
	$txtLead = $this->GetPropertyValue('lead-in');
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

	$db = $this->GetDatabase();

	$arMedia = $db->GetTitleObjects_forPropertyValue('Content_of',$strKey);
	$htMedia = NULL;
	if (is_array($arMedia)) {
	    foreach ($arMedia AS $objMTitle) {
		if ($objMTitle->GetTitleObject()->getNamespace() == NS_FILE) {
		    $txtMTitle = $objMTitle->TitleShown();
		    $txtMFTitle = $objMTitle->TitleFull();

		    $strFmt = $objMTitle->GetPropertyValue('Format');
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

	$htResps = $this->GetDataHelper()->RenderResponsesFor($strKey);
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
	In other words, find all pages to which this is a response.
	Normally, that will be just one page.
	More specifically, get the value(s) for the "responds to" property,
	  then find each page where Keyname is set to that value,
	  then render each title nicely in a list.
    */
    public function Render_Targets_forThis_ref() {
	$db = $this->GetDatabase();

	// 1. Get targets list
	$arTarg = $this->GetPropertyValues('Responds_to');

	// 2. For each target (keyname), generate link to the corresponding page
	//$objTTitle = new PsyCrit_Page($db);
	if (count($arTarg) > 0) {
	    $out = '';
	    foreach ($arTarg AS $n => $sKey) {
		//$sqlKeyname = $db->SanitizeString($sKey);	// don't quote
		$ar = $db->GetTitleObjects_forPropertyValue('Keyname',$sKey);
		if (count($ar) > 0) {
		    if (count($ar) == 1) {
			// get object for Target page
			$oTPage = array_shift($ar);	// load first-and-only title
			$sTitle = $oTPage->TitleKey();
			$sRef = $oTPage->GetPropertyValue('Cite/author');
			if ($sRef == '') {
			    $wtTLink = '[['.$sTitle.'|needs cite/author]]';
			} else {
			    $wtTLink = '[['.$sTitle.'|'.$sRef.']]';
			}
			$outRow = $wtTLink;
		    } else {
			// each article should have a unique keyname
			$outRow = $sKey.' is used in <font color=red>'.count($ar).'</font> pages!';
		    }
		} else {
		    $sWhere = __FILE__.' line '.__LINE__;
		    $outRow = "<font color=red title='this should not happen - $sWhere'>?[</font>".$sKey.'<font color=red>]</font>';
		}
		$out .= ' '.$outRow;
	    }
	} else {
	    $out = NULL;
	}
	return $out;
    }
}

class fctqPsyCritResponses extends \fcTable_wSource_wRecords {
    use \ftReadableTable, \ftSelectable_Table;

    // ++ SETUP ++ //

    // CEMENT
    protected function SingularName() {
	return __NAMESPACE__.'\\fcrqPsyCritResponse';
    }

    // ++ SETUP ++ //
    // ++ SQL: DATA READ ++ //

    // OVERRIDE
    protected function SourceString_forSelect() {
	return 	  
	  ' categorylinks AS cl'
	  .' LEFT JOIN page AS p'
	  .' ON cl_from=page_id'
	  ;
    }
    
    // -- SQL: DATA READ -- //

}
class fcrqPsyCritResponse extends \fcDataRecord {

    // ++ OUTPUT ++ //

    public function RenderSummary() {
	$arRow = $this->GetFieldValues();
	$strPTitle = $this->GetFieldValue('page_title');

	$arOut['debug'] = '<pre>'.print_r($arRow,TRUE).'</pre>';

	$smoTitle = new xcPsyCrit_Page();
	$smoTitle->Use_Title_Named($strPTitle);

	$txtDate = $smoTitle->GetPropertyValue('_dat');
	$dtDate = strtotime($txtDate);
	$wtDate = date('Y/m/d',$dtDate);

	$strDTitle = DBkeyToDisplay($smoTitle->GetPropertyValue('Title'));
	$wtLead =  $smoTitle->GetPropertyValue('Lead-in');

	$wtCred =  $smoTitle->GetPropertyValue('Author/ref');

	$wtOut = "{{faint|$wtDate}} '''$strDTitle''' ''$wtLead'' [[$strPTitle|$wtCred]]";

	// TODO: media (downloads)

	$arOut['wt'] = $wtOut;
	$arOut['hdr'] = $smoTitle->GetPropertyValue('Listing section');
	return $arOut;
    }

    // -- OUTPUT -- //

}