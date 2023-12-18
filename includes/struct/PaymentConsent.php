<?php

namespace Airwallex\Struct;

if (!defined('ABSPATH')) {
	exit;
}

class PaymentConsent extends AbstractBase {
	protected $id;
	protected $customerId;
	protected $metadata = [];
	protected $status;
	protected $initialPaymentIntentId;
	protected $createdAt;
	protected $updatedAt;
	protected $nextAction;
	protected $failureReason = [];
	protected $merchantTriggerReason;
	protected $nextTriggeredBy;
	protected $paymentMethod = [];
	protected $purpose;
	protected $requestId;

	/**
	 * Get ID
	 *
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set ID
	 *
	 * @param mixed $id
	 * @return PaymentConsent
	 */
	public function setId( $id ) {
		$this->id = $id;
		return $this;
	}

	/**
	 * Get Customer ID
	 *
	 * @return mixed
	 */
	public function getCustomerId() {
		return $this->customerId;
	}

	/**
	 * Set Customer ID
	 *
	 * @param mixed $customerId
	 * @return PaymentConsent
	 */
	public function setCustomerId($customerId) {
		$this->customerId = $customerId;
		return $this;
	}

	/**
	 * Get Metadata
	 *
	 * @return array
	 */
	public function getMetadata() {
		return $this->metadata;
	}

	/**
	 * Set Metadata
	 *
	 * @param array $metadata
	 * @return PaymentConsent
	 */
	public function setMetadata($metadata) {
		$this->metadata = $metadata;
		return $this;
	}

	/**
	 * Get Status
	 *
	 * @return mixed
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * Set Status
	 *
	 * @param mixed $status
	 * @return PaymentConsent
	 */
	public function setStatus($status) {
		$this->status = $status;
		return $this;
	}

	/**
	 * Get Initial Payment Intent Id
	 *
	 * @return mixed
	 */
	public function getInitialPaymentIntentId() {
		return $this->initialPaymentIntentId;
	}

	/**
	 * Set Initial Payment Intent Id
	 *
	 * @param mixed $initialPaymentIntentId
	 * @return PaymentConsent
	 */
	public function setInitialPaymentIntentId($initialPaymentIntentId) {
		$this->initialPaymentIntentId = $initialPaymentIntentId;
		return $this;
	}

	/**
	 * Get Created At
	 *
	 * @return mixed
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set Created At
	 *
	 * @param mixed $createdAt
	 * @return PaymentConsent
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
		return $this;
	}

	/**
	 * Get Updated At
	 *
	 * @return mixed
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * Set Created At
	 *
	 * @param mixed $createdAt
	 * @return PaymentConsent
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
		return $this;
	}

	/**
	 * Get Next Action
	 *
	 * @return mixed
	 */
	public function getNextAction() {
		return $this->nextAction;
	}

	/**
	 * Set Next Action
	 *
	 * @param mixed $nextAction
	 * @return PaymentConsent
	 */
	public function setNextAction($nextAction) {
		$this->nextAction = $nextAction;
		return $this;
	}

	/**
	 * Get Failure Reason
	 *
	 * @return array
	 */
	public function getFailureReason() {
		return $this->failureReason;
	}

	/**
	 * Set Failure Reason
	 *
	 * @param array $failureReason
	 * @return PaymentConsent
	 */
	public function setFailureReason($failureReason) {
		$this->failureReason = $failureReason;
		return $this;
	}

	/**
	 * Get Merchant Trigger Reason
	 *
	 * @return mixed
	 */
	public function getMerchantTriggerReason() {
		return $this->merchantTriggerReason;
	}

	/**
	 * Set Merchant Trigger Reason
	 *
	 * @param mixed $merchantTriggerReason
	 * @return PaymentConsent
	 */
	public function setMerchantTriggerReason($merchantTriggerReason) {
		$this->merchantTriggerReason = $merchantTriggerReason;
		return $this;
	}

	/**
	 * Get Next Triggered By
	 *
	 * @return mixed
	 */
	public function getNextTriggeredBy() {
		return $this->nextTriggeredBy;
	}

	/**
	 * Set Next Triggered By
	 *
	 * @param mixed $nextTriggeredBy
	 * @return PaymentConsent
	 */
	public function setNextTriggeredBy($nextTriggeredBy) {
		$this->nextTriggeredBy = $nextTriggeredBy;
		return $this;
	}

	/**
	 * Get Payment Method
	 *
	 * @return array
	 */
	public function getPaymentMethod() {
		return $this->paymentMethod;
	}

	/**
	 * Set Payment Method
	 *
	 * @param array $paymentMethod
	 * @return PaymentConsent
	 */
	public function setPaymentMethod($paymentMethod) {
		$this->paymentMethod = $paymentMethod;
		return $this;
	}

	/**
	 * Get Purpose
	 *
	 * @return mixed
	 */
	public function getPurpose() {
		return $this->purpose;
	}

	/**
	 * Set Purpose
	 *
	 * @param mixed $purpose
	 * @return PaymentConsent
	 */
	public function setPurpose($purpose) {
		$this->purpose = $purpose;
		return $this;
	}

	/**
	 * Get Request ID
	 *
	 * @return mixed
	 */
	public function getRequestId() {
		return $this->requestId;
	}

	/**
	 * Set Request ID
	 *
	 * @param mixed $requestId
	 * @return PaymentConsent
	 */
	public function setRequestId($requestId) {
		$this->requestId = $requestId;
		return $this;
	}
}
