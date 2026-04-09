<?php

namespace App\Interfaces;

interface SuccessfulResponseInterface
{
    public function getMessage(): string;

    public function setMessage(string $message): void;
}
