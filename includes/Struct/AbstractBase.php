<?php
namespace Airwallex\Struct;

use Airwallex\Services\LogService;

abstract class AbstractBase {
	public function __construct( $dataArray = null ) {
		if ( is_array( $dataArray ) ) {
			$this->setFromArray( $dataArray );
		}
	}

	public function setFromArray( $dataArray ) {
		foreach ( $dataArray as $fieldName => $fieldValue ) {
			$fieldName  = str_replace( '_', '', ucwords( $fieldName, '_' ) );
			$methodName = 'set' . ucfirst( $fieldName );
			if ( method_exists( $this, $methodName ) ) {
				$this->{$methodName}( $fieldValue );
			} else {
				LogService::getInstance()->warning( __METHOD__ . " field {$fieldName} not found in " . get_called_class() );
			}
		}
	}

	/**
	 * Get an array representation of the object
	 *
	 * @return array
	 */
	public function toArray() {
		$return = array();
		foreach ( array_keys( get_object_vars( $this ) ) as $property ) {
			if ( isset( $this->{$property} ) ) {
				if ( is_object( $this->{$property} ) && method_exists( $this->{$property}, 'toArray' ) ) {
					$value = $this->{$property}->toArray();
				} else {
					$value = $this->{$property};
				}
				$return[ $property ] = $value;
			}
		}
		return $return;
	}
}
