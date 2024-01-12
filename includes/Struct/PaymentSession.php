<?php

namespace Airwallex\Struct;

if (!defined('ABSPATH')) {
	exit;
}

class PaymentSession extends AbstractBase {

	protected $epochTimestamp;
	protected $expiresAt;
	protected $merchantSessionIdentifier;
	protected $nonce;
	protected $merchantIdentifier;
	protected $domainName;
	protected $displayName;
	protected $signature;
	protected $operationalAnalyticsIdentifier;
	protected $retries;
	protected $pspId;

	/**
	 * Get Epoch Timestamp
	 *
	 * @return mixed
	 */
	public function getEpochTimestamp() {
		return $this->epochTimestamp;
	}

	/**
	 * Set Epoch Timestamp
	 *
	 * @param mixed $epochTimestamp
	 * @return PaymentSession
	 */
	public function setEpochTimestamp($epochTimestamp) {
		$this->epochTimestamp = $epochTimestamp;
		return $this;
	}

	/**
	 * Get Expires At
	 *
	 * @return mixed
	 */
	public function getExpiresAt() {
		return $this->expiresAt;
	}

	/**
	 * Set Expires At
	 *
	 * @param mixed $expiresAt
	 * @return PaymentSession
	 */
	public function setExpiresAt($expiresAt) {
		$this->expiresAt = $expiresAt;
		return $this;
	}

	/**
	 * Get Merchant Session Identifier
	 *
	 * @return mixed
	 */
	public function getMerchantSessionIdentifier() {
		return $this->merchantSessionIdentifier;
	}

	/**
	 * Set Merchant Session Identifier
	 *
	 * @param mixed $merchantSessionIdentifier
	 * @return PaymentSession
	 */
	public function setMerchantSessionIdentifier($merchantSessionIdentifier) {
		$this->merchantSessionIdentifier = $merchantSessionIdentifier;
		return $this;
	}

	/**
	 * Get Nonce
	 *
	 * @return mixed
	 */
	public function getNonce() {
		return $this->nonce;
	}

	/**
	 * Set Nonce
	 *
	 * @param mixed $nonce
	 * @return PaymentSession
	 */
	public function setNonce($nonce) {
		$this->nonce = $nonce;
		return $this;
	}

	/**
	 * Get Merchant Identifier
	 *
	 * @return mixed
	 */
	public function getMerchantIdentifier() {
		return $this->merchantIdentifier;
	}

	/**
	 * Set Merchant Identifier
	 *
	 * @param mixed $merchantIdentifier
	 * @return PaymentSession
	 */
	public function setMerchantIdentifier($merchantIdentifier) {
		$this->merchantIdentifier = $merchantIdentifier;
		return $this;
	}

	/**
	 * Get Domain Name
	 *
	 * @return mixed
	 */
	public function getDomainName() {
		return $this->domainName;
	}

	/**
	 * Set Domain Name
	 *
	 * @param mixed $domainName
	 * @return PaymentSession
	 */
	public function setDomainName($domainName) {
		$this->domainName = $domainName;
		return $this;
	}

	/**
	 * Get Display Name
	 *
	 * @return mixed
	 */
	public function getDisplayName() {
		return $this->displayName;
	}

	/**
	 * Set Display Name
	 *
	 * @param mixed $displayName
	 * @return PaymentSession
	 */
	public function setDisplayName($displayName) {
		$this->displayName = $displayName;
		return $this;
	}

	/**
	 * Get Signature
	 *
	 * @return mixed
	 */
	public function getSignature() {
		return $this->signature;
	}

	/**
	 * Set Signature
	 *
	 * @param mixed $signature
	 * @return PaymentSession
	 */
	public function setSignature($signature) {
		$this->signature = $signature;
		return $this;
	}

	/**
	 * Get Operational Analytics Identifier
	 *
	 * @return mixed
	 */
	public function getOperationalAnalyticsIdentifier() {
		return $this->operationalAnalyticsIdentifier;
	}

	/**
	 * Set Operational Analytics Identifier
	 *
	 * @param mixed $operationalAnalyticsIdentifier
	 * @return PaymentSession
	 */
	public function setOperationalAnalyticsIdentifier($operationalAnalyticsIdentifier) {
		$this->operationalAnalyticsIdentifier = $operationalAnalyticsIdentifier;
		return $this;
	}

	/**
	 * Get Retries
	 *
	 * @return mixed
	 */
	public function getRetries() {
		return $this->retries;
	}

	/**
	 * Set Retries
	 *
	 * @param mixed $retries
	 * @return PaymentSession
	 */
	public function setRetries($retries) {
		$this->retries = $retries;
		return $this;
	}

	/**
	 * Get PSP ID
	 *
	 * @return mixed
	 */
	public function getPspId() {
		return $this->pspId;
	}

	/**
	 * Set PSP ID
	 *
	 * @param mixed $pspId
	 * @return PaymentSession
	 */
	public function setPspId($pspId) {
		$this->pspId = $pspId;
		return $this;
	}
}
