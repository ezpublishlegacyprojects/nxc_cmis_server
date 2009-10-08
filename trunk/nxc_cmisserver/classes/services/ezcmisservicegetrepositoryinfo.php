<?php
/**
 * Definition of eZCMISServiceGetRepositoryInfo class
 *
 * Created on: <1-Jun-2009 20:59:01 vd>
 *
 * COPYRIGHT NOTICE: Copyright (C) 2001-2009 Nexus AS
 * SOFTWARE LICENSE: GNU General Public License v2.0
 * NOTICE: >
 *   This program is free software; you can redistribute it and/or
 *   modify it under the terms of version 2.0  of the GNU General
 *   Public License as published by the Free Software Foundation.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of version 2.0 of the GNU General
 *   Public License along with this program; if not, write to the Free
 *   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *   MA 02110-1301, USA.
 */

/**
 * @service getRepositoryInfo: Returns information about the CMIS repository and the optional capabilities it supports.
 * @file ezcmisservicegetrepositoryinfo.php
 */

include_once( eZExtension::baseDirectory() . '/nxc_cmisserver/classes/services/ezcmisservicebase.php' );
include_once( eZExtension::baseDirectory() . '/nxc_cmisserver/classes/ezcmisatomtools.php' );
include_once( eZExtension::baseDirectory() . '/nxc_cmisserver/classes/ezcmis.php' );
include_once( eZExtension::baseDirectory() . '/nxc_cmisserver/classes/ezcmisserviceurl.php' );
include_once( eZExtension::baseDirectory() . '/nxc_cmisserver/classes/exceptions/ezcmisexceptions.php' );

class eZCMISServiceGetRepositoryInfo extends eZCMISServiceBase
{
    /**
     * @reimp
     */
    protected function createFields()
    {
        $this->addField( 'repositoryId', null, false );
    }

    /**
     * Fetches repository list from ini file
     *
     * @return array Repository list
     */
    protected static function fetchRepositoryList()
    {
        $ini = eZINI::instance( 'repository.ini' );
        // Fetch repository groups
        $repositoryGroupList = $ini->hasVariable( 'RepositorySettings', 'RepositoryList' ) ? $ini->variable( 'RepositorySettings', 'RepositoryList' ) : false;

        if ( !$repositoryGroupList )
        {
            throw new eZCMISRuntimeException( ezi18n( 'cmis', 'No repository groups found' ) );
        }

        $repositoryList = array();
        // Fetch settings for each griup
        foreach ( $repositoryGroupList as $key => $repositoryGroup )
        {
            if ( !$ini->hasGroup( $repositoryGroup ) )
            {
                continue;
            }

            $repositoryList[$key] = $ini->group( $repositoryGroup );
        }

        return $repositoryList;
    }

    /**
     * Fetches repository info
     *
     * @return array Repository info
     */
    protected static function getRepositoryInfoById( $repositoryId = false )
    {
        $repositoryList = self::fetchRepositoryList();

        $repositoryInfo = array();

        // If repositoryId is not provided fetch default
        if ( !$repositoryId )
        {
            $repositoryArray = isset( $repositoryList['default'] ) ? $repositoryList['default'] : false;
            if ( !$repositoryArray )
            {
                throw new eZCMISRuntimeException( ezi18n( 'cmis', 'No default repository configured' ) );
            }

            $repositoryInfo = self::fetchRepositoryInfo( $repositoryArray );
        }
        else
        {
            foreach ( $repositoryList as $key => $repository )
            {
                $info = self::fetchRepositoryInfo( $repository );
                if ( $info['repositoryId'] == $repositoryId )
                {
                    $repositoryInfo = self::fetchRepositoryInfo( $repository );
                    break;
                }
            }
        }

        return $repositoryInfo;
    }

    /**
     * Organizes repository info for \a $repositoryArray fetched from ini
     */
    protected static function fetchRepositoryInfo( $repositoryArray )
    {
        $rootNode = isset( $repositoryArray['RootNode'] ) ? $repositoryArray['RootNode'] : false;
        $name = isset( $repositoryArray['Name'] ) ? $repositoryArray['Name'] : 'Repository ' . $repositoryInfo['repositoryId'];

        if ( !$rootNode )
        {
            throw new eZCMISInvalidArgumentException( ezi18n( 'cmis', "No root node provided for repository '%name%'", null, array( '%name%' => $name ) ) );
        }

        $node = eZContentObjectTreeNode::fetch( $rootNode );
        if ( !$node )
        {
            throw new eZCMISObjectNotFoundException( ezi18n( 'cmis', "Could not fetch root node by node_id '%node_id%' for repository '%name%'", null, array( '%node_id%' => $rootNode, '%name%' => $name ) ) );
        }

        $repositoryInfo = array();

        if ( !$node->canRead() )
        {
            return $repositoryInfo;
        }

        $repositoryId = $node->attribute( 'remote_id' );

        $repositoryInfo['repositoryId'] = $repositoryId;
        $repositoryInfo['repositoryName'] = $name;
        $repositoryInfo['repositoryRelationship'] = isset( $repositoryArray['Relationship'] ) ? $repositoryArray['Relationship'] : 'self';
        $repositoryInfo['repositoryDescription'] = isset( $repositoryArray['Description'] ) ? $repositoryArray['Description'] : '';
        $repositoryInfo['vendorName'] = eZCMIS::VENDOR;
        $repositoryInfo['productName'] = eZCMIS::VENDOR;
        $repositoryInfo['productVersion'] = eZPublishSDK::version();
        $repositoryInfo['rootFolderId'] = eZCMISServiceURL::createURL( 'node', array( 'repositoryId' => $repositoryId, 'objectId' => $repositoryId ) );
        $repositoryInfo['capabilities'] = eZCMIS::getCapabilities();
        $repositoryInfo['cmisVersionSupported'] = eZCMIS::VERSION;
        $repositoryInfo['repositorySpecificInformation'] = isset( $repositoryArray['SpecificInformation'] ) ? $repositoryArray['SpecificInformation'] : '';

        return $repositoryInfo;
    }

    /**
     * Provides collection list of urls
     */
    protected static function getCollectionList( $repositoryInfo )
    {
        $repositoryId = isset( $repositoryInfo['repositoryId'] ) ? $repositoryInfo['repositoryId'] : '';

        return array( array( 'url' => eZCMISServiceURL::createURL( 'children', array( 'repositoryId' => $repositoryId, 'folderId' => $repositoryId ) ),
                             'type' => 'rootchildren',
                             'value' => 'root collection' ),
                      array( 'url' => eZCMISServiceURL::createURL( 'descendants', array( 'repositoryId' => $repositoryId, 'folderId' => $repositoryId ) ),
                             'type' => 'rootdescendants',
                             'value' => 'root collection' ),
                      array( 'url' => eZCMISServiceURL::createURL( 'checkedout', array( 'repositoryId' => $repositoryId ) ),
                             'type' => 'checkedout',
                             'value' => 'checkedout collection' ),
                      array( 'url' => eZCMISServiceURL::createURL( 'types', array( 'repositoryId' => $repositoryId ) ),
                             'type' => 'typeschildren',
                             'value' => 'type collection' ),
                      array( 'url' => eZCMISServiceURL::createURL( 'types', array( 'repositoryId' => $repositoryId ) ),
                             'type' => 'typesdescendants',
                             'value' => 'type collection' ),
                      /*array( 'url' => $apiURL . '/query',
                             'type' => 'query',
                             'value' => 'query collection' )
                      */
                      );

    }

    /**
     * Processes by GET http method
     */
    public function processRESTful()
    {
        $repositoryInfo = self::getRepositoryInfo();

        $doc = eZCMISAtomTools::createDocument();

        $root = eZCMISAtomTools::createRootNode( $doc, 'service', 'app' );
        $doc->appendChild( $root );
        $workspace = $doc->createElement( 'workspace' );
        $workspace->setAttribute( 'cmis:repositoryRelationship', $repositoryInfo['repositoryRelationship'] );
        $root->appendChild( $workspace );
        $title = $doc->createElement( 'atom:title', $repositoryInfo['repositoryName'] );
        $workspace->appendChild( $title );
        $info = eZCMISAtomTools::createElementByArray( $doc, 'repositoryInfo', $repositoryInfo );
        $workspace->appendChild( $info );

        // Create collection list
        $collectionList = self::getCollectionList( $repositoryInfo );

        foreach ( $collectionList as $collection )
        {
            $element = $doc->createElement( 'collection' );
            $element->setAttribute( 'href', $collection['url'] );
            $element->setAttribute( 'cmis:collectionType', $collection['type'] );
            $workspace->appendChild( $element );
            $value = $doc->createElement( 'atom:title', $collection['value'] );
            $element->appendChild( $value );
        }

        return $doc->saveXML();
    }

    /**
     * Provides repository info by get param
     *
     * @return array Info
     */
    public function getRepositoryInfo()
    {
        $http = eZHTTPTool::instance();
        $repositoryId = $this->getField( 'repositoryId' )->getValue();

        $repositoryInfo = self::getRepositoryInfoById( $repositoryId );
        $remoteRootId = isset( $repositoryInfo['repositoryId'] ) ? $repositoryInfo['repositoryId'] : false;

        if ( empty( $repositoryInfo ) or !$remoteRootId )
        {
            throw new eZCMISObjectNotFoundException( ezi18n( 'cmis', 'Repository does not exist' ) );
        }

        return $repositoryInfo;
    }

    /**
     * Provides repository Id
     *
     * @return string Id
     */
    public function getRepositoryId()
    {
        $repositoryInfo = $this->getRepositoryInfo();

        return $repositoryInfo['repositoryId'];
    }
}
?>
