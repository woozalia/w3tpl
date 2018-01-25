 <?php
/*
  PURPOSE: class for managing tag-variables in w3tpl
  HISTORY:
    2012-06-03 IS THERE ANY REASON not to rename clsW3VarName to clsW3Var?
    2015-09-10 Renamed clsW3VarName to clsW3Var.
    2015-09-28 extracted from W3TPL.php
    2017-10-29 renamed clsW3Var -> xcVar
*/

 class xcVar {
/*
  RULES:
    * An *expression* can be:
    ** a literal value - "this is a string"
    ** a reference to a variable - "$theVar"
    ** a reference to a special function - @row.fieldname
    * A variable reference can be:
    ** scalar value - "$aScalar"
    ** array index - "$anArray[index_expression]" where "index_expression" is an expression
    * We automatically find the value of all inner elements as needed, but leave the outermost unresolved
	in order to allow for different operations depending on context.
*/
	public $Expr;	// code-expression to parse
	public $NameRaw;	// unparsed name
	public $Name;	// final name of variable or function being referenced
	public $Index;	// (optional) index into variable array
	public $ValRaw;	// unparsed value
	public $Value;	// loaded value of variable or function, for operations
	public $isFunc;

	public function __construct($iExpr = NULL) {
		$this->isFunc = FALSE;
		W3AddTrace('clsW3Var: init=['.$iExpr.']');
		if (!is_null($iExpr)) {
			$this->ParseExpr($iExpr);
		}
	}
	/*----
	  ACTION: Clears the variable's value and removes any array elements
	*/
	public function Clear() {
	    if ($this->IsArray()) {
		$ar = $this->Value;
		foreach ($ar as $key => $val) {
		    $objNew = new clsW3Var();
		    $objNew->Name = $this->Name;
		    $objNew->Index = $key;
		    $objNew->Clear();
		}
	    }
	    $this->Value = NULL;
	    $this->Store();
	}
	/*----
	  RETURNS: full variable name -- including array index, if present
	  HISTORY:
	    2011-09-19 crude implementation so we can store array indexes in page properties
	*/
	public function FullName() {
	    $out = $this->Name;
	    if ($this->IsElem()) {
		$out .= '['.$this->Index.']';
	    }
	    return $out;
	}

	protected static function GetVar($iNameExpr) {
	    $strCls = __CLASS__;
	    $objVar = new $strCls;
	    $objVar->FetchVar_byNameExpr($iNameExpr);
	    return $objVar;
	}
	protected static function GetVarVal($iNameExpr) {
	    $objVar = self::GetVar($iNameExpr);
	    return $objVar->Value;
	}
	/*----
	  ACTION:
	    * parses iNameExpr to derive its value
	    * names this variable as the result of that
	    * fetches the value of this variable based on its new name
	    ** If variable is an array element, looks up the value within the array.
	  OUTPUT:
	    RETURNS: value
	    SETS internal field "Value"
	    CALLS ParseName(), which also sets internal fields
	  USED BY: Fetch()
	  ASSUMES: this is a VARIABLE (ELEMENT or ARRAY), not a function or something else
	*/
	protected function FetchVar_byNameExpr($iNameExpr) {
	    global $wgW3Trace_indent;
	    global $wgW3Vars;

	    W3AddTrace('FetchVar_byNameExpr ('.$iNameExpr.')');
	    $wgW3Trace_indent++;

	    $this->ParseName($iNameExpr);
	    $strName = $this->Name;	// get name parsed from expression

	    if ($this->IsElem()) {
		$strIndex = $this->Index;
		$strTrace = ' - INDEX ['.$strIndex.']=>';
		if (isset($wgW3Vars[$strName][$strIndex])) {
		    $this->Value = $wgW3Vars[$strName][$strIndex];
		    $strTrace .= '&ldquo;'.$this->Value.'&rdquo;';
		} else {
		    $this->Value = NULL;
		    $strTrace .= 'NULL';
		}
	    } else {
		if (isset($wgW3Vars[$strName])) {
		    $this->Value = $wgW3Vars[$strName];
		    $strTrace = ' - VAR => '.$this->SummarizeValue();
		} else {
		    $this->Value = NULL;
		    $strTrace = ' - NULL';
		}
	    }
	    W3AddTrace($strTrace);

	    $wgW3Trace_indent--;
	    W3AddTrace('/FetchVar_byNameExpr ('.$iNameExpr.')');
	    return $this->Value;
	}
	/*----
	  RETURNS: summary of the current Value
	    If it's a scalar, just returns it.
	    If it's something else, describes it.
	    This is just quick & dirty for now; could be expanded later.
	  PUBLIC because this class is sometimes instantiated, and we need to show its values in the trace
	*/
	public function SummarizeValue() {
	    if (is_array($this->Value)) {
		return '(array['.count($this->Value).'])';
	    } else {
		return '['.$this->Value.']';
	    }
	}
	/*----
	  HISTORY:
	    2012-06-04 substantial rewrite of this and associated methods
	  OUTPUT:
	    RETURNS parsed value
	    SETS internal fields - see ParseName()
	*/
	protected function ParseExpr($iExpr) {
		global $wgW3_doTrace_vars;
		global $wgW3Trace_indent;

		$strExpr = $iExpr;

		W3AddTrace('PARSE-EXPR ['.$iExpr.']');
		$wgW3Trace_indent++;

		$chFirst = substr($strExpr,0,1);
		switch ($chFirst) {
		  case '$':
			W3AddTrace(' (PARSE-EXPR as VAR) EXPR=['.$strExpr.']');
			$strRef = strtolower(substr($strExpr,1));
			W3AddTrace(' - REF=['.$strRef.']');
			$strVal = $this->FetchVar_byNameExpr($strRef);
			W3AddTrace(' - VAL=['.$strVal.']');
			break;
		  case '@':
			W3AddTrace(' (PARSE-EXPR as FUNC) EXPR=['.$strExpr.']');
			$strRef = strtolower(substr($strExpr,1));
			W3AddTrace(' - REF=['.$strRef.']');
			//$strVal = self::ParseExpr($strRef);	// TO BE REWRITTEN
			$strVal = W3GetSysData($strRef);
			$this->isFunc = TRUE;
			W3AddTrace(' - VAL=['.$strVal.']');
			break;
		  default:
			// it's a literal, so the string is the value
			W3AddTrace(' - string ['.$strExpr.'] is literal; no parsing to do');
			$strVal = $strExpr;
		}
		if ($wgW3_doTrace_vars) {
			W3AddTrace(' => ['.ShowHTML($strExpr).'] EXPR}');
		}
		$wgW3Trace_indent--;
		W3AddTrace('/PARSE-EXPR');

		return $strVal;
	}

	public function ParseExpr_toName($iExpr) {
	    $this->ExprRaw = $iExpr;
	    $this->Name = $this->ParseExpr($iExpr);
	    W3AddTrace(' STORED ['.$this->Name.'] as NAME');
	}
	public function ParseExpr_toValue($iExpr) {
	    $this->ExprRaw = $iExpr;
	    $this->Value = $this->ParseExpr($iExpr);
	    W3AddTrace(' STORED ['.$this->Value.'] as VALUE');
	}
	/*----
	  ACTION: parses a variable name, which may include array syntax
	  OUTPUT: sets internal fields --
	    Name - the name of the base var or array
	    Index - array index, if any
	  USED BY: W3HookArgs->GetVarObj_Named_byArgVal()
	*/

	public function ParseName($iName) {
	    global $wgW3_doTrace_vars;
	    global $wgW3Trace_indent;

	    // normalize all name strings to lower case
	    $strName = strtolower($iName);
	    $this->NameRaw = $iName;
	    W3AddTrace('PARSE-NAME ['.ShowHTML($iName).']');
	    $wgW3Trace_indent++;

	    if (substr($strName,0,1) == '@') {
		$this->isFunc = TRUE;
		$this->Name = substr($strName,1);
		W3AddTrace('is func ['.$this->Name.']');
	    } else {
		W3AddTrace('is var');
		if (substr($strName, -1) == ']') {
		// name includes index offset
		    $idxOpen = strpos($strName,'[');
		    if ($idxOpen) {
			$idxShut = strpos($strName,']',$idxOpen);
			$vIndex = substr($strName,$idxOpen+1,$idxShut-$idxOpen-1);
			$strTrace = ' - INDEX['.$vIndex.'] (@'.$idxOpen.'-'.$idxShut.' in ['.$strName.']) -> [';
			$strTrace .= $vIndex.']';
			$strName = substr($strName,0,$idxOpen);		// strip off subscript in brackets

/*			$objIdx = new clsW3VarName($vIndex);
			$objIdx->Fetch();				// calculate value of index expression
			$this->Index = $objIdx->Value;
*/
			$this->Index = W3GetExpr($vIndex);

			$strTrace .= ' - NEW NAME=['.$strName.'] INDEX=['.$this->Index.']';
			W3AddTrace($strTrace);
		    }
		}
		$this->Name = $strName;
	    }
	    W3AddTrace('name:'.ShowHTML($this->SummarizeValue()).' = val:'.$this->SummarizeValue());

	    $wgW3Trace_indent--;
	    W3AddTrace('/PARSE-NAME');
	}

	public function Index($iVal=NULL) {
	    if (!is_null($iVal)) {
		$this->SetIndex($iVal);
	    }
	    return $this->Index;
	}
	public function SetIndex($iValue) {
		$this->Index = $iValue;
		W3AddTrace('clsW3Var.SetIndex of ('.$this->Name.') to ['.$iValue.']');
	}
	public function IsElem() {	// is element of an array?
		return !is_null($this->Index);
	}
	public function IsVar() {
		return !is_null($this->Name) && !$this->isFunc;
	}
	public function IsArray() {
		return is_array($this->Value);
	}
	/*---
	  ACTION: Saves the variable in page properties
	    If the variable is an array, saves each element in a separate property
	  HISTORY:
	    2011-09-19 written to encapsulate existing functionality in <let>
	*/
	public function Save(clsPageProps $iProps) {
	    if ($this->IsArray()) {
		$iProps->SaveArray($this->Value,$this->FullName());
	    } else {
		$iProps->SaveVal($this->Name,$this->Value);
	    }
	}
	/*----
	  ACTION: Loads the variable from page properties
	*/
	public function Load(clsPageProps $iProps) {
	    $this->Value = $iProps->LoadVal($this->Name);
	    $this->Store();
	}
	/*----
	  ACTION: Loads the variable from page properties as an array
	*/
	public function LoadArray(clsPageProps $iProps) {
	    W3AddTrace(' -- LoadArray ENTER');
	    $arVal = $iProps->LoadVals($this->Name);
	    if (is_array($arVal)) {
		W3AddTrace(' -- values for ['.$this->Name.'] array:');
		foreach ($arVal as $key => $val) {
		    W3AddTrace(' --- ['.$key.'] = ['.$val.']');
		    $objNew = new clsW3Var();
		    $objNew->Name = $this->Name;
		    $objNew->Index = $key;
		    $objNew->Value = $val;
		    $objNew->Store();
		}
	    } else {
		W3AddTrace(' -- not an array');
	    }
	    W3AddTrace(' -- LoadArray EXIT');
	}
	/*----
	  ACTION: Loads all of the page's properties as an array
	*/
	public function LoadAll(clsPageProps $iProps) {
	    W3AddTrace(' -- LoadAll ENTER');
	    $arVal = $iProps->LoadVal();	// load all page properties
	    if (is_array($arVal)) {
		W3AddTrace(' -- all values for current page:');
		foreach ($arVal as $key => $val) {
		    W3AddTrace(' --- ['.$key.'] = ['.$val.']');
		    $objNew = new clsW3Var();
		    $objNew->Name = $this->Name;
		    $objNew->Index = $key;
		    $objNew->Value = $val;
		    $objNew->Store();
		}
	    } else {
		W3AddTrace(' -- array not returned! (BUG)');
	    }
	    W3AddTrace(' -- LoadAll EXIT');
	}
	public function Fetch() {
	    global $wgW3Vars;

	    $strName = strtolower($this->Name);
	    if ($this->isFunc) {
		$this->Value = W3GetSysData($this->Name);
	    } elseif ($this->IsVar()) {
		$this->FetchVar_byNameExpr($strName);
	    } else {
		    // literal value - already set.
	    }
	    W3AddTrace('clsW3Var.Fetch: value='.$this->SummarizeValue());
	    $this->Trace();
	}
	public function Store() {
		global $wgW3Vars;

		$strName = $this->Name;
		$strVal = $this->Value;
		if ($this->isFunc) {
			W3SetSysData($strName,$strVal);
		} elseif ($strName) {
			$strName = strtolower($strName);
			if ($this->isElem()) {
				$strIndex = $this->Index;
				$wgW3Vars[$strName][$strIndex] = $strVal;
				W3AddTrace(__METHOD__.': '.$strName.'['.$strIndex.'] &larr; &ldquo;'.$strVal.'&rdquo;');
			} else {
				$wgW3Vars[$strName] = $strVal;
				W3AddTrace(__METHOD__.': ['.$strName.'] &larr; &ldquo;'.$strVal.'&rdquo;');
			}
			$this->Trace();
		} else {
			W3AddTrace(__METHOD__.': ERROR: no name for value &ldquo;'.$strVal.'&rdquo;');
			$this->Trace();
		}
	}
	/*----
	  PURPOSE: does the Fetch() work now and returns the variable's value
	  USAGE: mostly for debugging
	*/
	public function Value() {
	    $this->Fetch();
	    return $this->Value;
	}

	public function DoSort($iRev=FALSE, $iVal=FALSE) {
		if (is_array($this->Value)) {
			if ($iVal) {
			// sort by value
				if ($iRev) {
					$ok = arsort($this->Value);
				} else {
					$ok = asort($this->Value);
				}
			} else {
				if ($iRev) {
					$ok = arsort($this->Value);
				} else {
					$ok = ksort($this->Value);
				}
			}
			if ($ok) {
				W3AddTrace('LET sort ['.$this->Name.'] OK');
			} else {
				W3AddTrace('LET sort ['.$this->Name.'] <b>ERROR</b>: failed.');
			}
		} else {
			W3AddTrace('LET sort <b>ERROR</b>: ['.$this->Name.'] is not an array.');
		}
	}

	public function CheckCopy($iArgs) {
		if ($iArgs->Exists('copy')) {
			$strArgVal = $iArgs->GetArgVal('copy');
			// copy the value of the variable named by the "copy" argument, but not its name:
			$strVarVal = self::GetVarVal($strArgVal);
			$this->Value = $strVarVal;

			//$this->Store();	// update master variable array
			$strDbg = 'CheckCopy() set from arg copy=['.$strArgVal.'] value=['.$strVarVal.']; result = '.$this->RenderDump();
			W3AddTrace($strDbg);	// show raw input
			return TRUE;
		} else {
			return FALSE;
		}
	}

	public function Trace() {
	    W3AddTrace($this->RenderDump());
	}
	public function Dump() {
	    echo $this->RenderDump();
	}
	public function RenderDump() {
	    if (is_array($this->Value)) {
		$strVal = '<i>array!</i>';
	    } else {
		$strVal = $this->Value;
	    }

	    $out = 'clsW3Var.Trace: '.
	      'Name=['.$this->Name.'] '.
	      'Val=['.$strVal.'] '.
	      'Index=['.$this->Index.'] '.
	      TrueFalseHTML('is var',$this->IsVar()).' '.
	      TrueFalseHTML('is element',$this->IsElem()).' '.
	      TrueFalseHTML('is func',$this->isFunc);
	    return $out;
	}
	public function RenderDumpArray() {
	    return clsArray::Render($this->Value);
	}
}
