<?php
/**
 * Definition of eZCMISTypeHandler class
 *
 * Created on: <1-Jun-2009 10:00:00 vd>
 *
 * COPYRIGHT NOTICE: Copyright (C) 2001-2009 NXC AS
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
 * Handler for CMIS types
 * @file ezcmisservicehandler.php
 */

include_once( eZExtension::baseDirectory() . '/nxc_cmisserver/classes/exceptions/ezcmisexceptions.php' );

class eZCMISTypeHandler
{
    /**
     * CMIS Object folder
     */
    const FOLDER_OBJECT = 'folder';

    /**
     * CMIS Object document
     */
    const DOCUMENT_OBJECT = 'document';

    /**
     * Provides all registred CMIS types
     */
    public static function getAllTypes()
    {
        $name = __METHOD__;

        if ( isset( $GLOBALS[$name] ) )
        {
            return $GLOBALS[$name];
        }

        $ini = eZINI::instance( 'type.ini' );
        $GLOBALS[$name] = $ini->groups();

        return $GLOBALS[$name];
    }

    /**
     * Provides type definition
     *
     * @return array
     */
    public static function getTypeDefinition( $typeId = false, $depth = 1, $includePropertyDefinitions = false )
    {
        $name = __METHOD__ . $typeId . $depth . $includePropertyDefinitions;

        if ( isset( $GLOBALS[$name] ) )
        {
            return $GLOBALS[$name];
        }

        $allTypes = self::getAllTypes();
        $result = array();

        if ( $typeId )
        {
            foreach ( $allTypes as $key => $type )
            {
                $groupTypeId = isset( $type['typeId'] ) ? $type['typeId'] : false;
                if ( !$groupTypeId )
                {
                    continue;
                }

                if ( $groupTypeId == $typeId )
                {
                    $result[] = self::createDefinition( $type, $includePropertyDefinitions );
                    $children = self::findTypeDescendants( $typeId, $depth );
                    foreach ( $children as $child )
                    {
                        $result[] = self::createDefinition( $child, $includePropertyDefinitions );
                    }
                }
            }
        }
        else
        {
            foreach ( $allTypes as $key => $type )
            {
                $result[] = self::createDefinition( $type, $includePropertyDefinitions );
            }
        }

        $GLOBALS[$name] = $result;

        return $result;
    }

    /**
     * Searches type descendants by \a $typeId
     *
     * @return array
     */
    protected static function findTypeDescendants( $typeId, $depth = 1, $currentDepth = 0 )
    {
        $allTypes = self::getAllTypes();
        $result = array();
        if ( $currentDepth >= $depth )
        {
            return $result;
        }

        foreach ( $allTypes as $key => $type )
        {
            $groupParentId = isset( $type['parentId'] ) ? $type['parentId'] : false;
            $groupTypeId = isset( $type['typeId'] ) ? $type['typeId'] : false;
            if ( !$groupParentId or !$groupTypeId )
            {
                continue;
            }

            if ( $groupParentId == $typeId )
            {
                $result[] = $type;
                $children = self::findTypeDescendants( $groupTypeId, $depth, $currentDepth++ );
                if ( $children )
                {
                    $result[] = $children;
                }
            }
        }

        return $result;
    }

    /**
     * Creates Type definition
     *
     * @TODO: Implement $includePropertyDefinitions
     */
    public static function createDefinition( $typeInfo, $includePropertyDefinitions = false )
    {
        $result = array();

        $typeId = isset( $typeInfo['typeId'] ) ? $typeInfo['typeId'] : false;
        if ( !$typeId )
        {
            return $result;
        }

        $result = $typeInfo;
        $class = eZContentClass::fetchByIdentifier( $typeId );
        $result['displayName'] = $class ? $class->attribute( 'name' ) : '';
        if ( isset( $result['contentStreamAllowed'] ) )
        {
            $contentStreamList = self::getContentStreamAllowedEnum();
            $result['contentStreamAllowed'] = strtolower( $result['contentStreamAllowed'] );
            if ( !in_array( $result['contentStreamAllowed'], $contentStreamList ) )
            {
                $result['contentStreamAllowed'] = $contentStreamList['default'];

            }
        }

        // Remove internal eZ Publish settings from result set
        unset( $result['contentAttributeId'] );
        unset( $result['aliasList'] );

        return $result;
    }

    /**
     * A value that indicates whether a content-stream MAY, SHALL, or SHALL NOT be included in
     * objects of this type. Values:
     *     notallowed: A content-stream SHALL NOT be included
     *     allowed: A content-stream MAY be included
     *     required: A content-stream SHALL be included (i.e. SHALL be included when the object
     *               is created, and SHALL NOT be deleted.)
     */
    protected static function getContentStreamAllowedEnum()
    {
        return array( 'default' => 'notallowed', 'allowed', 'required' );
    }

    /**
     * Checks if content stream is allowed
     *
     * @return bool
     */
    public function isContentStreamAllowedByTypeId( $typeId )
    {
        $def = eZCMISTypeHandler::getTypeDefinition( $typeId );

        return isset( $def[0]['contentStreamAllowed'] ) ? $def[0]['contentStreamAllowed'] == 'allowed' : null;
    }

    /**
     * Checks if content stream is NOT allowed
     *
     * @return bool
     */
    public function isContentStreamNotAllowedByTypeId( $typeId )
    {
        $def = eZCMISTypeHandler::getTypeDefinition( $typeId );

        return isset( $def[0]['contentStreamAllowed'] ) ? $def[0]['contentStreamAllowed'] == 'notallowed' : null;
    }

    /**
     * Checks if content stream is required
     *
     * @return bool
     */
    public function isContentStreamRequiredByTypeId( $typeId )
    {
        $def = eZCMISTypeHandler::getTypeDefinition( $typeId );

        return isset( $def[0]['contentStreamAllowed'] ) ? $def[0]['contentStreamAllowed'] == 'required' : null;
    }

    /**
     * Provides base type, like 'folder' or 'document', by object type id (class id) or its alias
     */
    public static function getBaseTypeByTypeId( $typeId )
    {
        $name = __METHOD__ . $typeId;

        if ( isset( $GLOBALS[$name] ) and $GLOBALS[$name] )
        {
            return $GLOBALS[$name];
        }

        $types = self::getAllTypes();
        $result = false;

        foreach ( $types as $type )
        {
            $groupTypeId = isset( $type['typeId'] ) ? $type['typeId'] : false;
            if ( !$groupTypeId )
            {
                continue;
            }

            $aliases = isset( $type['aliasList'] ) ? $type['aliasList'] : array();

            if ( ( $groupTypeId == $typeId ) or in_array( $typeId, $aliases ) )
            {
                $groupBaseType = isset( $type['baseType'] ) ? $type['baseType'] : false;
                switch ( $groupBaseType )
                {
                    case self::DOCUMENT_OBJECT:
                    {
                        $result = self::DOCUMENT_OBJECT;
                    } break;

                    case self::FOLDER_OBJECT:
                    {
                        $result = self::FOLDER_OBJECT;
                    } break;

                    default:
                    {
                        throw new eZCMISRuntimeException( ezi18n( 'cmis', "Unknown Base Type configured: '%type%'", null, array( '%type%' => $groupBaseType ) ) );
                    }
                }
            }
        }

        $GLOBALS[$name] = $result;

        return $result;
    }

    /**
     * Provides class identifier by object \a $typeId (it can be alias)
     * which can be instantiated
     *
     * @return string|bool
     */
    public static function getRealTypeId( $typeId )
    {
        $name = __METHOD__ . $typeId;

        if ( isset( $GLOBALS[$name] ) and $GLOBALS[$name] )
        {
            return $GLOBALS[$name];
        }

        $types = self::getAllTypes();
        $result = false;

        foreach ( $types as $type )
        {
            $groupTypeId = isset( $type['typeId'] ) ? $type['typeId'] : false;
            if ( !$groupTypeId )
            {
                continue;
            }

            $aliases = isset( $type['aliasList'] ) ? $type['aliasList'] : array();

            /**
             * If $typeId is equal with typeId field or it exists in aliases
             * return value assigned in type definition
             */
            if ( ( $groupTypeId == $typeId ) or in_array( $typeId, $aliases ) )
            {
                $result = $groupTypeId;
            }
        }

        $GLOBALS[$name] = $result;

        return $result;
    }

    /**
     * Checks is base type \a $type folder
     *
     * @return bool
     */
    public static function isFolder( $type )
    {
        return $type == self::FOLDER_OBJECT;
    }

    /**
     * Checks is base type \a $type document
     *
     * @return bool
     */
    public static function isDocument( $type )
    {
      return $type == self::DOCUMENT_OBJECT;
    }

    /**
     * Provides attribute identifier by object \a $typeId
     *
     * @return string|bool
     */
    public static function getContentAttributeId( $typeId )
    {
        $result = false;
        $types = self::getAllTypes();

        foreach ( $types as $type )
        {
            $groupTypeId = isset( $type['typeId'] ) ? $type['typeId'] : false;
            if ( !$groupTypeId )
            {
                continue;
            }

            if ( $groupTypeId == $typeId )
            {
                $result = isset( $type['contentAttributeId'] ) ? $type['contentAttributeId'] : false;
                if ( !$result )
                {
                    throw new eZCMISRuntimeException( ezi18n( 'cmis', "No attribute identifier provided for class '%class%'", null, array( '%class%' => $class[0] ) ) );
                }
            }
        }

        return $result;
    }

    /**
     * Provides all supported types
     *
     * @return array of types
     */
    public static function getTypeIdList()
    {
        $name = __METHOD__;

        if ( isset( $GLOBALS[$name] ) )
        {
            return $GLOBALS[$name];
        }

        $types = self::getAllTypes();
        $result = array();

        foreach ( $types as $type )
        {
            $groupTypeId = isset( $type['typeId'] ) ? $type['typeId'] : false;
            if ( !$groupTypeId )
            {
                continue;
            }

            $result[] = $groupTypeId;
        }

        $GLOBALS[$name] = $result;

        return $result;
    }
}
?>
