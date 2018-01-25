<?php namespace w3tpl;
/*
  TAG: <dump>
  HISTORY:
    2017-11-01 starting (loosely adapting from w3tpl1)
*/
class xcTag_dump extends xcTag {

    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'DUMP';
    }
    
    // -- PROPERTIES -- //
    // ++ EVENTS ++ //

    public function Go() {
	$doVars = $this->ArgumentExists('vars');
	
	if ($doVars) {
	    $out = xcVar::DumpAll();
	}
	return $out;
    }
    
    // -- EVENTS -- //
}