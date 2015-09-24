<?php
/*
  PURPOSE: structured debate functions for w3tpl, using only native MediaWiki data functions
  HISTORY:
    2015-09-10 started
    2015-09-20 census is having problems, so merging status class into point class in hopes that it can be straightened out
*/

clsLibrary::Load_byName('ferreteria.db.2');
clsLibrary::Load_byName('ferreteria.mw.2');

new w3tpl_module_Debate();	// class will self-register

// PATHS

define('KWP_REL_ICONS','custom/debate/');	// path from wiki root to debate icons folder

// ICONS

define('KFN_ICON_PRO_POINT','Arrow-button-up-20px.png');
define('KFN_ICON_CON_POINT','Arrow-button-dn-20px.png');
define('KFN_ICON_INF_POINT','Arrow-button-i-20px.png');

// URL QUERY PARAMETER NAMES

define('KURL_QUERY_DPAGE_ID','id');
define('KURL_QUERY_DPOINT_TYPE','type');

// TAG NAMES

define('KCAT_DEBATE_POINT','Debate_point');
define('KPROP_DPOINT_SUMMARY','debate-point-summary');	// one-line summary of point
define('KPROP_DPOINT_PARENT','debate-point-parent');	// name of parent page
define('KPROP_DPOINT_TYPE','debate-point-type');	// pro, con
  define('KVAL_DPOINT_TYPE_PRO','pro');				// pro: point supports its parent
  define('KVAL_DPOINT_TYPE_CON','con');				// con: point opposes its parent
  define('KVAL_DPOINT_TYPE_INF','inf');				// inf: informal/informational comment

//define('KS_DPOINT_TYPES','\pro\con\inf');
//define('KS_DPOINT_TYPE_LABELS','\argue for\argue against\comment');

// TEMPLATES / FORMS

define('KWP_FORM_NEW_POINT','debate/forms/point');	// page name for point-adding form
define('KWP_TPLT_NEW_POINT','debate/templates/point');	// page name for point-adding template
define('KTP_NEW_POINT_TITLE','debate/point/[$user$]/[$?TIMESTAMP$]');	// template for point-page titles

class w3tpl_module_Debate extends w3tpl_module {

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
	    $out = "\n<ul>";
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
	    return 'Some kind of problem; no rows.';
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
    protected function w3f_ShowPointForm() {
	$oReq = clsHTTP::Request();
	$idPage = $oReq->GetInt(KURL_QUERY_DPAGE_ID);
	$sType = $oReq->GetText(KURL_QUERY_DPOINT_TYPE);
	$wdoThis = $this->DebatePointObject_fromPageID($idPage);
	return $wdoThis->PointEntryForm($idPage,$sType);
    }

    // -- W3TPL API -- //
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
	    throw new exception("Couldn't get MediaWiki Title called '$sTitle'.");
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
}

class wcData extends fcDataConn_MW {
}

class wcDebatePoint extends clsTreeNode {
    private $woMod;	// main debate module
    private $mwo;
    public $id;
    static private $nObjCount=0;	// mainly for debugging

    // ++ SETUP ++ //

    public function __construct(w3tpl_module_Debate $oModule, $sTitle) {
	self::$nObjCount++;
	$this->id = self::$nObjCount;
	$this->woMod = $oModule;
	if (is_a($sTitle,'Title')) {	// $sTitle can be a MediaWiki Title object or a string (title's name)
	    $this->mwo = $sTitle;
	} else {
	    $this->mwo = Title::newFromText($sTitle);
	}
	$this->Name($this->mwo->getText());
    }

    // -- SETUP -- //
    // ++ FRAMEWORK ++ //

    protected function Module() {
	return $this->woMod;
    }

    protected function Engine() {
	return $this->Module()->Engine();
    }

    // -- FRAMEWORK -- //
    // ++ CALCULATED INFORMATION ++ //

    protected function MWID() {
	return $this->mwo->getArticleID();
    }
    public function LocalURL() {
	return $this->mwo->getLocalURL();
    }

    // -- CALCULATED INFORMATION -- //
    // ++ INTERNAL CALCULATIONS ++ //

    protected function CheckParent() {
	static $isParentChecked = FALSE;
	if (!$isParentChecked) {
	    if ($this->PropValue_exists(KPROP_DPOINT_PARENT)) {
		$sParName = $this->PropValue(KPROP_DPOINT_PARENT);
		$xoParent = $this->Module()->DebatePointObject_fromName($sParName);
		$xoParent->NodeAdd($this);

		$this->GetParent()->MapTree();
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
    */
    protected function MapTree() {
//	static $isMapped = FALSE;

//	if (!$isMapped) {
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
//	    $isMapped = TRUE;
//	}
    }

    // -- INTERNAL CALCULATIONS -- //
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
	  "\n<ul>"
	    ."\n  <li class=dpoint-parent><b>Parent</b>: $ftParent</li>"	// later: replace with summary-and-link
	    ."\n  <ul>"
	    ."\n    <li class=dpoint-current><b>This</b>: $ftSumm</li>"
	    ."\n    <ul>"
	    ."\n      ".$this->DisplayResponses()
	    ."\n      <li class=dpoint-action-links>Respond: $htResponseLinks</li>"
	    ."\n    </ul>"
	    ."\n  </ul>"
	  ."\n</ul>"
	  ;
	return $out;
    }
    /*----
      ASSUMES: this is a root dpoint
    */
    protected function DisplayResponseTree() {
	$ftThis = $this->StandardLink().$this->RenderLinksToRespond();
	$out =
	  "\n<ul>"
	  ."\n  <li>$ftThis</li>"
	  ."\n  <ul>"
	  .$this->DisplayResponses(TRUE)
	  ."\n  </ul>"
	  ."\n</ul>"
	  ;
	return $out;
    }
    protected function DisplayResponses($doTree=FALSE) {
	$ar = $this->Nodes();
	$out = NULL;
	$sLbl = $doTree?'':'<b>Response</b>: ';
	if (count($ar) > 0) {
	    foreach ($ar as $sName => $oKid) {
		$out .= "\n<li class=dpoint-response>$sLbl".$oKid->StandardLink()."</li>";
		if ($doTree) {
		    $out .= "\n<ul>"
		      .$oKid->DisplayResponses(TRUE)
		      ."\n</ul>";
		}
	    }
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
    // LATER we will probalby want a SupportsParent() so we can indicate if parent has any support
    public function OpposesParent() {
	return ($this->PropValue(KPROP_DPOINT_TYPE) == KVAL_DPOINT_TYPE_CON);
    }
    /*----
      RETURNS: Rendering of link to dpoint's page, with summary as link text
    */
    protected function StandardLink() {
	$url = $this->LocalURL();
	$arProps = $this->AllProps_array();
	$sSumm = $arProps[KPROP_DPOINT_SUMMARY];
	$htIcon = $this->IconTag();
	$htSumm = " <a href='$url'>$sSumm</a>";
	$out = $htIcon.$this->RenderStatusWrapper($htSumm);
	return $out;
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
    protected function PageTitle($idParent=NULL) {
	if (is_null($idParent)) {
	    return 'Help:Page not specified';
	} else {
	    $mwoParent = Title::newFromID($idParent);
	    return $mwoParent->getFullText();
	}
    }
    /*----
      RETURNS: rendering of the form for entering a new DPoint
    */
    public function PointEntryForm($idParent,$sType) {
	global $wgScriptPath,$wgUser;

	$sTpltName = KWP_TPLT_NEW_POINT;

	$oType = wcDebatePointType::Get_fromKey($sType);
	$sTDesc = $oType->LabelString();
	$htIcon = $oType->IconTag();		// type-icon for the response
	$htSumm = $this->StandardLink();	// link to the parent

	$sParent = $this->PageTitle($idParent);
	$mwoMakePage = Title::newFromText('Special:MakePage');
	$urlMakePage = $mwoMakePage->getLocalURL();

	$sTitleTplt = KTP_NEW_POINT_TITLE;
	$sUser = $wgUser->getTitleKey();	// we could also use ID, for slightly more anonymity

	$htForm = <<<__END__

<form action="$urlMakePage" target=_blank method=POST>
  <input type=hidden name="!TITLETPLT"	value="$sTitleTplt">
  <input type=hidden name="!TPLTPAGE"	value="$sTpltName">
  <input type=hidden name="!TIMEFMT"	value="Y/m/d/Hi">

  <input type=hidden name="!TPLTSTART"	value="@@START@@">
  <input type=hidden name="!TPLTSTOP"	value="@@STOP@@">
  <input type=hidden name="!IMMEDIATE"	value="1">

  <input type=hidden name="user"	value="$sUser">
  <input type=hidden name="point-parent" value="$sParent">
  <input type=hidden name="point-type"	value="$sType">

  <table>
    <tr><td align=right><b>Point type</b>:</td><td>$sTDesc <i>$htSumm</i></td></tr>
    <tr><td align=right><b>Summary</b>:</td><td>$htIcon<input name=point-summary size=60></td></tr>
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
    // ++ STATUS CALCULATIONS ++ //

    private $cntAll;	// total number of children
    private $cntActive;	// number of active children
    private $cntAgainst;// number of active contradictory immediate children

    // ACTION: make sure census has been taken
    protected function EnsureCensus() {
        static $didCensus=FALSE;
        if (!$didCensus) {
	    $arKids = $this->Nodes();
	    if (count($arKids) > 0) {

		$this->ResetStats();
		foreach ($arKids as $sName => $oKid) {
		    $this->SumToStats($oKid);
		}
		$didCensus = TRUE;
	    }
        }
    }
    protected function ResetStats() {
	$this->cntAll = 0;
	$this->cntActive = 0;
	$this->cntAgainst = 0;
    }
    protected function SumToStats(wcDebatePoint $oKid) {
	//$oKid->EnsureCensus();	// this can be removed when not debugging
	$this->cntAll++;
	if ($oKid->IsActive()) {
	    $this->cntActive++;
	    if ($oKid->OpposesParent()) {
		$this->cntAgainst++;
	    }
	}
    }

    // -- STATUS CALCULATIONS -- //
    // ++ STATUS RESULTS ++ //

    public function getTotalCount() {
	$this->EnsureCensus();
	return (int)$this->cntAll;
    }
    public function getActiveCount() {
	$this->EnsureCensus();
	return (int)$this->cntActive;
    }
    // RETURNS: Number of active kids who oppose this point
    public function getOpposedCount() {
	$this->EnsureCensus();
	return (int)$this->cntAgainst;
    }
    public function IsActive() {
	return ($this->getOpposedCount() == 0);
    }

    // -- STATUS RESULTS -- //
    // ++ STATUS UI ++ //

    public function RenderStatusSummary() {
	$qTot = $this->getTotalCount();
	if ($qTot > 0) {
	    $out = $qTot.'t';
	    $qAct = $this->getActiveCount();
	    if ($qAct > 0) {
		$out .= ' '.$qAct.'a';
		$qOpp = $this->getOpposedCount();
		if ($qOpp > 0) {
		    $out .= ' '.$qOpp.'o';
		}
	    }
	} else {
	    $out = '(0)';
	}
	return $out;	// id is for debugging; remove later
    }
    public function RenderStatusWrapper($htText) {
	$sCnt = $this->RenderStatusSummary();
	$htCnt = " <small>[$sCnt]</small>";
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

    static protected function TypeArray() {
	return array(
	  KVAL_DPOINT_TYPE_PRO,
	  KVAL_DPOINT_TYPE_CON,
	  KVAL_DPOINT_TYPE_INF
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
    static protected function IconNameArray() {
	return array(
	  KFN_ICON_PRO_POINT,
	  KFN_ICON_CON_POINT,
	  KFN_ICON_INF_POINT
	  );
    }
    static protected function Folder_forIcons() {
	global $wgScriptPath;

	return $wgScriptPath.'/'.KWP_REL_ICONS;
    }

    // -- STATIC: PSEUDO-CONSTANTS -- //
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
	    $mwo = Title::newFromText(KWP_FORM_NEW_POINT);
	}
	return $mwo;
    }
    static public function ResponseLinks($idPage) {
	$oTplt = new fcTemplate_array('[$','$]',KWP_FORM_NEW_POINT);
	$arArgs = array(	// Add query arguments to URL:
	  KURL_QUERY_DPAGE_ID	=> $idPage,	// ID of page to which we are responding
	  'action'	=> 'purge',	// have to purge the page so it will re-render
	  );

	$arTypes = static::GetAll_asArray();
	$out = NULL;
	foreach ($arTypes as $oType) {
	    $out .= $oType->ResponseLink($arArgs);
	}
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

    // ++ SETUP ++ //

    protected function ID() {
	return $this->id;
    }
    protected function KeyString() {
	return $this->sKey;
    }
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
