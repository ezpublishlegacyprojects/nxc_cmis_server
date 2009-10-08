<?php
/**
 * Definition of eZCMISModuleContent class
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
 * Handles operations on content stream
 *
 * @services:
 *    GET: getContentStream
 *    PUT: setContentStream
 *    DELETE: deleteContentStream
 *
 * @file ezcmismodulecontent.php
 */

include_once( eZExtension::baseDirectory() . '/ezcmis/classes/modules/ezcmismodulebase.php' );

class eZCMISModuleContent extends eZCMISModuleBase
{
    /**
     * Processes GET methods
     */
    protected function processGET()
    {
        return $this->processService( 'getContentStream' );
    }

    /**
     * Processes PUT methods
     */
    protected function processPUT()
    {
        return $this->processService( 'setContentStream' );
    }

    /**
     * Processes DELETE methods
     */
    protected function processDELETE()
    {
        $this->Code = 204;
        $this->setParam( 'documentId', $this->getParamValue( 'objectId' ) );

        return $this->processService( 'deleteContentStream' );
    }

    /**
     * @reimp
     */
    public function process()
    {
        return $this->processByHTTPMethod( array( 'GET' => 'processGET',
                                                  'PUT' => 'processPUT',
                                                  'DELETE' => 'processDELETE' ) );
    }
}
?>
