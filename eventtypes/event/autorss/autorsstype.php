<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Publish Auto RSS extension
// SOFTWARE RELEASE: 1.1.1
// COPYRIGHT NOTICE: Copyright (C) 1999 - 2024 7x and 2007-2008 Kristof Coomans <http://blog.kristofcoomans.be>
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

class AutoRSSType extends eZWorkflowEventType
{
    function __construct()
    {
        parent::__construct( 'autorss', ezpI18n::tr( 'extension/autorss', 'Auto RSS' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    function &attributeDecoder( $event, $attr )
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

    function fetchHTTPInput( $http, $base, $event )
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

    function execute( $process, $event )
    {
        $parameters = $process->attribute( 'parameter_list' );
        $object =& eZContentObject::fetch( $parameters['object_id'] );

        if ( $this->attributeDecoder( $event, 'defer' ) )
        {
            if ( eZSys::isShellExecution() === false )
            {
                return eZWorkflowType::STATUS_DEFERRED_TO_CRON_REPEAT;
            }
        }

        $mainNode =& $object->attribute( 'main_node' );
	$mainNodeID =& $mainNode->attribute( 'node_id' );

	/*
	$childNode = eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include',
                             'ClassFilterArray' => array( 'category'), 'Limit' => 1 ), $mainNodeID )[0];

        */
	$childNode = $mainNode->attribute('parent');

        $attributeMappings = $this->attributeDecoder( $event, 'attribute_mappings' );
        $pathOffset = $this->attributeDecoder( $event, 'path_offset' );

        //$this->createFeedIfNeeded( $mainNode, $attributeMappings, $pathOffset );
	//$this->createFeedIfNeeded( $childNode, $mainNode, $attributeMappings, $pathOffset );
	$this->createFeedIfNeeded( $mainNode, $childNode, $attributeMappings, $pathOffset );

        return eZWorkflowType::STATUS_ACCEPTED;
    }

    function createFeedIfNeeded( $node, $parentNode, $attributeMappings, $pathOffset )
    {
	// include_once( 'kernel/classes/ezrssexport.php' );
        // include_once( 'kernel/classes/ezrssexportitem.php' );

        $name = $node->attribute( 'node_id' );
	$nodeID = $node->attribute( 'node_id' );
	$name = $node->attribute( 'name' );

	//$nameURI = strtolower( "user-submitted-$name" );
	$nameURI = strtolower( "$name" );

        $rssExport = eZRSSExport::fetchByName( $nameURI );

        if ( is_object( $rssExport ) )
        {
            return false;
        }

        $rssExport = eZRSSExport::create( 14 );
        $rssExport->store();

        $rssExportID = $rssExport->attribute( 'id' );

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
	//$rssExport->setAttribute( 'title', implode( ' / ', $titleParts ) . ' / ' . $node->attribute( 'name' ) . ' / Submitted' );
	//$rssExport->setAttribute( 'title', 'User Submitted Feed' . ' / ' . $node->attribute( 'name' ) . ' / Submitted' );
	//$rssExport->setAttribute( 'title', 'User Submitted Feed' . ' / ' . $node->attribute( 'name' ) );

        $rssExport->setAttribute( 'url', $ini->variable( 'GeneralSettings', 'SiteURL' ) );
        $rssExport->setAttribute( 'description', 'Feed was automatically created via AutoRSS!' ); //TODO Add support for feed description abstraction
        $rssExport->setAttribute( 'rss_version', 'ATOM' );
        $rssExport->setAttribute( 'number_of_objects', 100 );
        $rssExport->setAttribute( 'active', 1 );

        $rssExport->setAttribute( 'access_url', $nameURI );
        $rssExport->setAttribute( 'main_node_only', 1 );

        // argument true will store it with status valid instead of draft
        $rssExport->store( true );
        // remove draft
        $rssExport->remove();

        return true;
    }
}

eZWorkflowEventType::registerEventType( 'autorss', 'AutoRSSType' );

?>