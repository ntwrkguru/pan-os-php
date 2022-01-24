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

            if( $context->isAPI )
                $dg->API_sync();
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


                if( $context->isAPI )
                {
                    $con = findConnectorOrDie($object);
                    $xpath = DH::elementToPanXPath($object->xmlroot);

                    $pan->removeDeviceGroup($object);
                    $con->sendDeleteRequest($xpath);
                }
                else
                    $pan->removeDeviceGroup($object);

            }
        }
    }
);
DeviceCallContext::$supportedActions['Template-create'] = array(
    'name' => 'template-create',
    'MainFunction' => function (DeviceCallContext $context) {
    },
    'GlobalFinishFunction' => function (DeviceCallContext $context) {
        $templateName = $context->arguments['name'];

        $pan = $context->subSystem;

        if( !$pan->isPanorama() )
            derr("only supported on Panorama config");


        $tmp_template = $pan->findTemplate($templateName);
        if( $tmp_template === null )
        {
            $string = "create Template: " . $templateName;
            #PH::ACTIONlog($context, $string);

            $dg = $pan->createTemplate($templateName);

            if( $context->isAPI )
                $dg->API_sync();
        }
        else
        {
            $string = "Template with name: " . $templateName . " already available!";
            PH::ACTIONlog( $context, $string );
        }
    },
    'args' => array(
        'name' => array('type' => 'string', 'default' => 'false'),
    ),
);
DeviceCallContext::$supportedActions['Template-delete'] = array(
    'name' => 'template-delete',
    'MainFunction' => function (DeviceCallContext $context) {

        $object = $context->object;
        $name = $object->name();

        $pan = $context->subSystem;
        if( !$pan->isPanorama() )
            derr( "only supported on Panorama config" );

        if( get_class($object) == "Template" )
        {
            /** @var Template $object */
            //if template is used in Template-Stack -> skip
            /*
            $childDG = $object->_childDeviceGroups;
            if( count($childDG) != 0 )
            {
                $string = "Template with name: '" . $name . "' is used in TemplateStack. Template can not removed";
                PH::ACTIONstatus($context, "SKIPPED", $string);
            }
            else
            {
            */
                $string ="     * delete Template: " . $name;
                PH::ACTIONlog( $context, $string );


                if( $context->isAPI )
                {
                    $con = findConnectorOrDie($object);
                    $xpath = DH::elementToPanXPath($object->xmlroot);

                    $pan->removeTemplate($object);
                    $con->sendDeleteRequest($xpath);
                }
                else
                    $pan->removeTemplate($object);

            //}
        }
    }
);

DeviceCallContext::$supportedActions['exportToExcel'] = array(
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
                        $context->device_array[ $tmp_node ][ $node->nodeName ] = $tmp_node;

                        if( $node->childNodes->length > 1 )
                        {
                            foreach( $node->childNodes as $child )
                            {
                                if( $node->nodeType != XML_ELEMENT_NODE )
                                    continue;


                                if( $child->nodeName == "entry" )
                                {
                                    $tmp_node = $child->textContent;
                                    $context->device_array[ $tmp_node ][ $child->getAttribute('name') ] = $tmp_node;
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

DeviceCallContext::$supportedActions['securityprofile-create-alert-only'] = array(
    'name' => 'securityprofile-create-alert-only',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;

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
            $pathString = dirname(__FILE__)."/../../iron-skillet";
            $av_xmlString_v9 = file_get_contents( $pathString."/panos_v9.1/templates/panorama/snippets/profiles_virus.xml");
            $av_xmlString_v10 = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/profiles_virus.xml");

            $as_xmlString_v9 = file_get_contents( $pathString."/panos_v9.1/templates/panorama/snippets/profiles_spyware.xml");
            $as_xmlString_v10 = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/profiles_spyware.xml");

            $vp_xmlString = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/profiles_vulnerability.xml");

            $url_xmlString_v9 = file_get_contents( $pathString."/panos_v9.1/templates/panorama/snippets/profiles_url_filtering.xml");
            $url_xmlString_v10 = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/profiles_url_filtering.xml");

            $fb_xmlString = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/profiles_file_blocking.xml");

            $wf_xmlString = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/profiles_wildfire_analysis.xml");

            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $context->arguments['shared'] )
                    $sharedStore = $sub->owner;
                else
                    $sharedStore = $sub;

                $name = "Alert-Only";
                $ownerDocument = $sub->xmlroot->ownerDocument;


                $block = $sharedStore->customURLProfileStore->find("Block");
                if( $block === null )
                {
                    $block = $sharedStore->customURLProfileStore->newCustomSecurityProfileURL("Block");
                    if( $context->isAPI )
                        $block->API_sync();
                }
                $allow = $sharedStore->customURLProfileStore->find("Allow");
                if( $allow === null )
                {
                    $allow = $sharedStore->customURLProfileStore->newCustomSecurityProfileURL("Allow");
                    if( $context->isAPI )
                        $allow->API_sync();
                }
                $nodecrypt = $sharedStore->customURLProfileStore->find("Custom-No-Decrypt");
                if( $nodecrypt === null )
                {
                    $nodecrypt = $sharedStore->customURLProfileStore->newCustomSecurityProfileURL("Custom-No-Decrypt");
                    if( $context->isAPI )
                        $nodecrypt->API_sync();
                }


                $av = $sharedStore->AntiVirusProfileStore->find($name . "-AV");
                if( $av === null )
                {
                    $store = $sharedStore->AntiVirusProfileStore;
                    $av = new AntiVirusProfile($name . "-AV", $store);
                    $newdoc = new DOMDocument;
                    if( $context->object->owner->version < 100 )
                        $newdoc->loadXML($av_xmlString_v9);
                    else
                        $newdoc->loadXML($av_xmlString_v10);
                    $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                    $node = DH::findFirstElementByNameAttr("entry", $name . "-AV", $node);
                    $node = $ownerDocument->importNode($node, TRUE);
                    $av->load_from_domxml($node);
                    $av->owner = null;
                    $store->addSecurityProfile($av);
                }

                $as = $sharedStore->AntiSpywareProfileStore->find($name . "-AS");
                if( $as === null )
                {
                    $store = $sharedStore->AntiSpywareProfileStore;
                    $as = new AntiSpywareProfile($name . "-AS", $store);
                    $newdoc = new DOMDocument;
                    if( $context->object->owner->version < 100 )
                        $newdoc->loadXML($as_xmlString_v9);
                    else
                        $newdoc->loadXML($as_xmlString_v10);
                    $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                    $node = DH::findFirstElementByNameAttr("entry", $name . "-AS", $node);
                    $node = $newdoc->importNode($node, TRUE);
                    $node = $ownerDocument->importNode($node, TRUE);
                    $as->load_from_domxml($node);
                    $as->owner = null;
                    $store->addSecurityProfile($as);
                }

                $vp = $sharedStore->VulnerabilityProfileStore->find($name . "-VP");
                if( $vp === null )
                {
                    $store = $sharedStore->VulnerabilityProfileStore;
                    $vp = new VulnerabilityProfile($name . "-VP", $store);
                    $newdoc = new DOMDocument;
                    $newdoc->loadXML($vp_xmlString);
                    $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                    $node = DH::findFirstElementByNameAttr("entry", $name . "-VP", $node);
                    $node = $newdoc->importNode($node, TRUE);
                    $node = $ownerDocument->importNode($node, TRUE);
                    $vp->load_from_domxml($node);
                    $vp->owner = null;
                    $store->addSecurityProfile($vp);
                }

                $url = $sharedStore->URLProfileStore->find($name . "-URL");
                if( $url === null )
                {
                    $store = $sharedStore->URLProfileStore;
                    $url = new URLProfile($name . "-URL", $store);
                    $newdoc = new DOMDocument;
                    if( $context->object->owner->version < 100 )
                        $newdoc->loadXML($url_xmlString_v9);
                    else
                        $newdoc->loadXML($url_xmlString_v10);
                    $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                    $node = DH::findFirstElementByNameAttr("entry", $name . "-URL", $node);
                    $node = $newdoc->importNode($node, TRUE);
                    $node = $ownerDocument->importNode($node, TRUE);
                    $url->load_from_domxml($node);
                    $url->owner = null;
                    $store->addSecurityProfile($url);
                }

                $fb = $sharedStore->FileBlockingProfileStore->find($name . "-FB");
                if( $fb === null )
                {
                    $store = $sharedStore->FileBlockingProfileStore;
                    $fb = new FileBlockingProfile($name . "-FB", $store);
                    $newdoc = new DOMDocument;
                    $newdoc->loadXML($fb_xmlString);
                    $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                    $node = DH::findFirstElementByNameAttr("entry", $name . "-FB", $node);
                    $node = $newdoc->importNode($node, TRUE);
                    $node = $ownerDocument->importNode($node, TRUE);
                    $fb->load_from_domxml($node);
                    $fb->owner = null;
                    $store->addSecurityProfile($fb);
                }

                $wf = $sharedStore->WildfireProfileStore->find($name . "-WF");
                if( $wf === null )
                {
                    $store = $sharedStore->WildfireProfileStore;
                    $wf = new WildfireProfile($name . "-WF", $store);
                    $newdoc = new DOMDocument;
                    $newdoc->loadXML($wf_xmlString);
                    $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                    $node = DH::findFirstElementByNameAttr("entry", $name . "-WF", $node);
                    $node = $newdoc->importNode($node, TRUE);
                    $node = $ownerDocument->importNode($node, TRUE);
                    $wf->load_from_domxml($node);
                    $wf->owner = null;
                    $store->addSecurityProfile($wf);
                }

                $secprofgrp = $sharedStore->securityProfileGroupStore->find($name);
                if( $secprofgrp === null )
                {
                    $secprofgrp = new SecurityProfileGroup($name, $sharedStore->securityProfileGroupStore, TRUE);

                    $secprofgrp->setSecProf_AV($av->name());
                    $secprofgrp->setSecProf_Spyware($as->name());
                    $secprofgrp->setSecProf_Vuln($vp->name());
                    $secprofgrp->setSecProf_URL($url->name());
                    $secprofgrp->setSecProf_FileBlock($fb->name());
                    $secprofgrp->setSecProf_Wildfire($wf->name());


                    $sharedStore->securityProfileGroupStore->addSecurityProfileGroup($secprofgrp);
                }


                if( $context->isAPI )
                {
                    $av->API_sync();
                    $as->API_sync();
                    $vp->API_sync();
                    $url->API_sync();
                    $fb->API_sync();
                    $wf->API_sync();
                    $secprofgrp->API_sync();
                }
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




DeviceCallContext::$supportedActions['LogForwardingProfile-create-BP'] = array(
    'name' => 'logforwardingprofile-create-bp',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;

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
            $pathString = dirname(__FILE__)."/../../iron-skillet";
            $lfp_bp_xmlstring = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/log_settings_profiles.xml");

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
                $node = DH::findFirstElementByNameAttr( "entry", "default", $node );
                $node = $ownerDocument->importNode($node, TRUE);


                $logSettings = DH::findFirstElementOrCreate('log-settings', $xmlRoot);
                $logSettingProfiles = DH::findFirstElementOrCreate('profiles', $logSettings);

                $entryDefault = DH::findFirstElementByNameAttr( 'entry', 'default', $logSettingProfiles );


                if( $entryDefault === null )
                {
                    $logSettingProfiles->appendChild( $node );

                    if( $context->isAPI )
                    {
                        $entryDefault_xmlroot = DH::findFirstElementByNameAttr( 'entry', 'default', $logSettingProfiles );

                        $xpath = DH::elementToPanXPath($logSettingProfiles);
                        $con = findConnectorOrDie($object);

                        $getXmlText_inline = DH::dom_to_xml($entryDefault_xmlroot, -1, FALSE);
                        $con->sendSetRequest($xpath, $getXmlText_inline);
                    }
                }
                else
                    mwarning( "LogForwardingProfile 'default' already available. BestPractise LogForwardingProfile 'default' not created", null, false );


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
    },
    'MainFunction' => function (DeviceCallContext $context)
    {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            $pathString = dirname(__FILE__)."/../../iron-skillet";
            $zpp_bp_xmlstring = file_get_contents( $pathString."/panos_v10.0/templates/panorama/snippets/zone_protection_profile.xml");

            if( $classtype == "VirtualSystem" || $classtype == "Template" )
            {
                $sub = $object;

                $sharedStore = $sub;
                if( $classtype == "Template" )
                {
                    $xmlRoot = $sharedStore->deviceConfiguration->network->xmlroot;
                    if( $xmlRoot === null )
                    {
                        $xmlRoot = DH::findFirstElementOrCreate('devices', $sharedStore->deviceConfiguration->xmlroot);

                        #$xmlRoot = DH::findFirstElementByNameAttrOrCreate( 'entry', 'localhost.localdomain', $xmlRoot, $sharedStore->deviceConfiguration->xmlroot->ownerDocument);
                        $xmlRoot = DH::findFirstElementOrCreate('entry', $xmlRoot);
                        $xmlRoot->setAttribute( "name", 'localhost.localdomain' );
                        $xmlRoot = DH::findFirstElementOrCreate('network', $xmlRoot);
                    }
                }
                elseif( $classtype == "VirtualSystem" )
                {
                    $xmlRoot = $sharedStore->owner->network->xmlroot;
                    if( $xmlRoot === null )
                    {
                        $xmlRoot = DH::findFirstElementOrCreate('devices', $sharedStore->owner->xmlroot);

                        #$xmlRoot = DH::findFirstElementByNameAttrOrCreate( 'entry', 'localhost.localdomain', $xmlRoot, $sharedStore->owner->xmlroot->ownerDocument);
                        $xmlRoot = DH::findFirstElementOrCreate('entry', $xmlRoot);
                        $xmlRoot->setAttribute( "name", 'localhost.localdomain' );
                        $xmlRoot = DH::findFirstElementOrCreate('network', $xmlRoot);
                    }
                }


                $ownerDocument = $sub->xmlroot->ownerDocument;

                $newdoc = new DOMDocument;
                $newdoc->loadXML( $zpp_bp_xmlstring );
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = DH::findFirstElementByNameAttr( "entry", "Recommended_Zone_Protection", $node );
                $node = $ownerDocument->importNode($node, TRUE);


                $networkProfiles = DH::findFirstElementOrCreate('profiles', $xmlRoot);
                $zppXMLroot = DH::findFirstElementOrCreate('zone-protection-profile', $networkProfiles);

                $entryDefault = DH::findFirstElementByNameAttr( 'entry', 'Recommended_Zone_Protection', $zppXMLroot );


                if( $entryDefault === null )
                {
                    $zppXMLroot->appendChild( $node );

                    if( $context->isAPI )
                    {
                        $entryDefault_xmlroot = DH::findFirstElementByNameAttr( 'entry', 'Recommended_Zone_Protection', $zppXMLroot );

                        $xpath = DH::elementToPanXPath($zppXMLroot);
                        $con = findConnectorOrDie($object);

                        $getXmlText_inline = DH::dom_to_xml($entryDefault_xmlroot, -1, FALSE);
                        $con->sendSetRequest($xpath, $getXmlText_inline);
                    }
                }

                else
                    mwarning( "ZoneProtectionProfile 'Recommended_Zone_Protection' already available. BestPractise ZoneProtectionProfile 'Recommended_Zone_Protection' not created", null, FALSE );

                //create for all VSYS and all templates
                #$context->first = false;
            }
        }
    }
);

DeviceCallContext::$supportedActions['CleanUpRule-create-BP'] = array(
    'name' => 'cleanuprule-create-bp',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
    },
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            if( $context->arguments['logprof'] )
                $logprof = $context->arguments['logprof'];
            else
                $logprof = "default";

            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                $skip = false;
                if( $classtype == "VirtualSystem" )
                {
                    //create security Rule at end
                    $name = "CleanupRule-BP";
                    $cleanupRule = $sub->securityRules->find( $name );
                    if( $cleanupRule === null )
                        $cleanupRule = $sub->securityRules->newSecurityRule( $name );
                    else
                        $skip = true;
                }
                elseif( $classtype == "DeviceGroup" )
                {
                    $sharedStore = $sub->owner;

                    //create security Rule at end
                    $name = "CleanupRule-BP";
                    $cleanupRule = $sharedStore->securityRules->find( $name );
                    if( $cleanupRule === null )
                        $cleanupRule = $sharedStore->securityRules->newSecurityRule("CleanupRule-BP", true);
                    else
                        $skip = true;
                }

                if( !$skip )
                {
                    $cleanupRule->source->setAny();
                    $cleanupRule->destination->setAny();
                    $cleanupRule->services->setAny();
                    $cleanupRule->setAction( 'deny');
                    $cleanupRule->setLogStart( false );
                    $cleanupRule->setLogEnd( true );
                    $cleanupRule->setLogSetting( $logprof );
                    if( $context->isAPI )
                        $cleanupRule->API_sync();
                }

                if( $classtype == "DeviceGroup" )
                    $context->first = false;
            }
        }
    },
    'args' => array(
    'logprof' => array('type' => 'string', 'default' => 'default',
        'help' => "LogForwardingProfile name"
    )
)
);

DeviceCallContext::$supportedActions['DefaultSecurityRule-create-BP'] = array(
    'name' => 'defaultsecurityRule-create-bp',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
    },
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            if( $context->arguments['logprof'] )
                $logprof = $context->arguments['logprof'];
            else
                $logprof = "default";

            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $classtype == "VirtualSystem" )
                {
                    $sharedStore = $sub;
                    $xmlRoot = $sharedStore->xmlroot;

                    $rulebase = DH::findFirstElementOrCreate( "rulebase", $xmlRoot );
                }
                elseif( $classtype == "DeviceGroup" )
                {
                    $sharedStore = $sub->owner;
                    $xmlRoot = DH::findFirstElementOrCreate('shared', $sharedStore->xmlroot);

                    $rulebase = DH::findFirstElementOrCreate( "post-rulebase", $xmlRoot );
                }

                $defaultSecurityRules = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );
                $rulebase->removeChild( $defaultSecurityRules );

                $defaultSecurityRules_xml = "<default-security-rules>
                    <rules>
                      <entry name=\"intrazone-default\">
                        <action>deny</action>
                        <log-start>no</log-start>
                        <log-end>yes</log-end>
                        <log-setting>".$logprof."</log-setting>
                      </entry>
                      <entry name=\"interzone-default\">
                        <action>deny</action>
                        <log-start>no</log-start>
                        <log-end>yes</log-end>
                        <log-setting>".$logprof."</log-setting>
                      </entry>
                    </rules>
                  </default-security-rules>";

                $ownerDocument = $sub->xmlroot->ownerDocument;

                $newdoc = new DOMDocument;
                $newdoc->loadXML( $defaultSecurityRules_xml );
                $node = $newdoc->importNode($newdoc->firstChild, TRUE);
                $node = $ownerDocument->importNode($node, TRUE);
                $rulebase->appendChild( $node );

                if( $context->isAPI )
                {
                    $defaultSecurityRules_xmlroot = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );

                    $xpath = DH::elementToPanXPath($defaultSecurityRules_xmlroot);
                    $con = findConnectorOrDie($object);

                    $getXmlText_inline = DH::dom_to_xml($defaultSecurityRules_xmlroot, -1, FALSE);
                    $con->sendEditRequest($xpath, $getXmlText_inline);
                }

                if( $classtype == "DeviceGroup" )
                    $context->first = false;
            }
        }
    },
    'args' => array(
        'logprof' => array('type' => 'string', 'default' => 'default',
            'help' => "LogForwardingProfile name"
        )
    )
);

DeviceCallContext::$supportedActions['DefaultSecurityRule-logend-enable'] = array(
    'name' => 'defaultsecurityrule-logend-enable',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
    },
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $classtype == "VirtualSystem" )
                {
                    $sharedStore = $sub;
                    $xmlRoot = $sharedStore->xmlroot;

                    $rulebase = DH::findFirstElementOrCreate( "rulebase", $xmlRoot );
                }
                elseif( $classtype == "DeviceGroup" )
                {
                    $sharedStore = $sub->owner;
                    $xmlRoot = DH::findFirstElementOrCreate('shared', $sharedStore->xmlroot);

                    $rulebase = DH::findFirstElementOrCreate( "post-rulebase", $xmlRoot );
                }

                $defaultSecurityRules = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );
                $rules = DH::findFirstElementOrCreate( "rules", $defaultSecurityRules );

                $array = array( "intrazone-default", "interzone-default" );
                foreach( $array as $entry)
                {
                    $tmp_XYZzone_xml = DH::findFirstElementByNameAttrOrCreate( "entry", $entry, $rules, $sharedStore->xmlroot->ownerDocument );
                    $logend = DH::findFirstElementOrCreate( "log-end", $tmp_XYZzone_xml );
                    $logend->textContent = "yes";
                }

                if( $context->isAPI )
                {
                    $defaultSecurityRules_xmlroot = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );

                    $xpath = DH::elementToPanXPath($defaultSecurityRules_xmlroot);
                    $con = findConnectorOrDie($object);

                    $getXmlText_inline = DH::dom_to_xml($defaultSecurityRules_xmlroot, -1, FALSE);
                    $con->sendEditRequest($xpath, $getXmlText_inline);
                }

                if( $classtype == "DeviceGroup" )
                    $context->first = false;
            }
        }
    }
);

DeviceCallContext::$supportedActions['DefaultSecurityRule-logstart-disable'] = array(
    'name' => 'defaultsecurityrule-logstart-disable',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
    },
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $classtype == "VirtualSystem" )
                {
                    $sharedStore = $sub;
                    $xmlRoot = $sharedStore->xmlroot;

                    $rulebase = DH::findFirstElementOrCreate( "rulebase", $xmlRoot );
                }
                elseif( $classtype == "DeviceGroup" )
                {
                    $sharedStore = $sub->owner;
                    $xmlRoot = DH::findFirstElementOrCreate('shared', $sharedStore->xmlroot);

                    $rulebase = DH::findFirstElementOrCreate( "post-rulebase", $xmlRoot );
                }

                $defaultSecurityRules = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );
                $rules = DH::findFirstElementOrCreate( "rules", $defaultSecurityRules );

                $array = array( "intrazone-default", "interzone-default" );
                foreach( $array as $entry)
                {
                    $tmp_XYZzone_xml = DH::findFirstElementByNameAttrOrCreate( "entry", $entry, $rules, $sharedStore->xmlroot->ownerDocument );

                    $logstart = DH::findFirstElementOrCreate( "log-start", $tmp_XYZzone_xml );
                    $logstart->textContent = "no";
                }

                if( $context->isAPI )
                {
                    $defaultSecurityRules_xmlroot = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );

                    $xpath = DH::elementToPanXPath($defaultSecurityRules_xmlroot);
                    $con = findConnectorOrDie($object);

                    $getXmlText_inline = DH::dom_to_xml($defaultSecurityRules_xmlroot, -1, FALSE);
                    $con->sendEditRequest($xpath, $getXmlText_inline);
                }

                if( $classtype == "DeviceGroup" )
                    $context->first = false;
            }
        }
    }
);

DeviceCallContext::$supportedActions['DefaultSecurityRule-logsetting-set'] = array(
    'name' => 'defaultsecurityrule-logsetting-set',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
    },
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            if( $context->arguments['logprof'] )
                $logprof = $context->arguments['logprof'];
            else
                $logprof = "default";

            $force = $context->arguments['force'];

            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $classtype == "VirtualSystem" )
                {
                    $sharedStore = $sub;
                    $xmlRoot = $sharedStore->xmlroot;

                    $rulebase = DH::findFirstElementOrCreate( "rulebase", $xmlRoot );
                }
                elseif( $classtype == "DeviceGroup" )
                {
                    $sharedStore = $sub->owner;
                    $xmlRoot = DH::findFirstElementOrCreate('shared', $sharedStore->xmlroot);

                    $rulebase = DH::findFirstElementOrCreate( "post-rulebase", $xmlRoot );
                }

                $defaultSecurityRules = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );
                $rules = DH::findFirstElementOrCreate( "rules", $defaultSecurityRules );

                $array = array( "intrazone-default", "interzone-default" );
                foreach( $array as $entry)
                {
                    $tmp_XYZzone_xml = DH::findFirstElementByNameAttrOrCreate( "entry", $entry, $rules, $sharedStore->xmlroot->ownerDocument );

                    $logsetting = DH::findFirstElement( "log-setting", $tmp_XYZzone_xml );
                    if( $logsetting !== FALSE || $force )
                    {
                        if( $force )
                            $logsetting->textContent = $logprof;
                    }
                    else
                    {
                        $logsetting = DH::findFirstElementOrCreate( "log-setting", $tmp_XYZzone_xml );
                        $logsetting->textContent = $logprof;
                    }
                }

                if( $context->isAPI )
                {
                    $defaultSecurityRules_xmlroot = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );

                    $xpath = DH::elementToPanXPath($defaultSecurityRules_xmlroot);
                    $con = findConnectorOrDie($object);

                    $getXmlText_inline = DH::dom_to_xml($defaultSecurityRules_xmlroot, -1, FALSE);
                    $con->sendEditRequest($xpath, $getXmlText_inline);
                }

                if( $classtype == "DeviceGroup" )
                    $context->first = false;
            }
        }
    },
    'args' => array(
        'logprof' => array('type' => 'string', 'default' => 'default',
            'help' => "LogForwardingProfile name"
        ),
        'force' => array('type' => 'bool', 'default' => 'false',
            'help' => "LogForwardingProfile overwrite"
        )
    )
);

DeviceCallContext::$supportedActions['DefaultSecurityRule-securityProfile-Remove'] = array(
    'name' => 'defaultsecurityrule-securityprofile-remove',
    'GlobalInitFunction' => function (DeviceCallContext $context) {
        $context->first = true;
    },
    'MainFunction' => function (DeviceCallContext $context) {
        $object = $context->object;
        $classtype = get_class($object);

        if( $context->first )
        {
            $force = $context->arguments['force'];

            if( $classtype == "VirtualSystem" || $classtype == "DeviceGroup" )
            {
                $sub = $object;

                if( $classtype == "VirtualSystem" )
                {
                    $sharedStore = $sub;
                    $xmlRoot = $sharedStore->xmlroot;

                    $rulebase = DH::findFirstElementOrCreate( "rulebase", $xmlRoot );
                }
                elseif( $classtype == "DeviceGroup" )
                {
                    $sharedStore = $sub->owner;
                    $xmlRoot = DH::findFirstElementOrCreate('shared', $sharedStore->xmlroot);

                    $rulebase = DH::findFirstElementOrCreate( "post-rulebase", $xmlRoot );
                }

                $defaultSecurityRules = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );
                $rules = DH::findFirstElementOrCreate( "rules", $defaultSecurityRules );

                $array = array( "intrazone-default", "interzone-default" );
                foreach( $array as $entry)
                {
                    $tmp_XYZzone_xml = DH::findFirstElementByNameAttrOrCreate( "entry", $entry, $rules, $sharedStore->xmlroot->ownerDocument );

                    $action = DH::findFirstElement( "action", $tmp_XYZzone_xml );
                    if( $action === FALSE )
                    {
                        if( $entry === "intrazone-default" )
                            $action_txt = "allow";
                        elseif( $entry === "interzone-default" )
                            $action_txt = "deny";
                    }
                    else
                        $action_txt = $action->textContent;

                    if( $action_txt !== "allow" || $force )
                    {
                        $profilesetting = DH::findFirstElement( "profile-setting", $tmp_XYZzone_xml );
                        if( $profilesetting !== FALSE )
                            $tmp_XYZzone_xml->removeChild( $profilesetting );
                    }
                }

                if( $context->isAPI )
                {
                    $defaultSecurityRules_xmlroot = DH::findFirstElementOrCreate( "default-security-rules", $rulebase );

                    $xpath = DH::elementToPanXPath($defaultSecurityRules_xmlroot);
                    $con = findConnectorOrDie($object);

                    $getXmlText_inline = DH::dom_to_xml($defaultSecurityRules_xmlroot, -1, FALSE);
                    $con->sendEditRequest($xpath, $getXmlText_inline);
                }

                if( $classtype == "DeviceGroup" )
                    $context->first = false;
            }
        }
    },
    'args' => array(
        'force' => array('type' => 'bool', 'default' => 'false',
            'help' => "per default, remove SecurityProfiles only if Rule action is NOT allow. force=true => remove always"
        )
    )
);