<?php
/**
 * © 2019 Palo Alto Networks, Inc.  All rights reserved.
 *
 * Licensed under SCRIPT SOFTWARE AGREEMENT, Palo Alto Networks, Inc., at https://www.paloaltonetworks.com/legal/script-software-license-1-0.pdf
 *
 */

echo "\n***********************************************\n";
echo "*********** " . basename(__FILE__) . " UTILITY **********\n\n";

set_include_path(dirname(__FILE__) . '/../' . PATH_SEPARATOR . get_include_path());
require_once dirname(__FILE__)."/../lib/pan_php_framework.php";

require_once dirname(__FILE__)."/../utils/lib/UTIL.php";
require_once("utils/lib/MERGER.php");


$supportedArguments = array();
$supportedArguments[] = array('niceName' => 'in', 'shortHelp' => 'input file ie: in=config.xml', 'argDesc' => '[filename]');
$supportedArguments[] = array('niceName' => 'out', 'shortHelp' => 'output file to save config after changes. Only required when input is a file. ie: out=save-config.xml', 'argDesc' => '[filename]');
$supportedArguments[] = array(
    'niceName' => 'DupAlgorithm',
    'shortHelp' =>
        "Specifies how to detect duplicates:\n" .
        "  - SameMembers: groups holding same members replaced by the one picked first (default)\n" .
        "  - SameIP4Mapping: groups resolving the same IP4 coverage will be replaced by the one picked first\n" .
        "  - WhereUsed: groups used exactly in the same location will be merged into 1 single groups with all members together\n",
    'argDesc' => 'SameMembers|SameIP4Mapping|WhereUsed');
$supportedArguments[] = array('niceName' => 'Location', 'shortHelp' => 'specify if you want to limit your query to a VSYS/DG. By default location=shared for Panorama, =vsys1 for PANOS', 'argDesc' => '=sys1|shared|dg1');
$supportedArguments[] = array('niceName' => 'mergeCountLimit', 'shortHelp' => 'stop operations after X objects have been merged', 'argDesc' => '100');
$supportedArguments[] = array('niceName' => 'pickFilter', 'shortHelp' => 'specify a filter a pick which object will be kept while others will be replaced by this one', 'argDesc' => '(name regex /^g/)');
$supportedArguments[] = array('niceName' => 'excludeFilter', 'shortHelp' => 'specify a filter to exclude objects from merging process entirely', 'argDesc' => '(name regex /^g/)');
$supportedArguments[] = array('niceName' => 'allowMergingWithUpperLevel', 'shortHelp' => 'when this argument is specified, it instructs the script to also look for duplicates in upper level');
$supportedArguments[] = array('niceName' => 'allowaddingmissingobjects', 'shortHelp' => 'when this argument is specified, it instructs the script to also add missing objects for duplicates in upper level');
$supportedArguments[] = array('niceName' => 'help', 'shortHelp' => 'this message');
$supportedArguments[] = array('niceName' => 'DebugAPI', 'shortHelp' => 'prints API calls when they happen');

$usageMsg = PH::boldText('USAGE: ') . "php " . basename(__FILE__) . " in=inputfile.xml [out=outputfile.xml] location=shared [DupAlgorithm=SameMembers] ['pickFilter=(name regex /^H-/)'] ...";


$PHP_FILE = __FILE__;
$utilType = "address-merger";


$merger = new MERGER($utilType, $argv, $PHP_FILE, $supportedArguments, $usageMsg);


if( isset(PH::$args['mergecountlimit']) )
    $merger->mergeCountLimit = PH::$args['mergecountlimit'];


if( isset(PH::$args['allowaddingmissingobjects']) )
    $merger->addMissingObjects = TRUE;

if( isset(PH::$args['dupalgorithm']) )
{
    $merger->dupAlg = strtolower(PH::$args['dupalgorithm']);
    if( $merger->dupAlg != 'samemembers' && $merger->dupAlg != 'sameip4mapping' && $merger->dupAlg != 'whereused' )
        $merger->display_error_usage_exit('unsupported value for dupAlgorithm: ' . PH::$args['dupalgorithm']);
}
else
    $merger->dupAlg = 'samemembers';


$merger->addressgroup_merging();


$merger->save_our_work( true );

echo "\n************* END OF SCRIPT " . basename(__FILE__) . " ************\n\n";

