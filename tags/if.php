<?php namespace w3tpl;
/*
  TAG: <if>
*/
class xcTag_if extends xcTag {
    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'IF';
    }
    
    // -- PROPERTIES -- //
    // ++ EVENTS ++ //
    
    public function Go() {
	$arArgs = $this->GetArguments();
	if (array_key_exists('flag',$arArgs)) {
	    $sFlagExpr = $arAgs['flag'];
	    $sFlagValue = xcVar::GetValue_fromExpression($sFlagExpr);
	    
	    // WRITING IN PROGRESS
	}
    }
}