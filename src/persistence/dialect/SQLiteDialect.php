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
 * @package com.makeabyte.agilephp.persistence.dialect
 */

/**
 * Responsible for SQLite specific database operations
 * 
 * @author Jeremy Hahn
 * @copyright Make A Byte, inc
 * @package com.makeabyte.agilephp.persistence.dialect
 * @version 0.3a
 */
class SQLiteDialect extends BasePersistence implements SQLDialect {

	  public function __construct( Database $db ) {

	  	     $this->pdo = new PDO( 'sqlite:' . $db->getName() . '.sqlite' );
	 	     $this->database = $db;
	  }

	  /**
	   * (non-PHPdoc)
	   * @see src/persistence/dialect/SQLDialect#create()
	   */
	  public function create() {

	  		 foreach( $this->database->getTables() as $table ) {

	  		 		$sql = 'CREATE TABLE "' . $table->getName() . '" ( ';

	  			  	$bCandidate = false;

	  		  	    // Compound keys are formatted differently in sqlite
	  	            $pkeyColumns = $table->getPrimaryKeyColumns();
	  		 		if( count( $pkeyColumns ) > 1 ) {

		  		 		foreach( $table->getColumns() as $column ) {

		  		 			     if( $column->isAutoIncrement() )
		  		 			     	 Logger::getInstance()->debug( 'Ignoring autoIncrement="true" for column ' . $column->getName() . '. Sqlite does not support the use of auto-increment with compound primary keys' );

		  		 				 $sql .= '"' . $column->getName() . '" ' . $column->getType() .
		  		 						 (($column->isRequired() == true) ? ' NOT NULL' : '') .
		  		 						 (($column->getDefault()) ? ' DEFAULT \'' . $column->getDefault() . '\'' : '') . ', ';
		  		 		}
		  		 		
		  		 		$sql .= 'PRIMARY KEY ( ';
		  		 		for( $i=0; $i<count( $pkeyColumns ); $i++ ) {

		  		 				 $sql .= '"' . $pkeyColumns[$i]->getName() . '"';

		  		 				 if( ($i+1) < count( $pkeyColumns ) )
		  		 				 	 $sql .= ', ';
		  		 		}
		  		 		$sql .= ' ) );';

	  		 		}
	  		 		else {

		  		 		foreach( $table->getColumns() as $column ) {
	
		  		 			     if( $column->isAutoIncrement() && $column->isPrimaryKey() )
		  		 			     	 $bCandidate = true;
	
		  		 				 $sql .= '"' . $column->getName() . '" ' . $column->getType() . (($column->isPrimaryKey() === true) ? ' PRIMARY KEY' : '') .
		  		 						 (($column->isAutoIncrement() === true) ? ' AUTOINCREMENT' : '') .
		  		 						 (($column->isRequired() == true) ? ' NOT NULL' : '') .
		  		 						 (($column->getDefault()) ? ' DEFAULT \'' . $column->getDefault() . '\'' : '') .
										 (($column->isForeignKey()) ? ' CONSTRAINT ' . $column->getForeignKey()->getName() . ' REFERENCES ' . $column->getForeignKey()->getReferencedTable() . '(' . $column->getForeignKey()->getReferencedColumn() . ')' : '') . ', ';
		  		 		}
	
		  		 		// remove last comma and space
				   		$sql = substr( $sql, 0, -2 );
	  		 		}

	  		 		$sql .= ' );';

			   		//if( $bCandidate && (count( $table->getPrimaryKeyColumns() ) > 1) )
			   			//throw new AgilePHP_PersistenceException( 'Sqlite does not allow the use of auto-increment with compound primary keys (' . $table->getName() . ')' );

			   		$this->query( $sql );

			   		// Throw exceptions
	  		 		if( $this->pdo->errorInfo() !== null ) {

	  		 			$info = $this->pdo->errorInfo();
	  		 			if( $info[0] == '0000' )
	  		 				continue;

				  	    throw new AgilePHP_PersistenceException( $info[2], $info[1] );
				  	}
	  		 }

	  		 // Create triggers for each of the foreign keys to enforce referential integrity after the tables have been built
	  		 foreach( $this->database->getTables() as $table ) {

			   		foreach( $table->getForeignKeyColumns() as $column ) {

			   			// Create default restrict triggers for inserts, updates, and deletes to referenced column
			   			$this->createInsertRestrictTrigger( $column->getForeignKey()->getName() . '_fkInsert', $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired() );
			   			$this->createUpdateRestrictTrigger( $column->getForeignKey()->getName() . '_refUpdate', $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired() );
			   			$this->createDeleteRestrictTrigger( $column->getForeignKey()->getName() . '_refDelete', $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired() );

			   			// Set appropriate ON UPDATE clause based on persistence.xml configuration
			   			switch( $column->getForeignKey()->getOnUpdate() ) {

			   				case 'NO_ACTION':
			   					break;

			   				case 'RESTRICT':
			   					$this->createUpdateRestrictTrigger( $column->getForeignKey()->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $table->getName(), $column->getName(), $column->isRequired() );		
			   					break;

			   				case 'CASCADE':
			   					$this->createUpdateCascadeTrigger( $column->getForeignKey()->getName(), $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired() );
			   					break;

			   				case 'SET_NULL':
								$this->createUpdateSetNullTrigger( $column->getForeignKey()->getName(), $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired() );
			   					break;

			   				case 'SET_DEFAULT':
			   					$this->createUpdateSetDefaultTrigger( $column->getForeignKey()->getName(), $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired(), $column->getDefault() );
			   					break;
			   			}

			   			// Set appropriate ON DELETE clause based on persistence.xml configuration
			   			switch( $column->getForeignKey()->getOnDelete() ) {

			   				case 'NO_ACTION':
			   					break;

			   				case 'RESTRICT':
			   					$this->createDeleteRestrictTrigger( $column->getForeignKey()->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $table->getName(), $column->getName(), $column->isRequired() );		
			   					break;

			   				case 'CASCADE':
			   					$this->createDeleteCascadeTrigger( $column->getForeignKey()->getName(), $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired() );
			   					break;

			   				case 'SET_NULL':
			   					$this->createDeleteSetNullTrigger( $column->getForeignKey()->getName(), $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired() );
			   					break;

			   				case 'SET_DEFAULT':
								$this->createDeleteSetDefaultTrigger( $column->getForeignKey()->getName(), $table->getName(), $column->getName(), $column->getForeignKey()->getReferencedTable(), $column->getForeignKey()->getReferencedColumn(), $column->isRequired(), $column->getDefault() );
			   					break;
			   			}
			   		}
	  		 }
	  }

	  /**
	   * (non-PHPdoc)
	   * @see src/persistence/BasePersistence#truncate($model)
	   */
	  public function truncate( $model ) {

	  	     $table = $this->getTableByModel( $model );
	  		 $this->query( 'DELETE FROM ' . $table->getName() . ';' );
	  }

	  /**
	   * (non-PHPdoc)
	   * @see src/persistence/dialect/SQLDialect#drop()
	   */
	  public function drop() {

  	 	 	 $dbfile = $this->database->getName() . '.sqlite';

  	 	 	 if( !file_exists( $dbfile ) )
  	  	 	 	 throw new AgilePHP_PersistenceException( 'Could not locate sqlite database: ' . $dbfile );

  	  	 	 chmod( $dbfile, 0777 );

  	  	 	 if( !unlink( $dbfile ) )
  		 	 	throw new AgilePHP_PersistenceException( 'Could not drop/delete the sqlite database: ' . $dbfile );
	  }

	  /**
	   * (non-PHPdoc)
	   * @see src/persistence/dialect/SQLDialect#reverseEngineer()
	   */
	  public function reverseEngineer() {

	  		 $Database = new Database();
	  		 $Database->setId( $this->database->getId() );
	  		 $Database->setName( $this->database->getName() );
	  		 $Database->setType( $this->database->getType() );
	  		 $Database->setHostname( $this->database->getHostname() );
	  		 $Database->setUsername( $this->database->getUsername() );
	  		 $Database->setPassword( $this->database->getPassword() );

	  		 $stmt = $this->query( "SELECT name, sql FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' ORDER BY 1" );
	  		 $stmt->setFetchMode( PDO::FETCH_OBJ );

	  		 foreach( $stmt->fetchAll() as $table )
  		 		$Database->addTable( $this->parseTable( $table->sql ) );

  		 	 return $Database;
	  }

	  /**
	   * Parses SQL database table SQL into AgilePHP Table objects.
	   * 
	   * @param String $sql CREATE TABLE SQL statement
	   * @return Table The parsed table instance
	   */
	  private function parseTable( $sql ) {

	  		  preg_match( '/TABLE\\s*"(.*?)"\\s/i', $sql, $name );

	  		  $Table = new Table();
	  		  $Table->setName( $name[1] );
	  		  $Table->setModel( ucfirst( $name[1] ) );

	  		  foreach( $this->parseColumns( $sql, $Table->getName() ) as $column )
 		  			$Table->addColumn( $column );

	  		  return $Table;
	  }

	  /**
	   * Parses SQL columns from a CREATE TABLE sql statement
	   * 
	   * @param String $sql The CREATE TABLE SQL statement to parse
	   * @param String $tblName The name of the table being parsed
	   * @return void
	   */
	  private function parseColumns( $sql, $tblName ) {

	  		  $colz = array();

	  		  preg_match( '/\((.*)\)/', $sql, $cols );

	  		  $columns = explode( ',', $cols[1] );
	  		  foreach( $columns as $column ) {

	  		  		preg_match( '/"(.*)"/', $column, $name );
	  		  		preg_match( '/\\s(\\w+)/', $column, $type );

	  		  		$Column = new Column( null, $tblName );
	  		  		$Column->setName( $name[1] );
	  		  		$Column->setType( strtolower( $type[1] ) );
	  		  		
	  		  		if( preg_match( '/primary key/i', $column ) )
	  		  			$Column->setPrimaryKey( true );

	  		  		if( preg_match( '/not null/i', $column ) )
	  		  			$Column->setRequired( true );

	  		  		preg_match( '/default\\s(.*)/i', $column, $default );
	  		  		if( count( $default ) )
	  		  			$Column->setDefault( trim( str_replace( "'", '', $default[1] ) ) );

	  		  		preg_match( '/(constraint\\s(.*?)\\sreferences\\s?(.*)\((.*)\))/i', $column, $fkey );
	  		  		
	  		  		if( count( $fkey ) == 5 ) {

	  		  			$ForeignKey = new ForeignKey( null, $tblName, $name[1] );
	  		  			$ForeignKey->setName( $fkey[2] );
	  		  			$ForeignKey->setSelect( $fkey[4] );
						$ForeignKey->setType( 'one-to-many' );
						$ForeignKey->setReferencedTable( $fkey[3] );
						$ForeignKey->setReferencedColumn( $fkey[4] );
						$ForeignKey->setReferencedController( ucfirst( $fkey[3] ) . 'Controller' );
						$ForeignKey->setOnDelete( 'SET_NULL' );
						$ForeignKey->setOnUpdate( 'CASCADE' );

						$Column->setForeignKey( $ForeignKey );
	  		  		}
	  		  		array_push( $colz, $Column );
	  		  }
	  		  return $colz; 
	  }

	  /**
	   * Creates an insert trigger responsible for enforcing foreign key constraints by restricting
	   * insert statements in the foreign key field that do not exist in the referenced table.column.
	   * 
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createInsertRestrictTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false ) {

	  		  $sql = 'CREATE TRIGGER tir' . $fkName . '
	  		  			BEFORE INSERT ON [' . $table . ']
	  		  			FOR EACH ROW BEGIN
	  		  				SELECT RAISE( ROLLBACK, \'Insert on table "' . $table . '" violates foreign key constraint "' . str_replace( '_fkInsert', '', $fkName ) . '"\' )
	  		  				WHERE NEW.' . $column . ' IS NOT NULL AND (SELECT ' . $rColumn . ' FROM ' . $rTable . ' WHERE ' . $rColumn . ' = NEW.' . $column . ') IS NULL;
	  		  			END;';

	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }

	  /**
	   * Creates an update trigger responsible for enforcing referential integrity of foreign key constraints
	   * by restricting update statements in the foreign key field that do not relate to a value in the referenced table.column.
	   *
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createUpdateRestrictTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false ) {
	
	  		  $sql = 'CREATE TRIGGER tur' . $fkName . '
						BEFORE UPDATE ON [' . $table . ']
						FOR EACH ROW BEGIN
						    SELECT RAISE( ROLLBACK, \'Update on table "' . $table . '" violates foreign key constraint "' . str_replace( '_refUpdate', '', $fkName ) . '"\' )
						      WHERE NEW.' . $column . ' IS NOT NULL AND (SELECT ' . $rColumn . ' FROM ' . $rTable . ' WHERE ' . $rColumn . ' = NEW.' . $column . ') IS NULL;
						END;';

	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }

 	  /**
	   * Creates a delete trigger responsible for enforcing referential integrity of foreign key constraints
	   * by restricting delete statements in the referenced table while references still exist in the foreign table.
	   *
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createDeleteRestrictTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false ) {
	
	  		  $sql = 'CREATE TRIGGER tdr' . $fkName . '
						BEFORE DELETE ON [' . $rTable . ']
						FOR EACH ROW BEGIN
						  SELECT RAISE( ROLLBACK, \'Delete on table "' . $rTable . '" violates foreign key constraint "' . str_replace( '_refDelete', '', $fkName ) . '"\' )
						  WHERE (SELECT ' . $column . ' FROM ' . $table . ' WHERE ' . $column . ' = OLD.' . $rColumn . ') IS NOT NULL;
						END;';

	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }

	  /**
	   * Creates an update trigger responsible for enforcing referential integrity of foreign key constraints
	   * by cascading updates to the foreign key.
	   * 
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createUpdateCascadeTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false ) {

	  		  $sql = 'CREATE TRIGGER tuc' . $fkName . '
						BEFORE UPDATE ON [' . $rTable . ']
						FOR EACH ROW BEGIN
						    UPDATE ' . $table . ' SET ' . $column . ' = NEW.' . $rColumn . ' WHERE ' . $table . '.' . $column . ' = OLD.' . $rColumn . ';
						END;';

	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }

	  /**
	   * Creates a delete trigger responsible for enforcing referential integrity of foreign key constraints
	   * by cascading deletes to the foreign key.
	   * 
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createDeleteCascadeTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false ) {

	  		  $sql = 'CREATE TRIGGER tdc' . $fkName . '
						BEFORE DELETE ON [' . $rTable . ']
						FOR EACH ROW BEGIN
						    DELETE FROM ' . $table . ' WHERE ' . $table . '.' . $column . ' = OLD.' . $rColumn . ';
						END;';

	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }

	  /**
	   * Creates a delete trigger responsible for enforcing referential integrity of foreign key constraints
	   * by setting the foreign key to null.
	   * 
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createUpdateSetNullTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false ) {

	  		  $sql = 'CREATE TRIGGER tusn' . $fkName . '
						BEFORE UPDATE ON [' . $rTable . ']
						FOR EACH ROW BEGIN
						    UPDATE ' . $table . ' SET ' . $column . ' = NULL WHERE ' . $column . ' = OLD.' . $rColumn . ';
						END;';
	  		  
	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }

	  /**
	   * Creates an update trigger responsible for enforcing referential integrity of foreign key constraints
	   * by setting the foreign key to null.
	   * 
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createDeleteSetNullTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false ) {

	  		  $sql = 'CREATE TRIGGER tdsn' . $fkName . '
						BEFORE DELETE ON [' . $rTable . ']
						FOR EACH ROW BEGIN
						    UPDATE ' . $table . ' SET ' . $column . ' = NULL WHERE ' . $column . ' = OLD.' . $rColumn . ';
						END;';

	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }
	  
	  /**
	   * Creates a delete trigger responsible for enforcing referential integrity of foreign key constraints
	   * by setting the foreign key to the columns default value.
	   * 
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @param mixed $default The default value of the column to set as configured in persistence.xml
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createUpdateSetDefaultTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false, $default ) {

	  		  $sql = 'CREATE TRIGGER tusd' . $fkName . '
						BEFORE UPDATE ON [' . $rTable . ']
						FOR EACH ROW BEGIN
						    UPDATE ' . $table . ' SET ' . $column . ' = "' . $default . '" WHERE ' . $column . ' = OLD.' . $rColumn . ';
						END;';
	  		  
	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }

	  /**
	   * Creates an update trigger responsible for enforcing referential integrity of foreign key constraints
	   * by setting the foreign key to the columns default value.
	   * 
	   * @param String $fkName The foreign key / constraint name
	   * @param String $table The table name containing the foreign key
	   * @param String $column The foreign key column name
	   * @param String $rTable The referenced table name
	   * @param String $rColumn The referenced column name
	   * @param bool $notNull True if the column is marked as NOT NULL / required, false otherwise.
	   * @param mixed $default The default value of the column to set as configured in persistence.xml
	   * @return void
	   * @throws AgilePHP_PersistenceException
	   */
	  private function createDeleteSetDefaultTrigger( $fkName, $table, $column, $rTable, $rColumn, $notNull = false, $default ) {

	  		  $sql = 'CREATE TRIGGER tdsd' . $fkName . '
						BEFORE DELETE ON [' . $rTable . ']
						FOR EACH ROW BEGIN
						    UPDATE ' . $table . ' SET ' . $column . ' = "' . $default . '" WHERE ' . $column . ' = OLD.' . $rColumn . ';
						END;';

	  		  $this->query( $sql );

  	 		  if( $this->pdo->errorInfo() !== null ) {

  	 			  $info = $this->pdo->errorInfo();
  	 			  if( $info[0] == '0000' ) return;

		  	      throw new AgilePHP_PersistenceException( $info[2], $info[1] );
		  	  }
	  }  
}
?>