<?php namespace w3tpl;
/*----
  PURPOSE: wiki-based blogging for w3tpl
    This version is pure MW (using page properties stored via w3tpl)
  HISTORY:
    2018-02-10 Created for VbzWiki (and should also work with HypertWiki, I think?)
*/

class xcModule_Blogging extends xcModule {

    // ++ TAG API ++ //

    /*----
      ACTION: Render a user's blog summary list
      HISTORY:
	2018-02-11 started, for VbzWiki
    */
    public function TagAPI_RenderUserBlogList(array $arArgs) {
	global $wgTitle;
    
	// There might be a better way to do this, but I just need to get this working:

	// 1. Get all titles that are blog posts
	$mwoCatPost = \Category::newFromName('Data/blog/post');
	$mwoTitles = $mwoCatPost->getMembers();	// this can later be modified to support paging through long lists

	$mwoTitle = $wgTitle;	// for now, we'll just assume that the blog list page is in userspace
	$sUserFilt = strtolower($mwoTitle->getRootText());
	
	// 2. Get all titles where the author is the current user
	//	we'll take this opportunity to sort the entries as well

	$arTitles = NULL;
	$dbg = NULL;
	foreach ($mwoTitles as $mwoTitle) {
	    $oPage = new xcBlogEntry($this,$mwoTitle);
	    $oProps = $oPage->GetProperties();
	    $oProps->LoadPropertyValues();
	    $sUserEntry = strtolower($oProps->GetValue('user'));

	    if ($sUserEntry == $sUserFilt) {
		// yes, this is the right user
		$sDate = $oProps->GetValue('timestamp');
		// ideally, we'd parse this into a numeric, but for now...
		$arTitles[$sDate] = $oPage;
	    }
	}
	    
	if (is_null($arTitles)) {
	    $out = "<i>No blog entries found for user '$sUserFilt'.</i>".$dbg;
	} else {
	    $out = NULL;

	    /* might be more useful in another context; was mainly for debugging
	    $n = count($arTitles);
	    $sPlur = \fcString::Pluralize($n,'y','ies');
	    $out = "<i>Found $n blog entr$sPlur for user '$sUserFilt'.</i>$dbg"; */

	    ksort($arTitles);
	    
	    foreach ($arTitles as $sDate => $oPage) {
		$out .= $oPage->RenderSummary();
	    }
	}
	return $out;
    }
    /*----
      ACTION: Render the blog entry defined by the current page
      HISTORY:
	2018-02-10 created for VbzWiki
    */
    public function TagAPI_RenderFullPost(array $arArgs) {
	$oBlogPage = new xcBlogEntry($this);
	$oBlogPage->Use_GlobalTitle();
	return $oBlogPage->RenderPage();
    }

    // -- TAG API -- //
    // ++ TABLE ++ //
    
    protected function BlogEntryTable() {
	return $this->GetDatabase()->GetTableWrapper(__NAMESPACE__.'\\xcBlogEntry');
    }

    // -- TABLE -- //
}
class xcBlogEntry extends \fcPageData_MW {

    // ++ SETUP ++ //

    public function __construct(xcModule $oMod, \Title $mwo=NULL) {
	$this->SetModule($oMod);
	parent::__construct($mwo);
    }
    
    // -- SETUP -- //
    // ++ FRAMEWORK ++ //

    private $oMod;
    protected function SetModule(xcModule $oMod) {
	$this->oMod = $oMod;
    }
    protected function GetModule() {
	return $this->oMod;
    }
    private $mwoPOutput=NULL;
    protected function GetMWParserOutput() {
	if (is_null($this->mwoPOutput)) {
	    $this->mwoPOutput = xcModule::GetParser()->getOutput();
	}
	return $this->mwoPOutput;
    }
    // TODO: This should probably be provided at a higher level somewhere
    protected function AddCategory($sName,$sSort) {
	$wtName = \fcDataConn_MW::NormalizeTitle($sName,NS_CATEGORY);
	$mwoPOutput = $this->GetMWParserOutput();
	$mwoPOutput->addCategory($wtName,$sSort);
    }

    // -- FRAMEWORK -- //
    // ++ OUTPUT ++ //

    /*----
      ACTION: Render just the blog entry summary, as in a list on a front page
      NOTE: This directly accesses property data instead of using MW API fx()
	because the latter only works for the page currently being edited.
    */
    public function RenderSummary() {
	$oProps = $this->GetProperties();
	$oProps->LoadPropertyValues();	// load all the values for this page
	
	$sTitle = $oProps->GetValue('title');	// title for blog entry

	$mwoTitle = $this->GetTitleObject();
	$urlTitle = $mwoTitle->getFullURL();
	$htTitle = "<a href='$urlTitle'>$sTitle</a>";

	$sWhen = $oProps->GetValue('timestamp');
	$dtWhen = strtotime($sWhen);
	$ftWhen = date('Y/m/d H:i (l)',$dtWhen);

	$txtAbove = $oProps->GetValue('textabove');	// this is the "preview"
	$htAbove = $this->GetModule()->Parse_WikiText($txtAbove);

	$sUser = $oProps->GetValue('user');
	$mwoUser = \User::newFromName($sUser);
	
	if ($mwoUser === FALSE) {
	    // couldn't get the object
	    $htUser = "[?user $sUser]";
	} else {
	    $mwoUTitle = $mwoUser->getUserPage();
	    $urlUser = $mwoUTitle->getFullURL();
	    $htUser = '<a href="'.$urlUser.'">'.$sUser.'</a>';
	}

	$out = <<<__END__
<h2>$htTitle</h2>
<span class=blog-excerpt-header><span class=blog-excerpt-title>
</span></span><span class=blog-excerpt-attrib><span class=blog-excerpt-author>by $htUser
</span> (<span class=blog-excerpt-timestamp>$ftWhen</span>)</span>
$htAbove
<b><i><a href="$urlTitle">more...</a></i></b>
__END__;

	return $out;
    }
    /*----
      ACTION: Render the current page as a blog page
      ASSUMES: certain standard page properties will be present (need to document these)
    */
    public function RenderPage() {
	$oProps = $this->GetProperties();
	$sTitle = $oProps->GetValue('title');	// title for blog entry
	$sUser = $oProps->GetValue('user');
	$sTime = $oProps->GetValue('timestamp');
	$sAbove = $oProps->GetValue('textabove');
	$sBelow = $oProps->GetValue('textbelow');

	if (is_null($sTitle)) {
	    throw new \exception('Could not retrieve value for "title".');
	}
	
	$sCats = $oProps->GetValue('topicsglobal');
	if (!is_null($sCats)) {
	    $arCats = \fcString::Xplode($sCats);
	    if (is_array($arCats)) {
		foreach ($arCats as $sCat) {
		    $this->AddCategory($sCat,$sTitle);
		}
	    }
	}
	
	$this->AddCategory('data/blog/post',$sTitle);
	$this->AddCategory("author/$sUser",$sTitle);
	
	$out = <<<__END__
==$sTitle==
''<small>posted at $sTime</small>''

$sAbove
----
$sBelow
__END__;
    
	return $this->GetModule()->Parse_WikiText($out);
   }

    // -- OUTPUT -- //
}