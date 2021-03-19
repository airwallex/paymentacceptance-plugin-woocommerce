<?php

namespace Airwallex\Struct;

class PaymentIntent extends AbstractBase{
    const STATUS_REQUIRES_CAPTURE = 'REQUIRES_CAPTURE';
    const STATUS_SUCCEEDED = 'SUCCEEDED';

    protected $id;
    protected $requestId;
    protected $amount;
    protected $currency;
    protected $order;
    protected $merchantOrderId;
    protected $supplementaryAmount;
    protected $descriptor;
    protected $status;
    protected $capturedAmount;
    protected $createdAt;
    protected $updatedAt;
    protected $clientSecret;
    protected $latestPaymentAttempt;

    /**
     * @return mixed
     */
    public function getLatestPaymentAttempt()
    {
        return $this->latestPaymentAttempt;
    }

    /**
     * @param mixed $latestPaymentAttempt
     * @return PaymentIntent
     */
    public function setLatestPaymentAttempt($latestPaymentAttempt)
    {
        $this->latestPaymentAttempt = $latestPaymentAttempt;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * @param mixed $clientSecret
     * @return PaymentIntent
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return PaymentIntent
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @param mixed $requestId
     * @return PaymentIntent
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     * @return PaymentIntent
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param mixed $currency
     * @return PaymentIntent
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param mixed $order
     * @return PaymentIntent
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMerchantOrderId()
    {
        return $this->merchantOrderId;
    }

    /**
     * @param mixed $merchantOrderId
     * @return PaymentIntent
     */
    public function setMerchantOrderId($merchantOrderId)
    {
        $this->merchantOrderId = $merchantOrderId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSupplementaryAmount()
    {
        return $this->supplementaryAmount;
    }

    /**
     * @param mixed $supplementaryAmount
     * @return PaymentIntent
     */
    public function setSupplementaryAmount($supplementaryAmount)
    {
        $this->supplementaryAmount = $supplementaryAmount;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescriptor()
    {
        return $this->descriptor;
    }

    /**
     * @param mixed $descriptor
     * @return PaymentIntent
     */
    public function setDescriptor($descriptor)
    {
        $this->descriptor = $descriptor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     * @return PaymentIntent
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCapturedAmount()
    {
        return $this->capturedAmount;
    }

    /**
     * @param mixed $capturedAmount
     * @return PaymentIntent
     */
    public function setCapturedAmount($capturedAmount)
    {
        $this->capturedAmount = $capturedAmount;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     * @return PaymentIntent
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param mixed $updatedAt
     * @return PaymentIntent
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }




}