<?php namespace w3tpl;
/*
  PURPOSE: base class for tags that handle variables
  REMEMBER:
    Values are only stored in the tag object when the tag-processing code puts them there.
      We therefore do not need a separate array to keep track of "old" values in order to
      allow current tag data to operate on them.
  HISTORY:
    2017-11-01 started
*/
abstract class xcTag_Var extends xcTag {

    /*----
      ACTION: checks to see if the "copy" attribute is present
	If it is, copies the value from the named variable.
      RETURNS: TRUE if "copy" is present, FALSE otherwise
      HISTORY:
	2017-11-07 This is currently not used anywhere except <let>, so moving it there for easier debugging.
	  At some point we will probably want a set of common attribute-handler functions, and then those can
	  be pulled back out into xcTag descendants as needed.
    */
    /*
    protected function CheckCopy() {
	if ($this->ArgExists('copy')) {
	    $sCopyName = $this->ArgValue('copy');		// get name of variable to copy
	    $sCopyValue = xcVar::GetVariableValue($sCopyName);	// get value of named variable
	    $this->SetInput($sCopyValue);			// replace tag input with value of other variable

	    return TRUE;					// success
	} else {
	    return FALSE;					// no copy requested
	}
    } */
}