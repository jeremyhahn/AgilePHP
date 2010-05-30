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
 * @package com.makeabyte.agilephp.validator
 */

/**
 * Validates values to ensure minimum length requirements are met
 *  
 * @author Jeremy Hahn
 * @copyright Make A Byte, inc
 * @package com.makeabyte.agilephp.validator
 */
class LengthValidator extends Validator {

	  private $size;

	  /**
	   * Creates a new LengthValidator
	   * 
	   * @param mixed $data The data to validate
	   * @param int $size A required length
	   * @return void
	   */
	  public function __construct( $data, $size = 1 ) {

	  		 $this->data = $data;
	  		 $this->size = $size;
	  }

	  public function validate() {

	  		 if( strlen( $data ) < $size ) return false;
	  		 return (strtotime( $this->data ) === false) ? false : true;
	  }
}
?>