<?php namespace w3tpl;
/*
  HISTORY:
    2017-10-29 split off from w3tpl.body.php
    2017-11-09 added $wgW3xAllowRawHTML option
      To allow only on protected pages:
	$wgW3xAllowRawHTML = $wgTitle->isProtected ('edit');
      To allow on all pages (e.g. on a site where only approved members can edit):
	$wgW3xAllowRawHTML = TRUE;
    2018-01-26 added $wgW3xSupportSMW option
*/
class xcW3TPL {

    static public function init() {

    	// make sure the application object exists and is the class we need
	xcApp::Make();
	if (self::GetProvideSMWSupport()) {
	    xcApp::Me()->SetDatabaseClass('fcDataConn_SMW');	// use db class with SMW support
	}
	
	$fpSelf = dirname( __FILE__, 2 );
	$fpTags = $fpSelf.'/tags';	// Can't see any reason for this not to be hard-coded
	xcTag::LoadAll($fpTags);
	
	xcModule::SetFolderPath($fpSelf.'/plugins');	// same (maybe change to "modules", though)

	return TRUE;
    }
    
    // ++ CONFIGURATION ++ //
    
    static public function GetAllowRawHTML() {
	global $wgW3xAllowRawHTML;
	
	return $wgW3xAllowRawHTML;
    }
    static protected function GetProvideSMWSupport() {
	global $wgW3xSupportSMW;
	
	return $wgW3xSupportSMW;	// might need to check for empty($wgW3xSupportSMW)
    }
    
    // -- CONFIGURATION -- //
}
class xcApp extends \fcApp_MW {

    // ++ FRAMEWORK ++ //
    
    private $oMod;
    public function SetModule(xcModule $oModule) {
	$this->oMod = $oModule;
    }
    public function GetModule() {
	return $this->oMod;
    }
    public function GetDataHelper() {
	return $this->GetModule()->GetDataHelper();
    }
    
    // -- FRAMEWORK -- //

}