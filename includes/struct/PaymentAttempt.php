<?php

namespace Airwallex\struct;

class PaymentAttempt extends AbstractBase
{
    const PENDING_STATUSES = [
        'RECEIVED',
        'PENDING_AUTHENTICATION',
        'AUTHENTICATION_REDIRECTED',
        'PENDING_AUTHORIZATION',
        'AUTHORIZED',
    ];
}