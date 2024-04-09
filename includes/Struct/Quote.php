<?php

namespace Airwallex\Struct;

defined('ABSPATH') || exit();

class Quote extends AbstractBase {
    protected $id;
    protected $type;
    protected $paymentCurrency;
    protected $currencyPair;
    protected $clientRate;
    protected $createdAt;
    protected $validFrom;
    protected $validTo;
    protected $targetCurrency;
    protected $paymentAmount;
    protected $targetAmount;
    protected $refreshAt;

    /**
     * Get the ID.
     *
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set the ID.
     *
     * @param mixed $id
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the type.
     *
     * @return mixed
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Set the type.
     *
     * @param mixed $type
     * @return $this
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * Get the payment currency.
     *
     * @return mixed
     */
    public function getPaymentCurrency() {
        return $this->paymentCurrency;
    }

    /**
     * Set the payment currency.
     *
     * @param mixed $paymentCurrency
     * @return $this
     */
    public function setPaymentCurrency($paymentCurrency) {
        $this->paymentCurrency = $paymentCurrency;
        return $this;
    }

    /**
     * Get the currency pair.
     *
     * @return mixed
     */
    public function getCurrencyPair() {
        return $this->currencyPair;
    }

    /**
     * Set the currency pair.
     *
     * @param mixed $currencyPair
     * @return $this
     */
    public function setCurrencyPair($currencyPair) {
        $this->currencyPair = $currencyPair;
        return $this;
    }

    /**
	 * Get client rate
	 *
	 * @return mixed
	 */
	public function getClientRate() {
		return $this->clientRate;
	}

	/**
	 * Set client rate
	 *
	 * @param mixed $clientRate
	 * @return Quote
	 */
	public function setClientRate( $clientRate ) {
		$this->clientRate = $clientRate;
		return $this;
	}

    /**
     * Get the creation date and time.
     *
     * @return mixed
     */
    public function getCreatedAt() {
        return $this->createdAt;
    }

    /**
     * Set the creation date and time.
     *
     * @param mixed $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get the validation start date and time.
     *
     * @return mixed
     */
    public function getValidFrom() {
        return $this->validFrom;
    }

    /**
     * Set the validation start date and time.
     *
     * @param mixed $validFrom
     * @return $this
     */
    public function setValidFrom($validFrom) {
        $this->validFrom = $validFrom;
        return $this;
    }

    /**
     * Get the validation end date and time.
     *
     * @return mixed
     */
    public function getValidTo() {
        return $this->validTo;
    }

    /**
     * Set the validation end date and time.
     *
     * @param mixed $validTo
     * @return $this
     */
    public function setValidTo($validTo) {
        $this->validTo = $validTo;
        return $this;
    }

    /**
     * Get the target currency.
     *
     * @return mixed
     */
    public function getTargetCurrency() {
        return $this->targetCurrency;
    }

    /**
     * Set the target currency.
     *
     * @param mixed $targetCurrency
     * @return $this
     */
    public function setTargetCurrency($targetCurrency) {
        $this->targetCurrency = $targetCurrency;
        return $this;
    }

    /**
     * Get the payment amount.
     *
     * @return mixed
     */
    public function getPaymentAmount() {
        return $this->paymentAmount;
    }

    /**
     * Set the payment amount.
     *
     * @param mixed $paymentAmount
     * @return $this
     */
    public function setPaymentAmount($paymentAmount) {
        $this->paymentAmount = $paymentAmount;
        return $this;
    }

    /**
     * Get the target amount.
     *
     * @return mixed
     */
    public function getTargetAmount() {
        return $this->targetAmount;
    }

    /**
     * Set the target amount.
     *
     * @param mixed $targetAmount
     * @return $this
     */
    public function setTargetAmount($targetAmount) {
        $this->targetAmount = $targetAmount;
        return $this;
    }

    /**
     * Get the refresh date and time.
     *
     * @return mixed
     */
    public function getRefreshAt() {
        return $this->refreshAt;
    }

    /**
     * Set the refresh date and time.
     *
     * @param mixed $refreshAt
     * @return $this
     */
    public function setRefreshAt($refreshAt) {
        $this->refreshAt = $refreshAt;
        return $this;
    }
}
