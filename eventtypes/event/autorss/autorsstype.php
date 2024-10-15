<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Publish Auto RSS extension
// SOFTWARE RELEASE: 1.x
// COPYRIGHT NOTICE: Copyright (C) 2007-2008 Kristof Coomans <http://blog.kristofcoomans.be>
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

include_once( 'kernel/classes/ezworkflowtype.php' );
include_once( 'kernel/classes/ezcontentobject.php' );

class AutoRSSType extends eZWorkflowEventType
{
    function AutoRSSType()
    {
        $this->eZWorkflowEventType( 'autorss', ezi18n( 'extension/autorss', 'Auto RSS' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    function &attributeDecoder( &$event, $attr )
    {
        $retValue = null;
        switch( $attr )
        {
            case 'path_offset':
            {
                $retValue = $event->attribute( 'data_int1' );
            } break;

            case 'defer':
            {
                $retValue = $event->attribute( 'data_int2' ) == 1 ? true : false;
            } break;

            case 'attribute_mappings':
            {
                $retValue = explode( ';', $event->attribute( 'data_text1' ) );
            } break;

            default:
            {
                eZDebug::writeNotice( 'unknown attribute: ' . $attr, 'AutoRSSType' );
            }
        }
        return $retValue;
    }

    function typeFunctionalAttributes()
    {
        return array( 'path_offset', 'attribute_mappings', 'defer' );
    }

    function fetchHTTPInput( &$http, $base, &$event )
    {
        // this condition can be removed when this issue if fixed: http://issues.ez.no/10685
        if ( count( $_POST ) > 0 )
        {
            $offset = false;
            $offsetPostVarName = 'PathOffset_' . $event->attribute( 'id' );
            if ( $http->hasPostVariable( $offsetPostVarName ) )
            {
                $offset = $http->postVariable( $offsetPostVarName );
            }

            if ( is_numeric( $offset ) )
            {
                $event->setAttribute( 'data_int1', $offset );
            }

            $deferPostVarName = 'Defer_' . $event->attribute( 'id' );
            $defer = false;
            if ( $http->hasPostVariable( $deferPostVarName ) )
            {
                $defer = true;
            }
            $event->setAttribute( 'data_int2', $defer );

            $mappingsPostVarName = 'AttributeMappings_' . $event->attribute( 'id' );
            if ( $http->hasPostVariable( $mappingsPostVarName ) )
            {
                $attributeMappings = $http->postVariable( $mappingsPostVarName );
            }
            else
            {
                $attributeMappings = array();
            }

            $event->setAttribute( 'data_text1', implode( ';', $attributeMappings ) );
        }
    }

    function execute( &$process, &$event )
    {
        $parameters = $process->attribute( 'parameter_list' );
        $object =& eZContentObject::fetch( $parameters['object_id'] );

        if ( $this->attributeDecoder( $event, 'defer' ) )
        {
            include_once( 'lib/ezutils/classes/ezsys.php' );
            if ( eZSys::isShellExecution() == false )
            {
                return EZ_WORKFLOW_TYPE_STATUS_DEFERRED_TO_CRON_REPEAT;
            }
        }

        $mainNode =& $object->attribute( 'main_node' );
        $attributeMappings = $this->attributeDecoder( $event, 'attribute_mappings' );
        $pathOffset = $this->attributeDecoder( $event, 'path_offset' );
        $this->createFeedIfNeeded( $mainNode, $attributeMappings, $pathOffset );

        return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
    }

    function createFeedIfNeeded( $node, $attributeMappings, $pathOffset )
    {
        include_once( 'kernel/classes/ezrssexport.php' );
        include_once( 'kernel/classes/ezrssexportitem.php' );

        $name = $node->attribute( 'node_id' );
        $rssExport = eZRSSExport::fetchByName( $name );

        if ( is_object( $rssExport ) )
        {
            return false;
        }

        $rssExport = eZRSSExport::create( 14 );
        $rssExport->store();

        $rssExportID = $rssExport->attribute( 'id' );

        include_once( 'lib/ezutils/classes/ezini.php' );
        $ini =& eZINI::instance( 'autorss.ini' );

        foreach ( $attributeMappings as $mappingIdentifier )
        {
            $mappingGroup = 'Mapping_' . $mappingIdentifier;
            if ( !$ini->hasGroup( $mappingGroup ) )
            {
                eZDebug::writeError( 'No RSS attribute mapping with identifier ' . $mappingIdentifier . ' in autorss.ini', 'AutoRSSType::execute' );
                continue;
            }

            $classID = $ini->variable( $mappingGroup, 'ClassID' );
            $titleIdentifier = $ini->variable( $mappingGroup, 'TitleIdentifier' );
            $descriptionIdentifier = $ini->variable( $mappingGroup, 'DescriptionIdentifier' );

            $rssExportItem = eZRSSExportItem::create( $rssExportID );
            $rssExportItem->setAttribute( 'subnodes', 1 );
            $rssExportItem->setAttribute( 'source_node_id', $node->attribute( 'node_id' ) );
            $rssExportItem->setAttribute( 'class_id', $classID );
            $rssExportItem->setAttribute( 'title', $titleIdentifier );
            $rssExportItem->setAttribute( 'description', $descriptionIdentifier );
            $rssExportItem->setAttribute( 'status', 1 );
            $rssExportItem->store();

            // delete draft version
            $rssExportItem->setAttribute( 'status', 0 );
            $rssExportItem->remove();

            unset( $rssExportItem );
        }

        $path = $node->fetchPath();

        $titleParts = array();
        foreach ( $path as $pathNode )
        {
            $titleParts[] = $pathNode->attribute( 'name' );
        }

        if ( $pathOffset > 0 )
        {
            $titleParts = array_slice( $titleParts, $pathOffset );
        }

        $rssExport->setAttribute( 'title', implode( ' / ', $titleParts ) . ' / ' . $node->attribute( 'name' ) );
        $rssExport->setAttribute( 'url', $ini->variable( 'GeneralSettings', 'SiteURL' ) );
        $rssExport->setAttribute( 'description', '' );
        $rssExport->setAttribute( 'rss_version', '2.0' );
        $rssExport->setAttribute( 'number_of_objects', 50 );
        $rssExport->setAttribute( 'active', 1 );

        $rssExport->setAttribute( 'access_url', $name );
        $rssExport->setAttribute( 'main_node_only', 1 );

        // argument true will store it with status valid instead of draft
        $rssExport->store( true );
        // remove draft
        $rssExport->remove();

        return true;
    }
}

eZWorkflowEventType::registerType( 'autorss', 'AutoRSSType' );

?>