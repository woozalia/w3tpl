<?php namespace w3tpl;
/*
  TAG: <get name=varname [index=offset]>
  ACTION: gets value of given variable
	checks function arguments, if function is defined
*/
class xcTag_get extends xcTag_Var {

    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'DUMP';
    }
    
    // -- PROPERTIES -- //
    // ++ EVENTS ++ //

    public function Go() {
	$sVarName = $this->RequireArgument('name');
	$sIndex = $this->ArgumentValueNz('index');
	
	$oVar = xcVar::GetVariable_FromExpression($sVarName,TRUE);
	$sOut = $oVar->GetValue();
	//die ("EXPRESSION: [$sVarName] VALUE=[$sOut]<pre>");
	return $sOut;
    }
    
    // -- EVENTS -- //

}