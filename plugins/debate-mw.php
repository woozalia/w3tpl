<?php
/*
  PURPOSE: structured debate functions for w3tpl, using only native MediaWiki data functions
  HISTORY:
    2015-09-10 started
    2015-09-20 census is having problems, so merging status class into point class in hopes that it can be straightened out
*/

//clsLibrary::Load_byName('ferreteria.db.2');
//clsLibrary::Load_byName('ferreteria.mw.2');

new w3tpl_module_Debate();	// class will self-register

// PATHS

define('KWP_REL_ICONS','custom/debate/');	// path from wiki root to debate icons folder

// ICONS

define('KFN_ICON_PRO_POINT','Arrow-button-up-20px.png');
define('KFN_ICON_CON_POINT','Arrow-button-dn-20px.png');
define('KFN_ICON_INF_POINT','Arrow-button-i-20px.png');
define('KFN_ICON_AND_ATTRIB','Button-ampersand.20px.png');	// used when a point requires all subpoints to be true, instead of only one

// URL QUERY PARAMETER NAMES

define('KURL_QUERY_DPAGE_ID','id');
define('KURL_QUERY_DPOINT_TYPE','type');

// TAG NAMES

define('KCAT_DEBATE_POINT','Debate_point');
define('KPROP_DPOINT_SUMMARY','debate-point-summary');	// one-line summary of point
define('KPROP_DPOINT_DETAILS','debate-point-details');	// additional details (optional)
define('KPROP_DPOINT_PARENT','debate-point-parent');	// name of parent page
define('KPROP_DPOINT_TYPE','debate-point-type');	// pro, con, inf
  define('KVAL_DPOINT_TYPE_PRO','pro');				// pro: point supports its parent
  define('KVAL_DPOINT_TYPE_CON','con');				// con: point opposes its parent
  define('KVAL_DPOINT_TYPE_INF','inf');				// inf: informal/informational comment
define('KPROP_DPOINT_ATTRIB_AND','debate-point-req-all');  // if 1, point is false if ANY support points are false

class w3tpl_module_Debate extends w3tpl_module {

    // ++ SETTINGS ++ //

    // wiki page name for new point form (needed for generating response links)
    static public function Page_forNewPointForm($wp=NULL) {
	static $wpSet = 'debate/forms/point';
	if (!is_null($wp)) {
	    $wpSet = $wp;
	}
	return $wpSet;
    }

    // -- SETTINGS -- //
    // ++ APP FRAMEWORK ++ //

    // PUBLIC so DPoint class can access it
    private $oDB;
    public function Engine() {
	if (empty($this->oDB)) {
	    $dbr = wfGetDB( DB_SLAVE );
	    $oDB = new wcData($dbr);
	    $this->oDB = $oDB;
	}
	return $this->oDB;
    }

    // -- APP FRAMEWORK -- //
    // ++ W3TPL API ++ //

    /*----
      ACTION: Show a list of all debates, with summaries
    */
    protected function w3f_ShowDebates() {
	$sql = $this->SQLfor_Pages_ofDebatePoints_withNoParent();	// show only debate roots

	$db = $this->Engine();
	$rs = $db->Recordset($sql);

	if ($rs->HasRows()) {
	    $out = "\n<ul class=debate-list>";
	    while ($rs->NextRow()) {
		$sPage = $rs->FieldValue('page_title');
		$idPage = $rs->FieldValue('page_id');

		$mwoTitle = Title::newFromID($idPage);
		$url = $mwoTitle->getLocalURL();

		$arProps = $this->Engine()->Props_forPage_arr($idPage);
		$sSumm = $arProps[KPROP_DPOINT_SUMMARY];
		$out .= "\n<li><a href='$url'>$sSumm</a></li>";
	    }
	    $out .= "\n</ul>";
	    return $out;
	} else {
	    return '<i>There are no debates yet.</i>';
	}
    }
    /*----
      ACTION: Display the debate data for the current page
      ASSUMES: page at least has a Summary
    */
    protected function w3f_ShowDebateData() {
	global $wgTitle;
	$wdoThis = $this->DebatePointObject($wgTitle);
	return $wdoThis->StandardDisplay();
    }
    protected function w3f_ShowPointForm(array $arArgs) {
	$oReq = clsHTTP::Request();
	$idPage = $oReq->GetInt(KURL_QUERY_DPAGE_ID);
	$sType = $oReq->GetText(KURL_QUERY_DPOINT_TYPE);

	self::SaveExecArgs($arArgs,array("template-page","new-page-name"));

	$wdoThis = $this->DebatePointObject_fromPageID($idPage);
	return $wdoThis->PointEntryForm($idPage,$sType);
    }
    protected function w3f_ShowDebateForm(array $arArgs) {
	global $wgUser;

	$mwoMakePage = Title::newFromText('Special:MakePage');
	$urlMakePage = $mwoMakePage->getLocalURL();

//	$sTpltName = self::Page_forNewPointTemplate();
//	$sTitleTplt = self::Name_forNewPointPage();
	self::SaveExecArgs($arArgs,array("template-page","new-page-name"));
	$wpTitleTplt = self::Template_forPageTitle();
	$wpTpltTitle = self::Title_forTemplatePage();

	$sUser = $wgUser->getTitleKey();	// we could also use ID, for slightly more anonymity

	$htForm = <<<__END__

<form action="$urlMakePage" target=_blank method=POST>
  <input type=hidden name="!TITLETPLT"	value="$wpTitleTplt">
  <input type=hidden name="!TPLTPAGE"	value="$wpTpltTitle">
  <input type=hidden name="!TIMEFMT"	value="Y/m/d/Hi">

  <input type=hidden name="!TPLTSTART"	value="@@START@@">
  <input type=hidden name="!TPLTSTOP"	value="@@STOP@@">
  <input type=hidden name="!IMMEDIATE"	value="1">

  <input type=hidden name="user"	value="$sUser">

  <table>
    <tr><td align=right><b>Summary</b>:</td><td><input name=point-summary size=60></td></tr>
    <tr><td align=right><b>Main category</b>:</td><td><input name=point-topic size=20></td></tr>
    <tr><td colspan=2><b>Additional explanation</b>:</td></tr>
    <tr><td colspan=2><textarea name=point-details rows=4 cols=80></textarea></td></tr>
    <tr><td colspan=2 align=center>
      <input type=submit name=btnSave value="Save">
      <input type=reset name=btnReset value="Clear">
    </td></tr>
  </table>
</form>
__END__;
	return $htForm;
    }

    // -- W3TPL API -- //
    // ++ STATIC: INPUT ++ //

    static protected function SaveExecArgs(array $arArgs, array $arReq=NULL) {
	if (!is_null($arReq)) {
	    // check for required arguments
	    $sErr = NULL;
	    foreach($arReq as $sArg) {
		if (!array_key_exists($sArg,$arArgs)) {
		    $sErr .= '<b>Error</b>: The "'.$sArg.'" parameter needs to be set.<br>';
		}
	    }
	    if (!is_null($sErr)) {
		return $sErr;
	    }
	}

	$val = clsArray::Nz($arArgs,'new-page-name');
	self::Template_forPageTitle($val);

	$val = clsArray::Nz($arArgs,'template-page');
	self::Title_forTemplatePage($val);
    }

    static public function Template_forPageTitle($wp=NULL) {
	static $wpSet = NULL;

	if (!is_null($wp)) {
	    $wpSet = $wp;
	}
	return $wpSet;
    }
    // maybe this should be Title_forPageContentTemplate(), but let's get things working first
    static public function Title_forTemplatePage($wp=NULL) {
	static $wpSet = NULL;

	if (!is_null($wp)) {
	    $wpSet = $wp;
	}
	return $wpSet;
    }

    // -- STATIC: INPUT -- //
    // ++ LOOKUP ++ //

    protected function DebatePointObject(Title $mwoTitle) {
	return new wcDebatePoint($this,$mwoTitle);
    }
    // PUBLIC so Debate Point objects can use them to fetch relative Debate Point objects
    public function DebatePointObject_fromPageID($idPage) {
	if (empty($idPage)) {
	    $wdoThis = new wcDebatePoint_blank();
	} else {
	    $mwoTitle = Title::newFromID($idPage);
	    $wdoThis = $this->DebatePointObject($mwoTitle);
	}
	return $wdoThis;
    }
    public function DebatePointObject_fromName($sTitle) {
	$mwo = Title::newFromText($sTitle);
	if (is_null($mwo)) {
	    return NULL;
	}
	$xo = $this->DebatePointObject($mwo);
	return $xo;
    }

    // -- LOOKUP -- //
    // ++ SQL CALCULATION: PLUGIN-SPECIFIC ++ //

    /*----
      RETURNS: SQL to retrieve a recordset of Debate Point pages
    */
    protected function SQLfor_Pages_ofDebatePoints() {
	return $this->SQLfor_Pages_inCategory(KCAT_DEBATE_POINT);
    }
    /*----
      RETURNS: SQL to retrieve a recordset of Debate Point pages with no parent point.
    */
    protected function SQLfor_Pages_ofDebatePoints_withNoParent() {
	$sqlWith = $this->SQLfor_Pages_withProperty(KPROP_DPOINT_PARENT);
	$sql = "SELECT p.*"
	  ." FROM (categorylinks AS cl"
	  ." LEFT JOIN page AS p ON cl_from=page_id)"
	  ." LEFT JOIN ($sqlWith) AS pwp ON p.page_id=pwp.page_id"
	  .' WHERE (cl_to="'.KCAT_DEBATE_POINT.'") AND (pwp.page_id IS NULL)'
	  ;
	return $sql;
    }

    // -- SQL CALCULATION: PLUGIN-SPECIFIC -- //
    // ++ SQL CALCULATION: MEDIAWIKI ++ //
    // These should eventually be ported back into Ferreteria (mw/db-conn-mw.php)

    /*----
      RETURNS: SQL to retrieve a list of Pages in the given category
	Categories are linked by name, in case they don't yet have pages.
	Pages are linked by ID.
    */
    protected function SQLfor_Pages_inCategory($sCatName) {
	$sqlName = $this->Engine()->Sanitize_andQuote($sCatName);
	$sql = "SELECT * FROM categorylinks AS cl LEFT JOIN page AS p ON cl_from=page_id WHERE cl_to=$sqlName";
	return $sql;
    }
    /*----
      RETURNS: SQL to retrieve a list of Pages that have the given Property
	FIELDS: Page ID, Property Value
      PUBLIC so Debate Point class can use it
    */
    public function SQLfor_Pages_withProperty($sPropName) {
	$sqlName = $this->Engine()->Sanitize_andQuote($sPropName);
	$sql = "SELECT page_id, pp_value"
	  ." FROM page LEFT JOIN page_props ON pp_page=page_id"
	  ." WHERE (pp_propname=$sqlName)";
	return $sql;
    }

    // ++ RECEIVED FORM DATA ++ //

    /* 2015-09-25 not sure if/why this is needed
    static public function Page_forNewPointForm($wp=NULL) {
	static $wpSet = NULL;
	if (!is_null($wp)) {
	    $wpSet = $wp;
	}
	return $wpSet;
    }
    */
}

class wcData extends fcDataConn_MW {
}

class wcDebatePoint extends clsTreeNode {
    private $woMod;	// main debate module

    // debugging:
    public $id;
    static private $nObjCount=0;

    // ++ SETUP ++ //

    public function __construct(w3tpl_module_Debate $oModule, $vTitle) {
	self::$nObjCount++;
	$this->id = self::$nObjCount;

	$this->woMod = $oModule;
	if (is_a($vTitle,'Title')) {	// $sTitle can be a MediaWiki Title object or a string (title's name)
	    $mwoTitle = $vTitle;
	} else {
	    $mwoTitle = Title::newFromText($vTitle);
	}
	$this->MW_TitleObject($mwoTitle);
	if (!is_object($mwoTitle)) {
	    throw new exception('MediaWiki Title was not set when creating Debate Point object.');
	}
    }

    // -- SETUP -- //
    // ++ FRAMEWORK ++ //

    protected function Module() {
	return $this->woMod;
    }
    protected function Engine() {
	return $this->Module()->Engine();
    }
    private $mwoTitle;
    protected function MW_TitleObject(Title $mwo=NULL) {
	if (!is_null($mwo)) {
	    $this->mwoTitle = $mwo;
	    $this->Name($mwo->getText());
	}
	return $this->mwoTitle;
    }

    // -- FRAMEWORK -- //
    // ++ CALCULATED INFORMATION ++ //

    protected function MWID() {
	return $this->MW_TitleObject()->getArticleID();
    }
    public function LocalURL() {
	return $this->MW_TitleObject()->getLocalURL();
    }
    protected function IsCreated() {
	return ($this->MW_TitleObject()->getArticleID() > 0);
    }

    // -- CALCULATED INFORMATION -- //
    // ++ DATA RECORDS ++ //

    private $rsKids;
    protected function ResponseRecords() {
	if (empty($this->rsKids)) {
	    $sql = $this->SQLfor_ChildrenOf($this->MWID());
	    $db = $this->Module()->Engine();
	    $this->rsKids = $db->Recordset($sql);
	}
	return $this->rsKids;
    }

    // -- DATA RECORDS -- //
    // ++ SQL CALCULATION ++ //

    /*----
      TODO: Find a way to prevent the problem where the parent property's title string doesn't match
	the DBKey (e.g. because it has spaces in it). Maybe we should be using Page IDs throughout.
    */
    protected function SQLfor_ChildrenOf($id) {
	if (empty($id)) {
	    throw new exception('Internal error: trying to retrieve children for page with no ID.');
	}

	// get text for title $id
	$mwoTitle = Title::newFromID($id);
	$sTitle = $mwoTitle->getDBkey();

	$sqlWith = $this->Module()->SQLfor_Pages_withProperty(KPROP_DPOINT_PARENT);
	$sqlTo = KCAT_DEBATE_POINT;

	$sql = "SELECT pp_value, p.*"
	  ." FROM (categorylinks AS cl"
	  ." LEFT JOIN page AS p ON cl_from=page_id)"
	  ." LEFT JOIN ($sqlWith) AS pwp ON p.page_id=pwp.page_id"
	  ." WHERE (cl_to='$sqlTo') AND (pp_value='$sTitle')";

	return $sql;
    }

    // -- SQL CALCULATION -- //
    // ++ CONVENTIONS ++ //

    /*----
      RETURNS: Appropriate rendering of dpoint:
	if it is a root: shows entire debate tree
	if it is a response: shows parent, immediate responses, and links for responding
    */
    public function StandardDisplay() {
	if ($this->HasPointParent()) {
	    return $this->DisplayResponseData();
	} else {
	    return $this->DisplayResponseTree();
	}
    }
    /*----
      RETURNS: Rendering of dpoint's data
      ASSUMES: dpoint has a parent
    */
    protected function DisplayResponseData() {
	$ftSumm = $this->StandardLink();
	$wdoParent = new wcDebatePoint($this->Module(),$this->ParentName());
	$ftParent = $wdoParent->StandardLink();
	$htResponseLinks = $this->RenderLinksToRespond();

	$out =
	  "\n<ul class=debate-point-data>"
	    ."\n  <li class=dpoint-parent><b>Parent</b>: $ftParent</li>"	// later: replace with summary-and-link
	    ."\n  <ul>"
	    ."\n    <li class=dpoint-current><b>This</b>: $ftSumm</li>"
	    ."\n    <ul>"
	    ."\n      ".$this->DisplayResponses()
	    ."\n      <li class=dpoint-action-links>Respond: $htResponseLinks</li>"
	    .$this->RenderDetails()
	    ."\n    </ul>"
	    ."\n  </ul>"
	  ."\n</ul>"
	  ;
	return $out;
    }
    /*----
      ASSUMES: this is a root dpoint
    */
    // TODO: rename to RenderResponseTree()
    protected function DisplayResponseTree() {
	$ftThis = $this->StandardLink().$this->RenderLinksToRespond();
	$out =
	  "\n<ul class=dpoint-response-tree-outer>"
	  ."\n  <li>$ftThis"
	  //."\n  <ul class=dpoint-response-tree-inner>"
	  .$this->RenderDetails()
	  .$this->DisplayResponses(TRUE)
	  //."\n  </ul>"
	  ."\n</li>"
	  ."\n</ul>"
	  ;
	return $out;
    }
    // TODO: rename to RenderResponses()
    protected function DisplayResponses($doTree=FALSE) {
	$ar = $this->Nodes();
	$out = NULL;
	$sLbl = $doTree?'':'<b>Response</b>: ';
	if (count($ar) > 0) {
	    $out .= "\n<ol class=dpoint-responses>";
	    foreach ($ar as $sName => $oKid) {
		$out .= "\n<li class=dpoint-response>$sLbl".$oKid->StandardLink();
		if ($doTree) {
		    $out .= $oKid->DisplayResponses(TRUE);
		}
		$out .= "\n</li>";
	    }
	    $out .= "\n</ol>";
	} else {
	    if ($doTree) {
		$out = NULL;
	    } else {
		$out = "\n<i>no responses yet</i>";
	    }
	}
	return $out;
    }

    protected function PointTypeObject() {
	$sType = $this->PropValue(KPROP_DPOINT_TYPE);
	return wcDebatePointType::Get_fromKey($sType);
    }
    /*----
      RETURNS: Rendering of link to dpoint's page, with summary as link text
    */
    protected function StandardLink() {
	global $wgOut;

	$url = $this->LocalURL();
	$arProps = $this->AllProps_array();
	$sSumm = $arProps[KPROP_DPOINT_SUMMARY];
	$htSumm = $wgOut->parseInline($sSumm,FALSE);
	$htIcon = $this->IconTag();
	if ($this->RequiresAllSupport()) {
	    $htIcon .= wcDebatePointType::IconTag_forAnd();
	}
	$htLine = "<a href='$url'>$htIcon</a> $htSumm";
	$out = $this->RenderStatusWrapper($htLine);

	return $out;
    }
    protected function RenderDetails() {
	global $wgOut;

	$arProps = $this->AllProps_array();
	$sDet = $arProps[KPROP_DPOINT_DETAILS];
	if ($sDet == '') {
	    return NULL;
	} else {
	    $htDet = $wgOut->parseInline($sDet,FALSE);
	    $out =
	    "\n<span class=dpoint-details>$htDet</span>";
	    return $out;
	}
    }
    // RETURNS: complete image tag to use for this Point
    protected function IconTag() {
	if ($this->HasPointParent()) {
	    $out = $this->PointTypeObject()->IconTag();
	} else {
	    $out = NULL;
	}
	return $out;
    }
    protected function RenderLinksToRespond() {
	return wcDebatePointType::ResponseLinks($this->MWID());
    }
    // TODO: Rename PageTitle() -> TitleString_fromID()
    protected function PageTitle($idTitle=NULL) {
	if (is_null($idTitle)) {
	    return 'Help:Page not specified';
	} else {
	    $mwoParent = Title::newFromID($idTitle);
	    return $mwoParent->getFullText();
	}
    }
    /*----
      RETURNS: rendering of the form for entering a new DPoint
      HISTORY:
	2015-09-27 Added "s" to !TIMEFMT because when you're transcribing a debate that has been manually mapped elsewhere,
	  it's all too easy to attempt to create two pages within the same minute.
    */
    public function PointEntryForm($idParent,$sType) {
	global $wgScriptPath,$wgUser;

	$wpTpltTitle = w3tpl_module_Debate::Template_forPageTitle();
	$wpTitleTplt = w3tpl_module_Debate::Title_forTemplatePage();

	$oType = wcDebatePointType::Get_fromKey($sType);
	$sTDesc = $oType->LabelString();
	$htIcon = $oType->IconTag();		// type-icon for the response
	$htSumm = $this->StandardLink();	// link to the parent

	$sParent = $this->PageTitle($idParent);
	$mwoMakePage = Title::newFromText('Special:MakePage');
	$urlMakePage = $mwoMakePage->getLocalURL();

	$sUser = $wgUser->getTitleKey();	// we could also use ID, for slightly more anonymity

	$htForm = <<<__END__

<form action="$urlMakePage" target=_blank method=POST>
  <input type=hidden name="!TITLETPLT"	value="$wpTpltTitle">
  <input type=hidden name="!TPLTPAGE"	value="$wpTitleTplt">
  <input type=hidden name="!TIMEFMT"	value="Y/m/d/His">

  <input type=hidden name="!TPLTSTART"	value="@@START@@">
  <input type=hidden name="!TPLTSTOP"	value="@@STOP@@">
  <input type=hidden name="!IMMEDIATE"	value="1">

  <input type=hidden name="user"	value="$sUser">
  <input type=hidden name="point-parent" value="$sParent">
  <input type=hidden name="point-type"	value="$sType">

  <table>
    <tr><td align=right><b>Point type</b>:</td><td>$sTDesc <i>$htSumm</i></td></tr>
    <tr><td align=right><b>Summary</b>:</td><td>$htIcon<input name=point-summary size=60></td></tr>
    <tr><td colspan=2><input name=point-attr-and type=checkbox> requires all supporting points to be true</tr></tr>
    <tr><td colspan=2><b>Additional explanation</b>:</td></tr>
    <tr><td colspan=2><textarea name=point-details rows=4 cols=80></textarea></td></tr>
    <tr><td colspan=2 align=center><input type=submit name=btnSave value="Save"></td></tr>
  </table>
</form>
__END__;
	return $htForm;
    }

    // -- CONVENTIONS -- //
    // ++ DATA LOOKUP ++ //

    private $arProps;
    protected function AllProps_array() {
	if (empty($arProps)) {
	    $id = $this->MWID();
	    $this->arProps = $this->Engine()->Props_forPage_arr($id);
	}
	return $this->arProps;
    }
    protected function PropValue($sName,$default=NULL) {
	return clsArray::Nz($this->AllProps_array(),$sName,$default);
    }
    protected function PropValue_exists($sName) {
	$arProps = $this->AllProps_array();
	if (is_array($arProps)) {
	    return array_key_exists($sName,$this->AllProps_array());
	} else {
	    return FALSE;
	}
    }

    // -- DATA LOOKUP -- //
    // ++ TREE CALCULATIONS ++ //

    protected function CheckParent() {
	static $isParentChecked = FALSE;
	if (!$isParentChecked) {
	    if ($this->PropValue_exists(KPROP_DPOINT_PARENT)) {
		$sParName = $this->PropValue(KPROP_DPOINT_PARENT);
		$xoParent = $this->Module()->DebatePointObject_fromName($sParName);
		if (is_null($xoParent)) {
		    // fail gracefully if the specified parent page can't be found
		} else {
		    $xoParent->NodeAdd($this);

		    $this->GetParent()->MapTree();
		}
	    } else {
		$this->MapTree();
	    }
	    $isParentChecked = TRUE;
	}
    }
    /*----
      OVERRIDE: need to make sure we've checked the page-property structure, because
	this node might have been created without building the debate tree.
      TODO: This is kind of clumsy -- it's easy to get caught in a recursive loop because
	of needing to do stuff to Parent while in CheckParent. Fix this somehow.
    */
    public function HasPointParent() {
	$this->CheckParent();
	$has = $this->HasParent();
	return $has;
    }
    public function GetPointParent() {
	$this->CheckParent();
	return parent::GetParent();
    }
    protected function ParentName() {
	return $this->GetPointParent()->Name();
    }
    /*----
      ACTION: Load data for all dpoints from here on out to the leaves and
	map them into a tree structure (using clsTreeNode).
      NOTE: This does *not* do any calculations; it just creates the tree in memory.
	See SumToStats() for calculations.
    */
    protected function MapTree() {
	if (!$this->IsCreated()) { return; }	// this happens for unclear reasons
	$rsKids = $this->ResponseRecords();
	if ($rsKids->HasRows()) {
	    while ($rsKids->NextRow()) {
		$idKid = $rsKids->FieldValue('page_id');
		$woKid = $this->Module()->DebatePointObject_fromPageID($idKid);
		$this->NodeAdd($woKid);
	    }
	    $arKids = $this->Nodes();
	    foreach ($arKids as $sName => $oKid) {
		$oKid->MapTree();
	    }
	}
    }

    // -- TREE CALCULATIONS -- //
    // ++ SELF STATUS ++ //

    public function SupportsParent() {
	return ($this->PropValue(KPROP_DPOINT_TYPE) == KVAL_DPOINT_TYPE_PRO);
    }
    public function OpposesParent() {
	return ($this->PropValue(KPROP_DPOINT_TYPE) == KVAL_DPOINT_TYPE_CON);
    }
    public function RequiresAllSupport() {
	$val = $this->PropValue(KPROP_DPOINT_ATTRIB_AND);
	$isOn = ($val == 'on') || ($val != 0);
    	return ($isOn);
    }

    // -- SELF STATUS -- //
    // ++ STATUS CALCULATIONS ++ //

    private $cntAll;		// total number of children
    private $cntActive;		// number of active children
    private $cntSupport;	// number of active supporting immediate children
    private $cntSupportFail;	// number of failed supporting immediate children
    private $cntAgainst;	// number of active contradictory immediate children

    // ACTION: make sure census has been taken
    private $didCensus=FALSE;
    protected function EnsureCensus() {

        if (!$this->didCensus) {
	    $arKids = $this->Nodes();
	    if (count($arKids) > 0) {

		$this->ResetStats();
		foreach ($arKids as $sName => $oKid) {
		    $this->SumToStats($oKid);
		}
		$this->didCensus = TRUE;
	    }
        }
    }
    protected function ResetStats() {
	$this->cntAll = 0;
	$this->cntActive = 0;
	$this->cntSupport = 0;
	$this->cntSupportFail = 0;
	$this->cntAgainst = 0;
    }
    protected function SumToStats(wcDebatePoint $oKid) {
	//$oKid->EnsureCensus();	// this can be removed when not debugging
	$this->cntAll++;
	$isPro = $oKid->SupportsParent();
	if ($oKid->IsActive()) {
	    $this->cntActive++;
	    if ($isPro) {
		$this->cntSupport++;
	    } elseif ($oKid->OpposesParent()) {
		$this->cntAgainst++;
	    }
	} else {
	    if ($isPro) {
		$this->cntSupportFail++;
	    }
	}
	$arCalc = array(
	  'cnt-all'	=> $this->cntAll,
	  'cnt-active'	=> $this->cntActive,
	  'cnt-support'	=> $this->cntSupport,
	  'cnt-supfail'	=> $this->cntSupportFail,
	  'cnt-oppose'	=> $this->cntAgainst,
	  );
//	$this->SaveCalculations($arCalc);
    }
    // PURPOSE: Save the Point's status so other Points can depend on it without having to recalculate the whole tree.
    /* 2015-09-29 This doesn't work using the existing properties code; I don't know why not. Insufficient documentation.
    protected function SaveCalculations(array $ar) {
	global $wgParser;

	$w3oProps = new clsPageProps($wgParser,$this->MW_TitleObject());
	echo "SAVING PROPERTIES FOR PAGE [".$this->MW_TitleObject()->getFullText().']<br>';
	foreach ($ar as $key => $val) {
	    $sPropName = "#debate-point-$key";	// add customary prefix for calculated properties
	    $sValSaved = $w3oProps->LoadVal($sPropName);
	    if ($sValSaved != $val) {
		$w3oProps->SaveVal($sPropName,$val);
	    }
	}
    } */

    // -- STATUS CALCULATIONS -- //
    // ++ STATUS RESULTS ++ //

    public function getTotalCount() {
	return (int)$this->cntAll;
    }
    public function getActiveCount() {
	return (int)$this->cntActive;
    }
    // RETURNS: Number of active supporting kids
    public function getSupportCount() {
	return (int)$this->cntSupport;
    }
    // RETURNS: Number of inactive supporting kids
    public function getSupportFailCount() {
	return (int)$this->cntSupportFail;
    }
    // RETURNS: Number of active kids who oppose this point
    public function getOpposedCount() {
	return (int)$this->cntAgainst;
    }
    // CALCULATE whether the current point should be active
    public function IsActive() {
	$this->EnsureCensus();
	if ($this->getOpposedCount() == 0) {
	    $ok = TRUE;
	    if ($this->getSupportFailCount() > 0) {
		if ($this->RequiresAllSupport()) {
		    $ok = FALSE;	// fails when a single support point fails
		} else {
		    if ($this->getSupportCount() == 0) {
			$ok = FALSE;	// fails when all support points fail
		    }
		}
	    }
	} else {
	    $ok = FALSE;
	}
	return $ok;
    }

    // -- STATUS RESULTS -- //
    // ++ STATUS UI ++ //

    public function RenderStatusSummary() {
	$qTot = $this->getTotalCount();
	if ($qTot > 0) {
	    $out = $qTot.'t';
	    $sDet = $qTot.' total';
	    $qAct = $this->getActiveCount();
	    if ($qAct > 0) {
		$out .= ' '.$qAct.'a';
		$sDet .= ", $qAct active";
		$qOpp = $this->getOpposedCount();
		if ($qOpp > 0) {
		    $out .= ' '.$qOpp.'-';
		    $sDet .= ", $qOpp opposing";
		}
		$qSup = $this->getSupportCount();
		if ($qSup > 0) {
		    $out .= ' '.$qSup.'+';
		    $sDet .= ", $qSup supporting";
		}
	    }
	    $qSupFail = $this->getSupportFailCount();
	    if ($qSupFail > 0) {
		$out .= ' '.$qSupFail.'+F';
		$sDet .= ", $qSupFail support failure";
	    }
	} else {
	    $out = '(0)';
	    $sDet = 'no response points';
	}
	return "<span class=debate-point-stats title='$sDet'>[$out]</span>";
    }
    public function RenderStatusWrapper($htText) {
	$htCnt = $this->RenderStatusSummary();
	$out = $htText;
	if (!$this->IsActive()) {
	    $out = "<s>$out</s>";
	}
	return $out.$htCnt;
    }

    // -- STATUS UI -- //
}
class wcDebatePoint_blank extends wcDebatePoint {
    public function __construct() {
    }
    protected function StandardLink() {
	return '<i>(Parent page not specified.)</i>';
    }
    public function LocalURL() {
	throw new exception('Blank page object should not be calling this.');
    }
}

class wcDebatePointType {

    // ++ STATIC: PSEUDO-CONSTANTS ++ //

    // these really don't need to be configurable

    static protected function TypeArray() {
	return array(
	  KVAL_DPOINT_TYPE_PRO,
	  KVAL_DPOINT_TYPE_CON,
	  KVAL_DPOINT_TYPE_INF
	  );
    }

    // -- STATIC: PSEUDO-CONSTANTS -- //
    // ++ STATIC: SETTINGS ++ //

    // these should all be configurable eventually

    static protected function IconNameArray() {
	return array(
	  KFN_ICON_PRO_POINT,
	  KFN_ICON_CON_POINT,
	  KFN_ICON_INF_POINT
	  );
    }
    static protected function LabelArray() {
	return array(
	  'argue for',
	  'argue against',
	  'comment on'
	  );
    }
    static protected function DescrArray() {
	return array(
	  'This point supports its parent point.',
	  'This point refutes its parent point.',
	  'This is an informal comment.'
	  );
    }
    static protected function Folder_forIcons() {
	global $wgScriptPath;

	return $wgScriptPath.'/'.KWP_REL_ICONS;
    }

    // -- STATIC: SETTINGS -- //
    // ++ STATIC: LOOKUP ++ //

    static protected function KeyToIndex($sKey) {
	return array_flip(self::TypeArray())[$sKey];
    }

    // -- STATIC: LOOKUP -- //
    // ++ STATIC: SETUP ++ //

    static public function Get_fromKey($sKey) {
	if (empty($sKey)) {
	    // useful when debugging forms - URL will not have a type
	    return new wcDebatePointType_blank();
	} else {
	    $idx = self::KeyToIndex($sKey);
	    return new wcDebatePointType($idx,$sKey);
	}
    }
    // RETURNS: array of Point Type objects, one per Point Type
    static public function GetAll_asArray() {
	$arTypes = self::TypeArray();
	foreach ($arTypes as $idx => $sType) {
	    $arOut[$sType] = static::Get_fromKey($sType);
	}
	return $arOut;
    }
    static protected function MWTitle_forForm() {
	static $mwo = NULL;
	if (is_null($mwo)) {
	    $mwo = Title::newFromText(w3tpl_module_Debate::Page_forNewPointForm());
	}
	return $mwo;
    }
    static public function ResponseLinks($idPage) {
	//$oTplt = new fcTemplate_array('[$','$]',self::Page_forNewPointForm());
	$arArgs = array(	// Add query arguments to URL:
	  KURL_QUERY_DPAGE_ID	=> $idPage,	// ID of page to which we are responding
	  'action'	=> 'purge',	// have to purge the page so it will re-render
	  );

	$arTypes = static::GetAll_asArray();
	$out = NULL;
	foreach ($arTypes as $oType) {
	    $out .= $oType->ResponseLink($arArgs);
	}
	return "<span class=debate-response-links>$out</span>";
    }
    static public function IconTag_forAnd() {
	$url = self::Folder_forIcons().KFN_ICON_AND_ATTRIB;
	$out = "<img src='$url' title='all support points must be true'>";
	return $out;
    }

    // -- STATIC -- //
    // ++ SETUP ++ //

    private $id;
    private $sKey;

    public function __construct($id,$sKey) {
	$this->id = $id;
	$this->sKey = $sKey;
    }

    // -- SETUP -- //
    // ++ OBJECT FIELDS ++ //

    protected function ID() {
	return $this->id;
    }
    protected function KeyString() {
	return $this->sKey;
    }

    // -- OBJECT FIELDS -- //
    // ++ OBJECT FIELD CALCULATIONS ++ //

    // PUBLIC so Point object can use it in entry form
    public function LabelString() {
	return self::LabelArray()[$this->ID()];
    }
    protected function Description() {
	return self::DescrArray()[$this->ID()];
    }
    public function ResponseLink(array $arArgs) {
	$arArgs[KURL_QUERY_DPOINT_TYPE] = $this->KeyString();
	$url = self::MWTitle_forForm()->getLinkURL($arArgs);
	$txt = htmlspecialchars($this->LabelString());
	$dsc = htmlspecialchars($this->Description());
	return " [<a href='$url' title='$dsc'>$txt</a>]";
    }
    public function IconTag() {
	$url = $this->IconURL();
	$txt = $this->Description();
	$out = "<img src='$url' title='$txt'>";
	return $out;
    }
    protected function IconURL() {
	$fn = self::IconNameArray()[$this->ID()];
	return self::Folder_forIcons().$fn;
    }

    // -- OBJECT FIELD CALCULATIONS -- //
}
class wcDebatePointType_blank extends wcDebatePointType {
    public function __construct() {
    }
    protected function ID() {
	return 0;
    }
    protected function KeyString() {
	return 'none';
    }
    public function LabelString() {
	return '(point type not specified)';
    }
    protected function Description() {
	return '(if the point type had been specified, its description would be here)';
    }
}
