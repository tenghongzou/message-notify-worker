<?php

namespace App\Service\Notification;

interface NotificationServiceInterface
{
    public function setToken(string $token): static;
    public function send(string $message, ?string $imageUrl = null): void;
}
