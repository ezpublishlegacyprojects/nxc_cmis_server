<?php
/**
 * Definition of eZCMISServiceCreateDocument class
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
 * @service createDocument: Creates a document object of the specified type in the (optionally) specified location.
 * @file ezcmisserviceecreatedocument.php
 */

include_once( eZExtension::baseDirectory() . '/ezcmis/classes/services/ezcmisservicebase.php' );
include_once( eZExtension::baseDirectory() . '/ezcmis/classes/services/ezcmisservicegetrepositoryinfo.php' );
include_once( eZExtension::baseDirectory() . '/ezcmis/classes/ezcmisobjecthandler.php' );
include_once( eZExtension::baseDirectory() . '/ezcmis/classes/exceptions/ezcmisexceptions.php' );
include_once( eZExtension::baseDirectory() . '/ezcmis/classes/ezcmisatomtools.php' );
include_once( eZExtension::baseDirectory() . '/ezcmis/classes/services/ezcmisservicegetproperties.php' );
include_once( eZExtension::baseDirectory() . '/ezcmis/classes/ezcmistypehandler.php' );

class eZCMISServiceCreateDocument extends eZCMISServiceBase
{
    /**
     * @reimp
     */
    protected function createFields()
    {
        $this->addField( 'repositoryId', null, false );
        // This parameter MUST be specified if the Repository does NOT support the optional unfiling capability
        $this->addField( 'folderId', null, true );
        $this->addField( 'post_data', null, true );

        /**
         * Enum versioningState: An enumeration specifying what the versioing state of the newly-created
         * object SHALL be. Valid values are:
         *    checkedout: The document SHALL be created in the checked-out state.
         *    major: The document SHALL be created as a major version
         *    minor (default): The document SHALL be created as a minor version.
         *
         * @TODO: Implement it
         */

        /**
         * <Array> policies: A list of policy IDs that SHALL be applied to the newly-created Document object.
         *
         * @TODO: Implement it
         */

        /**
         * <Array> ACE addACEs: A list of ACEs that SHALL be added to the newly-created Document object,
         * either using the ACL from folderId if specified, or being applied if no folderId is specified.
         *
         * @TODO: Implement it
         */

        /**
         * <Array> ACE removeACEs: A list of ACEs that SHALL be removed from the newly-created
         * Document object, either using the ACL from folderId if specified, or being ignored if no folderId is specified.
         *
         * @TODO: Implement it
         */
    }

    /**
     * @reimp
     */
    protected function checkFields()
    {
        parent::checkFields();

        $repositoryIdField = $this->getField( 'repositoryId' );
        $repository = new eZCMISServiceGetRepositoryInfo( array( 'repositoryId' => $repositoryIdField->getValue() ) );
        $repositoryId = $repository->getRepositoryId();
        $repositoryIdField->setValue( $repositoryId );

        $rootNode = eZCMISObjectHandler::fetchNode( $repositoryId );

        $folderId = $this->getField( 'folderId' )->getValue();

        $node = eZCMISObjectHandler::fetchNode( $folderId );
        $this->CMISObject = eZCMISObjectHandler::getObject( $node );

        if ( !$this->CMISObject or !eZCMIS::isChild( $rootNode, $node ) )
        {
            eZCMISExceptions::resourceIsNotAvailable();
        }

        if ( !$node->canCreate() )
        {
            eZCMISExceptions::accessDenied();
        }
    }

    /**
     * @reimp
     */
    public function processRESTful()
    {
        $repositoryId = $this->getField( 'repositoryId' )->getValue();
        $folderId = $this->getField( 'folderId' )->getValue();
        $postData = $this->getField( 'post_data' )->getValue();

        $info = eZCMISAtomTools::processXML( $postData, '/atom:entry' );

        // The identifier for the Object-Type of the Document object being created
        $typeId = eZCMISAtomTools::getPropertyObjectTypeId( $info[0] );

        $title = (string) eZCMISAtomTools::getValue( $info[0], 'title' );
        $summary = (string) eZCMISAtomTools::getValue( $info[0], 'summary' );
        $content = eZCMISAtomTools::getValue( $info[0], 'content' );

        $contentType = isset( $content['type'] ) ? (string) $content['type'] : false;

        if ( !$typeId )
        {
            eZCMISExceptions::isNotProvided( 'ObjectTypeId' );
        }

        /**
         * The Repository SHALL throw this exception if the Object-Type definition
         * specified by the typeId parameter 'contentStreamAllowed' attribute is set to 'not allowed' and a
         * contentStream input parameter is provided.
         */
        if ( $content and eZCMISTypeHandler::isContentStreamNotAllowedByTypeId( $typeId ) )
        {
            eZCMISExceptions::contentStreamIsNotSupported();
        }

        /**
         * If content is not provided and it is required,
         * A content-stream SHALL be included (i.e. SHALL be included when the object is created, and SHALL NOT be deleted.)
         */
        if ( !$content and eZCMISTypeHandler::isContentStreamRequiredByTypeId( $typeId ) )
        {
             eZCMISExceptions::contentStreamIsRequired();
        }

        // Fetch real typeId instead of alias
        $typeId = eZCMISTypeHandler::getRealTypeId( $typeId );

        // Check if the typeId is an Object-Type whose baseType is 'Document'
        $baseType = eZCMISTypeHandler::getBaseTypeByTypeId( $typeId );
        if ( !$baseType or !eZCMISTypeHandler::isDocument( $baseType ) )
        {
            throw new eZCMISConstraintViolationException( ezi18n( 'cmis', "The typeId ('%type%') is not an Object-Type whose baseType is 'Document'", null, array( '%type%' => $typeId ) ) );
        }

        if ( !$title )
        {
            eZCMISExceptions::isNotProvided( 'title' );
        }

        $newObject = eZCMISObjectHandler::createNew( $typeId, $folderId );
        if ( !$newObject )
        {
            throw new eZCMISRuntimeException( ezi18n( 'cmis', 'Could not create new CMIS object' ) );
        }

        $newObject->setTitle( $title );
        $newObject->setSummary( $summary );
        // @TODO: Is it needed here? E.g. to use setContentService service instead of using in this service
        $newObject->setContentStream( base64_decode( $content ), $contentType );

        $doc = eZCMISAtomTools::createDocument();

        $root = eZCMISAtomTools::createRootNode( $doc, 'entry' );
        $doc->appendChild( $root );

        eZCMISServiceGetProperties::createPropertyList( $doc, $root, $repositoryId, $newObject );

        return $doc->saveXML();
    }
}
?>
