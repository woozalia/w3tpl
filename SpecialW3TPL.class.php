<?php
require_once(KFP_MW_LIB.'/config-libs.php');
/*
clsLibMgr::Add('menus',		KFP_MW_LIB.'/menu.php',__FILE__,__LINE__);
clsLibMgr::Add('richtext',	KFP_MW_LIB.'/richtext.php',__FILE__,__LINE__);
clsLibMgr::Add('mw-base',	KFP_MW_LIB.'/mw-base.php',__FILE__,__LINE__);
clsLibMgr::Load('menus'		,__FILE__,__LINE__);
clsLibMgr::Load('richtext'	,__FILE__,__LINE__);
clsLibMgr::AddClass('clsMWData',	'mw-base');
*/
class SpecialW3TPL extends SpecialPageApp {
    function __construct() {
	// When called, this seems to try to load a page named SpecialW3TPL inside the Specials folder.
	parent::__construct( 'W3TPL' );
	$this->includable( TRUE );	// sure, why not?
    }

    function execute( $subPage ) {
	$mwoOut = $this->getOutput();
	$this->setHeaders();

	# Get request data from, e.g.
//	$param = $request->getText( 'param' );
	$this->GetArgs($subPage);	// parse URL arguments

	# Do stuff
	# ...

	$strPage = $this->Arg('page');
	$idPage = $this->Arg('id');
	if ($idPage == 'new') {
	    $idPage = NULL;
	}

	$this->UseHTML();

	switch ($strPage) {
	  case 'title':	// give information about a title (wiki page)
	    $objTitle = $this->Engine()->Titles($idPage);
	    $out = $objTitle->RenderPage();
	    break;
	  default:
	    $out = $this->RenderPageList();
        }
	$mwoOut->addHTML($out);
    }
    protected static function Engine() {
	return W3_clsAppData::Spawn();
    }
/*
    protected static function Engine() {
	$dbr =& wfGetDB( DB_SLAVE );
	return W3_clsAppData::Spawn($dbr);
    }
*/
    public function RenderPageList() {
	$dbr =& wfGetDB( DB_SLAVE );
	$sql = 'SELECT '
	  .'pp_page, qProps, page_namespace, page_title '
	  .'FROM (SELECT pp_page, COUNT(pp_page) as qProps FROM page_props GROUP BY pp_page) AS pg '
	  .'LEFT JOIN page '
	  .'ON pp_page=page_id '
	  .'ORDER BY page_namespace, page_title';

	$res = $dbr->query($sql);
	if ($dbr->numRows( $res ) <= 0) {
		$out = 'No pages currently have any properties.';
	} else {
	    $tbl = $this->Engine()->Titles();

	    $out = '<table>';
	    $odd = FALSE;
	    while ($row = $dbr->fetchRow($res)) {
		$htStyle = $odd?'odd':'even';
		$odd = !$odd;

		$idTitle = $row['pp_page'];
		$qProps = $row['qProps'];
		$sQProps = $qProps.' propert'.Pluralize($qProps,'y','ies');
		$oTitle = $tbl->GetItem($idTitle);
		$htTitle = $oTitle->PageLink();
		$htQPrps = $oTitle->AdminLink($sQProps);
		$out .= '<tr class="'.$htStyle.'">'
		  ."<td>$htTitle</a></td>"
		  ."<td>$htQPrps</td>"
		  .'</tr>';
	    }
	    $out .= '</table>';
	}
	return $out;
    }
}

class W3_clsAppData extends clsMWData {
    /* ****
      SECTION: BOILERPLATE
    */
    protected static function SelfClass() {
	return __CLASS__;
    }
    /*
      /SECTION: END BOILERPLATE
    **** */
    public function Titles($id=NULL) {
	return $this->Make('W3_Titles',$id);
    }
}

class W3_Titles extends clsTable_key_single {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('page');
	  $this->KeyName('page_id');
	  $this->ClassSng('W3_Title');
	  $this->ActionKey('title');
    }
}
class W3_Title extends clsRecs_key_single {
    private $mwo;

    /*====
      BOILERPLATE: self-linking
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return $this->Admin()->AdminRedirect($iarArgs);
    }
    /*====
      END BOILERPLATE
    */

    protected function InitVars() {
	parent::InitVars();
	$this->mwo = NULL;
    }

    public function MW_Object() {
	if (is_null($this->mwo)) {
	    $this->mwo = Title::newFromID($this->KeyValue());
	}
	return $this->mwo;
    }
    public function PageLink($iText=NULL) {
	$mwo = $this->MW_Object();
	$sText = is_null($iText)?($mwo->getFullText()):$iText;
	$out = '<a href="'.$mwo->getFullURL().'">'.$sText.'</a>';
	return $out;
    }
    public function RenderPage() {
	$mwo = $this->MW_Object();
	$out = '<h2>'.$mwo->getFullText().'</h2>';
	$out = $this->RenderProperties();
	return $out;
    }
    public function RenderProperties() {
	$sql = 'SELECT * FROM page_props WHERE pp_page='.$this->KeyValue();
	$dbr =& wfGetDB( DB_SLAVE );
	$res = $dbr->query($sql);
	if ($dbr->numRows( $res ) <= 0) {
	    $out = 'No properties found.';
	} else {
	    $out = "\n<table><th>name</th><th>value</th></tr>";
	    $odd = FALSE;
	    while ($row = $dbr->fetchRow($res)) {
		$htStyle = $odd?'"odd"':'"even"';
		$odd = !$odd;

		$key = $row['pp_propname'];
		$val = $row['pp_value'];
		$out .= "\n<tr class=$htStyle><td>$key</td><td>$val</td></tr>";
	    }
	    $out .= "\n</table>";
	}
	return $out;
    }
}
