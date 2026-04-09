<?php

namespace App\Interfaces;

interface ErrorResponseInterface
{
    public function getMessage(): string;

    public function setMessage(string $message): void;
}
