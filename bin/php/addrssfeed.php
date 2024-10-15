#!/usr/bin/env php
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

include_once( 'kernel/classes/ezscript.php' );
include_once( 'lib/ezutils/classes/ezcli.php' );

$cli =& eZCLI::instance();

$scriptSettings = array();
$scriptSettings['description'] = 'Add RSS feeds';
$scriptSettings['use-session'] = true;
$scriptSettings['use-modules'] = true;
$scriptSettings['use-extensions'] = true;

$script =& eZScript::instance( $scriptSettings );
$script->startup();

$config = '[path-offset:]';
$argumentConfig = '[content_class][attribute_mapping_identifier+]';
$optionHelp = false;
$arguments = false;
$useStandardOptions = true;

$options = $script->getOptions( $config, $argumentConfig, $optionHelp, $arguments, $useStandardOptions );
$script->initialize();

if ( count( $options['arguments'] ) < 2 )
{
    $script->shutdown( 1, 'wrong argument count' );
}

$pathOffset = isset( $options['path-offset'] ) && is_numeric( $options['path-offset'] ) ? $options['path-offset'] : 0;
$args = $options['arguments'];

$class = array_shift( $args );

include_once( 'kernel/classes/ezcontentobjecttreenode.php' );
$params = array(
    'ClassFilterType' => 'include',
    'ClassFilterArray' => array( $class ),
    'Limitation' => array()
);

$script->setIterationData( '.', '~' );

$nodes =& eZContentObjectTreeNode::subtree( $params, 1 );

include_once( 'extension/autorss/eventtypes/event/autorss/autorsstype.php' );
$nodeCount = count( $nodes );
$script->resetIteration( $nodeCount );
$cli->output( '' );
$cli->output( "Found $nodeCount nodes to investigate." );
$cli->output( '' );

foreach ( $nodes as $node )
{
    $result = AutoRSSType::createFeedIfNeeded( $node, $args, $pathOffset );
    $script->iterate( $cli, $result );
}

$script->shutdown( 0 );

?>
