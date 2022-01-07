<?php
/**
 * ISC License
 *
 * Copyright (c) 2014-2018, Palo Alto Networks Inc.
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

//Todo:
// - create template-stack ( add to FW device (serial#))
// - create template (incl adding to template-stack)
// - add devicegroup to FW device serial#
// - containercreate / devicecloudcreate
// - devicegroupsetparent
// - containersetparent / deviceloudsetparent
// - templatemovesharedtovsys
// - templatestackmovetofirsttemplate

DeviceCallContext::$supportedActions['display'] = array(
    'name' => 'display',
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        PH::print_stdout( "     * " . get_class($object) . " '{$object->name()}'" );
        PH::$JSON_TMP['sub']['object'][$object->name()]['name'] = $object->name();
        PH::$JSON_TMP['sub']['object'][$object->name()]['type'] = get_class($object);

        if( get_class($object) == "TemplateStack" )
        {
            $used_templates = $object->templates;
            foreach( $used_templates as $template )
            {
                PH::print_stdout( $context->padding." - " . get_class($template) . " '{$template->name()}'" );
                PH::$JSON_TMP['sub']['object'][$object->name()]['template'][] = $template->name();
            }
            //Todo: PH::print_stdout( where this TemplateStack is used SERIAL
        }
        elseif( get_class($object) == "VirtualSystem" )
        {
            /** @var VirtualSystem $object */
            PH::print_stdout( $context->padding." - Name: '{$object->alternativeName()}'" );
            PH::$JSON_TMP['sub']['object'][$object->name()]['alternativename'] = $object->alternativeName();
        }
        elseif( get_class($object) == "DeviceGroup" )
        {
            $parentDGS = $object->parentDeviceGroups();
            $parentDGS['shared'] = $object->owner;


            $tmp_padding = "";
            foreach( array_reverse( $parentDGS ) as $key => $DG)
            {
                PH::print_stdout( $context->padding.$tmp_padding."- ".$key );
                $tmp_padding .= "  ";
                PH::$JSON_TMP['sub']['object'][$object->name()]['hierarchy'][] = $key;
            }
            foreach( $object->getDevicesInGroup() as $key => $device )
            {
                PH::print_stdout( $context->padding."- ".$key );
                PH::$JSON_TMP['sub']['object'][$object->name()]['devices'][] = $key;
            }


        }
        elseif( get_class($object) == "ManagedDevice" )
        {
            $managedDevice = $context->object;
            $device = $managedDevice->owner->owner;

            $padding = "       ";
            /** @var ManagedDevice $managedDevice */

            if( $managedDevice->getDeviceGroup() != null )
            {
                PH::print_stdout( $padding."DG: ".$managedDevice->getDeviceGroup() );
                PH::$JSON_TMP['sub']['object'][$object->name()]['dg'] = $managedDevice->getDeviceGroup();
            }


            if( $managedDevice->getTemplate() != null )
            {
                PH::print_stdout( $padding."Template: ".$managedDevice->getTemplate() );
                PH::$JSON_TMP['sub']['object'][$object->name()]['template'] = $managedDevice->getTemplate();
            }


            if( $managedDevice->getTemplateStack() != null )
            {
                PH::print_stdout( $padding."TempalteStack: ".$managedDevice->getTemplateStack() );
                PH::$JSON_TMP['sub']['object'][$object->name()]['templatestack'][$managedDevice->getTemplateStack()]['name'] = $managedDevice->getTemplateStack();

                $templatestack = $device->findTemplateStack( $managedDevice->getTemplateStack() );
                foreach( $templatestack->templates as $template )
                {
                    $template_obj = $device->findTemplate( $template );
                    if( $template_obj !== null )
                    {
                        PH::print_stdout( " - ".$template_obj->name() );
                        PH::$JSON_TMP['sub']['object'][$object->name()]['templatestack'][$managedDevice->getTemplateStack()]['templates'][] = $template_obj->name();
                    }

                }
            }

            if( $managedDevice->isConnected )
            {
                PH::print_stdout( $padding."connected" );
                PH::print_stdout( $padding."IP-Address: ".$managedDevice->mgmtIP );
                PH::print_stdout( $padding."Hostname: ".$managedDevice->hostname );
                PH::print_stdout( $padding."PAN-OS: ".$managedDevice->version );
                PH::print_stdout( $padding."Model: ".$managedDevice->model );
                PH::$JSON_TMP['sub']['object'][$object->name()]['connected'] = "true";
                PH::$JSON_TMP['sub']['object'][$object->name()]['hostname'] = $managedDevice->hostname;
                PH::$JSON_TMP['sub']['object'][$object->name()]['ip-address'] = $managedDevice->mgmtIP;
                PH::$JSON_TMP['sub']['object'][$object->name()]['sw-version'] = $managedDevice->version;
                PH::$JSON_TMP['sub']['object'][$object->name()]['model'] = $managedDevice->model;
            }

        }
        elseif( get_class($object) == "Template" )
        {
            //Todo: PH::print_stdout( where this template is used // full templateStack hierarchy
        }

        PH::print_stdout( "" );
    },
);
DeviceCallContext::$supportedActions['displayreferences'] = array(
    'name' => 'displayReferences',
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;

        if( get_class($object) == "TemplateStack" )
        {

        }
        elseif( get_class($object) == "Template" )
        {
            //Todo: Templates are not displaying templatestack until now
            $object->display_references(7);
        }
        elseif( get_class($object) == "VirtualSystem" )
        {
            /** @var VirtualSystem $object */
        }
        elseif( get_class($object) == "DeviceGroup" )
        {

        }
        elseif( get_class($object) == "ManagedDevice" )
        {
            //serial is references in DG / template-stack, but also in Securityrules as target
            //Todo: secrule target is missing until now
            $object->display_references(7);
        }

        return null;

    },
);
DeviceCallContext::$supportedActions['DeviceGroup-create'] = array(
    'name' => 'devicegroup-create',
    'MainFunction' => function (DeviceCallContext $context) {
    },
    'GlobalFinishFunction' => function (DeviceCallContext $context) {
        $dgName = $context->arguments['name'];
        $parentDG = $context->arguments['parentdg'];

        $pan = $context->subSystem;

        if( !$pan->isPanorama() )
            derr("only supported on Panorama config");

        if( $parentDG != 'null' )
        {
            $tmp_parentdg = $pan->findDeviceGroup($parentDG);
            if( $tmp_parentdg === null )
            {
                $string = "parentDG set with '" . $parentDG . "' but not found on this config";
                PH::ACTIONstatus($context, "SKIPPED", $string);
                $parentDG = null;
            }
        }

        $tmp_dg = $pan->findDeviceGroup($dgName);
        if( $tmp_dg === null )
        {
            $string = "create DeviceGroup: " . $dgName;
            #PH::ACTIONlog($context, $string);
            if( $parentDG === 'null' )
                $parentDG = null;

            $dg = $pan->createDeviceGroup($dgName, $parentDG);

        }
        else
        {
            $string = "DeviceGroup with name: " . $dgName . " already available!";
            PH::ACTIONlog( $context, $string );
        }
    },
    'args' => array(
        'name' => array('type' => 'string', 'default' => 'false'),
        'parentdg' => array('type' => 'string', 'default' => 'null'),
    ),
);
DeviceCallContext::$supportedActions['DeviceGroup-delete'] = array(
    'name' => 'devicegroup-delete',
    'MainFunction' => function (DeviceCallContext $context) {

        $object = $context->object;
        $name = $object->name();

        $pan = $context->subSystem;
        if( !$pan->isPanorama() )
            derr( "only supported on Panorama config" );

        if( get_class($object) == "DeviceGroup" )
        {
            $childDG = $object->_childDeviceGroups;
            if( count($childDG) != 0 )
            {
                $string = "DG with name: '" . $name . "' has ChildDGs. DG can not removed";
                PH::ACTIONstatus($context, "SKIPPED", $string);
            }
            else
            {
                $string ="     * delete DeviceGroup: " . $name;
                PH::ACTIONlog( $context, $string );
                $pan->removeDeviceGroup($object);
            }
        }
    }
);
DeviceCallContext::$supportedActions[] = array(
    'name' => 'exportToExcel',
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        $context->objectList[] = $object;
    },
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->objectList = array();
    },
    'GlobalFinishFunction' => function (DeviceCallContext $context) {
        $args = &$context->arguments;
        $filename = $args['filename'];

        if( isset( $_SERVER['REQUEST_METHOD'] ) )
            $filename = "project/html/".$filename;

        $lines = '';
        $encloseFunction = function ($value, $nowrap = TRUE) {
            if( is_string($value) )
                $output = htmlspecialchars($value);
            elseif( is_array($value) )
            {
                $output = '';
                $first = TRUE;
                foreach( $value as $subValue )
                {
                    if( !$first )
                    {
                        $output .= '<br />';
                    }
                    else
                        $first = FALSE;

                    if( is_string($subValue) )
                        $output .= htmlspecialchars($subValue);
                    else
                        $output .= htmlspecialchars($subValue->name());
                }
            }
            else
                derr('unsupported');

            if( $nowrap )
                return '<td style="white-space: nowrap">' . $output . '</td>';

            return '<td>' . $output . '</td>';
        };


        $addWhereUsed = FALSE;
        $addUsedInLocation = FALSE;

        $optionalFields = &$context->arguments['additionalFields'];

        if( isset($optionalFields['WhereUsed']) )
            $addWhereUsed = TRUE;

        if( isset($optionalFields['UsedInLocation']) )
            $addUsedInLocation = TRUE;


        #$headers = '<th>location</th><th>name</th><th>template</th>';
        $headers = '<th>name</th><th>template</th>';

        if( $addWhereUsed )
            $headers .= '<th>where used</th>';
        if( $addUsedInLocation )
            $headers .= '<th>location used</th>';

        $count = 0;
        if( isset($context->objectList) )
        {
            foreach( $context->objectList as $object )
            {
                $count++;

                /** @var Tag $object */
                if( $count % 2 == 1 )
                    $lines .= "<tr>\n";
                else
                    $lines .= "<tr bgcolor=\"#DDDDDD\">";

                #$lines .= $encloseFunction(PH::getLocationString($object));

                $lines .= $encloseFunction($object->name());

                if( get_class($object) == "TemplateStack" )
                {
                    $lines .= $encloseFunction( array_reverse($object->templates) );
                }

                if( $addWhereUsed )
                {
                    $refTextArray = array();
                    foreach( $object->getReferences() as $ref )
                        $refTextArray[] = $ref->_PANC_shortName();

                    $lines .= $encloseFunction($refTextArray);
                }
                if( $addUsedInLocation )
                {
                    $refTextArray = array();
                    foreach( $object->getReferences() as $ref )
                    {
                        $location = PH::getLocationString($object->owner);
                        $refTextArray[$location] = $location;
                    }

                    $lines .= $encloseFunction($refTextArray);
                }

                $lines .= "</tr>\n";
            }
        }

        $content = file_get_contents(dirname(__FILE__) . '/html/export-template.html');
        $content = str_replace('%TableHeaders%', $headers, $content);

        $content = str_replace('%lines%', $lines, $content);

        $jscontent = file_get_contents(dirname(__FILE__) . '/html/jquery.min.js');
        $jscontent .= "\n";
        $jscontent .= file_get_contents(dirname(__FILE__) . '/html/jquery.stickytableheaders.min.js');
        $jscontent .= "\n\$('table').stickyTableHeaders();\n";

        $content = str_replace('%JSCONTENT%', $jscontent, $content);

        file_put_contents($filename, $content);
    },
    'args' => array('filename' => array('type' => 'string', 'default' => '*nodefault*'),
        'additionalFields' =>
            array('type' => 'pipeSeparatedList',
                'subtype' => 'string',
                'default' => '*NONE*',
                'choices' => array('WhereUsed', 'UsedInLocation'),
                'help' =>
                    "pipe(|) separated list of additional field to include in the report. The following is available:\n" .
                    "  - WhereUsed : list places where object is used (rules, groups ...)\n" .
                    "  - UsedInLocation : list locations (vsys,dg,shared) where object is used\n")
    )
);
DeviceCallContext::$supportedActions['template-add'] = array(
    'name' => 'template-add',
    'MainFunction' => function (DeviceCallContext $context) {

        /** @var TemplateStack $object */
        $object = $context->object;

        $pan = $context->subSystem;
        if( !$pan->isPanorama() )
            derr( "only supported on Panorama config" );

        if( get_class($object) == "TemplateStack" )
        {
            $templateName = $context->arguments['templateName'];
            $position = $context->arguments['position'];


            $template = $object->owner->findTemplate( $templateName );

            if( $template == null )
            {
                $string = "adding template '".$templateName."' because it is not found in this config";
                PH::ACTIONstatus( $context, "SKIPPED", $string );

                return null;
            }

            if( $context->isAPI )
                $object->API_addTemplate( $template, $position );
            else
                $object->addTemplate( $template, $position );
        }
        PH::print_stdout( "" );
    },
    'args' => array(
        'templateName' => array('type' => 'string', 'default' => 'false'),
        'position' => array('type' => 'string', 'default' => 'bottom'),
    ),
);
DeviceCallContext::$supportedActions['AddressStore-rewrite'] = array(
    'name' => 'addressstore-rewrite',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
    },
    'MainFunction' => function (DeviceCallContext $context) {

        /** @var DeviceGroup $object */
        $object = $context->object;

        $pan = $context->subSystem;
        if( !$pan->isPanorama() )
            derr( "only supported on Panorama config" );

        if( get_class($object) == "DeviceGroup" )
        {
            if( $context->first )
            {
                $object->owner->addressStore->rewriteAddressStoreXML();
                $object->owner->addressStore->rewriteAddressGroupStoreXML();
                $context->first = false;
            }

            $object->addressStore->rewriteAddressStoreXML();
            $object->addressStore->rewriteAddressGroupStoreXML();
        }

    }
  //rewriteAddressStoreXML()
);
DeviceCallContext::$supportedActions['exportInventoryToExcel'] = array(
    'name' => 'exportInventoryToExcel',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
        $context->fields = array();
        $context->device_array = array();
    },
    'MainFunction' => function (DeviceCallContext $context)
    {

        if( $context->first && get_class($context->object) == "ManagedDevice" )
        {
            $connector = findConnectorOrDie($context->object);
            $context->device_array = $connector->panorama_getAllFirewallsSerials();


            foreach( $context->device_array as $index => &$array )
            {
                foreach( $array as $key => $value )
                    $context->fields[$key] = $key;
            }


            foreach( $context->device_array as $index => &$array )
            {
                foreach( $context->fields as $key => $value )
                {
                    if( !isset( $array[$key] ) )
                        $array[$key] = "not set";
                }
            }
        }

    },
    'GlobalFinishFunction' => function (DeviceCallContext $context)
    {
        $content = "";
        if( get_class($context->object) == "ManagedDevice" )
        {
            $lines = '';

            $count = 0;
            if( !empty($context->device_array) )
            {
                foreach ($context->device_array as $device)
                {
                    $count++;

                    /** @var SecurityRule|NatRule $rule */
                    if ($count % 2 == 1)
                        $lines .= "<tr>\n";
                    else
                        $lines .= "<tr bgcolor=\"#DDDDDD\">";

                    foreach($context->fields as $fieldName => $fieldID )
                    {
                        $lines .= "<td>".$device[$fieldID]."</td>";
                    }
                    $lines .= "</tr>\n";
                }
            }

            $tableHeaders = '';
            foreach($context->fields as $fName => $value )
                $tableHeaders .= "<th>{$fName}</th>\n";

            $content = file_get_contents(dirname(__FILE__).'/html/export-template.html');


            $content = str_replace('%TableHeaders%', $tableHeaders, $content);

            $content = str_replace('%lines%', $lines, $content);

            $jscontent =  file_get_contents(dirname(__FILE__).'/html/jquery.min.js');
            $jscontent .= "\n";
            $jscontent .= file_get_contents(dirname(__FILE__).'/html/jquery.stickytableheaders.min.js');
            $jscontent .= "\n\$('table').stickyTableHeaders();\n";

            $content = str_replace('%JSCONTENT%', $jscontent, $content);
        }
        file_put_contents($context->arguments['filename'], $content);
    },
    'args' => array(
        'filename' => array('type' => 'string', 'default' => '*nodefault*',
            'help' => "only usable with 'devicetype=manageddevice'"
        )
    )
);
DeviceCallContext::$supportedActions['exportLicenseToExcel'] = array(
    'name' => 'exportLicenseToExcel',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
        $context->fields = array();
        $context->device_array = array();
    },
    'MainFunction' => function (DeviceCallContext $context)
    {

        if( $context->first && get_class($context->object) == "ManagedDevice" )
        {
            $connector = findConnectorOrDie($context->object);
            $configRoot = $connector->sendOpRequest( '<request><batch><license><info></info></license></batch></request>' );



            $configRoot = DH::findFirstElement('result', $configRoot);
            if( $configRoot === FALSE )
                derr("<result> was not found", $configRoot);

            $configRoot = DH::findFirstElement('devices', $configRoot);
            if( $configRoot === FALSE )
                derr("<config> was not found", $configRoot);


#var_dump( $configRoot );

            foreach( $configRoot->childNodes as $entry )
            {
                if( $entry->nodeType != XML_ELEMENT_NODE )
                    continue;

                foreach( $entry->childNodes as $node )
                {
                    if( $node->nodeType != XML_ELEMENT_NODE )
                        continue;


                    if( $node->nodeName == "serial" ||  $node->nodeName == "serial-no" )
                    {
                        #print $node->nodeName." : ".$node->textContent."\n";
                        $serial_no = $node->textContent;
                        $context->device_array[ $serial_no ][ $node->nodeName ] = $serial_no;
                    }
                    else
                    {
                        #print $node->nodeName." : ".$node->textContent."\n";
                        $tmp_node = $node->textContent;
                        $context->device_array[ $serial_no ][ $node->nodeName ] = $tmp_node;

                        if( $node->childNodes->length > 1 )
                        {
                            foreach( $node->childNodes as $child )
                            {
                                if( $node->nodeType != XML_ELEMENT_NODE )
                                    continue;


                                if( $child->nodeName == "entry" )
                                {
                                    $tmp_node = $child->textContent;
                                    $context->device_array[ $serial_no ][ $child->getAttribute('name') ] = $tmp_node;
                                }
                            }
                        }
                    }
                }
            }

            foreach( $context->device_array as $index => &$array )
            {
                foreach( $array as $key => $value )
                    $context->fields[$key] = $key;
            }


            foreach( $context->device_array as $index => &$array )
            {
                foreach( $context->fields as $key => $value )
                {
                    if( !isset( $array[$key] ) )
                        $array[$key] = "- - - - -";
                }
            }
        }
    },
    'GlobalFinishFunction' => function (DeviceCallContext $context)
    {
        $content = "";
        if( get_class($context->object) == "ManagedDevice" )
        {
            $lines = '';

            $count = 0;
            if( !empty($context->device_array) )
            {
                foreach ($context->device_array as $device)
                {
                    $count++;

                    /** @var SecurityRule|NatRule $rule */
                    if ($count % 2 == 1)
                        $lines .= "<tr>\n";
                    else
                        $lines .= "<tr bgcolor=\"#DDDDDD\">";

                    foreach($context->fields as $fieldName => $fieldID )
                    {
                        $lines .= "<td>".$device[$fieldID]."</td>";
                    }

                    $lines .= "</tr>\n";
                }
            }


            $tableHeaders = '';
            foreach($context->fields as $fName => $value )
                $tableHeaders .= "<th>{$fName}</th>\n";

            $content = file_get_contents(dirname(__FILE__).'/html/export-template.html');


            $content = str_replace('%TableHeaders%', $tableHeaders, $content);

            $content = str_replace('%lines%', $lines, $content);

            $jscontent =  file_get_contents(dirname(__FILE__).'/html/jquery.min.js');
            $jscontent .= "\n";
            $jscontent .= file_get_contents(dirname(__FILE__).'/html/jquery.stickytableheaders.min.js');
            $jscontent .= "\n\$('table').stickyTableHeaders();\n";

            $content = str_replace('%JSCONTENT%', $jscontent, $content);


        }

        file_put_contents($context->arguments['filename'], $content);
    },
    'args' => array(
        'filename' => array('type' => 'string', 'default' => '*nodefault*',
        'help' => "only usable with 'devicetype=manageddevice'"
        )
    )
);

DeviceCallContext::$supportedActions['display-shadowrule'] = array(
    'name' => 'display-shadowrule',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;

        if( !$context->isAPI )
            derr( "API mode needed for actions=display-shadowrule" );
    },
    'MainFunction' => function (DeviceCallContext $context)
    {
        $object = $context->object;
        $classtype = get_class($object);

        #if( $context->object->version < 91 )
        #    derr( "PAN-OS >= 9.1 is needed for display-shadowrule", null, false );

        $shadowArray = array();
        if( $classtype == "VirtualSystem" )
        {
            $type = "vsys";
            $type_name = $object->name();
            $countInfo = "<" . $type . ">" . $type_name . "</" . $type . ">";

            $shadowArray = $context->connector->getShadowInfo($countInfo, false);
        }
        elseif( $classtype == "ManagedDevice" )
        {
            if( $object->isConnected )
            {
                $type = "device-serial";
                $type_name = $object->name();
                $countInfo = "<" . $type . ">" . $type_name . "</" . $type . ">";

                $shadowArray = $context->connector->getShadowInfo($countInfo, true);
            }
        }
        elseif( $classtype == "DeviceGroup" )
        {
            /** @var DeviceGroup $object */
            $devices = $object->getDevicesInGroup();

            $shadowArray = array();
            foreach( $devices as $serial => $device )
            {
                $managedDevice = $object->owner->managedFirewallsStore->find( $serial );
                if( $managedDevice->isConnected )
                {
                    $type = "device-serial";
                    $type_name = $managedDevice->name();
                    $countInfo = "<" . $type . ">" . $type_name . "</" . $type . ">";

                    $shadowArray2 = $context->connector->getShadowInfo($countInfo, true);
                    $shadowArray = array_merge( $shadowArray, $shadowArray2 );
                }
            }
            //try to only use active device / skip passive FW
        }


        foreach( $shadowArray as $name => $array )
        {
            foreach( $array as $ruletype => $entries )
            {
                if( $ruletype == 'security'  || $ruletype == "security-rule" )
                    $ruletype = "securityRules";
                elseif( $ruletype == 'decryption' || $ruletype == "ssl-rule" )
                    $ruletype = "decryptionRules";
                else
                    $ruletype = "securityRules";

                if( $classtype == "ManagedDevice" )
                {
                    $subName = "DG";
                    PH::print_stdout( "     ** ".$subName.": " . $name );
                }

                foreach( $entries as $key => $item  )
                {
                    $rule = null;
                    $replace =  null;

                    //uid: $key -> search rule name for uid
                    if( $classtype == "ManagedDevice" )
                    {
                        /** @var PanoramaConf $pan */
                        $pan = $object->owner->owner;

                        /** @var DeviceGroup $sub */
                        $sub = $pan->findDeviceGroup($name);

                        $rule = $sub->$ruletype->findByUUID( $key );
                        while( $rule === null )
                        {
                            $sub = $sub->parentDeviceGroup;
                            if( $sub !== null )
                            {
                                $rule = $sub->$ruletype->findByUUID( $key );
                                $ownerDG = $sub->name();
                            }
                            else
                            {
                                $rule = $pan->$ruletype->findByUUID( $key );
                                $ownerDG = "shared";
                                if( $rule === null )
                                    break;
                            }
                        }
                    }
                    elseif( $classtype == "VirtualSystem" )
                    {
                        /** @var PANConf $pan */
                        $pan = $object->owner;

                        /** @var VirtualSystem $sub */
                        $sub = $pan->findVirtualSystem( $name );
                        $rule = $sub->$ruletype->findByUUID( $key );
                        $ownerDG = $name;

                        if( $rule === null )
                        {
                            $ruleArray = $sub->$ruletype->resultingRuleSet();
                            foreach( $ruleArray as $ruleSingle )
                            {
                                /** @var SecurityRule $ruleSingle */
                                if( $ruleSingle->uuid() === $key )
                                {
                                    $rule = $ruleSingle;
                                    $ownerDG = "panoramaPushedConfig";
                                }
                            }
                        }
                        $replace = "Rule '".$rule->name()."'";
                    }
                    elseif( $classtype == "DeviceGroup" )
                    {
                        /** @var PanoramaConf $pan */
                        $pan = $object->owner;

                        $rule = $object->$ruletype->findByUUID( $key );
                        $sub = $object;

                        while( $rule === null )
                        {
                            $sub = $sub->parentDeviceGroup;
                            if( $sub !== null )
                            {
                                $rule = $sub->$ruletype->findByUUID( $key );
                                $ownerDG = $sub->name();
                            }
                            else
                            {
                                $rule = $pan->$ruletype->findByUUID( $key );
                                $ownerDG = "shared";
                                if( $rule === null )
                                    break;
                            }
                        }
                    }

                    if( $rule !== null )
                        PH::print_stdout( "        * RULE: '" . $rule->name(). "' owner: '".$ownerDG."' shadows rule: " );
                    else
                        PH::print_stdout( "        * RULE: '" . $key."'" );

                    foreach( $item as $shadow )
                    {
                        if( $replace !== null )
                            $shadow = str_replace( $replace, "", $shadow );

                        $shadow = str_replace( " shadows rule ", "", $shadow );
                        $shadow = str_replace( "shadows ", "", $shadow );
                        $shadow = str_replace( ".", "", $shadow );
                        $shadow = str_replace( "'", "", $shadow );
                        PH::print_stdout( "          - '" . $shadow."'" );
                    }
                }
            }
        }
    }
);

DeviceCallContext::$supportedActions['securityprofile-create-alert-only'] = array(
    'name' => 'securityprofile-create-alert-only',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;

        if( $context->isAPI )
            derr( "API mode not implemented yet" );

        if( $context->subSystem->isPanorama() )
        {
            $countDG = count( $context->subSystem->getDeviceGroups() );
            if( $countDG == 0 )
            {
                #$dg = $context->subSystem->createDeviceGroup( "alert-only" );
                derr( "NO DG available; please run 'pa_device-edit in=InputConfig.xml out=OutputConfig.xml actions=devicegroup-create:DG-NAME' first", null, false );
            }
        }
    },
    'MainFunction' => function (DeviceCallContext $context)
    {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            $av_xmlString = "<entry name=\"Alert-Only-AV\">
        <decoder>
          <entry name=\"ftp\">
            <action>alert</action>
            <wildfire-action>alert</wildfire-action>
            <mlav-action>alert</mlav-action>
          </entry>
          <entry name=\"http\">
            <action>alert</action>
            <wildfire-action>alert</wildfire-action>
            <mlav-action>alert</mlav-action>
          </entry>
          <entry name=\"http2\">
            <action>alert</action>
            <wildfire-action>alert</wildfire-action>
            <mlav-action>alert</mlav-action>
          </entry>
          <entry name=\"imap\">
            <action>alert</action>
            <wildfire-action>alert</wildfire-action>
            <mlav-action>alert</mlav-action>
          </entry>
          <entry name=\"pop3\">
            <action>alert</action>
            <wildfire-action>alert</wildfire-action>
            <mlav-action>alert</mlav-action>
          </entry>
          <entry name=\"smb\">
            <action>alert</action>
            <wildfire-action>alert</wildfire-action>
            <mlav-action>alert</mlav-action>
          </entry>
          <entry name=\"smtp\">
            <action>alert</action>
            <wildfire-action>alert</wildfire-action>
            <mlav-action>alert</mlav-action>
          </entry>
        </decoder>
        <mlav-engine-filebased-enabled>
          <entry name=\"Windows Executables\">
            <mlav-policy-action>enable(alert-only)</mlav-policy-action>
          </entry>
          <entry name=\"PowerShell Script 1\">
            <mlav-policy-action>enable(alert-only)</mlav-policy-action>
          </entry>
          <entry name=\"PowerShell Script 2\">
            <mlav-policy-action>enable(alert-only)</mlav-policy-action>
          </entry>
          <entry name=\"Executable Linked Format\">
            <mlav-policy-action>enable(alert-only)</mlav-policy-action>
          </entry>
        </mlav-engine-filebased-enabled>
      </entry>";

            $as_xmlString = "<entry name=\"Alert-Only-AS\">
        <botnet-domains>
          <lists>
            <entry name=\"default-paloalto-dns\">
              <action>
                <alert/>
              </action>
              <packet-capture>single-packet</packet-capture>
            </entry>
          </lists>
          <dns-security-categories>
            <entry name=\"pan-dns-sec-benign\">
              <log-level>default</log-level>
              <action>allow</action>
              <packet-capture>disable</packet-capture>
            </entry>
            <entry name=\"pan-dns-sec-cc\">
              <log-level>default</log-level>
              <action>allow</action>
              <packet-capture>single-packet</packet-capture>
            </entry>
            <entry name=\"pan-dns-sec-ddns\">
              <log-level>default</log-level>
              <action>allow</action>
              <packet-capture>single-packet</packet-capture>
            </entry>
            <entry name=\"pan-dns-sec-malware\">
              <log-level>default</log-level>
              <action>allow</action>
              <packet-capture>single-packet</packet-capture>
            </entry>
            <entry name=\"pan-dns-sec-recent\">
              <log-level>default</log-level>
              <action>allow</action>
              <packet-capture>single-packet</packet-capture>
            </entry>
          </dns-security-categories>
          <sinkhole>
            <ipv4-address>sinkhole.paloaltonetworks.com</ipv4-address>
            <ipv6-address>2600:5200::1</ipv6-address>
          </sinkhole>
        </botnet-domains>
        <rules>
          <entry name=\"Alert-All\">
            <action>
              <alert/>
            </action>
            <severity>
              <member>any</member>
            </severity>
            <threat-name>any</threat-name>
            <category>any</category>
            <packet-capture>disable</packet-capture>
          </entry>
        </rules>
      </entry>";

            $vp_xmlString = "<entry name=\"Alert-Only-VP\">
        <rules>
          <entry name=\"Alert-All\">
            <action>
              <alert/>
            </action>
            <vendor-id>
              <member>any</member>
            </vendor-id>
            <severity>
              <member>any</member>
            </severity>
            <cve>
              <member>any</member>
            </cve>
            <threat-name>any</threat-name>
            <host>any</host>
            <category>any</category>
            <packet-capture>disable</packet-capture>
          </entry>
        </rules>
      </entry>";

            $url_xmlString = "<entry name=\"Alert-Only-URL\">
        <credential-enforcement>
          <mode>
            <ip-user/>
          </mode>
          <log-severity>medium</log-severity>
          <alert>
            <member>Allow</member>
            <member>Block</member>
            <member>abortion</member>
            <member>abused-drugs</member>
            <member>adult</member>
            <member>alcohol-and-tobacco</member>
            <member>auctions</member>
            <member>business-and-economy</member>
            <member>command-and-control</member>
            <member>computer-and-internet-info</member>
            <member>content-delivery-networks</member>
            <member>copyright-infringement</member>
            <member>cryptocurrency</member>
            <member>dating</member>
            <member>dynamic-dns</member>
            <member>educational-institutions</member>
            <member>entertainment-and-arts</member>
            <member>extremism</member>
            <member>financial-services</member>
            <member>gambling</member>
            <member>games</member>
            <member>government</member>
            <member>grayware</member>
            <member>hacking</member>
            <member>health-and-medicine</member>
            <member>high-risk</member>
            <member>home-and-garden</member>
            <member>hunting-and-fishing</member>
            <member>insufficient-content</member>
            <member>internet-communications-and-telephony</member>
            <member>internet-portals</member>
            <member>job-search</member>
            <member>legal</member>
            <member>low-risk</member>
            <member>malware</member>
            <member>medium-risk</member>
            <member>military</member>
            <member>motor-vehicles</member>
            <member>music</member>
            <member>newly-registered-domain</member>
            <member>news</member>
            <member>not-resolved</member>
            <member>nudity</member>
            <member>online-storage-and-backup</member>
            <member>parked</member>
            <member>peer-to-peer</member>
            <member>personal-sites-and-blogs</member>
            <member>philosophy-and-political-advocacy</member>
            <member>phishing</member>
            <member>private-ip-addresses</member>
            <member>proxy-avoidance-and-anonymizers</member>
            <member>questionable</member>
            <member>real-estate</member>
            <member>real-time-detection</member>
            <member>recreation-and-hobbies</member>
            <member>reference-and-research</member>
            <member>religion</member>
            <member>search-engines</member>
            <member>sex-education</member>
            <member>shareware-and-freeware</member>
            <member>shopping</member>
            <member>social-networking</member>
            <member>society</member>
            <member>sports</member>
            <member>stock-advice-and-tools</member>
            <member>streaming-media</member>
            <member>swimsuits-and-intimate-apparel</member>
            <member>training-and-tools</member>
            <member>translation</member>
            <member>travel</member>
            <member>unknown</member>
            <member>weapons</member>
            <member>web-advertisements</member>
            <member>web-based-email</member>
            <member>web-hosting</member>
          </alert>
        </credential-enforcement>
        <alert>
          <member>Allow</member>
          <member>Block</member>
          <member>abortion</member>
          <member>abused-drugs</member>
          <member>adult</member>
          <member>alcohol-and-tobacco</member>
          <member>auctions</member>
          <member>business-and-economy</member>
          <member>command-and-control</member>
          <member>computer-and-internet-info</member>
          <member>content-delivery-networks</member>
          <member>copyright-infringement</member>
          <member>cryptocurrency</member>
          <member>dating</member>
          <member>dynamic-dns</member>
          <member>educational-institutions</member>
          <member>entertainment-and-arts</member>
          <member>extremism</member>
          <member>financial-services</member>
          <member>gambling</member>
          <member>games</member>
          <member>government</member>
          <member>grayware</member>
          <member>hacking</member>
          <member>health-and-medicine</member>
          <member>high-risk</member>
          <member>home-and-garden</member>
          <member>hunting-and-fishing</member>
          <member>insufficient-content</member>
          <member>internet-communications-and-telephony</member>
          <member>internet-portals</member>
          <member>job-search</member>
          <member>legal</member>
          <member>low-risk</member>
          <member>malware</member>
          <member>medium-risk</member>
          <member>military</member>
          <member>motor-vehicles</member>
          <member>music</member>
          <member>newly-registered-domain</member>
          <member>news</member>
          <member>not-resolved</member>
          <member>nudity</member>
          <member>online-storage-and-backup</member>
          <member>parked</member>
          <member>peer-to-peer</member>
          <member>personal-sites-and-blogs</member>
          <member>philosophy-and-political-advocacy</member>
          <member>phishing</member>
          <member>private-ip-addresses</member>
          <member>proxy-avoidance-and-anonymizers</member>
          <member>questionable</member>
          <member>real-estate</member>
          <member>real-time-detection</member>
          <member>recreation-and-hobbies</member>
          <member>reference-and-research</member>
          <member>religion</member>
          <member>search-engines</member>
          <member>sex-education</member>
          <member>shareware-and-freeware</member>
          <member>shopping</member>
          <member>social-networking</member>
          <member>society</member>
          <member>sports</member>
          <member>stock-advice-and-tools</member>
          <member>streaming-media</member>
          <member>swimsuits-and-intimate-apparel</member>
          <member>training-and-tools</member>
          <member>translation</member>
          <member>travel</member>
          <member>unknown</member>
          <member>weapons</member>
          <member>web-advertisements</member>
          <member>web-based-email</member>
          <member>web-hosting</member>
        </alert>
        <mlav-engine-urlbased-enabled>
          <entry name=\"Phishing Detection\">
            <mlav-policy-action>alert</mlav-policy-action>
          </entry>
          <entry name=\"Javascript Exploit Detection\">
            <mlav-policy-action>alert</mlav-policy-action>
          </entry>
        </mlav-engine-urlbased-enabled>
      </entry>";

            $fb_xmlString = "<entry name=\"Alert-Only-FB\">
        <rules>
          <entry name=\"Alert-Only\">
            <application>
              <member>any</member>
            </application>
            <file-type>
              <member>any</member>
            </file-type>
            <direction>both</direction>
            <action>alert</action>
          </entry>
        </rules>
      </entry>";

            $wf_xmlString = "<entry name=\"Alert-Only-WF\">
        <rules>
          <entry name=\"Forward-All\">
            <application>
              <member>any</member>
            </application>
            <file-type>
              <member>any</member>
            </file-type>
            <direction>both</direction>
            <analysis>public-cloud</analysis>
          </entry>
        </rules>
      </entry>";

            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $context->arguments['shared'] )
                    $sharedStore = $sub->owner;
                else
                    $sharedStore = $sub;

                $name = "Alert-Only";
                $ownerDocument = $sub->xmlroot->ownerDocument;


                $sharedStore->customURLProfileStore->newCustomSecurityProfileURL( "Block" );
                $sharedStore->customURLProfileStore->newCustomSecurityProfileURL( "Allow" );
                $sharedStore->customURLProfileStore->newCustomSecurityProfileURL( "Custom-No-Decrypt" );


                $store = $sharedStore->AntiVirusProfileStore;
                $av = new AntiVirusProfile($name . "-AV", $store);
                $newdoc = new DOMDocument;
                $newdoc->loadXML($av_xmlString);
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);
                $av->load_from_domxml($node);
                $av->owner = null;
                $store->addSecurityProfile($av);

                $store = $sharedStore->AntiSpywareProfileStore;
                $as = new AntiSpywareProfile($name . "-AS", $store);
                $newdoc = new DOMDocument;
                $newdoc->loadXML($as_xmlString);
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);
                $as->load_from_domxml($node);
                $as->owner = null;
                $store->addSecurityProfile($as);

                $store = $sharedStore->VulnerabilityProfileStore;
                $vp = new VulnerabilityProfile($name . "-VP", $store);
                $newdoc = new DOMDocument;
                $newdoc->loadXML($vp_xmlString);
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);
                $vp->load_from_domxml($node);
                $vp->owner = null;
                $store->addSecurityProfile($vp);

                $store = $sharedStore->URLProfileStore;
                $url = new URLProfile($name . "-URL", $store);
                $newdoc = new DOMDocument;
                $newdoc->loadXML($url_xmlString);
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);
                $url->load_from_domxml($node);
                $url->owner = null;
                $store->addSecurityProfile($url);

                $store = $sharedStore->FileBlockingProfileStore;
                $fb = new FileBlockingProfile($name . "-FB", $store);
                $newdoc = new DOMDocument;
                $newdoc->loadXML($fb_xmlString);
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);
                $fb->load_from_domxml($node);
                $fb->owner = null;
                $store->addSecurityProfile($fb);

                $store = $sharedStore->WildfireProfileStore;
                $wf = new WildfireProfile($name . "-WF", $store);
                $newdoc = new DOMDocument;
                $newdoc->loadXML($wf_xmlString);
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);
                $wf->load_from_domxml($node);
                $wf->owner = null;
                $store->addSecurityProfile($wf);

                $secprofgrp = new SecurityProfileGroup($name, $sharedStore->securityProfileGroupStore, TRUE);

                $secprofgrp->setSecProf_AV($av->name());
                $secprofgrp->setSecProf_Spyware($as->name());
                $secprofgrp->setSecProf_Vuln($vp->name());
                $secprofgrp->setSecProf_URL($url->name());
                $secprofgrp->setSecProf_FileBlock($fb->name());
                $secprofgrp->setSecProf_Wildfire($wf->name());


                $sharedStore->securityProfileGroupStore->addSecurityProfileGroup($secprofgrp);

                $context->first = false;
            }
        }
    },
    'args' => array(
        'shared' => array('type' => 'bool', 'default' => 'false',
            'help' => "if set to true; securityProfiles are create at SHARED level; at least one DG must be available"
        )
    )
);

DeviceCallContext::$supportedActions['geoIP-check'] = array(
    'name' => 'geoIP-check',
    'GlobalInitFunction' => function (DeviceCallContext $context) {


        if( $context->subSystem->isPanorama() )
        {
            derr( "this action can be only run against PAN-OS FW", null, false );
        }

        $geoip = str_pad("geoIP JSON: ", 15) ."----------";
        $panos_geoip = str_pad("PAN-OS: ", 15) ."----------";

        $prefix = $context->arguments['checkIP'];

        if( filter_var($prefix, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) )
        {
            $filename = "ipv6";
            $prefixArray = explode(':', $prefix);
            $pattern = '/^' . $prefixArray[0] . ':' . $prefixArray[1] . ':/';
        }
        elseif( filter_var($prefix, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) )
        {
            $filename = "ipv4";
            $prefixArray = explode('.', $prefix);
            $pattern = '/^' . $prefixArray[0] . './';
        }
        else
            derr("not a valid IP: " . $prefix);


        $filepath = dirname(__FILE__)."/../../lib/resources/geoip/data/";
        $file = $filepath."RegionCC" . $filename . ".json";
        if ( !file_exists($file) )
        {
            derr( "Maxmind geo2ip lite database not downloaded correctly for PAN-OS-PHP", null, false );
        }
        $fileLine = file_get_contents( $file );
        $array = json_decode($fileLine, TRUE);
        unset( $fileLine);

        foreach( $array as $countryKey => $country )
        {
            foreach( $country as $value )
            {
                if( preg_match($pattern, $value) )
                    $responseArray[$value] = $countryKey;
            }
        }
        unset( $array );


        foreach( $responseArray as $ipKey => $countryKey )
        {
            if( cidr::netMatch($ipKey, $prefix) > 0 )
                $geoip = str_pad("geoIP JSON: ", 15) . $countryKey . " - " . $ipKey;
        }


        //###################################################

        if( $context->isAPI && $filename !== "ipv6" )
        {
            $request = "<show><location><ip>" . $prefix . "</ip></location></show>";

            try
            {
                $candidateDoc = $context->connector->sendOpRequest($request);
            }
            catch(Exception $e)
            {
                PH::disableExceptionSupport();
                print " ***** an error occured : " . $e->getMessage() . "\n\n";
            }


            #print $geoip . "\n";
            #$candidateDoc->preserveWhiteSpace = FALSE;
            #$candidateDoc->formatOutput = TRUE;
            #print $candidateDoc->saveXML();


            $result = DH::findFirstElement('result', $candidateDoc);
            $entry = DH::findFirstElement('entry', $result);

            $country = $entry->getAttribute("cc");
            $ip = DH::findFirstElement('ip', $entry)->textContent;
            $countryName = DH::findFirstElement('country', $entry)->textContent;

            $panos_geoip = str_pad("PAN-OS: ", 15) . $country . " - " . $ip . " - " . $countryName;
        }
        elseif($filename === "ipv6")
        {
            PH::print_stdout("not working for PAN-OS - ipv6 syntax for 'show location ip' not yet clear");
        }

        PH::print_stdout("");
        PH::print_stdout("");
        PH::print_stdout($geoip);
        PH::print_stdout($panos_geoip);
        PH::print_stdout("");

    },
    'MainFunction' => function (DeviceCallContext $context)
    {
    },
    'args' => array(
        'checkIP' => array('type' => 'string', 'default' => '8.8.8.8',
            'help' => "checkIP is IPv4 or IPv6 host address",
        )
    )
);



DeviceCallContext::$supportedActions['LogForwardingProfile-create-BP'] = array(
    'name' => 'logforwardingprofile-create-bp',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;

        if( $context->isAPI )
            derr( "API mode not implemented yet" );

        if( $context->subSystem->isPanorama() )
        {
            $countDG = count( $context->subSystem->getDeviceGroups() );
            if( $countDG == 0 )
            {
                #$dg = $context->subSystem->createDeviceGroup( "alert-only" );
                derr( "NO DG available; please run 'pa_device-edit in=InputConfig.xml out=OutputConfig.xml actions=devicegroup-create:DG-NAME' first", null, false );
            }
        }
    },
    'MainFunction' => function (DeviceCallContext $context)
    {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            $lfp_bp_xmlstring = "<entry name=\"default\">
  <match-list>
    <entry name=\"Traffic_Log_Forwarding\">
      <log-type>traffic</log-type>
      <filter>All Logs</filter>
      <send-to-panorama>yes</send-to-panorama>
    </entry>
    <entry name=\"Threat_Log_Forwarding\">
      <log-type>threat</log-type>
      <filter>All Logs</filter>
      <send-to-panorama>yes</send-to-panorama>
    </entry>
    <entry name=\"Wildfire_Log_Forwarding\">
      <log-type>wildfire</log-type>
      <filter>All Logs</filter>
      <send-to-panorama>yes</send-to-panorama>
    </entry>
    <entry name=\"URL_Log_Forwarding\">
      <log-type>url</log-type>
      <filter>All Logs</filter>
      <send-to-panorama>yes</send-to-panorama>
    </entry>
    <entry name=\"Data_Log_Forwarding\">
      <log-type>data</log-type>
      <filter>All Logs</filter>
      <send-to-panorama>yes</send-to-panorama>
    </entry>
    <entry name=\"Tunnel_Log_Forwarding\">
      <log-type>tunnel</log-type>
      <filter>All Logs</filter>
      <send-to-panorama>yes</send-to-panorama>
    </entry>
    <entry name=\"Auth_Log_Forwarding\">
      <log-type>auth</log-type>
      <filter>All Logs</filter>
      <send-to-panorama>yes</send-to-panorama>
    </entry>
  </match-list>
</entry>";

            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $context->arguments['shared'] )
                {
                    $sharedStore = $sub->owner;
                    $xmlRoot = DH::findFirstElementOrCreate('shared', $sharedStore->xmlroot);
                }
                else
                {
                    $sharedStore = $sub;
                    $xmlRoot = $sharedStore->xmlroot;
                }

                $ownerDocument = $sub->xmlroot->ownerDocument;

                $newdoc = new DOMDocument;
                $newdoc->loadXML( $lfp_bp_xmlstring );
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);


                $logSettings = DH::findFirstElementOrCreate('log-settings', $xmlRoot);
                $logSettingProfiles = DH::findFirstElementOrCreate('profiles', $logSettings);

                $entryDefault = DH::findFirstElementByNameAttr( 'entry', 'default', $logSettingProfiles );


                if( $entryDefault === null )
                    $logSettingProfiles->appendChild( $node );
                else
                    mwarning( "LogForwardingProfile 'default' already available. BestPractise LogForwardingProfile 'default' not created" );


                $context->first = false;
            }
        }
    },
    'args' => array(
        'shared' => array('type' => 'bool', 'default' => 'false',
            'help' => "if set to true; LogForwardingProfile is create at SHARED level; at least one DG must be available"
        )
    )
);

DeviceCallContext::$supportedActions['ZoneProtectionProfile-create-BP'] = array(
    'name' => 'zoneprotectionprofile-create-bp',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;

        if( $context->isAPI )
            derr( "API mode not implemented yet" );

        if( $context->subSystem->isPanorama() )
        {
            $countDG = count( $context->subSystem->getDeviceGroups() );
            if( $countDG == 0 )
            {
                #$dg = $context->subSystem->createDeviceGroup( "alert-only" );
                derr( "NO DG available; please run 'pa_device-edit in=InputConfig.xml out=OutputConfig.xml actions=devicegroup-create:DG-NAME' first", null, false );
            }
        }
    },
    'MainFunction' => function (DeviceCallContext $context)
    {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            $zpp_bp_xmlstring = "<entry name=\"Recommended_Zone_Protection\">
  <flood>
    <tcp-syn>
      <red>
        <alarm-rate>10000</alarm-rate>
        <activate-rate>10000</activate-rate>
        <maximal-rate>40000</maximal-rate>
      </red>
      <enable>no</enable>
    </tcp-syn>
    <icmp>
      <red>
        <alarm-rate>10000</alarm-rate>
        <activate-rate>10000</activate-rate>
        <maximal-rate>40000</maximal-rate>
      </red>
      <enable>no</enable>
    </icmp>
    <icmpv6>
      <red>
        <alarm-rate>10000</alarm-rate>
        <activate-rate>10000</activate-rate>
        <maximal-rate>40000</maximal-rate>
      </red>
      <enable>no</enable>
    </icmpv6>
    <other-ip>
      <red>
        <alarm-rate>10000</alarm-rate>
        <activate-rate>10000</activate-rate>
        <maximal-rate>40000</maximal-rate>
      </red>
      <enable>no</enable>
    </other-ip>
    <udp>
      <red>
        <alarm-rate>10000</alarm-rate>
        <activate-rate>10000</activate-rate>
        <maximal-rate>40000</maximal-rate>
      </red>
      <enable>no</enable>
    </udp>
  </flood>
  <scan>
    <entry name=\"8001\">
      <action>
        <alert/>
      </action>
      <interval>2</interval>
      <threshold>100</threshold>
    </entry>
    <entry name=\"8002\">
      <action>
        <alert/>
      </action>
      <interval>10</interval>
      <threshold>100</threshold>
    </entry>
    <entry name=\"8003\">
      <action>
        <alert/>
      </action>
      <interval>2</interval>
      <threshold>100</threshold>
    </entry>
  </scan>
  <discard-ip-spoof>yes</discard-ip-spoof>
  <discard-malformed-option>yes</discard-malformed-option>
  <remove-tcp-timestamp>yes</remove-tcp-timestamp>
  <strip-tcp-fast-open-and-data>no</strip-tcp-fast-open-and-data>
  <strip-mptcp-option>global</strip-mptcp-option>
</entry>";

            if( $classtype == "VirtualSystem" || $classtype == "Template" )
            {
                $sub = $object;

                $sharedStore = $sub;
                if( $classtype == "Template" )
                    $xmlRoot = $sharedStore->deviceConfiguration->network->xmlroot;
                elseif( $classtype == "VirtualSystem" )
                    $xmlRoot = $sharedStore->owner->network->xmlroot;

                $ownerDocument = $sub->xmlroot->ownerDocument;

                $newdoc = new DOMDocument;
                $newdoc->loadXML( $zpp_bp_xmlstring );
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);


                $networkProfiles = DH::findFirstElementOrCreate('profiles', $xmlRoot);
                $zppXMLroot = DH::findFirstElementOrCreate('zone-protection-profile', $networkProfiles);

                $entryDefault = DH::findFirstElementByNameAttr( 'entry', 'Recommended_Zone_Protection', $zppXMLroot );


                if( $entryDefault === null )
                    $zppXMLroot->appendChild( $node );
                else
                    mwarning( "ZoneProtectionProfile 'Recommended_Zone_Protection' already available. BestPractise ZoneProtectionProfile 'Recommended_Zone_Protection' not created" );


                $context->first = false;
            }
        }
    }
);