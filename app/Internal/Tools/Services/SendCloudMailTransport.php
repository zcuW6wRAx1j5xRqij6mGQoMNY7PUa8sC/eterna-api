<?php

namespace Internal\Tools\Services;

use Internal\Common\Actions\SendCloud;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Address;


class SendCloudMailTransport extends AbstractTransport
{

    public function __construct(
        protected SendCloud $client,
    ) {
        parent::__construct();
    }

    /**
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom();
        $to = collect($email->getTo())->map(function (Address $email) {
            return $email->getAddress();
        })->all();
        $subject = $email->getSubject();
        $text =  $email->getTextBody();
        $this->client->send($to,$text);
    }

    /**
     * 获取传输字符串的表示形式。
     */
    public function __toString(): string
    {
        return 'sendcloud';
    }
}
