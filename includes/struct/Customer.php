<?php

namespace Airwallex\Struct;

class Customer extends AbstractBase {

	protected $id;
	protected $requestId;
	protected $email;
	protected $firstName;
	protected $lastName;
	protected $merchantCustomerId;
	protected $createdAt;
	protected $updatedAt;

	/**
	 * Get customer ID
	 *
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set customer ID
	 *
	 * @param mixed $id
	 * @return Customer
	 */
	public function setId( $id ) {
		$this->id = $id;
		return $this;
	}

	/**
	 * Get request ID
	 *
	 * @return mixed
	 */
	public function getRequestId() {
		return $this->requestId;
	}

	/**
	 * Set request ID
	 *
	 * @param mixed $requestId
	 * @return Customer
	 */
	public function setRequestId( $requestId ) {
		$this->requestId = $requestId;
		return $this;
	}

	/**
	 * Get customer email
	 *
	 * @return mixed
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * Set customer email
	 *
	 * @param mixed $email
	 * @return Customer
	 */
	public function setEmail( $email ) {
		$this->email = $email;
		return $this;
	}

	/**
	 * Get customer first name
	 *
	 * @return mixed
	 */
	public function getFirstName() {
		return $this->firstName;
	}

	/**
	 * Set customer first name
	 *
	 * @param mixed $firstName
	 * @return Customer
	 */
	public function setFirstName( $firstName ) {
		$this->firstName = $firstName;
		return $this;
	}

	/**
	 * Get customer last name
	 *
	 * @return mixed
	 */
	public function getLastName() {
		return $this->lastName;
	}

	/**
	 * Set customer last name
	 *
	 * @param mixed $lastName
	 * @return Customer
	 */
	public function setLastName( $lastName ) {
		$this->lastName = $lastName;
		return $this;
	}

	/**
	 * Get merchant customer ID
	 *
	 * @return mixed
	 */
	public function getMerchantCustomerId() {
		return $this->merchantCustomerId;
	}

	/**
	 * Set merchant customer ID
	 *
	 * @param mixed $merchantCustomerId
	 * @return Customer
	 */
	public function setMerchantCustomerId( $merchantCustomerId ) {
		$this->merchantCustomerId = $merchantCustomerId;
		return $this;
	}

	/**
	 * Get customer created time
	 *
	 * @return mixed
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set customer created time
	 *
	 * @param mixed $createdAt
	 * @return Customer
	 */
	public function setCreatedAt( $createdAt ) {
		$this->createdAt = $createdAt;
		return $this;
	}

	/**
	 * Get customer updated time
	 *
	 * @return mixed
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * Set customer updated time
	 *
	 * @param mixed $updatedAt
	 * @return Customer
	 */
	public function setUpdatedAt( $updatedAt ) {
		$this->updatedAt = $updatedAt;
		return $this;
	}
}
