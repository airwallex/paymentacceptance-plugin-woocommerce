<?php

namespace Airwallex\Struct;

class PaymentIntent extends AbstractBase {

	const STATUS_REQUIRES_CAPTURE         = 'REQUIRES_CAPTURE';
	const STATUS_SUCCEEDED                = 'SUCCEEDED';
	const STATUS_REQUIRES_CUSTOMER_ACTION = 'REQUIRES_CUSTOMER_ACTION';

	const PENDING_STATUSES = array(
		self::STATUS_REQUIRES_CUSTOMER_ACTION,
	);
	const SUCCESS_STATUSES = ['SUCCEEDED', 'REQUIRES_CAPTURE', 'AUTHORIZED', 'DONE'];

	protected $id;
	protected $requestId;
	protected $amount;
	protected $currency;
	protected $order;
	protected $merchantOrderId;
	protected $customerId;
	protected $paymentConsentId;
	protected $descriptor;
	protected $metadata = [];
	protected $status;
	protected $capturedAmount;
	protected $latestPaymentAttempt;
	protected $createdAt;
	protected $updatedAt;
	protected $cancelledAt;
	protected $cancellationReason;
	protected $nextAction;
	protected $clientSecret;
	protected $supplementaryAmount;
	protected $returnUrl;

	/**
	 * Get customer ID
	 *
	 * @return mixed
	 */
	public function getCustomerId() {
		return $this->customerId;
	}

	/**
	 * Set customer ID
	 *
	 * @param mixed $customerId
	 * @return PaymentIntent
	 */
	public function setCustomerId( $customerId ) {
		$this->customerId = $customerId;
		return $this;
	}



	/**
	 * Get payment consent ID
	 *
	 * @return mixed
	 */
	public function getPaymentConsentId() {
		return $this->paymentConsentId;
	}

	/**
	 * Set payment consent ID
	 *
	 * @param mixed $paymentConsentId
	 * @return PaymentIntent
	 */
	public function setPaymentConsentId( $paymentConsentId ) {
		$this->paymentConsentId = $paymentConsentId;
		return $this;
	}

	/**
	 * Get metadata
	 *
	 * @return array
	 */
	public function getMetadata() {
		return $this->metadata;
	}

	/**
	 * Set metadata
	 *
	 * @param array $metadata
	 * @return PaymentIntent
	 */
	public function setMetadata( $metadata ) {
		$this->metadata = $metadata;
		return $this;
	}


	/**
	 * Get last payment attempt
	 *
	 * @return mixed
	 */
	public function getLatestPaymentAttempt() {
		return $this->latestPaymentAttempt;
	}

	/**
	 * Set last payment attempt
	 *
	 * @param mixed $latestPaymentAttempt
	 * @return PaymentIntent
	 */
	public function setLatestPaymentAttempt( $latestPaymentAttempt ) {
		$this->latestPaymentAttempt = $latestPaymentAttempt;
		return $this;
	}


	/**
	 * Get client secret
	 *
	 * @return mixed
	 */
	public function getClientSecret() {
		return $this->clientSecret;
	}

	/**
	 * Set client secret
	 *
	 * @param mixed $clientSecret
	 * @return PaymentIntent
	 */
	public function setClientSecret( $clientSecret ) {
		$this->clientSecret = $clientSecret;
		return $this;
	}

	/**
	 * Get payment intent ID
	 *
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set payment intent ID
	 *
	 * @param mixed $id
	 * @return PaymentIntent
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
	 * @return PaymentIntent
	 */
	public function setRequestId( $requestId ) {
		$this->requestId = $requestId;
		return $this;
	}

	/**
	 * Get intent amount
	 *
	 * @return mixed
	 */
	public function getAmount() {
		return $this->amount;
	}

	/**
	 * Set intent amount
	 *
	 * @param mixed $amount
	 * @return PaymentIntent
	 */
	public function setAmount( $amount ) {
		$this->amount = $amount;
		return $this;
	}

	/**
	 * Get currency
	 *
	 * @return mixed
	 */
	public function getCurrency() {
		return $this->currency;
	}

	/**
	 * Set currency
	 *
	 * @param mixed $currency
	 * @return PaymentIntent
	 */
	public function setCurrency( $currency ) {
		$this->currency = $currency;
		return $this;
	}

	/**
	 * Get order
	 *
	 * @return mixed
	 */
	public function getOrder() {
		return $this->order;
	}

	/**
	 * Set order
	 *
	 * @param mixed $order
	 * @return PaymentIntent
	 */
	public function setOrder( $order ) {
		$this->order = $order;
		return $this;
	}

	/**
	 * Get return url
	 *
	 * @return mixed
	 */
	public function getReturnUrl() {
		return $this->returnUrl;
	}

	/**
	 * Set return url
	 *
	 * @param mixed $returnUrl
	 * @return PaymentIntent
	 */
	public function setReturnUrl( $returnUrl ) {
		$this->returnUrl = $returnUrl;
		return $this;
	}

	/**
	 * Get merchant order id
	 *
	 * @return mixed
	 */
	public function getMerchantOrderId() {
		return $this->merchantOrderId;
	}

	/**
	 * Set merchant order id
	 *
	 * @param mixed $merchantOrderId
	 * @return PaymentIntent
	 */
	public function setMerchantOrderId( $merchantOrderId ) {
		$this->merchantOrderId = $merchantOrderId;
		return $this;
	}

	/**
	 * Get supplementary amount
	 *
	 * @return mixed
	 */
	public function getSupplementaryAmount() {
		return $this->supplementaryAmount;
	}

	/**
	 * Set supplementary amount
	 *
	 * @param mixed $supplementaryAmount
	 * @return PaymentIntent
	 */
	public function setSupplementaryAmount( $supplementaryAmount ) {
		$this->supplementaryAmount = $supplementaryAmount;
		return $this;
	}

	/**
	 * Get descriptor
	 *
	 * @return mixed
	 */
	public function getDescriptor() {
		return $this->descriptor;
	}

	/**
	 * Set descriptor
	 *
	 * @param mixed $descriptor
	 * @return PaymentIntent
	 */
	public function setDescriptor( $descriptor ) {
		$this->descriptor = $descriptor;
		return $this;
	}

	/**
	 * Get status
	 *
	 * @return mixed
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * Set status
	 *
	 * @param mixed $status
	 * @return PaymentIntent
	 */
	public function setStatus( $status ) {
		$this->status = $status;
		return $this;
	}

	/**
	 * Get capture amount
	 *
	 * @return mixed
	 */
	public function getCapturedAmount() {
		return $this->capturedAmount;
	}

	/**
	 * Set capture amount
	 *
	 * @param mixed $capturedAmount
	 * @return PaymentIntent
	 */
	public function setCapturedAmount( $capturedAmount ) {
		$this->capturedAmount = $capturedAmount;
		return $this;
	}

	/**
	 * Get created time
	 *
	 * @return mixed
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set created time
	 *
	 * @param mixed $createdAt
	 * @return PaymentIntent
	 */
	public function setCreatedAt( $createdAt ) {
		$this->createdAt = $createdAt;
		return $this;
	}

	/**
	 * Get updated time
	 *
	 * @return mixed
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * Set updated time
	 *
	 * @param mixed $updatedAt
	 * @return PaymentIntent
	 */
	public function setUpdatedAt( $updatedAt ) {
		$this->updatedAt = $updatedAt;
		return $this;
	}
	/**
	 * Get cancelled at
	 * 
	 * @return mixed
	 */
	public function getCancelledAt() {
		return $this->nextAction;
	}

	/**
	 * Set cancelled at
	 * 
	 * @param mixed $cancelledAt
	 * @return PaymentIntent
	 */
	public function setCancelledAt( $cancelledAt ) {
		$this->cancelledAt = $cancelledAt;
		return $this;
	}

	/**
	 * Get cancellation reason
	 * 
	 * @return array
	 */
	public function getCancellationReason() {
		return $this->cancellationReason;
	}

	/**
	 * Set next action
	 * 
	 * @param mixed $cancellationReason
	 * @return PaymentIntent
	 */
	public function setCancellationReason( $cancellationReason ) {
		$this->cancellationReason = $cancellationReason;
		return $this;
	}

	/**
	 * Get next action
	 * 
	 * @return mixed
	 */
	public function getNextAction() {
		return $this->nextAction;
	}

	/**
	 * Set next action
	 * 
	 * @param mixed $nextAction
	 * @return PaymentIntent
	 */
	public function setNextAction( $nextAction ) {
		$this->nextAction = $nextAction;
		return $this;
	}
}
