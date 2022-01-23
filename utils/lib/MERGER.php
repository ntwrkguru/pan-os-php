<?php
/**
 * ISC License
 *
 * Copyright (c) 2019, Palo Alto Networks Inc.
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

class MERGER extends UTIL
{
    public $utilType = null;
    public $location_array = array();
    public $pickFilter = null;
    public $excludeFilter = null;
    public $upperLevelSearch = FALSE;
    public $mergeCountLimit = FALSE;
    public $dupAlg = null;
    public $deletedObjects = array();
    public $addMissingObjects = FALSE;

    public function utilStart()
    {
        $this->usageMsg = PH::boldText('USAGE: ') . "php " . basename(__FILE__) . " in=inputfile.xml [out=outputfile.xml] location=shared [DupAlgorithm=XYZ] [MergeCountLimit=100] ['pickFilter=(name regex /^H-/)'] ...";

        $this->add_supported_arguments();


        $this->prepareSupportedArgumentsArray();

        PH::processCliArgs();

        $this->arg_validation();
        $this->help(PH::$args);
        $this->inDebugapiArgument();
        $this->inputValidation();
        $this->location_provided();

        $this->load_config();

        $this->location_array = $this->merger_location_array($this->utilType, $this->objectsLocation, $this->pan);


        $this->filterArgument( );


        $this->merger_arguments( );


        if( $this->utilType == "address-merger" )
            $this->address_merging();
        elseif( $this->utilType == "addressgroup-merger" )
            $this->addressgroup_merging();
        elseif( $this->utilType == "service-merger" )
            $this->service_merging();
        elseif( $this->utilType == "servicegroup-merger" )
            $this->servicegroup_merging();
        elseif( $this->utilType == "tag-merger" )
            $this->tag_merging();


        $this->merger_final_step();

    }

    function merger_location_array($utilType, $objectsLocation, $pan)
    {
        $this->utilType = $utilType;

        #if( $objectsLocation == 'any' || $objectsLocation == 'all' )
        if( $objectsLocation == 'any' )
        {
            if( $pan->isPanorama() )
            {
                $alldevicegroup = $pan->deviceGroups;
                #getDeviceGroups()
            }
            elseif( $pan->isFawkes() )
            {
                $subGroups = $pan->getContainers();
                $subGroups2 = $pan->getDeviceClouds();

                $alldevicegroup = array_merge( $subGroups, $subGroups2 );
            }
            elseif( $pan->isFirewall() )
                $alldevicegroup = $pan->virtualSystems;
            else
                $alldevicegroup = $pan->virtualSystems;

            $location_array = array();
            foreach( $alldevicegroup as $key => $tmp_location )
            {
                $objectsLocation = $tmp_location->name();
                $findLocation = $pan->findSubSystemByName($objectsLocation);
                if( $findLocation === null )
                    $this->locationNotFound( $objectsLocation );
                    #derr("cannot find DeviceGroup/VSYS named '{$objectsLocation}', check case or syntax");

                if( $this->utilType == "address-merger" || $this->utilType == "addressgroup-merger" )
                {
                    $store = $findLocation->addressStore;
                    $parentStore = $findLocation->owner->addressStore;
                }
                elseif( $this->utilType == "service-merger" || $this->utilType == "servicegroup-merger" )
                {
                    $store = $findLocation->serviceStore;
                    $parentStore = $findLocation->owner->serviceStore;
                }
                elseif( $this->utilType == "tag-merger" )
                {
                    $store = $findLocation->tagStore;
                    $parentStore = $findLocation->owner->tagStore;
                }
                if( get_class( $findLocation->owner ) == "FawkesConf" )
                    $parentStore = null;
                

                $location_array[$key]['findLocation'] = $findLocation;
                $location_array[$key]['store'] = $store;
                $location_array[$key]['parentStore'] = $parentStore;
                if( $pan->isPanorama() )
                {
                    $childDeviceGroups = $findLocation->childDeviceGroups(TRUE);
                    $location_array[$key]['childDeviceGroups'] = $childDeviceGroups;
                }
                elseif( $pan->isFawkes() )
                {
                    //child Container/CloudDevices
                    //Todo: swaschkut 20210414
                    $location_array[$key]['childDeviceGroups'] = array();
                }
                else
                    $location_array[$key]['childDeviceGroups'] = array();

            }

            $location_array = array_reverse($location_array);

            if( !$pan->isFawkes() )
            {
                $location_array[$key + 1]['findLocation'] = 'shared';
                if( $this->utilType == "address-merger" || $this->utilType == "addressgroup-merger" )
                    $location_array[$key + 1]['store'] = $pan->addressStore;
                elseif( $this->utilType == "service-merger" || $this->utilType == "servicegroup-merger" )
                    $location_array[$key + 1]['store'] = $pan->serviceStore;
                elseif( $this->utilType == "tag-merger" )
                    $location_array[$key + 1]['store'] = $pan->tagStore;

                $location_array[$key + 1]['parentStore'] = null;
                $location_array[$key + 1]['childDeviceGroups'] = $alldevicegroup;
            }


        }
        else
        {
            if( !$pan->isFawkes() && $objectsLocation == 'shared' )
            {
                if( $this->utilType == "address-merger" || $this->utilType == "addressgroup-merger" )
                    $store = $pan->addressStore;
                elseif( $this->utilType == "service-merger" || $this->utilType == "servicegroup-merger" )
                    $store = $pan->serviceStore;
                elseif( $this->utilType == "tag-merger" )
                    $store = $pan->tagStore;

                $parentStore = null;
                $location_array[0]['findLocation'] = $objectsLocation;
                $location_array[0]['store'] = $store;
                $location_array[0]['parentStore'] = $parentStore;
            }
            else
            {
                $findLocation = $pan->findSubSystemByName($objectsLocation);
                if( $findLocation === null )
                    $this->locationNotFound( $objectsLocation );
                    #derr("cannot find DeviceGroup/VSYS named '{$objectsLocation}', check case or syntax");

                if( $this->utilType == "address-merger" || $this->utilType == "addressgroup-merger" )
                {
                    $store = $findLocation->addressStore;

                    if( $pan->isPanorama() && isset($findLocation->parentDeviceGroup) && $findLocation->parentDeviceGroup !== null )
                        $parentStore = $findLocation->parentDeviceGroup->addressStore;
                    elseif( $pan->isFawkes() && isset($current->owner->parentContainer) && $current->owner->parentContainer !== null )
                        $parentStore = $findLocation->parentContainer->addressStore;
                    else
                        $parentStore = $findLocation->owner->addressStore;
                }
                elseif( $this->utilType == "service-merger" || $this->utilType == "servicegroup-merger" )
                {
                    $store = $findLocation->serviceStore;

                    if( $pan->isPanorama() && isset($findLocation->parentDeviceGroup) && $findLocation->parentDeviceGroup !== null )
                        $parentStore = $findLocation->parentDeviceGroup->serviceStore;
                    elseif( $pan->isFawkes() && isset($current->owner->parentContainer) && $current->owner->parentContainer !== null )
                        $parentStore = $findLocation->parentContainer->serviceStore;
                    else
                        $parentStore = $findLocation->owner->serviceStore;
                }
                elseif( $this->utilType == "tag-merger" )
                {
                    $store = $findLocation->tagStore;

                    if( $pan->isPanorama() && isset($findLocation->parentDeviceGroup) && $findLocation->parentDeviceGroup !== null )
                        $parentStore = $findLocation->parentDeviceGroup->tagStore;
                    elseif( $pan->isFawkes() && isset($current->owner->parentContainer) && $current->owner->parentContainer !== null )
                        $parentStore = $findLocation->parentContainer->tagStore;
                    else
                        $parentStore = $findLocation->owner->tagStore;
                }
                if( get_class( $findLocation->owner ) == "FawkesConf" )
                    $parentStore = null;

                $location_array[0]['findLocation'] = $findLocation;
                $location_array[0]['store'] = $store;
                $location_array[0]['parentStore'] = $parentStore;
            }

            if( $pan->isPanorama() )
            {
                if( $objectsLocation == 'shared' )
                    $childDeviceGroups = $pan->deviceGroups;
                else
                    $childDeviceGroups = $findLocation->childDeviceGroups(TRUE);
                $location_array[0]['childDeviceGroups'] = $childDeviceGroups;
            }
            elseif( $pan->isFawkes() )
            {
                //child Container/CloudDevices
                //Todo: swaschkut 20210414
                $location_array[0]['childDeviceGroups'] = array();
            }
            else
                $location_array[0]['childDeviceGroups'] = array();
        }

        return $location_array;
    }


    function filterArgument( )
    {
        if( $this->utilType == "address-merger" || $this->utilType == "addressgroup-merger" )
            $type = 'address';
        elseif( $this->utilType == "service-merger" || $this->utilType == "servicegroup-merger" )
            $type = 'service';
        elseif( $this->utilType == "tag-merger" )
            $type = 'tag';

        if( isset(PH::$args['pickfilter']) )
        {
            $this->pickFilter = new RQuery($type);
            $errMsg = '';
            if( $this->pickFilter->parseFromString(PH::$args['pickfilter'], $errMsg) === FALSE )
                derr("invalid pickFilter was input: " . $errMsg);
            PH::print_stdout( " - pickFilter was input: " );
            $this->pickFilter->display();
            PH::print_stdout( "" );

        }

        if( isset(PH::$args['excludefilter']) )
        {
            $this->excludeFilter = new RQuery($type);
            $errMsg = '';
            if( $this->excludeFilter->parseFromString(PH::$args['excludefilter'], $errMsg) === FALSE )
                derr("invalid pickFilter was input: " . $errMsg);
            PH::print_stdout( " - excludeFilter was input: " );
            $this->excludeFilter->display();
            PH::print_stdout( "" );
        }

        if( isset(PH::$args['allowmergingwithupperlevel']) )
            $this->upperLevelSearch = TRUE;
    }

    function findAncestor( $current, $object )
    {
        while( TRUE )
        {
            $findAncestor = $current->find($object->name(), null, TRUE);
            if( $findAncestor !== null )
            {
                return $findAncestor;
                break;
            }

            if( isset($current->owner->parentDeviceGroup) && $current->owner->parentDeviceGroup !== null )
                $current = $current->owner->parentDeviceGroup->addressStore;
            elseif( isset($current->owner->parentContainer) && $current->owner->parentContainer !== null )
                $current = $current->owner->parentContainer->addressStore;
            elseif( isset($current->owner->owner) && $current->owner->owner !== null && !$current->owner->owner->isFawkes() )
                $current = $current->owner->owner->addressStore;
            else
            {
                return null;
                break;
            }
        }

    }

    function add_supported_arguments()
    {
        $this->supportedArguments[] = array('niceName' => 'in', 'shortHelp' => 'input file ie: in=config.xml', 'argDesc' => '[filename]');
        $this->supportedArguments[] = array('niceName' => 'out', 'shortHelp' => 'output file to save config after changes. Only required when input is a file. ie: out=save-config.xml', 'argDesc' => '[filename]');
        $this->supportedArguments[] = array('niceName' => 'Location', 'shortHelp' => 'specify if you want to limit your query to a VSYS/DG. By default location=shared for Panorama, =vsys1 for PANOS', 'argDesc' => 'sys1|shared|dg1');

        $this->supportedArguments[] = array('niceName' => 'mergeCountLimit', 'shortHelp' => 'stop operations after X objects have been merged', 'argDesc' => '100');

        if( $this->utilType == "service-merger" )
        {
            $this->supportedArguments[] = array('niceName' => 'pickFilter',
                'shortHelp' => "specify a filter a pick which object will be kept while others will be replaced by this one.\n" .
                    "   ie: 2 services are found to be mergeable: 'H-1.1.1.1' and 'Server-ABC'. Then by using pickFilter=(name regex /^H-/) you would ensure that object H-1.1.1.1 would remain and Server-ABC be replaced by it.",
                'argDesc' => '(name regex /^g/)');
            $this->supportedArguments[] = array('niceName' => 'DupAlgorithm',
                'shortHelp' => "Specifies how to detect duplicates:\n" .
                    "  - SameDstSrcPorts: objects with same Dst and Src ports will be replaced by the one picked (default)\n" .
                    "  - SamePorts: objects with same Dst ports will be replaced by the one picked\n" .
                    "  - WhereUsed: objects used exactly in the same location will be merged into 1 single object and all ports covered by these objects will be aggregated\n",
                'argDesc' => 'SameDstSrcPorts|SamePorts|WhereUsed');
        }
        else
            $this->supportedArguments[] = array('niceName' => 'pickFilter', 'shortHelp' => 'specify a filter a pick which object will be kept while others will be replaced by this one', 'argDesc' => '(name regex /^g/)');

        if( $this->utilType == "address-merger" )
        {
            $this->supportedArguments[] = array('niceName' => 'DupAlgorithm',
                'shortHelp' => "Specifies how to detect duplicates:\n" .
                    "  - SameAddress: objects with same Network-Value will be replaced by the one picked (default)\n" .
                    "  - Identical: objects with same network-value and same name will be replaced by the one picked\n" .
                    "  - WhereUsed: objects used exactly in the same location will be merged into 1 single object and all ports covered by these objects will be aggregated\n",
                'argDesc' => 'SameAddress | Identical | WhereUsed');
        }
        elseif( $this->utilType == "addressgroup-merger" )
        {
            $this->supportedArguments[] = array('niceName' => 'DupAlgorithm',
                'shortHelp' => "Specifies how to detect duplicates:\n" .
                    "  - SameMembers: groups holding same members replaced by the one picked first (default)\n" .
                    "  - SameIP4Mapping: groups resolving the same IP4 coverage will be replaced by the one picked first\n" .
                    "  - WhereUsed: groups used exactly in the same location will be merged into 1 single groups with all members together\n",
                'argDesc' => 'SameMembers|SameIP4Mapping|WhereUsed');
        }
        elseif( $this->utilType == "servicegroup-merger" )
        {
            $this->supportedArguments[] = array('niceName' => 'DupAlgorithm',
                'shortHelp' => "Specifies how to detect duplicates:\n" .
                    "  - SameMembers: groups holding same members replaced by the one picked first (default)\n" .
                    "  - SamePortMapping: groups resolving the same port mapping coverage will be replaced by the one picked first\n" .
                    "  - WhereUsed: groups used exactly in the same location will be merged into 1 single groups with all members together\n",
                'argDesc' => 'SameMembers|SamePortMapping|WhereUsed');
        }
        elseif( $this->utilType == "tag-merger" )
        {
            $this->supportedArguments[] = array('niceName' => 'DupAlgorithm',
                'shortHelp' => "Specifies how to detect duplicates:\n" .
                    "  - SameColor: objects with same TAG-color will be replaced by the one picked (default)\n" .
                    "  - Identical: objects with same TAG-color and same name will be replaced by the one picked\n" .
                    "  - WhereUsed: objects used exactly in the same location will be merged into 1 single object and all ports covered by these objects will be aggregated\n",
                'argDesc' => 'SameColor | Identical | WhereUsed');
        }

        $this->supportedArguments[] = array('niceName' => 'excludeFilter', 'shortHelp' => 'specify a filter to exclude objects from merging process entirely', 'argDesc' => '(name regex /^g/)');
        $this->supportedArguments[] = array('niceName' => 'allowMergingWithUpperLevel', 'shortHelp' => 'when this argument is specified, it instructs the script to also look for duplicates in upper level');
        $this->supportedArguments[] = array('niceName' => 'allowaddingmissingobjects', 'shortHelp' => 'when this argument is specified, it instructs the script to also add missing objects for duplicates in upper level');
        $this->supportedArguments[] = array('niceName' => 'help', 'shortHelp' => 'this message');
        $this->supportedArguments[] = array('niceName' => 'DebugAPI', 'shortHelp' => 'prints API calls when they happen');

        $this->supportedArguments[] = array('niceName' => 'exportCSV', 'shortHelp' => 'when this argument is specified, it instructs the script to display the kept and removed objects per value');
    }

    function merger_arguments( )
    {
        $display_error = false;


        if( isset(PH::$args['mergecountlimit']) )
            $this->mergeCountLimit = PH::$args['mergecountlimit'];

        if( isset(PH::$args['dupalgorithm']) )
        {
            $this->dupAlg = strtolower(PH::$args['dupalgorithm']);
        }

        if( $this->utilType == "address-merger" )
        {
            if( $this->dupAlg != 'sameaddress' && $this->dupAlg != 'whereused' && $this->dupAlg != 'identical' )
                $display_error = true;

            $defaultDupAlg = 'sameaddress';
        }
        elseif( $this->utilType == "addressgroup-merger" )
        {
            if( $this->dupAlg != 'samemembers' && $this->dupAlg != 'sameip4mapping' && $this->dupAlg != 'whereused' )
                $display_error = true;

            if( isset(PH::$args['allowaddingmissingobjects']) )
                $this->addMissingObjects = TRUE;

            $defaultDupAlg = 'samemembers';
        }
        elseif( $this->utilType == "service-merger" )
        {
            if( $this->dupAlg != 'sameports' && $this->dupAlg != 'whereused' && $this->dupAlg != 'samedstsrcports' )
                $display_error = true;

            $defaultDupAlg = 'samedstsrcports';
        }
        elseif( $this->utilType == "servicegroup-merger" )
        {
            if( $this->dupAlg != 'samemembers' && $this->dupAlg != 'sameportmapping' && $this->dupAlg != 'whereused' )
                $display_error = true;

            $defaultDupAlg = 'samemembers';
        }
        elseif( $this->utilType == "tag-merger" )
        {
            if( $this->dupAlg != 'samecolor' && $this->dupAlg != 'whereused' && $this->dupAlg != 'identical' && $this->dupAlg != 'samename' )
                $display_error = true;

            $defaultDupAlg = 'identical';
        }




        if( isset(PH::$args['dupalgorithm']) )
        {
            #$this->dupAlg = strtolower(PH::$args['dupalgorithm']);
            if( $display_error )
                $this->display_error_usage_exit('unsupported value for dupAlgorithm: ' . PH::$args['dupalgorithm']);
        }
        else
            $this->dupAlg = $defaultDupAlg;

    }

    function addressgroup_merging()
    {
        foreach( $this->location_array as $tmp_location )
        {
            $store = $tmp_location['store'];
            $findLocation = $tmp_location['findLocation'];
            $parentStore = $tmp_location['parentStore'];
            if( $this->upperLevelSearch )
                $childDeviceGroups = $tmp_location['childDeviceGroups'];
            else
                $childDeviceGroups = array();

            PH::print_stdout( " - upper level search status : " . boolYesNo($this->upperLevelSearch) . "" );
            if( is_string($findLocation) )
                PH::print_stdout( " - location 'shared' found" );
            else
                PH::print_stdout( " - location '{$findLocation->name()}' found" );
            PH::print_stdout( " - found {$store->count()} address Objects" );
            PH::print_stdout( " - DupAlgorithm selected: {$this->dupAlg}" );
            PH::print_stdout( " - computing AddressGroup values database ... " );
            sleep(1);

            /**
             * @param AddressGroup $object
             * @return string
             */
            if( $this->dupAlg == 'samemembers' )
                $hashGenerator = function ($object) {
                    /** @var AddressGroup $object */
                    $value = '';

                    $members = $object->members();
                    usort($members, '__CmpObjName');

                    foreach( $members as $member )
                    {
                        $value .= './.' . $member->name();
                    }

                    //$value = md5($value);

                    return $value;
                };
            elseif( $this->dupAlg == 'sameip4mapping' )
                $hashGenerator = function ($object) {
                    /** @var AddressGroup $object */
                    $value = '';

                    $mapping = $object->getFullMapping();

                    $value = $mapping['ip4']->dumpToString();

                    if( count($mapping['unresolved']) > 0 )
                    {
                        ksort($mapping['unresolved']);
                        $value .= '//unresolved:/';

                        foreach( $mapping['unresolved'] as $unresolvedEntry )
                            $value .= $unresolvedEntry->name() . '.%.';
                    }
                    //$value = md5($value);

                    return $value;
                };
            elseif( $this->dupAlg == 'whereused' )
                $hashGenerator = function ($object) {
                    if( $object->countReferences() == 0 )
                        return null;

                    /** @var AddressGroup $object */
                    $value = $object->getRefHashComp() . '//dynamic:' . boolYesNo($object->isDynamic());

                    return $value;
                };
            else
                derr("unsupported dupAlgorithm");

//
// Building a hash table of all address objects with same value
//
            if( $this->upperLevelSearch )
                $objectsToSearchThrough = $store->nestedPointOfView();
            else
                $objectsToSearchThrough = $store->addressGroups();

            $child_hashMap = array();
            //todo: childDG/childDG to parentDG merge is always done; should it not combined to upperLevelSearch value?
            foreach( $childDeviceGroups as $dg )
            {
                /** @var DeviceGroup $dg */
                foreach( $dg->addressStore->addressGroups() as $object )
                {
                    if( !$object->isGroup() || $object->isDynamic() )
                        continue;

                    if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        continue;

                    $value = $hashGenerator($object);
                    if( $value === null )
                        continue;

                    #PH::print_stdout( "add objNAME: " . $object->name() . " DG: " . $object->owner->owner->name() );
                    $child_hashMap[$value][] = $object;
                }
            }

            $hashMap = array();
            $upperHashMap = array();
            foreach( $objectsToSearchThrough as $object )
            {
                if( !$object->isGroup() || $object->isDynamic() )
                    continue;

                if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                    continue;

                $skipThisOne = FALSE;

                // Object with descendants in lower device groups should be excluded
                if( $this->pan->isPanorama() )
                {
                    /*
                    foreach( $childDeviceGroups as $dg )
                    {
                        if( $dg->addressStore->find($object->name(), null, FALSE) !== null )
                        {
                            $skipThisOne = TRUE;
                            break;
                        }
                    }
                    if( $skipThisOne )
                        continue;
                    */
                }
                elseif( $this->pan->isFawkes() && $object->owner === $store )
                {
                    //do something
                }

                $value = $hashGenerator($object);
                if( $value === null )
                    continue;

                if( $object->owner === $store )
                {
                    $hashMap[$value][] = $object;
                    if( $parentStore !== null )
                    {
                        $findAncestor = $parentStore->find($object->name(), null, TRUE);
                        if( $findAncestor !== null )
                            $object->ancestor = $findAncestor;
                    }
                }
                else
                    $upperHashMap[$value][] = $object;
            }

//
// Hashes with single entries have no duplicate, let's remove them
//
            $countConcernedObjects = 0;
            foreach( $hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset(reset($hash)->ancestor) )
                {
                    //PH::print_stdout( "\nancestor not found for ".reset($hash)->name()."" );
                    unset($hashMap[$index]);
                }
                else
                    $countConcernedObjects += count($hash);
            }
            unset($hash);
            $countConcernedChildObjects = 0;
            foreach( $child_hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset(reset($hash)->ancestor) )
                    unset($child_hashMap[$index]);
                else
                    $countConcernedChildObjects += count($hash);
            }
            unset($hash);


            PH::print_stdout( " - found " . count($hashMap) . " duplicate values totalling {$countConcernedObjects} groups which are duplicate" );

            PH::print_stdout( " - found " . count($child_hashMap) . " duplicates childDG values totalling {$countConcernedChildObjects} address objects which are duplicate" );


            PH::print_stdout( "\n\nNow going after each duplicates for a replacement" );

            $countRemoved = 0;
            foreach( $hashMap as $index => &$hash )
            {
                #$skip = false;

                PH::print_stdout( "" );
                PH::print_stdout( " - value '{$index}'" );

                $pickedObject = null;

                if( $this->pickFilter !== null )
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        foreach( $upperHashMap[$index] as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($upperHashMap[$index]);

                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        foreach( $hash as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($hash);

                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }
                else
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        $pickedObject = reset($upperHashMap[$index]);
                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        $pickedObject = reset($hash);
                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }

                // Merging loop finally!
                foreach( $hash as $object )
                {
                    /** @var AddressGroup $object */
                    if( isset($object->ancestor) )
                    {
                        $ancestor = $object->ancestor;
                        /** @var AddressGroup $ancestor */
                        if( $this->upperLevelSearch && $ancestor->isGroup() && !$ancestor->isDynamic() && $this->dupAlg != 'whereused' )
                        {
                            if( $hashGenerator($object) != $hashGenerator($ancestor) )
                            {
                                $ancestor->displayValueDiff( $object, 7 );

                                if( $this->addMissingObjects )
                                {
                                    $diff = $ancestor->getValueDiff( $object );

                                    if( count($diff['minus']) != 0 )
                                        foreach( $diff['minus'] as $d )
                                        {
                                            /** @var Address|AddressGroup $d */

                                            if( $ancestor->owner->find( $d->name() ) !== null )
                                            {
                                                $text = "      - adding objects to group: ";
                                                $text .= $d->name();
                                                PH::print_stdout($text);
                                                if( $this->apiMode )
                                                    $ancestor->API_addMember( $d );
                                                else
                                                    $ancestor->addMember( $d );
                                            }
                                        }

                                    if( count($diff['plus']) != 0 )
                                        foreach( $diff['plus'] as $d )
                                        {
                                            /** @var Address|AddressGroup $d */
                                            //TMP usage to clean DG level ADDRESSgroup up
                                            $object->addMember( $d );
                                        }
                                }
                            }

                            if( $hashGenerator($object) == $hashGenerator($ancestor) )
                            {
                                $text = "    - group '{$object->name()}' merged with its ancestor, deleting this one... ";
                                $object->replaceMeGlobally($ancestor);
                                if( $this->apiMode )
                                    $object->owner->API_remove($object, TRUE);
                                else
                                    $object->owner->remove($object, TRUE);

                                PH::print_stdout( $text );

                                if( $pickedObject === $object )
                                    $pickedObject = $ancestor;

                                $countRemoved++;
                                if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                                {
                                    PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                                    break 2;
                                }
                                continue;
                            }
                        }
                        PH::print_stdout( "    - group '{$object->name()}' cannot be merged because it has an ancestor" );
                        continue;
                    }

                    if( $object === $pickedObject )
                        continue;

                    if( $this->dupAlg == 'whereused' )
                    {
                        PH::print_stdout( "    - merging '{$object->name()}' members into '{$pickedObject->name()}': " );
                        foreach( $object->members() as $member )
                        {
                            $text = "     - adding member '{$member->name()}'... ";
                            if( $this->apiMode )
                                $pickedObject->API_addMember($member);
                            else
                                $pickedObject->addMember($member);
                            PH::print_stdout( $text );
                        }
                        PH::print_stdout( "    - now removing '{$object->name()} from where it's used" );
                        if( $this->apiMode )
                        {
                            $object->API_removeWhereIamUsed(TRUE, 6);
                            $text = "    - deleting '{$object->name()}'... ";
                            $object->owner->API_remove($object);

                            PH::print_stdout( $text );
                        }
                        else
                        {
                            $object->removeWhereIamUsed(TRUE, 6);
                            $text = "    - deleting '{$object->name()}'... ";
                            $object->owner->remove($object);

                            PH::print_stdout( $text );
                        }
                    }
                    else
                    {
                        /*
                        if( $pickedObject->has( $object ) )
                        {
                            PH::print_stdout(  "   * SKIPPED : the pickedgroup {$pickedObject->_PANC_shortName()} has an object member named '{$object->_PANC_shortName()} that is planned to be replaced by this group" );
                            $skip = true;
                            continue;
                        }*/
                        PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                        $success = $object->__replaceWhereIamUsed($this->apiMode, $pickedObject, TRUE, 5);

                        if( $success )
                        {
                            PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                            if( $this->apiMode )
                            {
                                //true flag needed for nested groups in a specific constellation
                                $object->owner->API_remove($object, TRUE);
                            }
                            else
                            {
                                $object->owner->remove($object, TRUE);
                            }
                        }
                    }

                    #if( $skip )
                    #    continue;

                    $countRemoved++;

                    if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                    {
                        PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                        break 2;
                    }
                }
            }

            $countChildRemoved = 0;
            $countChildCreated = 0;
            foreach( $child_hashMap as $index => &$hash )
            {
                PH::print_stdout( "" );
                PH::print_stdout( " - value '{$index}'" );
                $this->deletedObjects[$index]['kept'] = "";
                $this->deletedObjects[$index]['removed'] = "";


                $pickedObject = null;

                if( $this->pickFilter !== null )
                {
                    foreach( $hash as $object )
                    {
                        if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        {
                            $pickedObject = $object;
                            break;
                        }
                    }
                    if( $pickedObject === null )
                        $pickedObject = reset($hash);
                }
                else
                {
                    $pickedObject = reset($hash);
                }


                $tmp_DG_name = $store->owner->name();
                if( $tmp_DG_name == "" )
                    $tmp_DG_name = 'shared';

                $tmp_address = $store->find( $pickedObject->name() );
                if( $tmp_address == null )
                {
                    PH::print_stdout( "   * move object to DG: '".$tmp_DG_name."' : '".$pickedObject->name()."'" );

                    $skip = false;
                    foreach( $pickedObject->members() as $memberObject )
                        if( $store->find($memberObject->name()) === null )
                        {
                            PH::print_stdout(  "   * SKIPPED : this group has an object named '{$memberObject->name()} that does not exist in target location '{$store->owner->name()}'" );
                            $skip = true;
                            break;
                        }
                    if( $skip )
                        continue;

                    /** @var AddressStore $store */
                    if( $this->apiMode )
                    {
                        $oldXpath = $pickedObject->getXPath();
                        $pickedObject->owner->remove($pickedObject);
                        $store->add($pickedObject);
                        $pickedObject->API_sync();
                        $this->pan->connector->sendDeleteRequest($oldXpath);
                    }
                    else
                    {
                        $pickedObject->owner->remove($pickedObject);
                        $store->add($pickedObject);
                    }


                    $countChildCreated++;
                }
                else
                {
                    if( !$tmp_address->isGroup() )
                    {
                        PH::print_stdout( "    - SKIP: object name '{$pickedObject->_PANC_shortName()}' of type AddressGroup can not be merged with object name: '{$tmp_address->_PANC_shortName()}' of type Address" );
                        continue;
                    }

                    $pickedObject_value = $hashGenerator($pickedObject);
                    $tmp_address_value = $hashGenerator($tmp_address);

                    if( $pickedObject_value == $tmp_address_value )
                    {
                        PH::print_stdout( "   * keeping object '{$tmp_address->_PANC_shortName()}'" );
                    }
                    else
                    {
                        PH::print_stdout( "    - SKIP: object name '{$pickedObject->_PANC_shortName()}' [with value '{$pickedObject_value}'] is not IDENTICAL to object name: '{$tmp_address->_PANC_shortName()}' [with value '{$tmp_address_value}']" );
                        continue;
                    }
                }

                // Merging loop finally!
                foreach( $hash as $objectIndex => $object )
                {
                    if( $object !==  $tmp_address)
                    {
                        PH::print_stdout( "    - group '{$object->name()}' DG: '".$object->owner->owner->name()."' merged with its ancestor at DG: '".$store->owner->name()."', deleting this one... " );

                        PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                        $success = $object->__replaceWhereIamUsed($this->apiMode, $tmp_address, TRUE, 5);

                        if( $success )
                        {
                            if( $this->apiMode )
                                $object->owner->API_remove($object, TRUE);
                            else
                                $object->owner->remove($object, TRUE);

                            $countChildRemoved++;
                        }
                    }
                }
            }

            PH::print_stdout( "\n\nDuplicates removal is now done. Number of objects after cleanup: '{$store->countAddressGroups()}' (removed {$countRemoved} groups)\n" );
            if( count( $child_hashMap ) >0 )
                PH::print_stdout( "Duplicates ChildDG removal is now done. Number of objects after cleanup: '{$store->countAddresses()}' (removed/created {$countChildRemoved}/{$countChildCreated} addresses)\n" );

            PH::print_stdout( "\n\n***********************************************\n" );

            PH::print_stdout( "\n" );
        }    
    }

    function address_merging()
    {
        foreach( $this->location_array as $tmp_location )
        {
            $store = $tmp_location['store'];
            $findLocation = $tmp_location['findLocation'];
            $parentStore = $tmp_location['parentStore'];
            if( $this->upperLevelSearch )
                $childDeviceGroups = $tmp_location['childDeviceGroups'];
            else
                $childDeviceGroups = array();

            PH::print_stdout( " - upper level search status : " . boolYesNo($this->upperLevelSearch) . "" );
            if( is_string($findLocation) )
                PH::print_stdout( " - location 'shared' found" );
            else
                PH::print_stdout( " - location '{$findLocation->name()}' found" );
            PH::print_stdout( " - found {$store->countAddresses()} address Objects" );
            PH::print_stdout( " - DupAlgorithm selected: {$this->dupAlg}" );
            PH::print_stdout( " - computing address values database ... " );
            sleep(1);

//
// Building a hash table of all address objects with same value
//
            if( $this->upperLevelSearch )
            {
                $objectsToSearchThrough = $store->nestedPointOfView();
                #$objectsToSearchThrough = $store->nestedPointOfView_sven();
            }
            else
                $objectsToSearchThrough = $store->addressObjects();

            $hashMap = array();
            $child_hashMap = array();
            $child_NamehashMap = array();
            $upperHashMap = array();
            if( $this->dupAlg == 'sameaddress' || $this->dupAlg == 'identical' )
            {
                //todo: childDG/childDG to parentDG merge is always done; should it not combined to upperLevelSearch value?
                foreach( $childDeviceGroups as $dg )
                {
                    foreach( $dg->addressStore->addressObjects() as $object )
                    {
                        if( !$object->isAddress() )
                            continue;
                        if( $object->isTmpAddr() )
                            continue;

                        if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            continue;

                        $value = $object->value();
                        $value = $object->type() . '-' . $value;

                        #PH::print_stdout( "add objNAME: " . $object->name() . " DG: " . $object->owner->owner->name() . "" );
                        $child_hashMap[$value][] = $object;
                        $child_NamehashMap[$object->name()][] = $object;
                    }
                }


                foreach( $objectsToSearchThrough as $object )
                {
                    if( !$object->isAddress() )
                        continue;
                    if( $object->isTmpAddr() )
                        continue;

                    if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        continue;

                    $skipThisOne = FALSE;

                    // Object with descendants in lower device groups should be excluded
                    if( $this->pan->isPanorama() && $object->owner === $store )
                    {

                        /*
                        foreach( $childDeviceGroups as $dg )
                        {
                            foreach( $dg->addressStore->addressObjects() as $object )
                            {
                                if( !$object->isAddress() )
                                    continue;
                                if( $object->isTmpAddr() )
                                    continue;

                                $value = $object->value();

                                PH::print_stdout( "add objNAME: ".$object->name(). " DG: ".$object->owner->owner->name()."" );
                                $child_hashMap[$value][] = $object;
                            }*/
                            /*
                             * //this does not make sense, why we should NOT merge higher level objects, if same name is available at lower???
                            if( $dg->addressStore->find($object->name(), null, FALSE) !== null )
                            {
                                $tmp_obj = $dg->addressStore->find($object->name(), null, FALSE);

                                PH::print_stdout( "\n- object '" . $object->name() . "' [value '{$object->value()}'] skipped because of same object name [ ";
                                if( $tmp_obj->isAddress() )
                                    PH::print_stdout( "with value '{$tmp_obj->value()}'";
                                else
                                    PH::print_stdout( "but as ADDRESSGROUP";
                                PH::print_stdout( " ] available at lower level DG: " . $dg->name() . "" );

                                $skipThisOne = TRUE;
                                break;
                            }*/
                        //}

                        /*
                        if( $skipThisOne )
                            continue;
                        */


                    }

                    $value = $object->value();

                    // if object is /32, let's remove it to match equivalent non /32 syntax
                    if( $object->isType_ipNetmask() && strpos($object->value(), '/32') !== FALSE )
                        $value = substr($value, 0, strlen($value) - 3);

                    $value = $object->type() . '-' . $value;

                    if( $object->owner === $store )
                    {
                        $hashMap[$value][] = $object;
                        if( $parentStore !== null )
                        {
                            $object->ancestor = self::findAncestor( $parentStore, $object );
                            /*
                            while( TRUE )
                            {

                                $findAncestor = $current->find($object->name(), null, TRUE);
                                if( $findAncestor !== null )
                                {
                                    $object->ancestor = $findAncestor;
                                    break;
                                }

                                if( isset($current->owner->parentDeviceGroup) && $current->owner->parentDeviceGroup !== null )
                                    $current = $current->owner->parentDeviceGroup->addressStore;
                                elseif( isset($current->owner->parentContainer) && $current->owner->parentContainer !== null )
                                    $current = $current->owner->parentContainer->addressStore;
                                elseif( isset($current->owner->owner) && $current->owner->owner !== null && !$current->owner->owner->isFawkes() )
                                    $current = $current->owner->owner->addressStore;
                                else
                                    break;

                            }*/
                        }
                    }
                    else
                        $upperHashMap[$value][] = $object;
                }
            }
            elseif( $this->dupAlg == 'whereused' )
                foreach( $objectsToSearchThrough as $object )
                {
                    if( !$object->isAddress() )
                        continue;
                    if( $object->isTmpAddr() )
                        continue;

                    if( $object->countReferences() == 0 )
                        continue;

                    if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        continue;

                    $value = $object->getRefHashComp() . $object->getNetworkValue();
                    $value = $object->value();
                    $value = $object->type() . '-' . $value;
                    if( $object->owner === $store )
                    {
                        $hashMap[$value][] = $object;
                        if( $parentStore !== null )
                        {
                            $object->ancestor = self::findAncestor( $parentStore, $object );
                            /*$findAncestor = $parentStore->find($object->name(), null, TRUE);
                            if( $findAncestor !== null )
                                $object->ancestor = $findAncestor;
                            */
                        }
                    }
                    else
                        $upperHashMap[$value][] = $object;
                }
            else derr("unsupported use case");

//
// Hashes with single entries have no duplicate, let's remove them
//
            $countConcernedObjects = 0;
            foreach( $hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset($child_hashMap[$index]) && !isset(reset($hash)->ancestor) )
                    unset($hashMap[$index]);
                else
                    $countConcernedObjects += count($hash);
            }
            unset($hash);

            $countConcernedChildObjects = 0;
            foreach( $child_hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset($hashMap[$index]) && !isset(reset($hash)->ancestor) )
                    unset($child_hashMap[$index]);
                else
                    $countConcernedChildObjects += count($hash);
            }
            unset($hash);



            PH::print_stdout( " - found " . count($hashMap) . " duplicates values totalling {$countConcernedObjects} address objects which are duplicate" );

            PH::print_stdout( " - found " . count($child_hashMap) . " duplicates childDG values totalling {$countConcernedChildObjects} address objects which are duplicate" );


            PH::print_stdout( "\n\nNow going after each duplicates for a replacement" );

            $countRemoved = 0;
            foreach( $hashMap as $index => &$hash )
            {
                PH::print_stdout( "" );
                PH::print_stdout( " - value '{$index}'" );
                $this->deletedObjects[$index]['kept'] = "";
                $this->deletedObjects[$index]['removed'] = "";


                $pickedObject = null;

                if( $this->pickFilter !== null )
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        foreach( $upperHashMap[$index] as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($upperHashMap[$index]);

                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        foreach( $hash as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($hash);

                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }
                else
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        $pickedObject = reset($upperHashMap[$index]);
                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        $pickedObject = reset($hash);
                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }


                // Merging loop finally!
                foreach( $hash as $objectIndex => $object )
                {
                    /** @var Address $object */
                    if( isset($object->ancestor) )
                    {
                        $ancestor = $object->ancestor;
                        $ancestor_different_value = "";

                        if( !$ancestor->isAddress() )
                        {
                            PH::print_stdout( "    - SKIP: object name '{$object->_PANC_shortName()}' as one ancestor is of type addressgroup" );
                            continue;
                        }

                        /** @var Address $ancestor */
                        if( $this->upperLevelSearch && !$ancestor->isGroup() && !$ancestor->isTmpAddr() && ($ancestor->isType_ipNetmask() || $ancestor->isType_ipRange() || $ancestor->isType_FQDN()) )
                        {
                            if( $object->getIP4Mapping()->equals($ancestor->getIP4Mapping())  || ( $object->isType_FQDN() && $ancestor->isType_FQDN() ) && ($object->value() == $ancestor->value() ) )
                            {
                                if( $this->dupAlg == 'identical' )
                                    if( $pickedObject->name() != $ancestor->name() )
                                    {
                                        PH::print_stdout( "    - SKIP: object name '{$pickedObject->_PANC_shortName()}' [with value '{$pickedObject->value()}'] is not IDENTICAL to object name from upperlevel '{$ancestor->_PANC_shortName()}' [with value '{$ancestor->value()}'] " );
                                        continue;
                                    }

                                $object->merge_tag_description_to( $ancestor, $this->apiMode );

                                $text = "    - object '{$object->name()}' merged with its ancestor, deleting this one... ";
                                $this->deletedObjects[$index]['kept'] = $pickedObject->name();
                                if( $this->deletedObjects[$index]['removed'] == "" )
                                    $this->deletedObjects[$index]['removed'] = $object->name();
                                else
                                    $this->deletedObjects[$index]['removed'] .= "|" . $object->name();
                                $object->replaceMeGlobally($ancestor);

                                if( $this->apiMode )
                                    $object->owner->API_remove($object);
                                else
                                    $object->owner->remove($object);

                                PH::print_stdout( $text );

                                $text = "         ancestor name: '{$ancestor->name()}' DG: ";
                                if( $ancestor->owner->owner->name() == "" )
                                    $text .= "'shared'";
                                else
                                    $text .= "'{$ancestor->owner->owner->name()}'";
                                $text .=  "  value: '{$ancestor->value()}' ";
                                PH::print_stdout( $text );

                                if( $pickedObject === $object )
                                    $pickedObject = $ancestor;

                                $countRemoved++;

                                if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                                {
                                    PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                                    break 2;
                                }

                                continue;
                            }
                            else
                                $ancestor_different_value = "with different value";


                        }
                        PH::print_stdout( "    - object '{$object->name()}' '{$ancestor->type()}' cannot be merged because it has an ancestor " . $ancestor_different_value . "" );

                        $text = "         ancestor name: '{$ancestor->name()}' DG: ";
                        if( $ancestor->owner->owner->name() == "" )
                            $text .= "'shared'";
                        else
                            $text .= "'{$ancestor->owner->owner->name()}'";
                        $text .=  "  value: '{$ancestor->value()}' ";
                        PH::print_stdout($text);

                        #unset($this->deletedObjects[$index]);
                        $this->deletedObjects[$index]['removed'] .= "|->ERROR ancestor: '" . $object->name() . "' cannot be merged";

                        continue;
                    }

                    if( $object === $pickedObject )
                        continue;

                    if( $this->dupAlg != 'identical' )
                    {
                        PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                        $success = $object->__replaceWhereIamUsed($this->apiMode, $pickedObject, TRUE, 5);

                        $object->merge_tag_description_to( $pickedObject, $this->apiMode );

                        if( $success )
                        {
                            PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                            $this->deletedObjects[$index]['kept'] = $pickedObject->name();
                            if( $this->deletedObjects[$index]['removed'] == "" )
                                $this->deletedObjects[$index]['removed'] = $object->name();
                            else
                                $this->deletedObjects[$index]['removed'] .= "|" . $object->name();
                            if( $this->apiMode )
                                $object->owner->API_remove($object);
                            else
                                $object->owner->remove($object);

                            $countRemoved++;
                        }


                        if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                        {
                            PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                            break 2;
                        }
                    }
                    else
                        PH::print_stdout( "    - SKIP: object name '{$object->_PANC_shortName()}' is not IDENTICAL" );
                }
            }


            $countChildRemoved = 0;
            $countChildCreated = 0;
            foreach( $child_hashMap as $index => &$hash )
            {
                PH::print_stdout( "" );
                PH::print_stdout( " - value '{$index}'" );
                $this->deletedObjects[$index]['kept'] = "";
                $this->deletedObjects[$index]['removed'] = "";


                $pickedObject = null;

                if( $this->pickFilter !== null )
                {
                    foreach( $hash as $object )
                    {
                        if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        {
                            $pickedObject = $object;
                            break;
                        }
                    }
                    if( $pickedObject === null )
                        $pickedObject = reset($hash);
                }
                else
                {
                    $pickedObject = reset($hash);
                }


                $tmp_DG_name = $store->owner->name();
                if( $tmp_DG_name == "" )
                    $tmp_DG_name = 'shared';

                $tmp_address = $store->find( $pickedObject->name() );
                if( $tmp_address == null )
                {
                    if( isset( $child_NamehashMap[ $pickedObject->name() ] ) )
                    {
                        $exit = false;
                        $exitObject = null;
                        foreach( $child_NamehashMap[ $pickedObject->name() ] as $obj )
                        {
                            if( $obj === $pickedObject )
                                continue;

                            /** @var Address $obj */
                            /** @var Address $pickedObject */
                            if( (!$obj->isType_FQDN() && !$pickedObject->isType_FQDN() ) &&  $obj->getNetworkMask() == '32' && $pickedObject->getNetworkMask() == '32'  )
                            {
                                if( ($obj->getNetworkMask() == $pickedObject->getNetworkMask() ) && $obj->getNetworkValue() == $pickedObject->getNetworkValue() )
                                    $exit = false;
                                else
                                {
                                    $exit = true;
                                    $exitObject = $obj;
                                }
                            }
                            elseif(  $obj->value() !== $pickedObject->value() )
                            {
                                $exit = true;
                                $exitObject = $obj;
                            }
                        }

                        if( $exit )
                        {
                            PH::print_stdout( "   * SKIP: no creation of object in DG: '".$tmp_DG_name."' as object with same name '{$exitObject->name()}' and different value '{$exitObject->value()}' exist at childDG level" );
                            continue;
                        }
                    }
                    PH::print_stdout( "   * create object in DG: '".$tmp_DG_name."' : '".$pickedObject->name()."'" );

                    /** @var AddressStore $store */
                    if( $this->apiMode )
                        $tmp_address = $store->API_newAddress($pickedObject->name(), $pickedObject->type(), $pickedObject->value(), $pickedObject->description() );
                    else
                        $tmp_address = $store->newAddress($pickedObject->name(), $pickedObject->type(), $pickedObject->value(), $pickedObject->description() );

                    $countChildCreated++;
                }
                else
                {
                    /** @var Address $tmp_address */
                    if( $tmp_address->isAddress() && $pickedObject->isAddress() && $tmp_address->type() === $pickedObject->type() && $tmp_address->value() === $pickedObject->value() )
                    {
                        PH::print_stdout( "   * keeping object '{$tmp_address->_PANC_shortName()}'" );
                    }
                    else
                    {
                        $string = "    - SKIP: object name '{$pickedObject->_PANC_shortName()}'";

                        if( $pickedObject->isAddress() )
                            $string .= " [with value '{$pickedObject->value()}']";
                        else
                            $string .= " [AdressGroup]";

                        $string .= " is not IDENTICAL to object name: '{$tmp_address->_PANC_shortName()}'";

                        if( $tmp_address->isAddress() )
                            $string .= " [with value '{$tmp_address->value()}']";
                        else
                            $string .= " [AdressGroup]";

                        PH::print_stdout( $string );

                        continue;
                    }
                }

                // Merging loop finally!
                foreach( $hash as $objectIndex => $object )
                {
                    PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                    $object->__replaceWhereIamUsed($this->apiMode, $tmp_address, TRUE, 5);

                    $object->merge_tag_description_to($tmp_address, $this->apiMode);

                    PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                    $this->deletedObjects[$index]['kept'] = $tmp_address->name();
                    if( $this->deletedObjects[$index]['removed'] == "" )
                        $this->deletedObjects[$index]['removed'] = $object->name();
                    else
                        $this->deletedObjects[$index]['removed'] .= "|" . $object->name();
                    if( $this->apiMode )
                        $object->owner->API_remove($object);
                    else
                        $object->owner->remove($object);

                    $countChildRemoved++;
                }
            }



            PH::print_stdout( "\n\nDuplicates removal is now done. Number of objects after cleanup: '{$store->countAddresses()}' (removed {$countRemoved} addresses)\n" );
            if( count( $child_hashMap ) >0 )
                PH::print_stdout( "Duplicates ChildDG removal is now done. Number of objects after cleanup: '{$store->countAddresses()}' (removed/created {$countChildRemoved}/{$countChildCreated} addresses)\n" );

            PH::print_stdout( "\n\n***********************************************\n" );

        }    
    }
    
    function servicegroup_merging()
    {
        foreach( $this->location_array as $tmp_location )
        {
            $store = $tmp_location['store'];
            $findLocation = $tmp_location['findLocation'];
            $parentStore = $tmp_location['parentStore'];
            if( $this->upperLevelSearch )
                $childDeviceGroups = $tmp_location['childDeviceGroups'];
            else
                $childDeviceGroups = array();

            PH::print_stdout( " - upper level search status : " . boolYesNo($this->upperLevelSearch) . "" );
            if( is_string($findLocation) )
                PH::print_stdout( " - location 'shared' found" );
            else
                PH::print_stdout( " - location '{$findLocation->name()}' found" );
            PH::print_stdout( " - found {$store->count()} services" );
            PH::print_stdout( " - DupAlgorithm selected: {$this->dupAlg}" );
            PH::print_stdout( " - computing ServiceGroup values database ... " );
            sleep(1);

            /**
             * @param ServiceGroup $object
             * @return string
             */
            if( $this->dupAlg == 'samemembers' )
                $hashGenerator = function ($object) {
                    /** @var ServiceGroup $object */
                    $value = '';

                    $members = $object->members();
                    usort($members, '__CmpObjName');

                    foreach( $members as $member )
                    {
                        $value .= './.' . $member->name();
                    }

                    return $value;
                };
            elseif( $this->dupAlg == 'sameportmapping' )
                $hashGenerator = function ($object) {
                    /** @var ServiceGroup $object */
                    $value = '';

                    $mapping = $object->dstPortMapping();

                    $value = $mapping->mappingToText();

                    if( count($mapping->unresolved) > 0 )
                    {
                        ksort($mapping->unresolved);
                        $value .= '//unresolved:/';

                        foreach( $mapping->unresolved as $unresolvedEntry )
                            $value .= $unresolvedEntry->name() . '.%.';
                    }

                    return $value;
                };
            elseif( $this->dupAlg == 'whereused' )
                $hashGenerator = function ($object) {
                    if( $object->countReferences() == 0 )
                        return null;

                    /** @var ServiceGroup $object */
                    $value = $object->getRefHashComp();

                    return $value;
                };
            else
                derr("unsupported dupAlgorithm");

//
// Building a hash table of all service objects with same value
//
            if( $this->upperLevelSearch )
                $objectsToSearchThrough = $store->nestedPointOfView();
            else
                $objectsToSearchThrough = $store->serviceGroups();

            $hashMap = array();
            $upperHashMap = array();
            foreach( $objectsToSearchThrough as $object )
            {
                if( !$object->isGroup() )
                    continue;

                if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                    continue;

                $skipThisOne = FALSE;

                // Object with descendants in lower device groups should be excluded
                if( $this->pan->isPanorama() )
                {
                    foreach( $childDeviceGroups as $dg )
                    {
                        if( $dg->serviceStore->find($object->name(), null, FALSE) !== null )
                        {
                            $skipThisOne = TRUE;
                            break;
                        }
                    }
                    if( $skipThisOne )
                        continue;
                }
                elseif( $this->pan->isFawkes() && $object->owner === $store )
                {
                    //do something
                }

                $value = $hashGenerator($object);
                if( $value === null )
                    continue;

                if( $object->owner === $store )
                {
                    $hashMap[$value][] = $object;
                    if( $parentStore !== null )
                    {
                        $object->ancestor = self::findAncestor( $parentStore, $object );
                        /*
                        $findAncestor = $parentStore->find($object->name(), null, TRUE);
                        if( $findAncestor !== null )
                            $object->ancestor = $findAncestor;
                        */
                    }
                }
                else
                    $upperHashMap[$value][] = $object;
            }

//
// Hashes with single entries have no duplicate, let's remove them
//
            $countConcernedObjects = 0;
            foreach( $hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset(reset($hash)->ancestor) )
                {
                    //PH::print_stdout( "\nancestor not found for ".reset($hash)->name()."" );
                    unset($hashMap[$index]);
                }
                else
                    $countConcernedObjects += count($hash);
            }
            unset($hash);


            PH::print_stdout( " - found " . count($hashMap) . " duplicate values totalling {$countConcernedObjects} groups which are duplicate" );

            PH::print_stdout( "\n\nNow going after each duplicates for a replacement" );

            $countRemoved = 0;
            foreach( $hashMap as $index => &$hash )
            {
                PH::print_stdout( "" );

                if( $this->dupAlg == 'sameportmapping' )
                {
                    PH::print_stdout( " - value '{$index}'" );
                }

                $setList = array();
                foreach( $hash as $object )
                {
                    /** @var Service $object */
                    $setList[] = PH::getLocationString($object->owner->owner) . '/' . $object->name();
                }
                PH::print_stdout( " - duplicate set : '" . PH::list_to_string($setList) . "'" );

                $pickedObject = null;

                if( $this->pickFilter !== null )
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        foreach( $upperHashMap[$index] as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($upperHashMap[$index]);

                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        foreach( $hash as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($hash);

                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }
                else
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        $pickedObject = reset($upperHashMap[$index]);
                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        $pickedObject = reset($hash);
                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }

                // Merging loop finally!
                foreach( $hash as $object )
                {
                    /** @var ServiceGroup $object */
                    if( isset($object->ancestor) )
                    {
                        $ancestor = $object->ancestor;
                        /** @var ServiceGroup $ancestor */
                        if( $this->upperLevelSearch && $ancestor->isGroup() )
                        {
                            if( $hashGenerator($object) != $hashGenerator($ancestor) )
                            {
                                $ancestor->displayValueDiff( $object, 7 );

                                if( $this->addMissingObjects )
                                {
                                    $diff = $ancestor->getValueDiff( $object );

                                    if( count($diff['minus']) != 0 )
                                        foreach( $diff['minus'] as $d )
                                        {
                                            /** @var Service|ServiceGroup $d */

                                            if( $ancestor->owner->find( $d->name() ) !== null )
                                            {
                                                PH::print_stdout( "      - adding objects to group: ".$d->name()."" );
                                                if( $this->apiMode )
                                                    $ancestor->API_addMember( $d );
                                                else
                                                    $ancestor->addMember( $d );
                                            }
                                        }

                                    if( count($diff['plus']) != 0 )
                                        foreach( $diff['plus'] as $d )
                                        {
                                            /** @var Service|ServiceGroup $d */
                                            //TMP usage to clean DG level ADDRESSgroup up
                                            $object->addMember( $d );
                                        }
                                }
                            }

                            if( $hashGenerator($object) == $hashGenerator($ancestor) )
                            {
                                $text = "    - group '{$object->name()}' merged with its ancestor, deleting this one... ";
                                $object->replaceMeGlobally($ancestor);
                                if( $this->apiMode )
                                    $object->owner->API_remove($object);
                                else
                                    $object->owner->remove($object);

                                PH::print_stdout( $text );

                                if( $pickedObject === $object )
                                    $pickedObject = $ancestor;

                                $countRemoved++;
                                if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                                {
                                    PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                                    break 2;
                                }
                                continue;
                            }
                        }
                        PH::print_stdout( "    - group '{$object->name()}' cannot be merged because it has an ancestor" );
                        continue;
                    }

                    if( $object === $pickedObject )
                        continue;

                    if( $this->dupAlg == 'whereused' )
                    {
                        PH::print_stdout( "    - merging '{$object->name()}' members into '{$pickedObject->name()}': " );
                        foreach( $object->members() as $member )
                        {
                            $text = "     - adding member '{$member->name()}'... ";
                            if( $this->apiMode )
                                $pickedObject->API_addMember($member);
                            else
                                $pickedObject->addMember($member);

                            PH::print_stdout( $text );
                        }
                        PH::print_stdout( "    - now removing '{$object->name()} from where it's used" );
                        if( $this->apiMode )
                        {
                            $object->API_removeWhereIamUsed(TRUE, 6);
                            $text = "    - deleting '{$object->name()}'... ";
                            $object->owner->API_remove($object);

                            PH::print_stdout( $text );
                        }
                        else
                        {
                            $object->removeWhereIamUsed(TRUE, 6);
                            $text = "    - deleting '{$object->name()}'... ";
                            $object->owner->remove($object);

                            PH::print_stdout( $text );
                        }
                    }
                    else
                    {
                        PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                        $object->__replaceWhereIamUsed($this->apiMode, $pickedObject, TRUE, 5);

                        PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                        if( $this->apiMode )
                        {
                            //true flag needed for nested groups in a specific constellation
                            $object->owner->API_remove($object, TRUE);
                        }
                        else
                        {
                            $object->owner->remove($object, TRUE);
                        }
                    }

                    $countRemoved++;

                    if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                    {
                        PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                        break 2;
                    }
                }
            }

            PH::print_stdout( "\n\nDuplicates removal is now done. Number of objects after cleanup: '{$store->countServiceGroups()}' (removed {$countRemoved} groups)\n" );

            PH::print_stdout( "\n\n***********************************************\n" );

            PH::print_stdout( "\n" );
        }
    }
    
    function service_merging()
    {
        foreach( $this->location_array as $tmp_location )
        {
            $store = $tmp_location['store'];
            $findLocation = $tmp_location['findLocation'];
            $parentStore = $tmp_location['parentStore'];
            if( $this->upperLevelSearch )
                $childDeviceGroups = $tmp_location['childDeviceGroups'];
            else
                $childDeviceGroups = array();

            PH::print_stdout( " - upper level search status : " . boolYesNo($this->upperLevelSearch) . "" );
            if( is_string($findLocation) )
                PH::print_stdout( " - location 'shared' found" );
            else
                PH::print_stdout( " - location '{$findLocation->name()}' found" );
            PH::print_stdout( " - found {$store->countServices()} services" );
            PH::print_stdout( " - DupAlgorithm selected: {$this->dupAlg}" );
            PH::print_stdout( " - computing service values database ... " );
            sleep(1);


//
// Building a hash table of all service based on their REAL port mapping
//
            if( $this->upperLevelSearch )
                $objectsToSearchThrough = $store->nestedPointOfView();
            else
                $objectsToSearchThrough = $store->serviceObjects();

            $hashMap = array();
            $child_hashMap = array();
            $child_NamehashMap = array();
            $upperHashMap = array();
            if( $this->dupAlg == 'sameports' || $this->dupAlg == 'samedstsrcports' )
            {
                //todo: childDG/childDG to parentDG merge is always done; should it not combined to upperLevelSearch value?
                foreach( $childDeviceGroups as $dg )
                {
                    /** @var DeviceGroup $dg */
                    foreach( $dg->serviceStore->serviceObjects() as $object )
                    {
                        if( !$object->isService() )
                            continue;
                        if( $object->isTmpSrv() )
                            continue;

                        if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            continue;

                        $value = $object->dstPortMapping()->mappingToText();

                        #PH::print_stdout( "add objNAME: " . $object->name() . " DG: " . $object->owner->owner->name() . "" );
                        $child_hashMap[$value][] = $object;
                        $child_NamehashMap[$object->name()][] = $object;
                    }
                }

                foreach( $objectsToSearchThrough as $object )
                {
                    if( !$object->isService() )
                        continue;
                    if( $object->isTmpSrv() )
                        continue;

                    if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        continue;


                    $skipThisOne = FALSE;

                    // Object with descendants in lower device groups should be excluded
                    if( $this->pan->isPanorama() && $object->owner === $store )
                    {
                        /*
                        foreach( $childDeviceGroups as $dg )
                        {
                            if( $dg->serviceStore->find($object->name(), null, FALSE) !== null )
                            {
                                $tmp_obj = $dg->serviceStore->find($object->name(), null, FALSE);

                                PH::print_stdout( "\n- object '" . $object->name() . "' [prot: '{$object->protocol()}' dst: '{$object->dstPortMapping()->mappingToText()}' src: '{$object->srcPortMapping()->mappingToText()}'] skipped because of same object name [ ";
                                if( $tmp_obj->isService() )
                                    PH::print_stdout( "with prot: '{$tmp_obj->protocol()}' dst: '{$tmp_obj->dstPortMapping()->mappingToText()}' src: '{$tmp_obj->srcPortMapping()->mappingToText()}'";
                                else
                                    PH::print_stdout( "but as SERVICEGROUP";
                                PH::print_stdout( " ] available at lower level DG: " . $dg->name() . "" );

                                $skipThisOne = TRUE;
                                break;
                            }
                        }
                        if( $skipThisOne )
                            continue;
                        */
                    }
                    elseif( $this->pan->isFawkes() && $object->owner === $store )
                    {
                        //do something
                    }

                    $value = $object->dstPortMapping()->mappingToText();

                    if( $object->owner === $store )
                    {
                        $hashMap[$value][] = $object;
                        if( $parentStore !== null )
                        {
                            $object->ancestor = self::findAncestor($parentStore, $object);
                            /*
                            $findAncestor = $parentStore->find($object->name(), null, TRUE);
                            if( $findAncestor !== null )
                                $object->ancestor = $findAncestor;
                            */
                        }
                    }
                    else
                        $upperHashMap[$value][] = $object;

                }
            }
            elseif( $this->dupAlg == 'whereused' )
            {
                foreach( $objectsToSearchThrough as $object )
                {
                    if( !$object->isService() )
                        continue;
                    if( $object->isTmpSrv() )
                        continue;

                    if( $object->countReferences() == 0 )
                        continue;

                    if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        continue;

                    $value = $object->getRefHashComp() . $object->protocol();
                    if( $object->owner === $store )
                    {
                        $hashMap[$value][] = $object;
                        if( $parentStore !== null )
                        {
                            $object->ancestor = self::findAncestor($parentStore, $object);
                            /*
                            $findAncestor = $parentStore->find($object->name(), null, TRUE);
                            if( $findAncestor !== null )
                                $object->ancestor = $findAncestor;
                            */
                        }
                    }
                    else
                        $upperHashMap[$value][] = $object;
                }
            }
            else derr("unsupported use case");

//
// Hashes with single entries have no duplicate, let's remove them
//
            $countConcernedObjects = 0;
            foreach( $hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset(reset($hash)->ancestor) )
                    unset($hashMap[$index]);
                else
                    $countConcernedObjects += count($hash);
            }
            unset($hash);
            $countConcernedChildObjects = 0;
            foreach( $child_hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset(reset($hash)->ancestor) )
                    unset($child_hashMap[$index]);
                else
                    $countConcernedChildObjects += count($hash);
            }
            unset($hash);


            PH::print_stdout( " - found " . count($hashMap) . " duplicates values totalling {$countConcernedObjects} service objects which are duplicate" );

            PH::print_stdout( " - found " . count($child_hashMap) . " duplicates childDG values totalling {$countConcernedChildObjects} service objects which are duplicate" );


            PH::print_stdout( "\n\nNow going after each duplicates for a replacement" );

            $countRemoved = 0;
            if( $this->dupAlg == 'sameports' || $this->dupAlg == 'samedstsrcports' )
            {
                foreach( $hashMap as $index => &$hash )
                {
                    PH::print_stdout( "" );
                    PH::print_stdout( " - value '{$index}'" );

                    $pickedObject = null;

                    if( $this->pickFilter !== null )
                    {
                        if( isset($upperHashMap[$index]) )
                        {
                            foreach( $upperHashMap[$index] as $object )
                            {
                                if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                                {
                                    $pickedObject = $object;
                                    break;
                                }
                            }
                            if( $pickedObject === null )
                                $pickedObject = reset($upperHashMap[$index]);

                            PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                        }
                        else
                        {
                            foreach( $hash as $object )
                            {
                                if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                                {
                                    $pickedObject = $object;
                                    break;
                                }
                            }
                            if( $pickedObject === null )
                                $pickedObject = reset($hash);

                            PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                        }
                    }
                    else
                    {
                        if( isset($upperHashMap[$index]) )
                        {
                            $pickedObject = reset($upperHashMap[$index]);
                            PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                        }
                        else
                        {
                            $pickedObject = reset($hash);
                            PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                        }
                    }

                    foreach( $hash as $object )
                    {
                        /** @var Service $object */

                        if( isset($object->ancestor) )
                        {
                            $ancestor = $object->ancestor;

                            if( !$ancestor->isService() )
                            {
                                PH::print_stdout( "    - SKIP: object name '{$object->_PANC_shortName()}' as one ancestor is of type servicegroup" );
                                continue;
                            }

                            /** @var Service $ancestor */
                            if( $this->upperLevelSearch && !$ancestor->isGroup() && !$ancestor->isTmpSrv() )
                            {
                                if( $object->dstPortMapping()->equals($ancestor->dstPortMapping()) )
                                {
                                    if( !$object->srcPortMapping()->equals($ancestor->srcPortMapping()) && $this->dupAlg == 'samedstsrcports' )
                                    {
                                        $text = "    - object '{$object->name()}' cannot be merged because of different SRC port information";
                                        $text .= "  object value: " . $object->srcPortMapping()->mappingToText() . " | pickedObject value: " . $ancestor->srcPortMapping()->mappingToText();
                                        PH::print_stdout( $text );
                                        continue;
                                    }
                                    elseif( $object->getOverride() != $ancestor->getOverride() )
                                    {
                                        $text = "    - object '{$object->name()}' cannot be merged because of different Override information";
                                        $text .="  object value: " . $object->getOverride() . " | pickedObject value: " . $ancestor->getOverride();
                                        PH::print_stdout( $text );
                                        continue;
                                    }

                                    $text = "    - object '{$object->name()}' merged with its ancestor, deleting this one... ";
                                    $object->replaceMeGlobally($ancestor);
                                    if( $this->apiMode )
                                        $object->owner->API_remove($object, TRUE);
                                    else
                                        $object->owner->remove($object, TRUE);

                                    PH::print_stdout( $text );

                                    $text = "         ancestor name: '{$ancestor->name()}' DG: ";
                                    if( $ancestor->owner->owner->name() == "" ) $text .= "'shared'";
                                    else $text .= "'{$ancestor->owner->owner->name()}'";
                                    $text .=  "  value: '{$ancestor->getDestPort()}' ";
                                    PH::print_stdout( $text );

                                    if( $pickedObject === $object )
                                        $pickedObject = $ancestor;

                                    $countRemoved++;
                                    continue;
                                }
                            }
                            PH::print_stdout( "    - object '{$object->name()}' cannot be merged because it has an ancestor" );

                            $text = "         ancestor name: '{$ancestor->name()}' DG: ";
                            if( $ancestor->owner->owner->name() == "" ) $text .= "'shared'";
                            else $text .= "'{$ancestor->owner->owner->name()}'";
                            $text .=  "  value: '{$ancestor->getDestPort()}' ";
                            PH::print_stdout( $text );

                            continue;
                        }
                        else
                        {
                            if( !$object->srcPortMapping()->equals($pickedObject->srcPortMapping()) && $this->dupAlg == 'samedstsrcports' )
                            {
                                $text = "    - object '{$object->name()}' cannot be merged because of different SRC port information";
                                $text .= "  object value: " . $object->srcPortMapping()->mappingToText() . " | pickedObject value: " . $pickedObject->srcPortMapping()->mappingToText();
                                PH::print_stdout( $text );
                                continue;
                            }
                            elseif( $object->getOverride() != $pickedObject->getOverride() )
                            {
                                $text = "    - object '{$object->name()}' cannot be merged because of different Override information";
                                $text .= "  object value: " . $object->getOverride() . " | pickedObject value: " . $pickedObject->getOverride();
                                PH::print_stdout( $text );
                                continue;
                            }
                        }

                        if( $object === $pickedObject )
                            continue;


                        PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                        $object->__replaceWhereIamUsed($this->apiMode, $pickedObject, TRUE, 5);

                        PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                        if( $this->apiMode )
                        {
                            $object->owner->API_remove($object, TRUE);
                        }
                        else
                        {
                            $object->owner->remove($object, TRUE);
                        }

                        $countRemoved++;

                        if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                        {
                            PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                            break 2;
                        }
                    }
                }

                $countChildRemoved = 0;
                $countChildCreated = 0;
                foreach( $child_hashMap as $index => &$hash )
                {
                    PH::print_stdout( "" );
                    PH::print_stdout( " - value '{$index}'" );
                    $this->deletedObjects[$index]['kept'] = "";
                    $this->deletedObjects[$index]['removed'] = "";


                    $pickedObject = null;

                    if( $this->pickFilter !== null )
                    {
                        foreach( $hash as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($hash);
                    }
                    else
                    {
                        $pickedObject = reset($hash);
                    }


                    $tmp_DG_name = $store->owner->name();
                    if( $tmp_DG_name == "" )
                        $tmp_DG_name = 'shared';

                    /** @var Service $tmp_service */
                    $tmp_service = $store->find( $pickedObject->name() );
                    if( $tmp_service == null )
                    {
                        if( isset( $child_NamehashMap[ $pickedObject->name() ] ) )
                        {
                            $exit = false;
                            $exitObject = null;
                            foreach( $child_NamehashMap[ $pickedObject->name() ] as $obj )
                            {
                                if( !$obj->dstPortMapping()->equals($pickedObject->dstPortMapping()) || !$obj->srcPortMapping()->equals($pickedObject->srcPortMapping()) )
                                {
                                    $exit = true;
                                    $exitObject = $obj;
                                }
                            }

                            if( $exit )
                            {
                                PH::print_stdout( "   * SKIP: no creation of object in DG: '".$tmp_DG_name."' as object with same name '{$exitObject->name()}' and different value '{$exitObject->dstPortMapping()->mappingToText()}' exist at childDG level" );
                                continue;
                            }
                        }
                        PH::print_stdout( "   * create object in DG: '".$tmp_DG_name."' : '".$pickedObject->name()."'" );

                        /** @var ServiceStore $store */
                        if( $this->apiMode )
                            $tmp_service = $store->API_newService($pickedObject->name(), $pickedObject->protocol(), $pickedObject->getDestPort(), $pickedObject->description(), $pickedObject->getSourcePort() );
                        else
                            $tmp_service = $store->newService($pickedObject->name(), $pickedObject->protocol(), $pickedObject->getDestPort(), $pickedObject->description(), $pickedObject->getSourcePort() );

                        $countChildCreated++;
                    }
                    else
                    {
                        if( $tmp_service->equals( $pickedObject ) )
                        {
                            PH::print_stdout( "   * keeping object '{$tmp_service->_PANC_shortName()}'" );
                        }
                        else
                        {
                            $string = "    - SKIP: object name '{$pickedObject->_PANC_shortName()}'";
                            if( $pickedObject->isService() )
                                $string .= " [with value '{$pickedObject->getDestPort()}']";
                            else
                                $string .= " [ServiceGroup] ";

                            $string .= " is not IDENTICAL to object name: '{$tmp_service->_PANC_shortName()}'";

                            if( $tmp_service->isService() )
                                $string .= " [with value '{$tmp_service->getDestPort()}']";
                            else
                                $string .= " [ServiceGroup] ";

                            PH::print_stdout( $string );

                            continue;
                        }
                    }

                    // Merging loop finally!
                    foreach( $hash as $objectIndex => $object )
                    {
                        PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                        $object->__replaceWhereIamUsed($this->apiMode, $tmp_service, TRUE, 5);

                        #$object->merge_tag_description_to($tmp_service, $this->apiMode);

                        PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                        $this->deletedObjects[$index]['kept'] = $tmp_service->name();
                        if( $this->deletedObjects[$index]['removed'] == "" )
                            $this->deletedObjects[$index]['removed'] = $object->name();
                        else
                            $this->deletedObjects[$index]['removed'] .= "|" . $object->name();
                        if( $this->apiMode )
                            $object->owner->API_remove($object);
                        else
                            $object->owner->remove($object);

                        $countChildRemoved++;
                    }
                }
            }
            elseif( $this->dupAlg == 'whereused' )
                foreach( $hashMap as $index => &$hash )
                {
                    PH::print_stdout( "" );

                    $setList = array();
                    foreach( $hash as $object )
                    {
                        /** @var Service $object */
                        $setList[] = PH::getLocationString($object->owner->owner) . '/' . $object->name();
                    }
                    PH::print_stdout( " - duplicate set : '" . PH::list_to_string($setList) . "'" );

                    /** @var Service $pickedObject */
                    $pickedObject = null;

                    if( $this->pickFilter !== null )
                    {
                        foreach( $hash as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                    }

                    if( $pickedObject === null )
                        $pickedObject = reset($hash);

                    PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );

                    foreach( $hash as $object )
                    {
                        /** @var Service $object */

                        if( isset($object->ancestor) )
                        {
                            $ancestor = $object->ancestor;
                            /** @var Service $ancestor */
                            PH::print_stdout( "    - object '{$object->name()}' cannot be merged because it has an ancestor" );

                            $text = "         ancestor name: '{$ancestor->name()}' DG: ";
                            if( $ancestor->owner->owner->name() == "" ) $text .= "'shared'";
                            else $text .= "'{$ancestor->owner->owner->name()}'";
                            $text .=  "  value: '{$ancestor->value()}' ";
                            PH::print_stdout($text);

                            continue;
                        }

                        if( $object === $pickedObject )
                            continue;

                        $localMapping = $object->dstPortMapping();
                        PH::print_stdout( "    - adding the following ports to first service: " . $localMapping->mappingToText() . "" );
                        $localMapping->mergeWithMapping($pickedObject->dstPortMapping());

                        if( $this->apiMode )
                        {
                            if( $pickedObject->isTcp() )
                                $pickedObject->API_setDestPort($localMapping->tcpMappingToText());
                            else
                                $pickedObject->API_setDestPort($localMapping->udpMappingToText());
                            PH::print_stdout( "    - removing '{$object->name()}' from places where it's used:" );
                            $object->API_removeWhereIamUsed(TRUE, 7);
                            $object->owner->API_remove($object);
                            $countRemoved++;
                        }
                        else
                        {
                            if( $pickedObject->isTcp() )
                                $pickedObject->setDestPort($localMapping->tcpMappingToText());
                            else
                                $pickedObject->setDestPort($localMapping->udpMappingToText());

                            PH::print_stdout( "    - removing '{$object->name()}' from places where it's used:" );
                            $object->removeWhereIamUsed(TRUE, 7);
                            $object->owner->remove($object);
                            $countRemoved++;
                        }


                        if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                        {
                            PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACH mergeCountLimit ({$this->mergeCountLimit})" );
                            break 2;
                        }

                    }
                    PH::print_stdout( "   * final mapping for service '{$pickedObject->name()}': {$pickedObject->getDestPort()}" );

                    PH::print_stdout( "" );
                }
            else derr("unsupported use case");


            PH::print_stdout( "\n\nDuplicates removal is now done. Number of objects after cleanup: '{$store->countServices()}' (removed {$countRemoved} services)\n" );
            if( count( $child_hashMap ) >0 )
                PH::print_stdout( "Duplicates ChildDG removal is now done. Number of objects after cleanup: '{$store->countServices()}' (removed/created {$countChildRemoved}/{$countChildCreated} addresses)\n" );

            PH::print_stdout( "\n\n***********************************************\n" );

        }
    }

    function tag_merging()
    {
        foreach( $this->location_array as $tmp_location )
        {
            $store = $tmp_location['store'];
            $findLocation = $tmp_location['findLocation'];
            $parentStore = $tmp_location['parentStore'];
            $childDeviceGroups = $tmp_location['childDeviceGroups'];

            PH::print_stdout( " - upper level search status : " . boolYesNo($this->upperLevelSearch) . "" );
            if( is_string($findLocation) )
                PH::print_stdout( " - location 'shared' found" );
            else
                PH::print_stdout( " - location '{$findLocation->name()}' found" );
            PH::print_stdout( " - found {$store->count()} tag Objects" );
            PH::print_stdout( " - DupAlgorithm selected: {$this->dupAlg}" );
            PH::print_stdout( " - computing tag values database ... " );
            sleep(1);

            //
            // Building a hash table of all tag objects with same value
            //
            if( $this->upperLevelSearch )
                $objectsToSearchThrough = $store->nestedPointOfView();
            else
                $objectsToSearchThrough = $store->tags();

            $hashMap = array();
            $child_hashMap = array();
            $child_NamehashMap = array();
            $upperHashMap = array();
            if( $this->dupAlg == 'samecolor' || $this->dupAlg == 'identical' || $this->dupAlg == 'samename' )
            {
                //todo: childDG/childDG to parentDG merge is always done; should it not combined to upperLevelSearch value?
                foreach( $childDeviceGroups as $dg )
                {
                    /** @var DeviceGroup $dg */
                    foreach( $dg->tagStore->tags() as $object )
                    {
                        if( !$object->isTag() )
                            continue;
                        if( $object->isTmp() )
                            continue;

                        if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            continue;

                        $value = $object->getColor();
                        $value = $object->name();

                        #PH::print_stdout( "add objNAME: " . $object->name() . " DG: " . $object->owner->owner->name() . "" );
                        $child_hashMap[$value][] = $object;
                        $child_NamehashMap[$object->name()][] = $object;
                    }
                }

                foreach( $objectsToSearchThrough as $object )
                {
                    if( !$object->isTag() )
                        continue;
                    if( $object->isTmp() )
                        continue;

                    if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        continue;

                    $skipThisOne = FALSE;

                    // Object with descendants in lower device groups should be excluded
                    if( $this->pan->isPanorama() && $object->owner === $store )
                    {
                        /*
                        foreach( $childDeviceGroups as $dg )
                        {
                            if( $dg->tagStore->find($object->name(), null, FALSE) !== null )
                            {
                                $tmp_obj = $dg->tagStore->find($object->name(), null, FALSE);
                                PH::print_stdout( PH::boldText("\n\n - object '" . $object->name() . "' [value '{$object->getColor()}'] skipped because of same object name [with value '{$tmp_obj->getColor()}'] available at lower level DG: " . $dg->name() . "\n\n");
                                $skipThisOne = TRUE;
                                break;
                            }
                        }
                        if( $skipThisOne )
                            continue;
                        */
                    }
                    elseif( $this->pan->isPanorama() && $object->owner === $store )
                    {
                        //do something
                    }

                    $value = $object->getColor();
                    $value = $object->name();

                    /*
                    // if object is /32, let's remove it to match equivalent non /32 syntax
                    if( $object->isType_ipNetmask() && strpos($object->value(), '/32') !== FALSE )
                        $value = substr($value, 0, strlen($value) - 3);
        
                    $value = $object->type() . '-' . $value;
                    */

                    if( $object->owner === $store )
                    {
                        $hashMap[$value][] = $object;
                        if( $parentStore !== null )
                        {
                            $object->ancestor = self::findAncestor( $parentStore, $object );
                            /*
                            $findAncestor = $parentStore->find($object->name(), null, TRUE);
                            if( $findAncestor !== null )
                                $object->ancestor = $findAncestor;
                            */
                        }
                    }
                    else
                        $upperHashMap[$value][] = $object;
                }
            }
            elseif( $this->dupAlg == 'whereused' )
                foreach( $objectsToSearchThrough as $object )
                {
                    if( !$object->isTag() )
                        continue;
                    if( $object->isTmp() )
                        continue;

                    if( $object->countReferences() == 0 )
                        continue;

                    if( $this->excludeFilter !== null && $this->excludeFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        continue;

                    #$value = $object->getRefHashComp() . $object->getNetworkValue();
                    $value = $object->getRefHashComp() . $object->name();
                    if( $object->owner === $store )
                    {
                        $hashMap[$value][] = $object;
                        if( $parentStore !== null )
                        {
                            $object->ancestor = self::findAncestor( $parentStore, $object );
                            /*
                            $findAncestor = $parentStore->find($object->name(), null, TRUE);
                            if( $findAncestor !== null )
                                $object->ancestor = $findAncestor;
                            */
                        }
                    }
                    else
                        $upperHashMap[$value][] = $object;
                }
            else derr("unsupported use case");

//
// Hashes with single entries have no duplicate, let's remove them
//
            $countConcernedObjects = 0;
            foreach( $hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset(reset($hash)->ancestor) )
                    unset($hashMap[$index]);
                else
                    $countConcernedObjects += count($hash);
            }
            unset($hash);
            $countConcernedChildObjects = 0;
            foreach( $child_hashMap as $index => &$hash )
            {
                if( count($hash) == 1 && !isset($upperHashMap[$index]) && !isset(reset($hash)->ancestor) )
                    unset($child_hashMap[$index]);
                else
                    $countConcernedChildObjects += count($hash);
            }
            unset($hash);


            PH::print_stdout( " - found " . count($hashMap) . " duplicates values totalling {$countConcernedObjects} tag objects which are duplicate" );

            PH::print_stdout( " - found " . count($child_hashMap) . " duplicates childDG values totalling {$countConcernedChildObjects} tag objects which are duplicate" );


            PH::print_stdout( "\n\nNow going after each duplicates for a replacement" );

            $countRemoved = 0;
            foreach( $hashMap as $index => &$hash )
            {
                PH::print_stdout( "" );
                PH::print_stdout( " - name '{$index}'" );
                $this->deletedObjects[$index]['kept'] = "";
                $this->deletedObjects[$index]['removed'] = "";


                $pickedObject = null;

                if( $this->pickFilter !== null )
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        foreach( $upperHashMap[$index] as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($upperHashMap[$index]);

                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        foreach( $hash as $object )
                        {
                            if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                            {
                                $pickedObject = $object;
                                break;
                            }
                        }
                        if( $pickedObject === null )
                            $pickedObject = reset($hash);

                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }
                else
                {
                    if( isset($upperHashMap[$index]) )
                    {
                        $pickedObject = reset($upperHashMap[$index]);
                        PH::print_stdout( "   * using object from upper level : '{$pickedObject->_PANC_shortName()}'" );
                    }
                    else
                    {
                        $pickedObject = reset($hash);
                        PH::print_stdout( "   * keeping object '{$pickedObject->_PANC_shortName()}'" );
                    }
                }


                // Merging loop finally!
                foreach( $hash as $objectIndex => $object )
                {
                    /** @var Tag $object */
                    if( isset($object->ancestor) )
                    {
                        $ancestor = $object->ancestor;
                        $ancestor_different_color = "";

                        if( !$ancestor->isTag() )
                        {
                            PH::print_stdout( "    - SKIP: object name '{$object->_PANC_shortName()}' has one ancestor which is not TAG object" );
                            continue;
                        }

                        /** @var Tag $ancestor */
                        #if( $this->upperLevelSearch && !$ancestor->isGroup() && !$ancestor->isTmpAddr() && ($ancestor->isType_ipNetmask() || $ancestor->isType_ipRange() || $ancestor->isType_FQDN()) )
                        if( $this->upperLevelSearch && !$ancestor->isTmp() )
                        {
                            if( $object->sameValue($ancestor) || $this->dupAlg == 'samename' ) //same color
                            {
                                if( $this->dupAlg == 'identical' )
                                    if( $pickedObject->name() != $ancestor->name() )
                                    {
                                        PH::print_stdout( "    - SKIP: object name '{$object->_PANC_shortName()}' is not IDENTICAL to object name from upperlevel '{$pickedObject->_PANC_shortName()}' " );
                                        continue;
                                    }

                                $text = "    - object '{$object->name()}' merged with its ancestor, deleting this one... ";
                                $this->deletedObjects[$index]['kept'] = $pickedObject->name();
                                if( $this->deletedObjects[$index]['removed'] == "" )
                                    $this->deletedObjects[$index]['removed'] = $object->name();
                                else
                                    $this->deletedObjects[$index]['removed'] .= "|" . $object->name();
                                $object->replaceMeGlobally($ancestor);
                                if( $this->apiMode )
                                    $object->owner->API_removeTag($object);
                                else
                                    $object->owner->removeTag($object);

                                PH::print_stdout($text);

                                $text = "         ancestor name: '{$ancestor->name()}' DG: ";
                                if( $ancestor->owner->owner->name() == "" ) $text .= "'shared'";
                                else $text .= "'{$ancestor->owner->owner->name()}'";
                                $text .=  "  color: '{$ancestor->getColor()}' ";
                                PH::print_stdout($text);

                                if( $pickedObject === $object )
                                    $pickedObject = $ancestor;

                                $countRemoved++;

                                if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                                {
                                    PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                                    break 2;
                                }

                                continue;
                            }
                            else
                                $ancestor_different_color = "with different color";


                        }
                        PH::print_stdout( "    - object '{$object->name()}' cannot be merged because it has an ancestor " . $ancestor_different_color . "" );

                        $text = "         ancestor name: '{$ancestor->name()}' DG: ";
                        if( $ancestor->owner->owner->name() == "" ) $text .= "'shared'";
                        else $text .= "'{$ancestor->owner->owner->name()}'";
                        $text .=  "  color: '{$ancestor->getColor()}' ";
                        PH::print_stdout($text);

                        #unset($this->deletedObjects[$index]);
                        $this->deletedObjects[$index]['removed'] .= "|->ERROR ancestor: '" . $object->name() . "' cannot be merged";

                        continue;
                    }

                    if( $object === $pickedObject )
                        continue;

                    if( $this->dupAlg != 'identical' )
                    {
                        PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                        mwarning("implementation needed for TAG");
                        //Todo;SWASCHKUT
                        #$object->__replaceWhereIamUsed($this->apiMode, $pickedObject, TRUE, 5);

                        PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                        $this->deletedObjects[$index]['kept'] = $pickedObject->name();
                        if( $this->deletedObjects[$index]['removed'] == "" )
                            $this->deletedObjects[$index]['removed'] = $object->name();
                        else
                            $this->deletedObjects[$index]['removed'] .= "|" . $object->name();
                        if( $this->apiMode )
                        {
                            $object->owner->API_removeTag($object);
                        }
                        else
                        {
                            $object->owner->removeTag($object);
                        }

                        $countRemoved++;

                        if( $this->mergeCountLimit !== FALSE && $countRemoved >= $this->mergeCountLimit )
                        {
                            PH::print_stdout( "\n *** STOPPING MERGE OPERATIONS NOW SINCE WE REACHED mergeCountLimit ({$this->mergeCountLimit})" );
                            break 2;
                        }
                    }
                    else
                        PH::print_stdout( "    - SKIP: object name '{$object->_PANC_shortName()}' is not IDENTICAL" );
                }
            }

            $countChildRemoved = 0;
            $countChildCreated = 0;
            foreach( $child_hashMap as $index => &$hash )
            {
                PH::print_stdout( "" );
                PH::print_stdout( " - value '{$index}'" );
                $this->deletedObjects[$index]['kept'] = "";
                $this->deletedObjects[$index]['removed'] = "";


                $pickedObject = null;

                if( $this->pickFilter !== null )
                {
                    foreach( $hash as $object )
                    {
                        if( $this->pickFilter->matchSingleObject(array('object' => $object, 'nestedQueries' => &$nestedQueries)) )
                        {
                            $pickedObject = $object;
                            break;
                        }
                    }
                    if( $pickedObject === null )
                        $pickedObject = reset($hash);
                }
                else
                {
                    $pickedObject = reset($hash);
                }


                $tmp_DG_name = $store->owner->name();
                if( $tmp_DG_name == "" )
                    $tmp_DG_name = 'shared';

                /** @var Tag $tmp_tag */
                $tmp_tag = $store->find( $pickedObject->name() );
                if( $tmp_tag == null )
                {
                    if( isset( $child_NamehashMap[ $pickedObject->name() ] ) )
                    {
                        $exit = false;
                        $exitObject = null;
                        foreach( $child_NamehashMap[ $pickedObject->name() ] as $obj )
                        {
                            if( $obj->sameValue($pickedObject) ) //same color
                            {
                                $exit = true;
                                $exitObject = $obj;
                            }
                        }

                        if( $exit )
                        {
                            PH::print_stdout( "   * SKIP: no creation of object in DG: '".$tmp_DG_name."' as object with same name '{$exitObject->name()}' and different value exist at childDG level" );
                            continue;
                        }
                    }
                    PH::print_stdout( "   * create object in DG: '".$tmp_DG_name."' : '".$pickedObject->name()."'" );

                    $tmp_tag = $store->createTag($pickedObject->name() );
                    $tmp_tag->setColor( $pickedObject->getColor() );

                    /** @var TagStore $store */
                    if( $this->apiMode )
                    {
                        $tmp_tag->API_sync();;
                    }


                    $countChildCreated++;
                }
                else
                {
                    if( $tmp_tag->equals( $pickedObject ) )
                    {
                        PH::print_stdout( "   * keeping object '{$tmp_tag->_PANC_shortName()}'" );
                    }
                    else
                    {
                        PH::print_stdout( "    - SKIP: object name '{$pickedObject->_PANC_shortName()}' [with value '{$pickedObject->getColor()}'] is not IDENTICAL to object name: '{$tmp_tag->_PANC_shortName()}' [with value '{$tmp_tag->getColor()}'] " );
                        continue;
                    }
                }

                // Merging loop finally!
                foreach( $hash as $objectIndex => $object )
                {
                    PH::print_stdout( "    - replacing '{$object->_PANC_shortName()}' ..." );
                    #$object->__replaceWhereIamUsed($this->apiMode, $tmp_tag, TRUE, 5);
                    $object->replaceMeGlobally($tmp_tag);
                    #$object->merge_tag_description_to($tmp_tag, $this->apiMode);

                    PH::print_stdout( "    - deleting '{$object->_PANC_shortName()}'" );
                    $this->deletedObjects[$index]['kept'] = $tmp_tag->name();
                    if( $this->deletedObjects[$index]['removed'] == "" )
                        $this->deletedObjects[$index]['removed'] = $object->name();
                    else
                        $this->deletedObjects[$index]['removed'] .= "|" . $object->name();
                    if( $this->apiMode )
                        $object->owner->API_removeTag($object);
                    else
                        $object->owner->removeTag($object);

                    $countChildRemoved++;
                }
            }


            PH::print_stdout( "\n\nDuplicates removal is now done. Number of objects after cleanup: '{$store->count()}' (removed {$countRemoved} tags)\n" );
            if( count( $child_hashMap ) >0 )
                PH::print_stdout( "Duplicates ChildDG removal is now done. Number of objects after cleanup: '{$store->count()}' (removed/created {$countChildRemoved}/{$countChildCreated} tags)\n" );

            PH::print_stdout( "\n\n***********************************************\n" );

        }
    }


    function merger_final_step()
    {
        $this->save_our_work( true );

        if( isset(PH::$args['exportcsv']) )
        {
            foreach( $this->deletedObjects as $obj_index => $object_name )
            {
                if( !isset($object_name['kept']) )
                    print_r($object_name);
                PH::print_stdout($obj_index . "," . $object_name['kept'] . "," . $object_name['removed'] );
            }
        }

        $this->endOfScript();
    }
}