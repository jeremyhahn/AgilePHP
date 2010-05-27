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
 * @package com.makeabyte.agilephp.annotation
 */

/**
 * Extends the PHP ReflectioMethod to provide details about method level
 * AgilePHP annotations. 
 * 
 * @author Jeremy Hahn
 * @copyright Make A Byte, inc
 * @package com.makeabyte.agilephp.annotation
 * @version 0.2a
 */
class AnnotatedMethod extends ReflectionMethod {

	  private $annotations = array();

	  /**
	   * Creates a new instance of AnnotatedMethod.
	   * 
	   * @param mixed $class The name or instance of a class to inspect
	   * @param String $method The name of the method to inspect.
	   * @return void
	   * @throws AgilePHP_AnnotationException
	   */
	  public function __construct( $class, $method ) {

	  		 try {
			        parent::__construct( $class, $method );

			  		$parser = AnnotationParser::getInstance();
			  		$parser->parse( parent::getDeclaringClass()->getName() );

			  		$annotations = $parser->getMethodAnnotations( $this );
			  		$this->annotations = count($annotations) ? $annotations : null;
	  		 }
	  		 catch( ReflectionException $re ) {

	  		 		throw new AgilePHP_AnnotationException( $re->getMessage(), $re->getCode() );
	  		 }
	  }

	  /**
	   * Returns boolean indicator based on the presence of any method level annotations.
	   * 
	   * @return True if this method has any annotations, false otherwise.
	   */
	  public function isAnnotated() {

	  		 return count( $this->annotations ) && isset( $this->annotations[0] ) ? true : false;
	  }

	  /**
	   * Checks the method for the presence of the specified annotation.
	   * 
	   * @param String $annotation The name of the annotation.
	   * @return True if the annotation is present, false otherwise.
	   */
	  public function hasAnnotation( $annotation ) {

	  	     if( $this->isAnnotated() ) {

		  		 foreach( $this->annotations as $annote ) {
	
		  		 		  $class = new ReflectionClass( $annote );
		  		 		  if( $class->getName() == $annotation )
		  		 		  	  return true;
		  		 }
	  	     }

	  		 return false;
	  }

	  /**
	   * Returns all method annotations. If a name is specified
	   * only annotations which match the specified name will be returned,
	   * otherwise all annotations are returned.
	   * 
	   * @param String $name Optional name of the annotation to filter on. Default is return
	   * 					 all annotations.
	   * @return An array of method level annotations or false of no annotations could
	   * 		 be found.
	   */
	  public function getAnnotations( $name = null ) {

	  		 if( $name != null ) {

	  		 	 $annotations = array();
		  		 foreach( $this->annotations as $annote ) {
	
		  		 		  if( $annote instanceof $name )
		  		 		  	  array_push( $annotations, $annote );
		  		 }

		  		 if( !count( $annotations ) ) return false;

		  		 return $annotations;
	  		 }

	  		 return $this->annotations;
	  }

	  /**
	   * Gets an annotation instance by name. If the named annotation is found more
	   * than once, an array of annotations are returned.
	   * 
	   * @param String $name The name of the annotation
	   * @return The annotation instance or false if the annotation was not found
	   */
	  public function getAnnotation( $annotation ) {

	  		 $annotations = array();

	  		 foreach( $this->annotations as $annote ) {

	  		 		  $class = new ReflectionClass( $annote );
	  		 		  if( $class->getName() == $annotation )
	  		 		  	  array_push( $annotations, $annote );
	  		 }

	  		 if( !count( $annotations ) ) return false;

	  		 return (count($annotations) > 1) ? $annotations : $annotations[0];
	  }

	  /**
	   * Gets the parent class as an AnnotatedClass
	   * 
	   * @return AnnotatedClass
	   */
	  public function getDeclaringClass() {

	  	     $class = parent::getDeclaringClass();
			 return new AnnotatedClass( $class->getName() );
	  }
}
?>