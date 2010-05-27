<?php 
/**
 * AgilePHP Framework :: The Rapid "for developers" PHP5 framework
 * Copyright (C) 2009-2010 Make A Byte, inc
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package com.makeabyte.agilephp.generator.util
 */

/**
 * Responsible for OpenAppstore integration
 * 
 * @author Jeremy Hahn
 * @copyright Make A Byte, inc
 * @package com.makeabyte.agilephp.ide.classes
 * 
 * @todo Rename to AppstoreRemote and move setProperty to its own class - ComponentsRemote
 */
class ComponentsRemote {

	  private $api;
	  private $platformId;

	  public function __construct() {

	  		 $config = new Config();

 	  		 $config->setName( 'appstore_endpoint' );
 	  		 $endpoint = $config->getValue();

 	  		 $config->setName( 'appstore_username' );
 	  		 $username = $config->getValue();

 	  		 $config->setName( 'appstore_password' );
 	  		 $password = $config->getValue();

 	  		 $config->setName( 'appstore_apikey' );
 	  		 $apikey = $config->getValue();

 	  		 $config->setName( 'appstore_platformId' );
	  		 $this->platformId = $config->getValue();

	  		 $this->api = new AppstoreAPI();
	  		 $this->api->login( $username, $password, $apikey );
	  }

	  #@RemoteMethod
	  public function getApps() {

	  		 $o = new stdClass;
	  		 $o->apps = $this->api->getAppsByPlatform( $this->platformId );

	  		 return $o;
	  }

	  #@RemoteMethod
	  public function install( $projectRoot, $id, $appId ) {

	  		 $projectRoot = preg_replace( '/\|/', DIRECTORY_SEPARATOR, $projectRoot );
	  		 $file = $this->download( $projectRoot, $id, $appId );

			 if( !$this->unzip( $projectRoot, $file ) )
	             throw new AgilePHP_Exception( 'Could not extract downloaded component \'' . $file . '\'.' );

	         $component = $projectRoot . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $appId . DIRECTORY_SEPARATOR . $appId . '.php';
	         $controller = $projectRoot . DIRECTORY_SEPARATOR . 'control' . DIRECTORY_SEPARATOR . $appId . '.php';

	         if( !file_exists( $component ) ) {

	         	 Logger::getInstance()->warn( 'ComponentsRemote::install Missing component controller at \'' . $file . '\'.' );
	         	 return false;
	         }

	         // Load database schema
	         $component_xml = $projectRoot . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'component.xml';
	         if( file_exists( $component_xml ) ) {
	
	         	 $persistence_xml = $projectRoot . DIRECTORY_SEPARATOR . 'persistence.xml';
		  		 $xml = simplexml_load_file( $component_xml );
	
		  		 if( isset( $xml->component->persistence ) ) {

		  		 	 $pm = new PersistenceManager( null, $persistence_xml );
			  		 foreach( $xml->component->persistence->table as $table ) {

				  		 	  $Table = new Table( $table );
				  		 	  $pm->createTable( $table );
			  		 }
		  		 }
	         }
	  }

	  #@RemoteMethod
	  public function setProperty( $componentId, $name, $value ) {

	  		 $path = preg_replace( '/\|/', DIRECTORY_SEPARATOR, $componentId );
	  		 if( !file_exists( $path ) )
	  		 	 throw new AgilePHP_Exception( 'Component path not found \'' . $path . '\'.' );

	  		 $file = $path . DIRECTORY_SEPARATOR . 'component.xml';

	  		 $xml = simplexml_load_file( $file );

	  		 foreach( $xml->component->param as $param )
	  		 	if( $param->attributes()->name == $name )
	  		 		$param['value'] = $value;

	  		 $xml->asXML( $file );

	  		 return true;
	  }

	  /**
	   * Downloads a component to the web application components directory
	   * 
	   * @param $id The id of the application in OpenAppstore
	   * @param $appId The appId of the application in OpenAppstore
	   * @return The file path to the downloaded file
	   */
	  private function download( $projectRoot, $id, $appId ) {

	  		  $path = $projectRoot . DIRECTORY_SEPARATOR . 'components';
	  		  return $this->api->download( $id, $appId, $path );
	  }

	  /**
	   * Unzips a downloaded component
	   * 
	   * @param $file The file path of the archive to extract
	   * @return True if the archive was successfully extracted or false if on failure
	   */
	  private function unzip( $projectRoot, $file ) {

			  $zip = new ZipArchive();

              if( $zip->open( $file ) === TRUE ) {

                   $zip->extractTo( $projectRoot . DIRECTORY_SEPARATOR . 'components' );
                   $zip->close();

                   unlink( $file );
                   return true;
              }

              // try to unzip using shell as last resort
              exec( 'cd ' . $projectRoot . DIRECTORY_SEPARATOR . 'components; unzip ' . $file, $output );
              if( !$output ) return false;

              return unlink( $file );
	  }

	  /**
	   * Copies componentcontroller.php to the project/control directory if it exists
	   * 
	   * @param string $projectRoot The full file path to the project
	   * @param string $componentName The name of the component to copy the controllers from
	   */
	  private function copyController( $projectRoot, $componentName ) {

	  		  $it = new RecursiveDirectoryIterator( $projectRoot . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $componentName );
		 	  foreach( new RecursiveIteratorIterator( $it ) as $file ) {

		   	      	   if( substr( $file, -1 ) != '.' && substr( $file, -2 ) != '..' ) {

				 		   if( strtolower( basename( $file ) ) == 'appcontroller.php' ) {

				 		   		if( !copy( $file, $projectRoot . DIRECTORY_SEPARATOR . 'control' . DIRECTORY_SEPARATOR . $componentName . '.php' ) )
				 		   			 throw new AgilePHP_Exception( 'Failed to copy component controller to project.' );

				 		   		if( !unlink( $file ) )
				 		   			throw new AgilePHP_Exception( 'Failed to delete downloaded component.' );
				 		   }
		   	      	   }
		 	  }
	  }
}
?>