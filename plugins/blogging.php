<?php namespace w3tpl;
/*----
  PURPOSE: wiki-based blogging for w3tpl
  HISTORY:
    2012-11-02 rewriting essentially from scratch after Kate/server glitch
    2018-01-26 updated TagAPI f() name
*/

//require_once('smw-links.php');
xcModule::LoadModule('smw-links');	// defines xcModule_SMWLinks

class xcModule_Blogging extends xcModule_SMWLinks {

    // ++ TAG API ++ //

    /*----
      ACTION: show summaries for applicable blog entries
      INPUT:
	TODO - max: maximum number of entries to show
	TODO - start: starting index (i.e. which entry to start with)
	TODO - user: show only entries by this user; if NULL or not set, show all users
    */
    public function TagAPI_ShowBlogEntryBriefs(array $iArgs) {

	$db = \fcApp::Me()->GetDatabase();
	$ar = $db->GetTitleObjects_forPropertyValue('Page type','Blog post');

	if (is_array($ar)) {
	    $out = '';

	    // sort by date -- show newest entries first
	    foreach ($ar as $idSMW => $oPage) {
		//$strRTitle = $arRow['s_title'];		// raw title
		//$intNSpace = $arRow['s_namespace'];	// namespace
		//$mwoTitle = \Title::newFromText($strRTitle,$intNSpace);
		//$oPage->SetTitleObject($mwoTitle);
		$txtDate = $oPage->GetPropertyValue('When_Posted');
		
		$oPage->DumpProperties();
		
		//echo "DATE=[$txtDate]<br>";
		$intDate = strtotime($txtDate);
		$arSort[$intDate] = $oPage->GetTitleObject();
	    }
	    krsort($arSort);
	    //echo "SORTED ARRAY:".\fcArray::Render($arSort);

	    // display the sorted entries
	    $oBlogPage = new Blog_Entry($this);
	    foreach ($arSort as $intDate => $mwoTitle) {
		$oBlogPage->SetTitleObject($mwoTitle);
		$oBlogPage->ClearProperties();	// 2018-02-03 it seems somehow sloppy that this is necessary
		$out .= $oBlogPage->RenderSummary();
	    }
	    return $out;
	} else {
	    return '<i>no blog entries yet</i>';
	}
    }

    // -- TAG API -- //

}

class Blog_Entry extends \fcPageData_SMW {

    // ++ SETUP ++ //

    public function __construct(xcModule $oMod) {
	$this->SetModule($oMod);
    }
    private $oMod;
    protected function SetModule(xcModule $oMod) {
	$this->oMod = $oMod;
    }
    protected function GetModule() {
	return $this->oMod;
    }

    public function RenderSummary() {
	$strRTitle = $this->GetPropertyValue('Title');	// raw title
	if (is_null($strRTitle)) {
	    throw new exception('Could not retrieve Title value.');
	}
	$strDTitle = \fcDataConn_MW::VisualizeTitle($strRTitle);

	$mwoTitle = $this->GetTitleObject();
	$urlTitle = $mwoTitle->getFullURL();
	$htTitle = '<a href="'.$urlTitle.'">'.$strDTitle.'</a>';

	//$txtDate = $this->GetPropVal('_dat');
	$txtDate = $this->GetPropertyValue('When_Posted');
	$dtDate = strtotime($txtDate);
	$ftDate = date('Y/m/d',$dtDate);

	$txtAbove = $this->GetPropertyValue('TextAbove');	// this is the "preview"
	$htAbove = $this->GetModule()->Parse_WikiText($txtAbove);

	$txtUser = $this->GetPropertyValue('User');
	$mwoUser = \User::newFromName($txtUser);
	
	if ($mwoUser === FALSE) {
	    // couldn't get the object
	    $htUser = "[?user $txtUser]";
	} else {
	    $mwoUTitle = $mwoUser->getUserPage();
	    $urlUser = $mwoUTitle->getFullURL();
	    $htUser = '<a href="'.$urlUser.'">'.$txtUser.'</a>';
	}

	$out = '<h2>'.$htTitle.'</h2>'
	  .'<span class=blog-excerpt-header><span class=blog-excerpt-title>'
	  .'</span></span><span class=blog-excerpt-attrib><span class=blog-excerpt-author>by '.$htUser
	  .'</span> (<span class=blog-excerpt-timestamp>'.$ftDate.'</span>)</span>'
	  .$htAbove
	  .' <b><i><a href="'.$urlTitle.'">more...</a></i></b>';

	return $out;
    }
}
