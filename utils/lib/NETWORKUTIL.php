<?php


class NETWORKUTIL extends UTIL
{
    public function utilStart()
    {
        $this->utilInit();

        $this->utilActionFilter();


        $this->location_filter_object();


        $this->time_to_process_objects();


        $this->GlobalFinishAction();

        PH::print_stdout( "" );
        PH::print_stdout( " **** PROCESSED $this->totalObjectsProcessed objects over {$this->totalObjectsOfSelectedStores} available ****" );
        PH::print_stdout( "" );
        PH::print_stdout( "" );

        $this->stats();

        $this->save_our_work(TRUE);

        if( PH::$shadow_json )
            print json_encode( PH::$JSON_OUT, JSON_PRETTY_PRINT );
    }


    public function location_filter_object()
    {
        $sub = null;

        foreach( $this->objectsLocation as $location )
        {
            $locationFound = FALSE;

            if( $this->configType == 'panos' )
            {
                #if( $location == 'shared' || $location == 'any' || $location == 'all' )
                if( $location == 'shared' || $location == 'any' )
                {
                    if( $this->utilType == 'virtualwire' )
                        $this->objectsToProcess[] = Array('store' => $this->pan->network->virtualWireStore, 'objects' => $this->pan->network->virtualWireStore->virtualWires());
                    elseif( $this->utilType == 'interface' )
                        $this->objectsToProcess[] = Array('store' => $this->pan->network, 'objects' => $this->pan->network->getAllInterfaces());
                    elseif( $this->utilType == 'routing' )
                        $this->objectsToProcess[] = Array('store' => $this->pan->network->virtualRouterStore, 'objects' => $this->pan->network->virtualRouterStore->getAll());
                    elseif( $this->utilType == 'zone' )
                    {
                        //zone store only in vsys available
                    }

                    $locationFound = TRUE;
                }


                foreach( $this->pan->getVirtualSystems() as $sub )
                {
                    if( isset(PH::$args['loadpanoramapushedconfig']) )
                    {

                        if( ($location == 'any' || $location == $sub->name() && !isset($ruleStoresToProcess[$sub->name()])) )
                        {
                            if( $this->utilType == 'virtualwire' )
                            {}
                            elseif( $this->utilType == 'interface' )
                            {}
                            elseif( $this->utilType == 'routing' )
                            {}
                            elseif( $this->utilType == 'zone' )
                            {}

                            $locationFound = TRUE;
                        }
                    }
                    else
                    {
                        if( ($location == 'any' || $location == $sub->name() && !isset($ruleStoresToProcess[$sub->name()])) )
                        {
                            if( $this->utilType == 'virtualwire' )
                            {}
                            elseif( $this->utilType == 'interface' )
                                $this->objectsToProcess[] = Array('store' => $sub->importedInterfaces, 'objects' => $sub->importedInterfaces->getAll());
                            elseif( $this->utilType == 'routing' )
                            {}
                            elseif( $this->utilType == 'zone' )
                                $this->objectsToProcess[] = array('store' => $sub->zoneStore, 'objects' => $sub->zoneStore->getall());

                            $locationFound = TRUE;
                        }
                    }

                    self::GlobalInitAction($sub);
                }
            }
            else
            {
                if( $this->configType == 'panorama' )
                    $subGroups = $this->pan->getDeviceGroups();
                elseif( $this->configType == 'fawkes' )
                {
                    $subGroups = $this->pan->getContainers();
                    $subGroups2 = $this->pan->getDeviceClouds();

                    $subGroups = array_merge( $subGroups, $subGroups2 );
                }

                if( $this->configType == 'panorama' )
                {
                    foreach( $this->pan->templates as $template )
                    {
                        if( $location == 'shared' || $location == 'any'  )
                        {
                            if( $this->utilType == 'virtualwire' )
                                $this->objectsToProcess[] = Array('store' => $template->deviceConfiguration->network->virtualWireStore, 'objects' => $template->deviceConfiguration->network->virtualWireStore->virtualWires());
                            elseif( $this->utilType == 'interface' )
                                $this->objectsToProcess[] = Array('store' => $template->deviceConfiguration->network, 'objects' => $template->deviceConfiguration->network->getAllInterfaces());
                            elseif( $this->utilType == 'routing' )
                                $this->objectsToProcess[] = Array('store' => $template->deviceConfiguration->network->virtualRouterStore, 'objects' => $template->deviceConfiguration->network->virtualRouterStore->getAll());
                            elseif( $this->utilType == 'zone' )
                            {
                                //zone store only in vsys available
                            }

                            $locationFound = true;
                        }

                        foreach( $template->deviceConfiguration->getVirtualSystems() as $sub )
                        {
                            if( ($location == 'any' || $location == $sub->name()) && !isset($util->objectsToProcess[$sub->name() . '%pre']) )
                            {
                                if( $this->utilType == 'virtualwire' )
                                {}
                                elseif( $this->utilType == 'interface' )
                                    $this->objectsToProcess[] = array('store' => $sub->importedInterfaces, 'objects' => $sub->importedInterfaces->getAll());
                                elseif( $this->utilType == 'routing' )
                                {}
                                elseif( $this->utilType == 'zone' )
                                    $this->objectsToProcess[] = array('store' => $sub->zoneStore, 'objects' => $sub->zoneStore->getall());

                                $locationFound = TRUE;
                            }
                        }
                    }
                }
            }

            if( !$locationFound )
                self::locationNotFound($location, $this->configType, $this->pan);
        }
    }

}