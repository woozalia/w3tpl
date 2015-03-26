<?php
/*
  HISTORY: see http://htyp.org/W3TPL/history
  TO DO:
    Move "TO DO" items to Redmine.
    Move "ELEMENTS" items to HTYP, if not already there; not needed here.
    Get rid of the master variable array ($wgW3Vars) as a separate entity from the local object.
      It should be an array of objects, created as needed (cached).
      Scalars and Arrays should have separate classes. Maybe functions, too.
    An alternate <call> syntax might be good, where the function name and arguments are given explicitly:
      <run func=funcName arg1=value arg2=value input=arg3>this will get passed as the value of arg3</run>
      ...or possibly just define the function name as a tag for the shorter form: <funcName value1 value2>
    Functions should be able to return values
    Variables inside functions should be local by default; need a way to make them global as well if we do that
    <load> should do nothing by default
      "parse[=var]" should tell it to parse the page's contents and place the results in var
      "raw=var" should tell it to put the page's unparsed contents into var
      "props=var" should tell it to load the page's page_props into array var[]
      "smwprops=var" should tell it to load the page's Semantic MediaWiki properties into array var[]
  ELEMENTS:
    <hide>: Runs the parser on everything in between the tags, but doesn't display the result.
	    Useful for doing a lot of "programmish" stuff, so you can format it nicely and comment it without messing up your display
    <let>, <get>: much like PageVars extension, but using XML tags instead of {{#parser}} functions
    <func>: defines a function which can be called with arguments later
	    <arg>: optional method of passing arguments to a function
    <call>: call a previously defined function
    <dump>: show list of all variables (with values) and functions (with code)
    <if>, <else>: control structure
    <xploop list="\demarcated\list" repl=string-to-replace sep=separator></xploop>: Same as {{#xploop}}, but uses varname instead of $s$
    TO-DO <w3tpl></w3tpl>: The language itself
	    parser should later be optimized for execution time by using PHP intrinsic string fx instead of PHP-code loop
*/

// TODO: tag functions should be made into static methods here
class clsW3TPL_tags {
}