<?php
/**
 * Definition of eZCMISTestCreateFolder class
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
 * Tests creating of a folder
 *
 * @file ezcmistestcreatefolder.php
 */

include_once( eZExtension::baseDirectory() . '/ezcmis/classes/tests/ezcmistestbase.php' );
include_once( eZExtension::baseDirectory() . '/ezcmis/classes/ezcmisatomtools.php' );

class eZCMISTestCreateFolder extends eZCMISTestBase
{
    /**
     * @reimp
     */
    protected function test()
    {
        $result = $this->httpRequest( $this->EndPoint, $this->User, $this->Password );

        $this->checkCode( '200', $result->code, 'GET', $this->EndPoint, $result->response );
        $this->checkResponse( $result->response );

        // Check root children link
        $rootChildren = eZCMISAtomTools::processXML( $result->response, '/app:service/app:workspace/app:collection[@cmis:collectionType="rootchildren"]' );
        $childrenLink = isset( $rootChildren[0]['href'] ) ? (string) $rootChildren[0]['href'] : false;

        if ( !$childrenLink )
        {
            $this->addMessage( ezi18n( 'cmis', 'Could not fetch "%collection%" collection', null, array( 'rootchildren' ) ) );
            $this->addMessage( $result->response );
            $this->throwError();
        }

        $result = $this->httpRequest( $childrenLink, $this->User, $this->Password );

        $this->checkCode( '200', $result->code, 'GET', $childrenLink, $result->response );
        $this->checkResponse( $result->response );

        $title = 'TestCreateFolder title ' . rand();
        $summary = 'Summary ' . $title;
        // Is it needed to be hardcoded or need to fetch from typedefinitions?
        $objectTypeId = 'folder';

        $doc = eZCMISAtomTools::createDocument();

        $root = eZCMISAtomTools::createRootNode( $doc, 'entry' );
        $doc->appendChild( $root );

        $titleDoc = $doc->createElement( 'title', $title );
        $root->appendChild( $titleDoc );

        $summary = $doc->createElement( 'summary', $summary );
        $root->appendChild( $summary );

        $object = $doc->createElement( 'cmis:object' );
        $root->appendChild( $object );

        $properties = $doc->createElement( 'cmis:properties' );
        $object->appendChild( $properties );

        $propertyId = $doc->createElement( 'cmis:propertyId' );
        $propertyId->setAttribute( 'cmis:name', 'ObjectTypeId' );
        $properties->appendChild( $propertyId );

        $value = $doc->createElement( 'cmis:value', $objectTypeId );
        $propertyId->appendChild( $value );

        $xml = $doc->saveXML();

        $header = array();
        $header[] = 'Content-type: application/atom+xml;type=entry';
        $header[] = 'Content-length: ' . strlen( $xml );
        $header[] = 'MIME-Version: 1.0';

        $result = $this->httpRequest( $childrenLink,  $this->User, $this->Password, $header, 'CUSTOM-POST', $xml );

        $this->checkCode( '201', $result->code, 'POST', $childrenLink, $result->response );
        $this->checkResponse( $result->response );

        $entry = eZCMISAtomTools::processXML( $result->response, '/atom:entry' );
        $objectId = eZCMISAtomTools::getPropertyValue( $entry[0], 'ObjectId' );

        if ( !$objectId )
        {
            $this->addMessage( ezi18n( 'cmis', 'Could not fetch "ObjectId"' ) );
            $this->addMessage( $result->response );
            $this->throwError();
        }
    }
}
?>