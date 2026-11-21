<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the real network; fake every upstream call explicitly.
        Http::preventStrayRequests();
    }

    /** Headers of a browser request from the shop (web) origin, which Sanctum makes stateful (session + CSRF). */
    protected const array SHOP = ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];

    /** The committed Fake Store snapshot, used as the upstream fixture. */
    protected function snapshotJson(): string
    {
        return (string) file_get_contents(base_path('database/data/fakestore-products.json'));
    }

    /** Makes every email fail with a transport error (SMTP down). Use instead of Mail::fake(). */
    protected function useFailingMailer(): void
    {
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('SMTP is down');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });
        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
    }
}
