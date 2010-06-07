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
 * @package com.makeabyte.agilephp.persistence.type
 */

/**
 * AgilePHP interceptor responsible for type casting a value to a PHP/SQL compatible BigInt 
 * 
 * @author Jeremy Hahn
 * @copyright Make A Byte, inc
 * @package com.makeabyte.agilephp.persistence.type
 * <code>
 * #@BigIntType
 * public function setMyBigint( $value ) {
 * 
 * 		  $this->myBigint = $value;
 * }
 * </code>
 */
#@Interceptor
class BigIntType {

	  /**
	   * Casts the first input parameter to a 64-bit "BigInt"
	   * 
	   * @param InvocationContext $ic The intercepted invocation context
	   * @return mixed InvocationContext The modified InvocationContext containing the casted BigInt value
	   */
	  #@AroundInvoke
	  public function cast( InvocationContext $ic ) {

	  		 if( !$ic->getParameters() )
	  		 	 throw new AgilePHP_InterceptionException( '#@BigIntType::cast Requires a method which accepts at least one parameter.' );

		  	 // Dont process arguments being set by persistence classes
	  		 $callee = $ic->getCallee();
	  		 $pieces = explode( DIRECTORY_SEPARATOR, $callee['file'] );
	  		 $className = str_replace( '.php', '', array_pop( $pieces ) );

	  		 if( $className == 'BasePersistence' || $className == 'PersistenceManager' || preg_match( '/dialect$/i', $className ) )
	  		 	 return $ic->proceed();

	  	     $params = $ic->getParameters();

	  	     $precision = ini_get( 'precision' );
			 ini_set( 'precision', 16 );
			 $params[0] = sprintf( '%.0f', $number );
			 ini_set( 'precision', $precision );

	  	     return $ic->proceed();
	  }
}
?>