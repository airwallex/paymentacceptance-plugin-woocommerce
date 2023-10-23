<?php

namespace Airwallex\struct;

class PaymentAttempt extends AbstractBase {

	const PENDING_STATUSES = array(
		'RECEIVED',
		'PENDING_AUTHENTICATION',
		'AUTHENTICATION_REDIRECTED',
		'PENDING_AUTHORIZATION',
		'AUTHORIZED',
	);
}
