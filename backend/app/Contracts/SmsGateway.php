<?php

namespace App\Contracts;

use App\Services\Sms\SmsSendResult;

interface SmsGateway
{
    public function send(string $to, string $message): SmsSendResult;
}
