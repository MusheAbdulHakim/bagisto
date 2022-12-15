<?php

namespace Mushe\Rave\Payment;

use Webkul\Payment\Payment\Payment;

class Rave extends Payment
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $code  = 'rave';

    public function getRedirectUrl()
    {
        return route('rave.redirect');
    }
}