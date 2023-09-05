<?php

namespace Airwallex\Struct;

class Refund extends AbstractBase
{
    const META_REFUND_ID = '_airwallex_refund_id_';
    const STATUS_CREATED = 'CREATED';
    const STATUS_RECEIVED = 'RECEIVED';
    const STATUS_SUCCEEDED = 'SUCCEEDED';

    protected $id;
    protected $requestId;
    protected $paymentIntentId;
    protected $paymentAttemptId;
    protected $amount;
    protected $currency;
    protected $reason;
    protected $status;
    protected $createdAt;
    protected $updatedAt;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return Refund
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
     * @return Refund
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPaymentIntentId()
    {
        return $this->paymentIntentId;
    }

    /**
     * @param mixed $paymentIntentId
     * @return Refund
     */
    public function setPaymentIntentId($paymentIntentId)
    {
        $this->paymentIntentId = $paymentIntentId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPaymentAttemptId()
    {
        return $this->paymentAttemptId;
    }

    /**
     * @param mixed $paymentAttemptId
     * @return Refund
     */
    public function setPaymentAttemptId($paymentAttemptId)
    {
        $this->paymentAttemptId = $paymentAttemptId;
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
     * @return Refund
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
     * @return Refund
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @param mixed $reason
     * @return Refund
     */
    public function setReason($reason)
    {
        $this->reason = $reason;
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
     * @return Refund
     */
    public function setStatus($status)
    {
        $this->status = $status;
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
     * @return Refund
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
     * @return Refund
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getMetaKey() {
        return self::META_REFUND_ID . $this->id;
    }
}